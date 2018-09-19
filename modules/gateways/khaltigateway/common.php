<?php
function khaltigateway_get_livetest_mode($gatewayParams){
    $testMode = $gatewayParams['testMode'];
    if($testMode == 'on' || $testMode === true){
        return "test";
    }else{
        return "live";
    }
}

function khaltigateway_get_public_key($gatewayParams){
    $mode = khaltigateway_get_livetest_mode($gatewayParams);
    return $gatewayParams["{$mode}PublicKey"];
}

function khaltigateway_get_secret_key($gatewayParams){
    $mode = khaltigateway_get_livetest_mode($gatewayParams);
    return $gatewayParams["{$mode}SecretKey"];
}

function khaltigateway_get_transaction($gatewayParams, $idx){
    $url = "https://khalti.com/api/v2/merchant-transaction/{$idx}/";

    # Make the call using API.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $khaltiSecretKey = khaltigateway_get_secret_key($gatewayParams);
    $headers = ['Authorization: Key '.$khaltiSecretKey];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Response
    $response = curl_exec($ch);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $response;
}

function khaltigateway_confirm_transaction($gatewayParams, $khaltiToken, $khaltiAmount){
    $args = http_build_query(array(
        'token' => $khaltiToken,
        'amount'  => $khaltiAmount
    ));
    
    $url = "https://khalti.com/api/v2/payment/verify/";
    
    # Make the call using API.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,$args);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $khaltiSecretKey = khaltigateway_get_secret_key($gatewayParams);
    $headers = ['Authorization: Key '.$khaltiSecretKey];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    // Response
    $response = curl_exec($ch);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $response;
}
