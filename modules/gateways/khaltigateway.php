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

require_once __DIR__ . "/khaltigateway/common.php";

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
        'APIVersion' => '2.0', // Use API Version 1.1
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
        // 'livePublicKey' => array(
        //     'FriendlyName' => 'Live Public Key',
        //     'Type' => 'text',
        //     'Size' => '48',
        //     'Default' => 'live_public_key_01234567890123456789012345678901',
        //     'Description' => 'Please visit https://khalti.com/merchant to get your keys',
        // ),
        // 'liveSecretKey' => array(
        //     'FriendlyName' => 'Live Secret Key',
        //     'Type' => 'password',
        //     'Size' => '48',
        //     'Default' => 'live_secret_key_01234567890123456789012345678901',
        //     'Description' => 'Please visit https://khalti.com/merchant to get your keys',
        // ),
        // 'testPublicKey' => array(
        //     'FriendlyName' => 'TEST Public Key',
        //     'Type' => 'text',
        //     'Size' => '48',
        //     'Default' => 'test_public_01234567890123456789012345678901',
        //     'Description' => 'Please visit https://khalti.com/merchant to get your keys',
        // ),
        // 'testSecretKey' => array(
        //     'FriendlyName' => 'TEST Secret Key',
        //     'Type' => 'password',
        //     'Size' => '48',
        //     'Default' => 'test_secret_01234567890123456789012345678901',
        //     'Description' => 'Please visit https://khalti.com/merchant to get your keys',
        // ),
        'testPaymentAPIKey' => array(
            'FriendlyName' => 'Checkout Mode TEST KEY',
            'Type' => 'password',
            'Size' => '48',
            'Default' => 'test_secret_01234567890123456789012345678901',
            'Description' => 'Please visit https://khalti.com/merchant to get your keys',
        ),
        'livePaymentAPIKey' => array(
            'FriendlyName' => 'Checkout Mode Live KEY',
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

function khaltigateway_current_page()
{
    $filename = basename($_SERVER['SCRIPT_FILENAME']);
    return str_replace(".PHP", "", strtoupper($filename));
}

function khaltigateway_processing_code()
{
    return <<<EOT
        <h3>Processing <i class='fa fa-spin fa-circle-notch'></i></h3>
EOT;
}

function khaltigateway_noinvoicepage_code()
{
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

function khaltigateway_invoicepage_code($gatewayParams)
{
    $systemUrl = $gatewayParams['systemurl'];
    $invoiceId = $gatewayParams['invoiceid'];
    $description = htmlspecialchars(strip_tags($gatewayParams["description"]));
    $amount = $gatewayParams['amount'];
    $amountInPaisa = $amount * 100;
    $currencyCode = $gatewayParams['currency'];

    $moduleUrl = "modules/gateways/khaltigateway/";

    $step2Url = $systemUrl . $moduleUrl . "kcheckout_step2.php";
    $invoiceUrl = $systemUrl . "viewinvoice.php?id={$invoiceId}";
    $successUrl = "{$invoiceUrl}&paymentsuccess=true";

    // print_r($successUrl);

    $amountInPaisa = $amount * 100;

    $processingCode = khaltigateway_processing_code();


    // print_r($gatewayParams["cart"]->items[1]->name);
    $cart = array();
    foreach ($gatewayParams["cart"]->items as $item) {
        $amount = intval($item->getAmount()->getValue() * 100);
        $qty = $item->getQuantity();
        $cart[] = array(
            "name" => $item->getName(),
            "identity"=> $item->getUuid(),
            "total_price" => $amount,
            "quantity" => $qty,
            "unit_price" => $amount / $qty
        );
    }

    $checkout_args = array(
        "return_url" => "{$step2Url}",
        "website_url" => "{$systemUrl}",
        "amount" => $amountInPaisa,
        "purchase_order_id" => "{$invoiceId}",
        "purchase_order_name" => "{$description}",
        "customer_info" => array(
            "name" => "Ashim Upadhaya",
            "email" => "example@gmail.com",
            "phone" => "9811496763"
        ),
        "amount_breakdown" => array(
            array(
                "label" => "Invoice Number - {$invoiceId}",
                "amount" => $amountInPaisa
            ),
        ),
        "product_details" => $cart
    );

    $payment_initiate = khaltigateway_epay_initiate($gatewayParams, $checkout_args);
    $pidx = $payment_initiate["pidx"] || false;
    if (!$pidx){
        $msg = $payment_initiate["status_code"] . "<h3>Error: " . $payment_initiate['message'] . "</h3>";
        $msg .= "<div style='color:Red'>Error while initiating transaction. <br /> Please check your payment gateway settings (Khalti).</div>";
        return $msg;
    }

    $pidx_url = $payment_initiate["payment_url"];

    $buttonCSS = "";

    return <<<EOT
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
                        <img src='https://khalti-mediakit.s3.ap-south-1.amazonaws.com/brand/khalti-logo-color.200.png' />
                    </div>
                </div>
                <div class='col-sm-7 text-left' style='border-left:1px solid #f9f9f9'>
                    <small>You can pay with Khalti account or other e-Banking Options</small>
                    <br />
                    <br />
                    <a id='khalti-payment-button' href='{$pidx_url}' class='btn btn-primary btn-large' style='{$buttonCSS}'>
                        {$gatewayParams['langpaynow']}
                    </a>
                </div>
            </div>
        </div>
    </div>
EOT;

}

function khaltigateway_link($gatewayParams)
{
    $currentPage = khaltigateway_current_page();
    if ($currentPage !== "VIEWINVOICE") {
        // Wait for the page to be redirected to the invoice page.
        return khaltigateway_noinvoicepage_code();
    }
    return  khaltigateway_invoicepage_code($gatewayParams);
}

/**
 * Refund transaction.
 * Yet to be implemented.
 *
 * Called when a refund is requested for a previously successful transaction.
 *
 * @param array $gatewayParams Payment Gateway Module Parameters
 *
 * @return array Transaction response status
 */
function khaltigateway_refund($gatewayParams)
{
    return false;

    // Gateway Configuration Parameters
    $accountId = $gatewayParams['accountID'];
    $secretKey = $gatewayParams['secretKey'];
    $testMode = $gatewayParams['testMode'];
    $dropdownField = $gatewayParams['dropdownField'];
    $radioField = $gatewayParams['radioField'];
    $textareaField = $gatewayParams['textareaField'];

    // Transaction Parameters
    $transactionIdToRefund = $gatewayParams['transid'];
    $refundAmount = $gatewayParams['amount'];
    $currencyCode = $gatewayParams['currency'];

    // Client Parameters
    $firstname = $gatewayParams['clientdetails']['firstname'];
    $lastname = $gatewayParams['clientdetails']['lastname'];
    $email = $gatewayParams['clientdetails']['email'];
    $address1 = $gatewayParams['clientdetails']['address1'];
    $address2 = $gatewayParams['clientdetails']['address2'];
    $city = $gatewayParams['clientdetails']['city'];
    $state = $gatewayParams['clientdetails']['state'];
    $postcode = $gatewayParams['clientdetails']['postcode'];
    $country = $gatewayParams['clientdetails']['country'];
    $phone = $gatewayParams['clientdetails']['phonenumber'];

    // System Parameters
    $companyName = $gatewayParams['companyname'];
    $systemUrl = $gatewayParams['systemurl'];
    $langPayNow = $gatewayParams['langpaynow'];
    $moduleDisplayName = $gatewayParams['name'];
    $moduleName = $gatewayParams['paymentmethod'];
    $whmcsVersion = $gatewayParams['whmcsVersion'];

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
