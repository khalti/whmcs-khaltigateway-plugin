<?php

if (!defined("KHALTIGATEWAY_EPAY_LIVE_ENDPOINT")) {
    define('KHALTIGATEWAY_EPAY_INITIATE_API', "epayment/initiate/");
    define('KHALTIGATEWAY_EPAY_LOOKUP_API', "epayment/lookup/");

    define('KHALTIGATEWAY_EPAY_TEST_ENDPOINT', "https://a.khalti.com/api/v2/");
    define('KHALTIGATEWAY_EPAY_LIVE_ENDPOINT', "https://a.khalti.com/api/v2/");
}

// $KHALTIGATEWAY_EPAY_ROOT = array(
//     "test" => $KHALTIGATEWAY_EPAY_TEST_ENDPOINT,
//     "live" => $KHALTIGATEWAY_EPAY_LIVE_ENDPOINT
// );

function khaltigateway_get_livetest_mode($gatewayParams)
{
    $testMode = $gatewayParams['testMode'];
    if ($testMode == 'on' || $testMode === true) {
        return "test";
    } else {
        return "live";
    }
}

function khaltigateway_epay_api_endpoint($gatewayParams)
{
    $livetestMode = khaltigateway_get_livetest_mode($gatewayParams);
    return constant("KHALTIGATEWAY_EPAY_" . strtoupper($livetestMode) . "_ENDPOINT");
}

function khaltigateway_epay_api_authentication_key($gatewayParams)
{
    $mode = khaltigateway_get_livetest_mode($gatewayParams);
    return $gatewayParams["{$mode}PaymentAPIKey"];
}

function khaltigateway_make_api_call($gatewayParams, $api, $payload)
{
    if (!$api) {
        return null;
    }

    $url = khaltigateway_epay_api_endpoint($gatewayParams) . $api;
    $api_key = khaltigateway_epay_api_authentication_key($gatewayParams);

    $postdata = json_encode($payload);

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
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    $response = curl_exec($ch);
    if (curl_error($ch)) {
        return NULL;
        die('Unable to connect: ' . curl_errno($ch) . ' - ' . curl_error($ch));
    }
    curl_close($ch);

    $jsonData = json_decode($response, true);
    return $jsonData;
}

function khaltigateway_epay_initiate($gatewayParams, $checkout_params)
{
    $jsonData = khaltigateway_make_api_call($gatewayParams, KHALTIGATEWAY_EPAY_INITIATE_API, $checkout_params);
    return $jsonData;
}

function khaltigateway_epay_lookup($gatewayParams, $pidx)
{
    $payload = array(
        "pidx" => $pidx
    );
    $jsonData = khaltigateway_make_api_call($gatewayParams, KHALTIGATEWAY_EPAY_LOOKUP_API, $payload);
    return $jsonData;
}
