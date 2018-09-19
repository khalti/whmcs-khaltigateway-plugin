<?php
/**
 * WHMCS Sample Merchant Gateway Module
 *
 * This sample file demonstrates how a merchant gateway module supporting
 * 3D Secure Authentication, Captures and Refunds can be structured.
 *
 * If your merchant gateway does not support 3D Secure Authentication, you can
 * simply omit that function and the callback file from your own module.
 *
 * Within the module itself, all functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. For this
 * example file, the filename is "khaltigateway" and therefore all functions
 * begin "khaltigateway_".
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__."/khaltigateway/common.php";

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see https://developers.whmcs.com/payment-gateways/meta-data-params/
 *
 * @return array
 */
function khaltigateway_MetaData()
{
    return array(
        'DisplayName' => 'Khalti Payment Gateway',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

/**
 * Define gateway configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * Supported field types include:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each field type and their possible configuration parameters are
 * provided in the sample function below.
 *
 * @see https://developers.whmcs.com/payment-gateways/configuration/
 *
 * @return array
 */
function khaltigateway_config()
{
    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Khalti.com Payment Gateway',
        ),
        // a text field type allows for single line text input
        'livePublicKey' => array(
            'FriendlyName' => 'Live Public Key',
            'Type' => 'text',
            'Size' => '48',
            'Default' => 'live_public_key_01234567890123456789012345678901',
            'Description' => 'Please visit https://khalti.com/merchant to get your keys',
        ),
        'liveSecretKey' => array(
            'FriendlyName' => 'Live Secret Key',
            'Type' => 'password',
            'Size' => '48',
            'Default' => 'live_secret_key_01234567890123456789012345678901',
            'Description' => 'Please visit https://khalti.com/merchant to get your keys',
        ),
        'testPublicKey' => array(
            'FriendlyName' => 'TEST Public Key',
            'Type' => 'text',
            'Size' => '48',
            'Default' => 'test_public_01234567890123456789012345678901',
            'Description' => 'Please visit https://khalti.com/merchant to get your keys',
        ),
        'testSecretKey' => array(
            'FriendlyName' => 'TEST Secret Key',
            'Type' => 'password',
            'Size' => '48',
            'Default' => 'test_secret_01234567890123456789012345678901',
            'Description' => 'Please visit https://khalti.com/merchant to get your keys',
        ),
        // the yesno field type displays a single checkbox option
        'testMode' => array(
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable test mode',
        )
    );
}

function khaltigateway_current_page(){
    $filename = basename($_SERVER['SCRIPT_FILENAME']);
    return str_replace(".PHP", "", strtoupper($filename));
}

function khaltigateway_processing_code(){
    return <<<EOT
        <h3>Processing <i class='fa fa-spin fa-circle-notch'></i></h3>
EOT;
}

function khaltigateway_noinvoicepage_code(){
    return <<<EOT
    <div class='row'>
    <div class='col-sm-6 col-sm-offset-3'>
    <h3>You are being redirected to the invoice page. </h3>
    <hr />
    <h4>You can choose to pay either with the balance on your <strong>Khalti Digital Wallet</strong> 
    <br />
    or
    <br />
    the e-Banking options provided by your bank</h4>
    </div>
    </div>
EOT;
}

