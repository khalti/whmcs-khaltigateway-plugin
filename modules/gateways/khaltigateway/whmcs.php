<?php

function khaltigateway_acknowledge_whmcs_for_payment($post_data)
{
    $khalti_transaction_id = $post_data["khalti_transaction_id"];

    $wh_payload = $post_data['wh_payload'];
    $wh_response = $post_data['wh_response'];
    $wh_invoiceId = $post_data['wh_invoiceId'];
    $wh_gatewayModule = $post_data['wh_gatewayModule'];
    $wh_transactionId = $post_data['wh_transactionId'];
    $wh_paymentAmount = $post_data['wh_paymentAmount'];
    $wh_paymentFee = $post_data['wh_paymentFee'];
    $wh_paymentSuccess = $post_data['wh_paymentSuccess'];

    /**
     * Check Callback Transaction ID.
     *
     * Performs a check for any existing transactions with the same given
     * transaction number.
     *
     * Performs a die upon encountering a duplicate.
     *
     * @param string $transactionId Unique Transaction ID
     */
    checkCbTransID($khalti_transaction_id);

    /**
     * Log Transaction.
     *
     * Add an entry to the Gateway Log for debugging purposes.
     *
     * The debug data can be a string or an array. In the case of an
     * array it will be
     *
     * @param string $gatewayName        Display label
     * @param string|array $debugData    Data to log
     * @param string $transactionStatus  Status
     */
    $debugData = json_encode(array(
        'payload' => $wh_payload,
        'khalti_response' => $wh_response,
        'invoiceId' => $wh_invoiceId
    ));

    logTransaction($wh_gatewayModule, $debugData, "Success");

    /**
     * Add Invoice Payment.
     *
     * Applies a payment transaction entry to the given invoice ID.
     *
     * @param int $invoiceId         Invoice ID
     * @param string $transactionId  Transaction ID
     * @param float $paymentAmount   Amount paid (defaults to full balance)
     * @param float $paymentFee      Payment fee (optional)
     * @param string $gatewayModule  Gateway module name
     */
    $paymentFee = 0.0;
    addInvoicePayment(
        $wh_invoiceId,
        $wh_transactionId,
        $wh_paymentAmount,
        $wh_paymentFee,
        $wh_gatewayModule
    );

    /**
     * Redirect to invoice.
     *
     * Performs redirect back to the invoice upon completion of the 3D Secure
     * process displaying the transaction result along with the invoice.
     *
     * @param int $invoiceId        Invoice ID
     * @param bool $paymentSuccess  Payment status
     */
    callback3DSecureRedirect($wh_invoiceId, $wh_paymentSuccess);
}
