<?php

# Build the constants
if (!defined("KHALTIGATEWAY_WHMCS_MODULE_NAME")) {
    define("KHALTIGATEWAY_WHMCS_MODULE_NAME", "khaltigateway");

    define("KHALTIGATEWAY_PAYMENT_GATEWAY_ROOT_DIR", dirname(__FILE__));
    define("KHALTIGATEWAY_HELPERS_DIR", dirname(__FILE__) . "/" . KHALTIGATEWAY_WHMCS_MODULE_NAME);

    define("KHALTIGATEWAY_LIVE_MODE", "live");
    define("KHALTIGATEWAY_TEST_MODE", "test");

    define('KHALTIGATEWAY_EPAY_INITIATE_API', "epayment/initiate/");
    define('KHALTIGATEWAY_EPAY_LOOKUP_API', "epayment/lookup/");

    define('KHALTIGATEWAY_EPAY_TEST_ENDPOINT', "https://a.khalti.com/api/v2/");
    define('KHALTIGATEWAY_EPAY_LIVE_ENDPOINT', "https://a.khalti.com/api/v2/");
}

// // Require libraries needed for gateway module functions.
// $WHMCS_ROOT = dirname($_SERVER['SCRIPT_FILENAME']);
// require_once "{$WHMCS_ROOT}/init.php";

// print_r($whmcs);
// die();

// $whmcs->load_function('gateway');
// $whmcs->load_function('invoice');

// Fetch gateway configuration parameters.
$khaltigateway_gateway_params = getGatewayVariables(KHALTIGATEWAY_WHMCS_MODULE_NAME);

// Die if module is not active.
if (!$khaltigateway_gateway_params['type']) {
    die("Module Not Activated");
}

require_once __DIR__ ."/utils.php";
require_once __DIR__ ."/khalti_helpers.php";
require_once __DIR__ ."/checkout.php";
