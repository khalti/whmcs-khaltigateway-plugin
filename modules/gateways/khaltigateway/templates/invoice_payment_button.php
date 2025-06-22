<div class="row khaltigateway-button-wrapper">
    <div class="col-12">
        <div class="khaltigateway-button-content d-flex align-items-center">
            <div class="khaltigateway-logo mr-3">
                <img src="<?php echo htmlspecialchars($_inc_vars['khalti_logo_url']); ?>" 
                     alt="Khalti Digital Wallet" 
                     class="img-fluid" 
                     style="max-height: 50px;" 
                     onerror="this.src='<?php echo $systemurl; ?>assets/images/default_payment_logo.png';" />
            </div>
            <div class="khaltigateway-details">
                <p class="mb-1 small text-muted">Pay securely with Khalti Digital Wallet</p>
                <a id="khalti-payment-button" 
                   href="<?php echo $_inc_vars['pidx_url']; ?>" 
                   class="btn btn-primary btn-lg" 
                   style="<?php echo $_inc_vars['button_css']; ?> border-radius: 5px; padding: 10px 20px;" 
                   title="Pay NPR <?php echo $_inc_vars['npr_amount']; ?> with Khalti"
                   aria-label="Pay with Khalti Digital Wallet">
                    <?php echo $_inc_vars['gateway_params']['langpaynow']; ?> <span class="small">(NPR <?php echo $_inc_vars['npr_amount']; ?>)</span>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    .khaltigateway-button-wrapper {
        margin: 20px 0;
        padding: 15px;
        background-color: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e9ecef;
    }
    .khaltigateway-button-content {
        display: flex;
        align-items: center;
        justify-content: start;
        flex-wrap: wrap;
    }
    .khaltigateway-logo img {
        max-width: 100px;
        height: auto;
    }
    .khaltigateway-details .btn {
        font-weight: 600;
        transition: background-color 0.3s ease;
    }
    .khaltigateway-details .btn:hover {
        background-color: #5a2d82; /* Khalti brand purple */
    }
    @media (max-width: 576px) {
        .khaltigateway-button-content {
            flex-direction: column;
            align-items: start;
        }
        .khaltigateway-logo {
            margin-bottom: 10px;
        }
    }
</style>