function khaltigateway_invoicepage_code($params){

    $systemUrl = $params['systemurl'];

    $invoiceId = $params['invoiceid'];
    $description = htmlspecialchars(strip_tags($params["description"]));
    $amount = $params['amount'];
    $amountInPaisa = $amount * 100;
    $currencyCode = $params['currency'];

    $publicKey = khaltigateway_get_public_key($params);

    $moduleUrl = "modules/gateways/khaltigateway/";

    $step2Url = $systemUrl.$moduleUrl."step2.php";
    $successUrl = $systemUrl."viewinvoice.php?id={$invoiceId}&paymentsuccess=true";

    $amountInPaisa = $amount * 100;

    $processingCode = khaltigateway_processing_code();

    $buttonCSS = "";
        
    return <<<EOT
    <script src="https://khalti.com/static/khalti-checkout.js"></script>
    <script type='text/javascript'>
    function ajaxpost(payload){
        xhr = new XMLHttpRequest();
        xhr.open('POST', '{$step2Url}');
        xhr.onload = function() {
            console.log("Response Received - " + xhr.status, " -> ", xhr.responseText);
            var resp = xhr.responseText;
            document.getElementById('khaltigateway-button-content').style.display="none";
            document.getElementById('khaltigateway-processing').style.display="";    
            if (xhr.status != 200) {
                document.getElementById('khaltigateway-processing').innerHTML = resp;
                alert("Payment processing failed");
            }else{
                document.getElementById('khaltigateway-processing').innerHTML = "<h4>Thank you. We have received your payment.</h4>";
                window.setTimeout(function(){
                    location.href="{$successUrl}";
                }, 300);
            }
        };
        xhr.send(JSON.stringify(payload));
    }
    </script>    
    <div class='row' id='khaltigateway-button-wrapper'>
        <div class='col-sm-12' style='padding:2em;'>
            <div class='row' id='khaltigateway-processing' style='display:none'>
                <div class='col-sm-12'>
                    {$processingCode}
                </div>
            </div>
            <div class='row' id='khaltigateway-button-content'>
                <div class='col-sm-5'>
                    <div class='thumbnail' style='border:0px;box-shadow:none; margin-top:2em;'>
                        <img src='https://d7vw40z4bofef.cloudfront.net/static/khalti_logo_alt.png' />
                    </div>
                </div>
                <div class='col-sm-7 text-left' style='border-left:1px solid #f9f9f9'>
                    <small>You can pay with Khalti account or other e-Banking Options</small>
                    <br />
                    <br />
                    <button id='khalti-payment-button' onclick='javascript:void(0)' class='btn btn-primary btn-large' style='{$buttonCSS}'>
                        {$params['langpaynow']}
                    </button>
                </div>
            </div>
        </div>
    </div>
    <script>
        var config = {
            "publicKey": "{$publicKey}",
            "productIdentity": "Invoice#{$invoiceId} - [{$currencyCode}{$amount}]",
            "productName": "{$description}",
            "productUrl": "viewinvoice.php?id={$invoiceId}",
            "eventHandler": {
                onSuccess (payload) {
                    console.log(payload);
                    var confirmationPayload = payload;
                    confirmationPayload['invoiceId'] = '{$invoiceId}';
                    ajaxpost(confirmationPayload);
                },
                onError (error) {
                    alert("Payment processing failed");
                    console.log(error);
                },
                onClose () {
                    document.getElementById('khaltigateway-button-content').style.display="";
                    document.getElementById('khaltigateway-processing').style.display="none";
                    console.log('widget is closing');
                }
            }
        };
        var checkout = new KhaltiCheckout(config);
        var btn = document.getElementById("khalti-payment-button");
        btn.onclick = function () {
            document.getElementById('khaltigateway-button-content').style.display="none";
            document.getElementById('khaltigateway-processing').style.display="";
            checkout.show({amount: {$amountInPaisa}});
        }
    </script>
EOT;
}

function khaltigateway_link($params) {
    $currentPage = khaltigateway_current_page();
    if($currentPage !== "VIEWINVOICE"){
        // Wait for the page to be redirected to the invoice page.
        return khaltigateway_noinvoicepage_code();
    }
    return  khaltigateway_invoicepage_code($params);
}

/**
 * Refund transaction.
 * Yet to be implemented.
 *
 * Called when a refund is requested for a previously successful transaction.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @return array Transaction response status
 */
function khaltigateway_refund($params)
{
    return false;

    // Gateway Configuration Parameters
    $accountId = $params['accountID'];
    $secretKey = $params['secretKey'];
    $testMode = $params['testMode'];
    $dropdownField = $params['dropdownField'];
    $radioField = $params['radioField'];
    $textareaField = $params['textareaField'];

    // Transaction Parameters
    $transactionIdToRefund = $params['transid'];
    $refundAmount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    // perform API call to initiate refund and interpret result

    return array(
        // 'success' if successful, otherwise 'declined', 'error' for failure
        'status' => 'success',
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $responseData,
        // Unique Transaction ID for the refund transaction
        'transid' => $refundTransactionId,
        // Optional fee amount for the fee value refunded
        'fees' => $feeAmount,
    );
}
