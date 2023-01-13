<?php

/**
 * Khalti.com Payment Gateway WHMCS Module
 * 
 * @see https://docs.khalti.com/
 * 
 * @copyright Copyright (c) Khalti Private Limited
 * @author : @acpmasquerade for Khalti.com
 */


if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . "/khaltigateway/init.php";

function khaltigateway_MetaData()
{
    return array(
        'DisplayName' => 'Khalti Payment Gateway (KPG-2)',
        'APIVersion' => '2.0', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

function khaltigateway_config()
{
    $sandbox_target = "<a href='https://sandbox.khalti.com' target='_blank'>sandbox.khalti.com</a>";
    $live_target = "<a href='https://admin.khalti.com' target='_blank'>admin.khalti.com</a>";

    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Khalti.com Payment Gateway (KPG-2)',
        ),
        'test_api_key' => array(
            'FriendlyName' => 'TEST API Secret Key for KPG-2',
            'Type' => 'text',
            'Size' => '48',
            'Default' => 'test_key_01234567890123456789012345678901',
            'Description' => "Please visit {$sandbox_target} to get your keys",
        ),
        'live_api_key' => array(
            'FriendlyName' => 'LIVE API Secret Key for KPG-2',
            'Type' => 'password',
            'Size' => '48',
            'Default' => 'live_key_01234567890123456789012345678901',
            'Description' => "Please visit {$live_target} to get your keys",
        ),
        'is_debug_mode' => array(
            'FriendlyName' => 'Enable Debugging',
            'Type' => 'yesno',
            'Description' => 'Tick to enable debugging mode',
        ),
        'is_test_mode' => array(
            'FriendlyName' => 'Enable Test (sandbox) Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable sandbox mode of integration',
        )
    );
}

function khaltigateway_link($gateway_params)
{
    $currentPage = khaltigateway_whmcs_current_page();
    if ($currentPage !== "VIEWINVOICE") {
        return khaltigateway_noinvoicepage_code();
    }
    return  khaltigateway_invoicepage_code($gateway_params);
}

/**
 * @TODO: Implement this function
 */
function khaltigateway_refund($gateway_params)
{
    return false;

    // Gateway Configuration Parameters
    $accountId = $gateway_params['accountID'];
    $secretKey = $gateway_params['secretKey'];
    $testMode = $gateway_params['testMode'];
    $dropdownField = $gateway_params['dropdownField'];
    $radioField = $gateway_params['radioField'];
    $textareaField = $gateway_params['textareaField'];

    // Transaction Parameters
    $transactionIdToRefund = $gateway_params['transid'];
    $refundAmount = $gateway_params['amount'];
    $currencyCode = $gateway_params['currency'];

    // Client Parameters
    $firstname = $gateway_params['clientdetails']['firstname'];
    $lastname = $gateway_params['clientdetails']['lastname'];
    $email = $gateway_params['clientdetails']['email'];
    $address1 = $gateway_params['clientdetails']['address1'];
    $address2 = $gateway_params['clientdetails']['address2'];
    $city = $gateway_params['clientdetails']['city'];
    $state = $gateway_params['clientdetails']['state'];
    $postcode = $gateway_params['clientdetails']['postcode'];
    $country = $gateway_params['clientdetails']['country'];
    $phone = $gateway_params['clientdetails']['phonenumber'];

    // System Parameters
    $companyName = $gateway_params['companyname'];
    $system_url = $gateway_params['systemurl'];
    $langPayNow = $gateway_params['langpaynow'];
    $moduleDisplayName = $gateway_params['name'];
    $moduleName = $gateway_params['paymentmethod'];
    $whmcsVersion = $gateway_params['whmcsVersion'];

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
