<?php

/**
 * Khalti.com Payment Gateway WHMCS Module
 * 
 * @see https://docs.khalti.com/
 * @see https://github.com/khalti/whmcs-khaltigateway-plugin
 * @copyright Copyright (c) Khalti Private Limited
 * @author : @acpmasquerade for Khalti.com / @aerawatcorp
 */

function khaltigateway_validate_currency($currency_code)
{
    return $currency_code == "NPR";
}

function khaltigateway_convert_currency($currency_code, $amount)
{
    $payment_currency_id = WHMCS\Database\Capsule::table("tblcurrencies")->where("code", $currency_code)->value("id");
    $npr_currency_id = WHMCS\Database\Capsule::table("tblcurrencies")->where("code", "NPR")->value("id");
    if (is_null($payment_currency_id)) {
        return FALSE;
    }
    return convertCurrency($amount, $payment_currency_id, $npr_currency_id);
}

function khaltigateway_convert_from_npr_to_basecurrency($npr_amount)
{
    $base_currency_id = WHMCS\Database\Capsule::table("tblcurrencies")->where("default", "1")->value("id");
    $npr_currency_id = WHMCS\Database\Capsule::table("tblcurrencies")->where("code", "NPR")->value("id");
    if ($base_currency_id) {
        if ($base_currency_id == $npr_currency_id) {
            return $npr_amount;
        } else {
            return convertCurrency($npr_amount, $npr_currency_id, $base_currency_id);
        }
    } else {
        return FALSE;
    }
}

function khaltigateway_whmcs_current_page()
{
    $filename = basename($_SERVER['SCRIPT_FILENAME']);
    return str_replace(".PHP", "", strtoupper($filename));
}

function khaltigateway_get_production_mode($gateway_params)
{
    $is_test_mode = $gateway_params['is_test_mode'] == "on";

    if ($is_test_mode) {
        return KHALTIGATEWAY_TEST_MODE;
    } else {
        return KHALTIGATEWAY_LIVE_MODE;
    }
}

function khaltigateway_debug($gateway_params, $data)
{
    $is_debug_mode = $gateway_params['is_debug_mode'] == "on";

    if ($is_debug_mode) {
        echo <<<EOT
        <div class='alert alert-warning' style='margin:0 10%; border-left:10px solid #5E338D;'>
        <strong>Debug Information for Khalti Payment Gateway</strong>
EOT;
        ndie($data);
        echo "</div>";
    }
}

function khaltigateway_epay_api_endpoint($gateway_params)
{
    $mode_name = khaltigateway_get_production_mode($gateway_params);
    return constant("KHALTIGATEWAY_EPAY_" . strtoupper($mode_name) . "_ENDPOINT");
}

function khaltigateway_epay_api_authentication_key($gateway_params)
{
    $mode_name = khaltigateway_get_production_mode($gateway_params);
    return $gateway_params["{$mode_name}_api_key"];
}

function khaltigateway_make_api_call($gateway_params, $api, $payload)
{
    if (!$api) {
        return null;
    }

    $url = khaltigateway_epay_api_endpoint($gateway_params) . $api;
    $api_key = khaltigateway_epay_api_authentication_key($gateway_params);

    $post_data = json_encode($payload);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Key ' . $api_key,
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    $response = curl_exec($ch);
    if (curl_error($ch)) {
        khaltigateway_debug($gateway_params, $ch);
        return null;
    }
    curl_close($ch);

    khaltigateway_debug($gateway_params, $response);

    return json_decode($response, true);
}

function khaltigateway_refund_api_call($api_key, $transactionIdToRefund)
{
    $apiEndpoint = constant("KHALTIGATEWAY_EPAY_REFUND_ENDPOINT") . $transactionIdToRefund . "/refund/";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiEndpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Key ' . $api_key
    ]);

    $response = curl_exec($ch);
    curl_close($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    return [json_decode($response, true), $httpCode];
}

function khaltigateway_epay_initiate($gateway_params, $checkout_params)
{
    return khaltigateway_make_api_call($gateway_params, KHALTIGATEWAY_EPAY_INITIATE_API, $checkout_params);
}

function khaltigateway_epay_lookup($gateway_params, $pidx)
{
    $payload = array(
        "pidx" => $pidx
    );
    return khaltigateway_make_api_call($gateway_params, KHALTIGATEWAY_EPAY_LOOKUP_API, $payload);
}

function khaltigateway_whmcs_local_api($command, $args)
{
    return localAPI($command, $args);
}

function khaltigateway_whmcs_get_invoice($invoice_id)
{
    return khaltigateway_whmcs_local_api("GetInvoice", array("invoiceid" => $invoice_id));
}

function khaltigateway_whmcs_get_client($userid)
{
    return khaltigateway_whmcs_local_api("GetClientsDetails", ["clientid" => $userid, "stats" => true]);
}
