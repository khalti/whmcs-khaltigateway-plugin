<?php

/**
 * Khalti.com Payment Gateway WHMCS Module Callback Handler
 * 
 * @see https://docs.khalti.com/
 * 
 * @copyright Copyright (c) Khalti Private Limited
 * @author : @acpmasquerade for Khalti.com
 * @author : @om-ghimire for Khalti.com
 */

header("Content-Type: application/json");

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load WHMCS core
$WHMCS_ROOT = dirname(dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME']))));
require_once $WHMCS_ROOT . "/init.php";

$whmcs->load_function('gateway');
$whmcs->load_function('invoice');

// Load Khalti gateway files
require_once __DIR__ . "/init.php";
require_once __DIR__ . "/whmcs.php";

// Fetch callback parameters safely (support POST, GET, or raw JSON input)
$rawInput = file_get_contents('php://input');
$callback_args = !empty($_POST) ? $_POST : (!empty($_GET) ? $_GET : (!empty($rawInput) ? json_decode($rawInput, true) : []));

// Extract required parameters
$pidx = $callback_args['pidx'] ?? null;
$khalti_transaction_id = $callback_args['transaction_id'] ?? ($callback_args['txnId'] ?? null);
$amount_paisa = isset($callback_args['amount']) ? intval($callback_args['amount']) : null;
$amount_rs = $amount_paisa !== null ? $amount_paisa / 100 : null; // Convert paisa to rupees
$purchase_order_id = $callback_args['purchase_order_id'] ?? null;

// Helper function to send error response and log transaction
function error_resp($msg, $gateway_module = 'khaltigateway') {
    logTransaction($gateway_module, $msg, "Error");
    header("HTTP/1.1 400 Bad Request");
    die(json_encode(['error' => $msg]));
}

// Ensure gateway parameters are available
global $khaltigateway_gateway_params;
if (!isset($khaltigateway_gateway_params)) {
    error_resp("Gateway parameters missing.", 'khaltigateway');
}

$gateway_module = $khaltigateway_gateway_params['paymentmethod'] ?? 'khaltigateway';

// Log received callback data for debugging
logTransaction($gateway_module, "Callback received: " . json_encode($callback_args), "Debug");

// Validate essential parameters
if (!$khalti_transaction_id || !$amount_paisa) {
    error_resp("Missing transaction ID or amount.", $gateway_module);
}

if (!$purchase_order_id) {
    error_resp("Missing purchase_order_id parameter.", $gateway_module);
}

// Extract invoice ID from purchase_order_id (e.g., "invoice:1234_xyz" -> 1234)
$invoice_id = $purchase_order_id;
$parts = explode('_', $purchase_order_id);
if (isset($parts[0])) {
    $invoiceParts = explode(':', $parts[0]);
    if (isset($invoiceParts[1]) && is_numeric($invoiceParts[1])) {
        $invoice_id = $invoiceParts[1];
    }
}

if (!$invoice_id || !is_numeric($invoice_id)) {
    error_resp("Invalid purchase_order_id format, cannot extract invoice ID.", $gateway_module);
}

// Confirm payment status from Khalti
$response = khaltigateway_epay_lookup($khaltigateway_gateway_params, $pidx);

if (!$response) {
    error_resp("Failed to confirm payment with Khalti.", $gateway_module);
}

// Log Khalti response for debugging
logTransaction($gateway_module, "Khalti response: " . json_encode($response), "Debug");

// Handle different payment statuses
$status = $response['status'] ?? '';
switch (strtolower($status)) {
    case 'refunded':
    case 'refuded': // typo fix
        error_resp("Payment already refunded.", $gateway_module);
        break;
    case 'expired':
        error_resp("Payment request expired.", $gateway_module);
        break;
    case 'pending':
        error_resp("Payment is still pending.", $gateway_module);
        break;
    case 'completed':
        // Payment completed, proceed to record it
        break;
    default:
        error_resp("Payment status is not complete. Status: {$status}", $gateway_module);
}

$invoice = localAPI("GetInvoice", ["invoiceid" => $invoice_id]);
if (!$invoice || ($invoice['result'] ?? '') !== 'success') {
    error_resp("Could not fetch invoice with ID {$invoice_id} from WHMCS.", $gateway_module);
}

$wh_paymentAmount = $amount_rs;
if ($wh_paymentAmount === null || $wh_paymentAmount <= 0) {
    error_resp("Invalid payment amount.", $gateway_module);
}
$expectedAmount = floatval($invoice['total']);
if (abs($wh_paymentAmount - $expectedAmount) > 0.01) {
    error_resp("Amount mismatch: Invoice expects {$expectedAmount}, received {$wh_paymentAmount}.", $gateway_module);
}
$submit_data = [
    'wh_payload' => $callback_args,
    'wh_response' => $response,
    'wh_invoiceId' => $invoice_id,
    'wh_gatewayModule' => $gateway_module,
    'wh_transactionId' => $khalti_transaction_id,
    'wh_paymentAmount' => $wh_paymentAmount,
    'wh_paymentFee' => 0,
    'wh_paymentSuccess' => true,
];
logTransaction($gateway_module, "Submitting payment to WHMCS: " . json_encode($submit_data), "Debug");


khaltigateway_acknowledge_whmcs_for_payment($submit_data);
logTransaction($gateway_module, "Payment callback processing completed successfully.", "Success");
$invoice_url = $khaltigateway_gateway_params['SystemURL'] . "/viewinvoice.php?id=" . $invoice_id;

// Only redirect if request method is GET (likely user browser)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    logTransaction($gateway_module, "Redirecting user to invoice page: {$invoice_url}", "Info");
    header("Location: " . $invoice_url);
    exit;
}

// Otherwise, respond with JSON (likely webhook)
echo json_encode(['status' => 'success', 'invoice_id' => $invoice_id]);
exit;