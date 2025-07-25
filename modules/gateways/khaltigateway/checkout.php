<?php
/**
 * Khalti.com Payment Gateway WHMCS Module
 * @see https://docs.khalti.com/
 * @see https://github.com/khalti/whmcs-khaltigateway-plugin
 * @copyright Copyright (c) Khalti Private Limited
 * @author : @acpmasquerade for Khalti.com / aerawatcorp
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
        if ($npr_amount === false) {
            return khaltigateway_invalid_currency_page();
        }
        khaltigateway_debug($gateway_params, "Converted amount: {$npr_amount} from {$amount} {$currency_code} to NPR");
    } else {
        $npr_amount = $amount;
    }

    $invoice = khaltigateway_whmcs_get_invoice($invoice_id);
    $userid = $invoice["userid"];

    $customer_details = khaltigateway_whmcs_get_client($userid);
    $customer_name = $customer_details["fullname"];
    $customer_email = $customer_details["email"];
    $customer_phone_number = $customer_details["phonenumber"];

    $npr_amount_in_paisa = $npr_amount * 100;
    $module_url = "modules/gateways/khaltigateway/";

    $callback_url = "{$system_url}{$module_url}callback.php";
    $invoice_url = "{$system_url}viewinvoice.php?id={$invoice_id}";
    $successUrl = "{$invoice_url}&paymentsuccess=true";

        $cart = array();

        foreach ($gateway_params["cart"]->items as $item) {
            $amount = $item->getAmount()->getValue();
            $currency_code = $item->getAmount()->getCurrency()['code'];

            // Convert to NPR if necessary
            if (!khaltigateway_validate_currency($currency_code)) {
                $converted_amount = khaltigateway_convert_currency($currency_code, $amount);
                if ($converted_amount === false) {
                    return khaltigateway_invalid_currency_page();
                }
                $amount = $converted_amount;
            }

            $item_amount_in_paisa = intval(round($amount * 100));
            $qty = intval($item->getQuantity());

            // Skip invalid entries
            if ($item_amount_in_paisa < 1 || $qty < 1) {
                khaltigateway_debug(array(
                    'item' => $item->getName(),
                    'qty' => $qty,
                    'amount_paisa' => $item_amount_in_paisa
                ), 'Skipping invalid cart item');
                continue;
            }

            $unit_price = intval(round($item_amount_in_paisa / $qty));

            $cart[] = array(
                "name" => $item->getName(),
                "identity" => $item->getUuid(),
                "total_price" => $item_amount_in_paisa,
                "quantity" => $qty,
                "unit_price" => $unit_price
            );
        }



    $checkout_args = array(
        "return_url" => "{$callback_url}",
        "website_url" => "{$system_url}",
        "amount" => $npr_amount_in_paisa,
        "purchase_order_id" => "{$invoice_id}",
        "purchase_order_name" => "{$description}",
        "customer_info" => array(
            "name" => $customer_name,
            "email" => $customer_email,
            "phone" => $customer_phone_number
        ),
        "amount_breakdown" => array(
            array(
                "label" => "Invoice Number - {$invoice_id}",
                "amount" => $npr_amount_in_paisa
            ),
        ),
        "product_details" => $cart
    );

    return khaltigateway_pidx_page($gateway_params, $npr_amount, $checkout_args);
}

function khaltigateway_pidx_page($gateway_params, $npr_amount, $checkout_args)
{
    $payment_initiate = khaltigateway_epay_initiate($gateway_params, $checkout_args);
    $pidx = $payment_initiate["pidx"];

    if (!$pidx) {
        return file_get_contents(__DIR__ . "/templates/initiate_failed.html");
    }

    /*
     * Variables required for the template
     * pidx_url
     * button_css
     * gateway_params
     * npr_amount
     */
    $pidx_url = $payment_initiate["payment_url"];
    return file_include_contents(__DIR__ . "/templates/invoice_payment_button.php", array(
        'khalti_logo_url' => 'https://cdn.nayathegana.com/media/2025/07/13/23471783407643cb921f718ed30726b9.png',
        "pidx_url" => $pidx_url,
        "button_css" => "",
        "gateway_params" => $gateway_params,
        "npr_amount" => $npr_amount
    ));
}

function khaltigateway_invalid_currency_page()
{
    return file_get_contents(__DIR__ . '/templates/invalid_currency.html');
}
