<?php 

function khaltigateway_noinvoicepage_code()
{
    return file_get_contents(__DIR__."/templates/noninvoice_page.html");
}

function khaltigateway_invoicepage_code($gateway_params)
{
    $system_url = $gateway_params['systemurl'];
    $invoice_id = $gateway_params['invoiceid'];
    $description = htmlspecialchars(strip_tags($gateway_params["description"]));
    $amount = $gateway_params['amount'];
    $amount_in_paisa = $amount * 100;
    $currency_code = $gateway_params['currency'];

    if(!khaltigateway_validate_currency($currency_code)){
        return file_get_contents(__DIR__ . '/templates/invalid_currency.html');
    }

    $module_url = "modules/gateways/khaltigateway/";

    $callback_url = $system_url . $module_url . "callback.php";
    $invoice_url = $system_url . "viewinvoice.php?id={$invoice_id}";
    $successUrl = "{$invoice_url}&paymentsuccess=true";

    $cart = array();
    foreach ($gateway_params["cart"]->items as $item) {
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
        "return_url" => "{$callback_url}",
        "website_url" => "{$system_url}",
        "amount" => $amount_in_paisa,
        "purchase_order_id" => "{$invoice_id}",
        "purchase_order_name" => "{$description}",
        "customer_info" => array(
            "name" => "Ashim Upadhaya",
            "email" => "example@gmail.com",
            "phone" => "9811496763"
        ),
        "amount_breakdown" => array(
            array(
                "label" => "Invoice Number - {$invoice_id}",
                "amount" => $amount_in_paisa
            ),
        ),
        "product_details" => $cart
    );

    $payment_initiate = khaltigateway_epay_initiate($gateway_params, $checkout_args);
    $pidx = $payment_initiate["pidx"] || false;

    if (!$pidx){
        file_get_contents(__DIR__."/templates/initiate_failed.html");
    }

    $pidx_url = $payment_initiate["payment_url"];

    $buttonCSS = "";

    return <<<EOT
        <div class='row' id='khaltigateway-button-wrapper'>
        <div class='col-sm-12' style='padding:2em;'>
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
                        {$gateway_params['langpaynow']}
                    </a>
                </div>
            </div>
        </div>
    </div>
EOT;

}