<?php

require_once "{$WHMCS_ROOT}/init.php";
$whmcs->load_function('gateway');
$whmcs->load_function('invoice');

// Detect module name from filename.
$gatewayModuleName = "khaltigateway";

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

function jdie(){
    die(json_encode(array("idx"=>null)));
}
