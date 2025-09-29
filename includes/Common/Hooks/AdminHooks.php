<?php

namespace Iyzico\IyzipayWoocommerce\Common\Hooks;

use Iyzico\IyzipayWoocommerce\Checkout\CheckoutForm;
use Iyzico\IyzipayWoocommerce\Pwi\Pwi;

class AdminHooks
{
    private $checkoutForm = null;
    private $pwi = null;

    public function register(): void
    {
        add_action('woocommerce_update_options_payment_gateways_iyzico', function () {
            $this->getCheckoutForm()->process_admin_options();
        });

        add_action('woocommerce_update_options_payment_gateways_pwi', function () {
            $this->getPwi()->process_admin_options();
        });

        add_action('woocommerce_update_options_payment_gateways_iyzico', function () {
            $this->getCheckoutForm()->admin_overlay_script();
        });
    }

    private function getCheckoutForm()
    {
        if ($this->checkoutForm === null) {
            $this->checkoutForm = new CheckoutForm();
        }
        return $this->checkoutForm;
    }

    private function getPwi()
    {
        if ($this->pwi === null) {
            $this->pwi = new Pwi();
        }
        return $this->pwi;
    }
}