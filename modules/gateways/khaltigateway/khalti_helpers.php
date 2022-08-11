<?php

/** 
 * Khalti Payment Gateway for WHMCS 
 * Author : @acpmasquerade for Khalti.com
 */

function khaltigateway_validate_currency($currency_code)
{
    return in_array(strtoupper($currency_code), array(
        "NPR", "NRS"
    ));
}

function khaltigateway_convert_currency($currency_code, $amount)
{
    $payment_currency_id = WHMCS\Database\Capsule::table("tblcurrencies")->where("code", $currency_code)->value("id");
    $npr_currency_id = WHMCS\Database\Capsule::table("tblcurrencies")->where("code", "NPR")->value("id");
    if (is_null($payment_currency_id)) {
        return FALSE;
    }
    return convertCurrency($amount, $payment_currency_id, $npr_currency_id); //Here the result is the same amount sent
}

function khaltigateway_whmcs_current_page()
{
    $filename = basename($_SERVER['SCRIPT_FILENAME']);
    return str_replace(".PHP", "", strtoupper($filename));
}

function khaltigateway_get_production_mode($gateway_params)
{
    $is_test_mode = $gateway_params['is_test_mode'];
    if ($is_test_mode == 'on' || $is_test_mode === true) {
        return KHALTIGATEWAY_TEST_MODE;
    } else {
        return KHALTIGATEWAY_LIVE_MODE;
    }
}

function khaltigateway_testmode_debug($gateway_params, $data)
{
    if (khaltigateway_get_production_mode($gateway_params) == KHALTIGATEWAY_TEST_MODE) {
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

    // Call the API
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
        return NULL;
    }
    curl_close($ch);

    khaltigateway_testmode_debug($gateway_params, $response);

    return json_decode($response, true);
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