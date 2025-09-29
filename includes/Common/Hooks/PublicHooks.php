<?php

namespace Iyzico\IyzipayWoocommerce\Common\Hooks;

use Iyzico\IyzipayWoocommerce\Checkout\CheckoutForm;
use Iyzico\IyzipayWoocommerce\Common\Helpers\BuyerProtection;
use Iyzico\IyzipayWoocommerce\Common\Helpers\WebhookHelper;

class PublicHooks
{
    private $checkoutForm = null;
    private $buyerProtection = null;
    private $webhookHelper = null;

    public function register()
    {
        add_action('rest_api_init', function () {
            $this->getWebhookHelper()->addRoute();
        });

        add_action('woocommerce_receipt_iyzico', function ($orderId) {
            $this->getCheckoutForm()->load_form();
            $this->getCheckoutForm()->checkout_form($orderId);
        });

        add_action('woocommerce_api_request', function () {
            $this->getCheckoutForm()->handle_api_request();
        });

        add_action('woocommerce_before_checkout_form', function () {
            $this->getCheckoutForm()->display_errors();
        }, 10);

        add_action('wp_footer', function () {
            $this->getBuyerProtection()->iyzicoOverlayScriptMobileCss();
            $this->getBuyerProtection()->add_buyer_protection_divs();
        });

        add_action('wp_enqueue_scripts', function () {
            $this->getBuyerProtection()->enqueue_iyzico_overlay_script();
        });

        // Add buyer protection div for product detail pages
        add_action('woocommerce_after_add_to_cart_button', function () {
            $this->getBuyerProtection()->add_product_detail_div();
        });
    }

    private function getWebhookHelper()
    {
        if ($this->webhookHelper === null) {
            $this->webhookHelper = new WebhookHelper();
        }
        return $this->webhookHelper;
    }

    private function getCheckoutForm()
    {
        if ($this->checkoutForm === null) {
            $this->checkoutForm = new CheckoutForm();
        }
        return $this->checkoutForm;
    }

    private function getBuyerProtection()
    {
        if ($this->buyerProtection === null) {
            $this->buyerProtection = new BuyerProtection();
        }
        return $this->buyerProtection;
    }
}