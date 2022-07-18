<?php
function khaltigateway_get_livetest_mode($gatewayParams)
{
    $testMode = $gatewayParams['testMode'];
    if ($testMode == 'on' || $testMode === true) {
        return "test";
    } else {
        return "live";
    }
}

function khaltigateway_api_endpoint($gatewayParams)
{
    $livetestMode = khaltigateway_get_livetest_mode($gatewayParams);
    if ($livetestMode == "test") {
        $endpoint = "https://a.khalti.com/";
    } else {
        $endpoint = "https://khalti.com/";
    }
    return "{$endpoint}api/v2/";
}

function khaltigateway_payment_initiate_endpoint($gatewayParams)
{
    return khaltigateway_api_endpoint($gatewayParams) . "epayment/initiate/";
}

function khaltigateway_get_payment_key($gatewayParams)
{
    $mode = khaltigateway_get_livetest_mode($gatewayParams);
    return $gatewayParams["{$mode}PaymentAPIKey"];
}

function khaltigateway_transaction_initiate_for_checkout($checkout_params, $gatewayParams)
{
    $url = khaltigateway_payment_initiate_endpoint($gatewayParams);
    $api_key = khaltigateway_get_payment_key($gatewayParams);

    $postdata = json_encode($checkout_params);

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
        die('Unable to connect: ' . curl_errno($ch) . ' - ' . curl_error($ch));
    }
    curl_close($ch);

    $jsonData = json_decode($response, true);
    return $jsonData;
}

// function khaltigateway_get_secret_key($gatewayParams){
//     $mode = khaltigateway_get_livetest_mode($gatewayParams);
//     return $gatewayParams["{$mode}SecretKey"];
// }

// function khaltigateway_get_public_key($gatewayParams){
//     $mode = khaltigateway_get_livetest_mode($gatewayParams);
//     return $gatewayParams["{$mode}PublicKey"];
// }

function khaltigateway_get_transaction($gatewayParams, $idx)
{
    $url = "https://khalti.com/api/v2/merchant-transaction/{$idx}/";

    # Make the call using API.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $khaltiSecretKey = khaltigateway_get_secret_key($gatewayParams);
    $headers = ['Authorization: Key ' . $khaltiSecretKey];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Response
    $response = curl_exec($ch);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $response;
}

function khaltigateway_confirm_transaction($gatewayParams, $khaltiToken, $khaltiAmount)
{
    $args = http_build_query(array(
        'token' => $khaltiToken,
        'amount'  => $khaltiAmount
    ));

    $url = "https://khalti.com/api/v2/payment/verify/";

    # Make the call using API.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $khaltiSecretKey = khaltigateway_get_secret_key($gatewayParams);
    $headers = ['Authorization: Key ' . $khaltiSecretKey];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Response
    $response = curl_exec($ch);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $response;
}
