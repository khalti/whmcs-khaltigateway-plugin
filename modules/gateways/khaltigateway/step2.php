<?php
/** Khalti.com Payment Gateway WHMCS Module */

header("Content-Type: application/json");
// Require libraries needed for gateway module functions.
$WHMCS_ROOT = dirname(dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME']))));
require_once dirname(__FILE__)."/init.php";
require_once dirname(__FILE__)."/common.php";

$payload = file_get_contents("php://input");
$payload = json_decode($payload);

$khaltiToken = $payload->token;
$khaltiAmount = $payload->amount;
$invoiceId = $payload->invoiceId;

$gatewayModule = $gatewayParams['name'];

function error_resp($msg){
    header("HTTP/1.1 400 Bad Request");
    die($msg);
}

if(!$khaltiToken || !$khaltiAmount){
    error_resp("Insufficient Data to proceed.");
}

/**
 * Validate Callback Invoice ID.
 *
 * Checks invoice ID is a valid invoice number. Note it will count an
 * invoice in any status as valid.
 *
 * Performs a die upon encountering an invalid Invoice ID.
 *
 * Returns a normalised invoice ID.
 *
 * @param int $invoiceId Invoice ID
 * @param string $gatewayName Gateway Name
 */
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayModule);

$response = khaltigateway_confirm_transaction($gatewayParams, $khaltiToken, $khaltiAmount);

if(!$response){
    error_resp("Confirmation Failed.");
}

$response = json_decode($response);

if(!$response->idx){
    error_resp("Payment rejected by Khalti.");
}

if($response->refunded == true){
    error_resp("ERROR !! Payment already refunded.");
}

$transactionId = $response->idx;

/**
 * Check Callback Transaction ID.
 *
 * Performs a check for any existing transactions with the same given
 * transaction number.
 *
 * Performs a die upon encountering a duplicate.
 *
 * @param string $transactionId Unique Transaction ID
 */
checkCbTransID($transactionId);

/**
 * Log Transaction.
 *
 * Add an entry to the Gateway Log for debugging purposes.
 *
 * The debug data can be a string or an array. In the case of an
 * array it will be
 *
 * @param string $gatewayName        Display label
 * @param string|array $debugData    Data to log
 * @param string $transactionStatus  Status
 */
$debugData = json_encode(array(
    'payload' => $payload,
    'khalti_response' => $response,
    'invoiceId' => $invoiceId
));

logTransaction($gatewayModule, $debugData, "Success");
$paymentSuccess = true;

/**
 * Add Invoice Payment.
 *
 * Applies a payment transaction entry to the given invoice ID.
 *
 * @param int $invoiceId         Invoice ID
 * @param string $transactionId  Transaction ID
 * @param float $paymentAmount   Amount paid (defaults to full balance)
 * @param float $paymentFee      Payment fee (optional)
 * @param string $gatewayModule  Gateway module name
 */
$paymentAmount = $khaltiAmount / 100.0;
$paymentFee = 0.0;
addInvoicePayment(
    $invoiceId,
    $transactionId,
    $paymentAmount,
    $paymentFee,
    $gatewayModule
);

/**
 * Redirect to invoice.
 *
 * Performs redirect back to the invoice upon completion of the 3D Secure
 * process displaying the transaction result along with the invoice.
 *
 * @param int $invoiceId        Invoice ID
 * @param bool $paymentSuccess  Payment status
 */
callback3DSecureRedirect($invoiceId, $paymentSuccess);
