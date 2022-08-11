<?php
/** Khalti.com Payment Gateway WHMCS Module */
header("Content-Type: application/json");
// Require libraries needed for gateway module functions.
$WHMCS_ROOT = dirname(dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME']))));

# load whmcs
require_once $WHMCS_ROOT."/init.php";
require_once $WHMCS_ROOT."/includes/gatewayfunctions.php";

# load the khaltigateway init file
require_once __DIR__."/init.php";
require_once __DIR__."/whmcs.php";

$callback_args = $_GET;

$pidx = $callback_args['pidx'];
$khalti_transaction_id = $callback_args['transaction_id'] ? $callback_args['transaction_id'] : $callback_args['txnId'];

$amount_paisa = intval($callback_args['amount']);
$amount_rs = $amount_paisa / 100;
$invoice_id = $callback_args['purchase_order_id'];

$gateway_module = $khaltigateway_gateway_params['paymentmethod'];

function error_resp($msg){
    header("HTTP/1.1 400 Bad Request");
    die($msg);
}

if(!$khalti_transaction_id || !$amount_paisa){
    error_resp("Insufficient Data to proceed.");
}

$response = khaltigateway_epay_lookup($khaltigateway_gateway_params, $pidx);

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

$khaltigateway_whmcs_submit_data = array(
    'wh_payload' => $wh_payload,
    'wh_response' => $wh_response,
    'wh_invoiceId' => $wh_invoiceId,
    'wh_gatewayModule' => $wh_gatewayModule,
    'wh_transactionId' => $wh_transactionId,
    'wh_paymentAmount' => $wh_paymentAmount,
    'wh_paymentFee' => $wh_paymentFee,
    'wh_paymentSuccess' => $wh_paymentSuccess
);

// mdie($khaltigateway_whmcs_submit_data);

khaltigateway_acknowledge_whmcs_for_payment($khaltigateway_whmcs_submit_data);
