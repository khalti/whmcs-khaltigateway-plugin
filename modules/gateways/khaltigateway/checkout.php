<?php

/**
 * Khalti.com Payment Gateway WHMCS Module
 * 
 * @see https://docs.khalti.com/
 * 
 * @copyright Copyright (c) Khalti Private Limited
 * @author : @acpmasquerade for Khalti.com
 */

function khaltigateway_noinvoicepage_code()
{
    return file_get_contents(__DIR__ . "/templates/noninvoice_page.html");
}

function khaltigateway_invoicepage_code($gateway_params)
{
    $system_url = $gateway_params['systemurl'];
    $invoice_id = $gateway_params['invoiceid'];
    $description = htmlspecialchars(strip_tags($gateway_params["description"]));
    $amount = $gateway_params['amount'];
    $currency_code = $gateway_params['currency'];

    if (!khaltigateway_validate_currency($currency_code)) {
        $npr_amount = khaltigateway_convert_currency($currency_code, $amount);
        if ($npr_amount === FALSE) {
            return file_get_contents(__DIR__ . '/templates/invalid_currency.html');
        }
        khaltigateway_testmode_debug($gateway_params, "Converted amount: " . $npr_amount . " from " . $amount . " " . $currency_code . " to NPR");
    } else {
        $npr_amount = $amount;
    }

    $npr_amount_in_paisa = $npr_amount * 100;

    $module_url = "modules/gateways/khaltigateway/";

    $callback_url = $system_url . $module_url . "callback.php";
    $invoice_url = $system_url . "viewinvoice.php?id={$invoice_id}";
    $successUrl = "{$invoice_url}&paymentsuccess=true";

    $cart = array();
    foreach ($gateway_params["cart"]->items as $item) {
        $amount = $item->getAmount()->getValue();
        $currency_code = $item->getAmount()->getCurrency()['code'];
        if (!khaltigateway_validate_currency($currency_code)) {
            $amount = khaltigateway_convert_currency($currency_code, $amount);
            if ($amount === FALSE) {
                return file_get_contents(__DIR__ . '/templates/invalid_currency.html');
            }
        }

        $item_amount_in_paisa = intval($amount * 100);

        $qty = $item->getQuantity();
        $cart[] = array(
            "name" => $item->getName(),
            "identity" => $item->getUuid(),
            "total_price" => $item_amount_in_paisa,
            "quantity" => $qty,
            "unit_price" => $item_amount_in_paisa / $qty
        );
    }

    $checkout_args = array(
        "return_url" => "{$callback_url}",
        "website_url" => "{$system_url}",
        "amount" => $npr_amount_in_paisa,
        "purchase_order_id" => "{$invoice_id}",
        "purchase_order_name" => "{$description}",
        "customer_info" => array(
            "name" => "Ashim Upadhaya", // todo
            "email" => "example@gmail.com", // todo
            "phone" => "9811496763" //todo
        ),
        "amount_breakdown" => array(
            array(
                "label" => "Invoice Number - {$invoice_id}",
                "amount" => $npr_amount_in_paisa
            ),
        ),
        "product_details" => $cart
    );

    $payment_initiate = khaltigateway_epay_initiate($gateway_params, $checkout_args);
    $pidx = $payment_initiate["pidx"];

    if (!$pidx) {
        return file_get_contents(__DIR__ . "/templates/initiate_failed.html");
    }

    /** 
     * Variables required for the template
     * pidx_url
     * button_css
     * gateway_params
     * npr_amount
     */
    $pidx_url = $payment_initiate["payment_url"];
    return file_include_contents(__DIR__ . "/templates/invoice_payment_button.php", array(
        'khalti_logo_url' => 'https://khalti-mediakit.s3.ap-south-1.amazonaws.com/brand/khalti-logo-color.200.png',
        "pidx_url" => $pidx_url,
        "button_css" => "",
        "gateway_params" => $gateway_params,
        "npr_amount" => $npr_amount
    ));
}
