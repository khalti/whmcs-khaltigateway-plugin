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
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

try {

    // Load WHMCS core
    $WHMCS_ROOT = dirname(dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME']))));
    require_once $WHMCS_ROOT . "/init.php";

    $whmcs->load_function('gateway');
    $whmcs->load_function('invoice');

    // Load Khalti gateway files
    require_once __DIR__ . "/init.php";
    require_once __DIR__ . "/whmcs.php";

    // Fetch callback parameters
    $rawInput = file_get_contents('php://input');
    $callback_args = !empty($_POST) ? $_POST : (!empty($_GET) ? $_GET : (!empty($rawInput) ? json_decode($rawInput, true) : []));
    
    // Extract essential fields
    $pidx = $callback_args['pidx'] ?? null;
    $khalti_transaction_id = $callback_args['transaction_id'] ?? ($callback_args['txnId'] ?? null);
    $amount_paisa = isset($callback_args['amount']) ? intval($callback_args['amount']) : null;
    $amount_rs = $amount_paisa !== null ? $amount_paisa / 100 : null;
    $purchase_order_id = $callback_args['purchase_order_id'] ?? null;

    // Helper: safe error response and logging
    function error_resp($msg, $gateway_module = 'khaltigateway') {
        if (function_exists('logTransaction')) {
            logTransaction($gateway_module, $msg, "Error");
        }
        http_response_code(400);
        die(json_encode(['error' => $msg]));
    }

    // Ensure gateway params exist
    global $khaltigateway_gateway_params;
    if (!isset($khaltigateway_gateway_params)) {
        error_resp("Gateway parameters missing.", 'khaltigateway');
    }
    $gateway_module = $khaltigateway_gateway_params['paymentmethod'] ?? 'khaltigateway';

    logTransaction($gateway_module, "Callback received: " . json_encode($callback_args), "Debug");

    // Validate required fields
    if (!$khalti_transaction_id || !$amount_paisa) {
        error_resp("Missing transaction ID or amount.", $gateway_module);
    }
    if (!$purchase_order_id) {
        error_resp("Missing purchase_order_id parameter.", $gateway_module);
    }

    // Extract invoice ID safely
    $invoice_id = $purchase_order_id;
    $parts = explode('_', $purchase_order_id);
    if (isset($parts[0])) {
        $invoiceParts = explode(':', $parts[0]);
        if (isset($invoiceParts[1]) && is_numeric($invoiceParts[1])) {
            $invoice_id = $invoiceParts[1];
        }
    }
    if (!$invoice_id || !is_numeric($invoice_id)) {
        error_resp("Invalid purchase_order_id format.", $gateway_module);
    }

    // Secure Khalti payment verification
    $response = khaltigateway_epay_lookup($khaltigateway_gateway_params, $pidx);
    if (!$response) {
        error_resp("Failed to confirm payment with Khalti.", $gateway_module);
    }

    logTransaction($gateway_module, "Khalti response: " . json_encode($response), "Debug");

    // Handle payment status
    $status = strtolower($response['status'] ?? '');
    switch ($status) {
        case 'refunded':
        case 'refuded': // typo fix
            error_resp("Payment already refunded.", $gateway_module);
        case 'expired':
            error_resp("Payment request expired.", $gateway_module);
        case 'pending':
            error_resp("Payment is still pending.", $gateway_module);
        case 'completed':
            // continue
            break;
        default:
            error_resp("Payment status not completed. Status: {$status}", $gateway_module);
    }

    // Fetch invoice
    $invoice = localAPI("GetInvoice", ["invoiceid" => $invoice_id]);
    if (!$invoice || ($invoice['result'] ?? '') !== 'success') {
        $invoice_exists = false;
    } else {
        $invoice_exists = true;
        $expectedAmount = floatval($invoice['total']);
        if (abs($amount_rs - $expectedAmount) > 0.01) {
            error_resp("Amount mismatch: Invoice expects {$expectedAmount}, received {$amount_rs}.", $gateway_module);
        }
    }

    // Prepare submission
    $submit_data = [
        'wh_payload' => $callback_args,
        'wh_response' => $response,
        'wh_invoiceId' => $invoice_id,
        'wh_gatewayModule' => $gateway_module,
        'wh_transactionId' => $khalti_transaction_id,
        'wh_paymentAmount' => $amount_rs,
        'wh_paymentFee' => 0,
        'wh_paymentSuccess' => true,
    ];

    logTransaction($gateway_module, "Submitting payment to WHMCS: " . json_encode($submit_data), "Debug");

    // Acknowledge WHMCS
    khaltigateway_acknowledge_whmcs_for_payment($submit_data);
    logTransaction($gateway_module, "Payment processing completed successfully.", "Success");

    // Prepare invoice URL safely
    $system_url = rtrim($khaltigateway_gateway_params['SystemURL'], '/');
    $invoice_url = $system_url . "/viewinvoice.php?id=" . $invoice_id;

    // Handle GET redirect safely
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($invoice_exists) {
            header("Location: " . $invoice_url);
            exit;
        } else {
            echo "Invoice #{$invoice_id} not found. Please check your WHMCS system.";
            exit;
        }
    }

    // For webhooks / non-browser requests
    echo json_encode([
        'status' => 'success',
        'invoice_id' => $invoice_id,
        'invoice_exists' => $invoice_exists
    ]);
    exit;

} catch (Exception $e) {
    // Catch all unexpected errors
    $error_msg = "Exception caught: " . $e->getMessage();
    if (isset($gateway_module)) {
        logTransaction($gateway_module, $error_msg, "Error");
    }
    http_response_code(500);
    echo json_encode(['error' => $error_msg]);
    exit;
}
