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

$gateway_module = $gatewayParams['name'];

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
$wh_gatewayModule = $gateway_module;



