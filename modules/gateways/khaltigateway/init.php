<?php

/**
 * Khalti.com Payment Gateway WHMCS Module
 * @see https://docs.khalti.com/
 * @see https://github.com/khalti/whmcs-khaltigateway-plugin
 * @copyright Copyright (c) Khalti Private Limited
 * @author : @acpmasquerade for Khalti.com / aerawatcorp
 */

require_once __DIR__ . "/utils.php";
require_once __DIR__ . "/khalti_helpers.php";
require_once __DIR__ . "/checkout.php";

// Build the constants
if (!defined("KHALTIGATEWAY_WHMCS_MODULE_NAME")) {
    define("KHALTIGATEWAY_WHMCS_MODULE_NAME", "khaltigateway");

    define("KHALTIGATEWAY_PAYMENT_GATEWAY_ROOT_DIR", dirname(__FILE__));
    define("KHALTIGATEWAY_HELPERS_DIR", KHALTIGATEWAY_PAYMENT_GATEWAY_ROOT_DIR . "/" . KHALTIGATEWAY_WHMCS_MODULE_NAME);

    define("KHALTIGATEWAY_LIVE_MODE", "live");
    define("KHALTIGATEWAY_TEST_MODE", "test");

    define('KHALTIGATEWAY_EPAY_INITIATE_API', "epayment/initiate/");
    define('KHALTIGATEWAY_EPAY_LOOKUP_API', "epayment/lookup/");

    define('KHALTIGATEWAY_EPAY_TEST_ENDPOINT', "https://a.khalti.com/api/v2/"); 
    define('KHALTIGATEWAY_EPAY_LIVE_ENDPOINT', "https://khalti.com/api/v2/");

    define('KHALTIGATEWAY_WHMCS_VIEWINOVICE_PAGE', "VIEWINVOICE");
}

// Fetch gateway configuration parameters if GatewayModule is activated
try {
    $khaltigateway_gateway_params = getGatewayVariables(KHALTIGATEWAY_WHMCS_MODULE_NAME);
} catch (Exception $e) {
    // Module is probably not activated yet. 
    // simply ignore the error and return empty array.
}
