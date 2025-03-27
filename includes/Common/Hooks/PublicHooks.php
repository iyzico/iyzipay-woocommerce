<?php

namespace Iyzico\IyzipayWoocommerce\Common\Hooks;

use Iyzico\IyzipayWoocommerce\Checkout\CheckoutForm;
use Iyzico\IyzipayWoocommerce\Common\Helpers\BuyerProtection;

class PublicHooks {

	private $checkoutForm;
	private $buyerProtection;

	public function __construct() {
		$this->checkoutForm    = new CheckoutForm();
		$this->buyerProtection = new BuyerProtection();
	}

	public function register() {

		add_action( 'woocommerce_receipt_iyzico', [ $this->checkoutForm, 'load_form' ] );
		add_action( 'woocommerce_receipt_iyzico', [ $this->checkoutForm, 'checkout_form' ] );
		add_action( 'woocommerce_api_request', [ $this->checkoutForm, 'handle_api_request' ] );
		add_action( 'woocommerce_before_checkout_form', [ $this->checkoutForm, 'display_errors' ], 10 );

		add_action( 'wp_footer', [ $this->buyerProtection, 'iyzicoOverlayScriptMobileCss' ] );
		add_action( 'wp_footer', [ $this->buyerProtection, 'getOverlayScript' ] );
	}
}