<?php
/**
 * Khalti.com Payment Gateway WHMCS Module Callback Handler
 * 
 * @see https://docs.khalti.com/
 * 
 * @copyright Copyright (c) Khalti Private Limited	
 * @author : @yubrajpandeya for Khalti.com
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

$moduleName = 'khaltigateway';
$gatewayParams = getGatewayVariables($moduleName);
$gatewayName = $gatewayParams['name'] ?? $moduleName;

function gw_log($module, $data, $status = 'Callback') {
    logTransaction($module, $data, $status);
}
function maskSecret($s) {
    if (!$s) return null;
    return substr($s, 0, 4) . str_repeat('*', max(0, strlen($s) - 4));
}
function returnJson($module, $payload, $httpCode = 200) {
    gw_log($module, $payload, $payload['result'] ?? 'Callback JSON');
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

// Read input (POST, GET or raw JSON)
$raw = file_get_contents('php://input');
$payload = !empty($_POST) ? $_POST : (!empty($_GET) ? $_GET : (!empty($raw) ? json_decode($raw, true) : []));
if (!is_array($payload)) $payload = [];

gw_log($gatewayName, ['incoming' => $payload, 'method' => $_SERVER['REQUEST_METHOD']], 'Callback Received');

// Extract common fields
$pidx = $payload['pidx'] ?? null;
$cb_transaction_id = $payload['transaction_id'] ?? ($payload['txnId'] ?? ($payload['tidx'] ?? null));
$amount_param = $payload['amount'] ?? ($payload['total_amount'] ?? null); // paisa expected
$purchase_order_id = $payload['purchase_order_id'] ?? ($payload['purchase_order_name'] ?? null);
$status_param = $payload['status'] ?? null;

// Basic presence checks
if (!$pidx || !$purchase_order_id || !$amount_param) {
    gw_log($gatewayName, ['error' => 'Missing required callback params', 'payload' => $payload], 'Error');
    // try redirect back to invoice if possible
    $invRedirect = null;
    if ($purchase_order_id) {
        if (is_numeric($purchase_order_id)) $invRedirect = (int) $purchase_order_id;
        elseif (preg_match('/(\d{1,10})/', $purchase_order_id, $m)) $invRedirect = (int)$m[1];
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $invRedirect) {
        $sys = rtrim($gatewayParams['systemurl'] ?? ($gatewayParams['SystemURL'] ?? ''), '/');
        header('Location: ' . ($sys ?: '') . '/viewinvoice.php?id=' . $invRedirect . '&khalti_verify=missing_params');
        exit;
    }
    returnJson($gatewayName, ['status' => 'error', 'message' => 'Missing required callback params', 'payload' => $payload], 400);
}

// Normalize amounts (Khalti uses paisa)
$amount_paisa = intval($amount_param);
if ($amount_paisa <= 0) {
    returnJson($gatewayName, ['status' => 'error', 'message' => 'Invalid amount value', 'amount' => $amount_param], 400);
}
$amount_rs = $amount_paisa / 100.0;

// Extract invoice id (handles numeric or strings containing invoice number)
$invoice_id = null;
if (is_numeric($purchase_order_id)) {
    $invoice_id = (int)$purchase_order_id;
} else {
    if (preg_match('/(\d{1,10})/', $purchase_order_id, $m)) {
        $invoice_id = (int)$m[1];
    }
}
if (!$invoice_id) {
    gw_log($gatewayName, ['error' => 'Unable to extract invoice id', 'purchase_order_id' => $purchase_order_id], 'Error');
    returnJson($gatewayName, ['status' => 'error', 'message' => 'Unable to extract invoice id from purchase_order_id'], 400);
}

// Validate invoice id with WHMCS helper (will log and/or exit on problems)
try {
    checkCbInvoiceID($invoice_id, $gatewayName);
} catch (Exception $e) {
    gw_log($gatewayName, ['error' => 'checkCbInvoiceID failed', 'exception' => $e->getMessage()], 'Error');
    returnJson($gatewayName, ['status' => 'error', 'message' => 'Invoice validation failed: ' . $e->getMessage()], 400);
}

// Prevent duplicate processing using callback transaction id (if provided)
if ($cb_transaction_id) {
    try {
        checkCbTransID($cb_transaction_id);
    } catch (Exception $e) {
        // Duplicate; redirect to invoice
        gw_log($gatewayName, ['error' => 'Duplicate transaction in callback', 'txid' => $cb_transaction_id], 'Error');
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $sys = rtrim($gatewayParams['systemurl'] ?? ($gatewayParams['SystemURL'] ?? ''), '/');
            header('Location: ' . ($sys ?: '') . '/viewinvoice.php?id=' . $invoice_id . '&khalti_verify=duplicate_tx');
            exit;
        } else {
            returnJson($gatewayName, ['status' => 'error', 'message' => 'Duplicate transaction: ' . $cb_transaction_id], 400);
        }
    }
}

// Resolve secret and testmode using your khaltigateway_config keys
// Primary expected keys: test_api_key, live_api_key, is_test_mode
$possible_secret_keys = ['test_api_key', 'live_api_key', 'live_api', 'test_api', 'live_secret_key','live_key','test_key','secretKey','secret_key','secret','api_key'];
$secret = null;
$found_keys = [];
// prefer the environment flag is_test_mode to select test/live key
$testmode = false;
$tm = $gatewayParams['is_test_mode'] ?? $gatewayParams['testmode'] ?? $gatewayParams['sandbox'] ?? null;
if ($tm === 'on' || $tm === '1' || $tm === 'yes' || $tm === 'true') $testmode = true;

// pick according to testmode
if ($testmode) {
    if (!empty($gatewayParams['test_api_key'])) { $secret = trim($gatewayParams['test_api_key']); $found_keys[] = 'test_api_key'; }
} else {
    if (!empty($gatewayParams['live_api_key'])) { $secret = trim($gatewayParams['live_api_key']); $found_keys[] = 'live_api_key'; }
}
// fallback search through many possible names
if (!$secret && is_array($gatewayParams)) {
    foreach ($possible_secret_keys as $k) {
        if (!empty($gatewayParams[$k])) {
            $secret = trim($gatewayParams[$k]);
            $found_keys[] = $k;
            break;
        }
    }
}
// legacy global/config/env fallback
if (!$secret && isset($khaltigateway_gateway_params) && is_array($khaltigateway_gateway_params)) {
    foreach ($possible_secret_keys as $k) {
        if (!empty($khaltigateway_gateway_params[$k])) { $secret = $khaltigateway_gateway_params[$k]; $found_keys[] = 'legacy:'.$k; break; }
    }
}
if (!$secret && file_exists(__DIR__ . '/khalti_config.php')) {
    $cfg = include __DIR__ . '/khalti_config.php';
    if (is_array($cfg)) {
        foreach ($possible_secret_keys as $k) {
            if (!empty($cfg[$k])) { $secret = $cfg[$k]; $found_keys[] = 'file:'.$k; break; }
        }
    }
}
if (!$secret && getenv('KHALTI_SECRET')) { $secret = getenv('KHALTI_SECRET'); $found_keys[] = 'env:KHALTI_SECRET'; }

gw_log($gatewayName, ['found_keys' => $found_keys, 'masked_secret' => maskSecret($secret), 'testmode' => $testmode], 'Secret detection');

// If no secret & not in testmode -> redirect/return with helpful message
if (!$secret && !$testmode) {
    gw_log($gatewayName, ['error' => 'Khalti secret missing and testmode disabled'], 'Error');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $sys = rtrim($gatewayParams['systemurl'] ?? ($gatewayParams['SystemURL'] ?? ''), '/');
        header('Location: ' . ($sys ?: '') . '/viewinvoice.php?id=' . $invoice_id . '&khalti_verify=missing_secret');
        exit;
    }
    returnJson($gatewayName, ['status' => 'error', 'message' => 'Khalti secret key not set in gateway configuration.'], 400);
}

// Choose API base (lookup endpoint)
$apiLookup = $testmode ? 'https://dev.khalti.com/api/v2/epayment/lookup/' : 'https://khalti.com/api/v2/epayment/lookup/';

// Lookup function (calls Khalti /epayment/lookup/ with JSON body {"pidx":"..."} )
function khalti_lookup($url, $pidx, $secret) {
    $ch = curl_init($url);
    $payload = json_encode(['pidx' => $pidx]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Key ' . $secret,
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false) return ['ok' => false, 'error' => 'cURL error: ' . $err, 'http' => $httpCode];
    $json = json_decode($resp, true);
    if (!is_array($json)) return ['ok' => false, 'error' => 'Invalid JSON from Khalti', 'raw' => $resp, 'http' => $httpCode];
    return ['ok' => true, 'response' => $json, 'http' => $httpCode];
}

// If we have a secret, perform lookup
$lookup = null;
if ($secret) {
    $lookup = khalti_lookup($apiLookup, $pidx, $secret);
    gw_log($gatewayName, ['lookup' => $lookup], 'Khalti Lookup');

    if (!$lookup['ok']) {
        // lookup failed: show friendly redirect to invoice for GETs
        gw_log($gatewayName, ['error' => 'Khalti lookup request failed', 'detail' => $lookup], 'Error');
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $sys = rtrim($gatewayParams['systemurl'] ?? ($gatewayParams['SystemURL'] ?? ''), '/');
            header('Location: ' . ($sys ?: '') . '/viewinvoice.php?id=' . $invoice_id . '&khalti_verify=lookup_failed');
            exit;
        } else {
            returnJson($gatewayName, ['status' => 'error', 'message' => 'Khalti lookup failed', 'details' => $lookup], 400);
        }
    }

    $resp = $lookup['response'];
    $lk_status = $resp['status'] ?? null;                 // Completed, Pending, etc
    $lk_total = isset($resp['total_amount']) ? intval($resp['total_amount']) : null; // paisa
    $lk_txid = $resp['transaction_id'] ?? ($resp['tidx'] ?? null);

    // Status must be Completed to succeed
    if (!($lk_status && strtolower($lk_status) === 'completed')) {
        gw_log($gatewayName, ['error' => 'Khalti lookup returned non-complete status', 'status' => $lk_status, 'resp' => $resp], 'Error');
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $sys = rtrim($gatewayParams['systemurl'] ?? ($gatewayParams['SystemURL'] ?? ''), '/');
            header('Location: ' . ($sys ?: '') . '/viewinvoice.php?id=' . $invoice_id . '&khalti_verify=not_completed');
            exit;
        } else {
            returnJson($gatewayName, ['status' => 'error', 'message' => 'Payment not completed according to Khalti lookup', 'lookup' => $resp], 400);
        }
    }

    // Determine final txid (prefer lookup transaction id)
    if (!$lk_txid) $lk_txid = $cb_transaction_id;
    if (!$lk_txid) {
        gw_log($gatewayName, ['error' => 'No transaction id from lookup or callback', 'lookup' => $resp], 'Error');
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $sys = rtrim($gatewayParams['systemurl'] ?? ($gatewayParams['SystemURL'] ?? ''), '/');
            header('Location: ' . ($sys ?: '') . '/viewinvoice.php?id=' . $invoice_id . '&khalti_verify=no_txid');
            exit;
        } else {
            returnJson($gatewayName, ['status' => 'error', 'message' => 'No transaction id provided by Khalti lookup'], 400);
        }
    }

    // Prevent duplicate using lookup txid
    try {
        checkCbTransID($lk_txid);
    } catch (Exception $e) {
        gw_log($gatewayName, ['error' => 'Duplicate transaction (lookup txid)', 'txid' => $lk_txid], 'Error');
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $sys = rtrim($gatewayParams['systemurl'] ?? ($gatewayParams['SystemURL'] ?? ''), '/');
            header('Location: ' . ($sys ?: '') . '/viewinvoice.php?id=' . $invoice_id . '&khalti_verify=duplicate_tx');
            exit;
        } else {
            returnJson($gatewayName, ['status' => 'error', 'message' => 'Duplicate transaction: ' . $lk_txid], 400);
        }
    }

    // Amount checks: compare lookup total_amount (paisa) with callback and invoice
    if ($lk_total !== null) {
        if ($lk_total !== $amount_paisa) {
            gw_log($gatewayName, ['error' => 'Amount mismatch between callback and lookup', 'callback' => $amount_paisa, 'lookup' => $lk_total], 'Error');
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $sys = rtrim($gatewayParams['systemurl'] ?? ($gatewayParams['SystemURL'] ?? ''), '/');
                header('Location: ' . ($sys ?: '') . '/viewinvoice.php?id=' . $invoice_id . '&khalti_verify=amount_mismatch');
                exit;
            } else {
                returnJson($gatewayName, ['status' => 'error', 'message' => 'Amount mismatch between callback and Khalti lookup', 'callback' => $amount_paisa, 'lookup' => $lk_total], 400);
            }
        }

        // Validate invoice amount using checkCbAmount (expects rupees)
        $lk_amount_rs = $lk_total / 100.0;
        try {
            if (function_exists('checkCbAmount')) {
                checkCbAmount($lk_amount_rs, $invoice_id);
            } else {
                // fallback: simple compare
                $invoiceData = localAPI('GetInvoice', ['invoiceid' => $invoice_id], $gatewayParams['adminuser'] ?? null);
                if (($invoiceData['result'] ?? '') === 'success') {
                    $expected = floatval($invoiceData['total']);
                    if (abs($expected - $lk_amount_rs) > 0.01) {
                        gw_log($gatewayName, ['error' => 'Invoice total mismatch', 'invoice_total' => $expected, 'lookup_amount' => $lk_amount_rs], 'Error');
                        returnJson($gatewayName, ['status' => 'error', 'message' => 'Invoice total mismatch', 'invoice' => $expected, 'lookup' => $lk_amount_rs], 400);
                    }
                } else {
                    gw_log($gatewayName, ['warning' => 'Unable to fetch invoice for amount comparison', 'result' => $invoiceData], 'Warning');
                }
            }
        } catch (Exception $e) {
            gw_log($gatewayName, ['error' => 'checkCbAmount failed', 'exception' => $e->getMessage()], 'Error');
            returnJson($gatewayName, ['status' => 'error', 'message' => 'Amount validation failed: ' . $e->getMessage()], 400);
        }
    }

    // All good — record payment
    $amountFormatted = number_format($lk_total !== null ? ($lk_total/100.0) : $amount_rs, 2, '.', '');
    try {
        addInvoicePayment($invoice_id, $lk_txid, $amountFormatted, 0, $moduleName);
        gw_log($gatewayName, ['message' => 'Payment applied', 'invoiceid' => $invoice_id, 'txid' => $lk_txid, 'amount' => $amountFormatted], 'Success');
    } catch (Exception $e) {
        gw_log($gatewayName, ['error' => 'addInvoicePayment failed', 'exception' => $e->getMessage()], 'Error');
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $sys = rtrim($gatewayParams['systemurl'] ?? ($gatewayParams['SystemURL'] ?? ''), '/');
            header('Location: ' . ($sys ?: '') . '/viewinvoice.php?id=' . $invoice_id . '&khalti_verify=add_failed');
            exit;
        } else {
            returnJson($gatewayName, ['status' => 'error', 'message' => 'Failed to record payment: ' . $e->getMessage()], 500);
        }
    }

    // Redirect browser clients to the invoice page (clean)
    $sys = rtrim($gatewayParams['systemurl'] ?? ($gatewayParams['SystemURL'] ?? ''), '/');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Location: ' . ($sys ?: '') . '/viewinvoice.php?id=' . $invoice_id);
        exit;
    } else {
        returnJson($gatewayName, ['status' => 'success', 'invoice' => $invoice_id, 'txid' => $lk_txid, 'amount' => $amountFormatted], 200);
    }
}

// If we reach here there is no secret but testmode must be true (fallback)
if (!$testmode) {
    gw_log($gatewayName, ['error' => 'No secret and test mode disabled'], 'Error');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $sys = rtrim($gatewayParams['systemurl'] ?? ($gatewayParams['SystemURL'] ?? ''), '/');
        header('Location: ' . ($sys ?: '') . '/viewinvoice.php?id=' . $invoice_id . '&khalti_verify=missing_secret');
        exit;
    }
    returnJson($gatewayName, ['status' => 'error', 'message' => 'Missing secret and testmode disabled'], 400);
}

// TEST fallback (strict): status must be Completed and invoice amount must match
if (empty($status_param) || strtolower($status_param) !== 'completed') {
    gw_log($gatewayName, ['error' => 'Test fallback denied - status not completed', 'status' => $status_param], 'Error');
    $sys = rtrim($gatewayParams['systemurl'] ?? ($gatewayParams['SystemURL'] ?? ''), '/');
    header('Location: ' . ($sys ?: '') . '/viewinvoice.php?id=' . $invoice_id . '&khalti_test_fallback=denied');
    exit;
}

// Fetch invoice and validate amount
$inv = localAPI('GetInvoice', ['invoiceid' => $invoice_id], $gatewayParams['adminuser'] ?? null);
if (($inv['result'] ?? '') !== 'success') {
    gw_log($gatewayName, ['error' => 'Test fallback invoice fetch failed', 'invoice_result' => $inv], 'Error');
    $sys = rtrim($gatewayParams['systemurl'] ?? ($gatewayParams['SystemURL'] ?? ''), '/');
    header('Location: ' . ($sys ?: '') . '/viewinvoice.php?id=' . $invoice_id . '&khalti_test_fallback=invoice_fetch_failed');
    exit;
}
$invoiceTotalRs = floatval($inv['total']);
$invoiceTotalPaisa = (int) round($invoiceTotalRs * 100);
if (abs($invoiceTotalPaisa - $amount_paisa) > 1) {
    gw_log($gatewayName, ['error' => 'Test fallback amount mismatch', 'invoice' => $invoiceTotalPaisa, 'callback' => $amount_paisa], 'Error');
    $sys = rtrim($gatewayParams['systemurl'] ?? ($gatewayParams['SystemURL'] ?? ''), '/');
    header('Location: ' . ($sys ?: '') . '/viewinvoice.php?id=' . $invoice_id . '&khalti_test_fallback=amount_mismatch');
    exit;
}

// Use callback txid or synthetic test id
$final_txid = $cb_transaction_id ?? ('khalti_test_' . time());
$amountFormatted = number_format($amount_rs, 2, '.', '');
try {
    addInvoicePayment($invoice_id, $final_txid, $amountFormatted, 0, $moduleName);
    gw_log($gatewayName, ['message' => 'Test fallback payment applied', 'invoice' => $invoice_id, 'txid' => $final_txid, 'amount' => $amountFormatted], 'Success');
} catch (Exception $e) {
    gw_log($gatewayName, ['error' => 'addInvoicePayment failed (test fallback)', 'exception' => $e->getMessage()], 'Error');
    $sys = rtrim($gatewayParams['systemurl'] ?? ($gatewayParams['SystemURL'] ?? ''), '/');
    header('Location: ' . ($sys ?: '') . '/viewinvoice.php?id=' . $invoice_id . '&khalti_test_fallback=add_failed');
    exit;
}

$sys = rtrim($gatewayParams['systemurl'] ?? ($gatewayParams['SystemURL'] ?? ''), '/');
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Location: ' . ($sys ?: '') . '/viewinvoice.php?id=' . $invoice_id);
    exit;
} else {
    returnJson($gatewayName, ['status' => 'success', 'invoice' => $invoice_id, 'txid' => $final_txid, 'amount' => $amountFormatted], 200);
}
