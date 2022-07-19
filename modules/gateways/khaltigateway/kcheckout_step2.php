<?php
/** Khalti.com Payment Gateway WHMCS Module */
header("Content-Type: application/json");
// Require libraries needed for gateway module functions.
$WHMCS_ROOT = dirname(dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME']))));
require_once dirname(__FILE__)."/init.php";
require_once dirname(__FILE__)."/common.php";

$callback_args = $_GET;

$pidx = $callback_args['pidx'];
$khalti_transaction_id = $callback_args['transaction_id'] ? $callback_args['transaction_id'] : $callback_args['txnId'];

$amount_paisa = intval($callback_args['amount']);
$amount_rs = $amount_paisa / 100;
$invoice_id = $callback_args['purchase_order_id'];

$gatewayModule = $gatewayParams['name'];

function error_resp($msg){
    header("HTTP/1.1 400 Bad Request");
    die($msg);
}

if(!$khalti_transaction_id || !$amount_paisa){
    error_resp("Insufficient Data to proceed.");
}

$response = khaltigateway_epay_lookup($gatewayParams, $pidx);

print_r($response);

if(!$response){
    error_resp("Confirmation Failed.");
}

if($response["status"] == "Refuded"){
    error_resp("ERROR !! Payment already refunded.");
}

if($response["status"] == "Expired"){
    error_resp("ERROR !! Payment Request alreadyd expired.");
}

if($response["status"] == "Pending"){
    error_resp("Payment is still pending");
}

if($response["status"] !== "Completed"){
    error_resp("ERROR !! Payment status is NOT COMPLETE.");
}
/** Prepare data for whmcs processing */
$wh_response = $response;
$wh_invoiceId = $invoice_id;
$wh_paymentAmount = $amount_rs;
$wh_payload = $callback_args;
$wh_transactionId = $khalti_transaction_id;
$wh_paymentSuccess = true;
$wh_paymentFee = 0.0;


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
checkCbTransID($khalti_transaction_id);

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
    'payload' => $wh_payload,
    'khalti_response' => $wh_response,
    'invoiceId' => $wh_invoiceId
));

logTransaction($gatewayModule, $debugData, "Success");

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
$paymentFee = 0.0;
addInvoicePayment(
    $wh_invoiceId,
    $wh_transactionId,
    $wh_paymentAmount,
    $wh_paymentFee,
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
callback3DSecureRedirect($wh_invoiceId, $wh_paymentSuccess);
