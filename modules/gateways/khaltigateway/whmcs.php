<?php

/**
 * Khalti Payment Gateway Module for WHMCS
 * 
 * Licensed under the MIT License. 
 * https://opensource.org/licenses/MIT
 */

function khaltigateway_acknowledge_whmcs_for_payment($post_data)
{
    try {
        /**
         * Retrieve Transaction ID.
         *
         * Obtains the transaction ID from the post data.
         */
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
         * Log Transaction Attempt.
         *
         * Adds a pre-processing log entry to help with debugging.
         *
         * @param string $gatewayName        Display label
         * @param string $debugData          Data to log
         * @param string $transactionStatus  Status
         */
        logTransaction($wh_gatewayModule, "Processing payment for Invoice ID: $wh_invoiceId, Transaction ID: $wh_transactionId, Amount: $wh_paymentAmount", "Debug");

        /**
         * Check Callback Transaction ID.
         *
         * Performs a check for any existing transactions with the same given transaction number.
         *
         * Performs a die upon encountering a duplicate.
         *
         * @param string $transactionId Unique Transaction ID
         */
        checkCbTransID($khalti_transaction_id);

        /**
         * Log Transaction.
         *
         * Adds an entry to the Gateway Log for debugging purposes.
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
         * @param float $paymentAmount   Amount paid
         * @param float $paymentFee      Payment fee (optional)
         * @param string $gatewayModule  Gateway module name
         */
        addInvoicePayment(
            $wh_invoiceId,
            $wh_transactionId,
            $wh_paymentAmount,
            $wh_paymentFee,
            $wh_gatewayModule
        );

        /**
         * Redirect to Invoice.
         *
         * Performs redirect back to the invoice upon completion displaying the transaction result.
         *
         * @param int $invoiceId        Invoice ID
         * @param bool $paymentSuccess  Payment status
         */
        callback3DSecureRedirect($wh_invoiceId, $wh_paymentSuccess);

    } catch (Exception $e) {
        /**
         * Log Transaction Failure.
         *
         * Logs any error encountered during the payment process for debugging purposes.
         *
         * @param string $gatewayName        Display label
         * @param string $debugData          Error message
         * @param string $transactionStatus  Status
         */
        logTransaction($wh_gatewayModule, "Error in khaltigateway_acknowledge_whmcs_for_payment: " . $e->getMessage(), "Failure");

        /**
         * Terminate Process.
         *
         * Stops the process and displays the error message.
         */
        die("Error processing payment: " . $e->getMessage());
    }
}
