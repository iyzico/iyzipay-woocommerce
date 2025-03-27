<?php

namespace Iyzico\IyzipayWoocommerce\Common\Hooks;

use Iyzico\IyzipayWoocommerce\Checkout\CheckoutForm;
use Iyzico\IyzipayWoocommerce\Pwi\Pwi;

class AdminHooks
{

	private $checkoutForm;
	private $pwi;

	public function __construct()
	{
		$this->checkoutForm = new CheckoutForm();
		$this->pwi = new Pwi();
	}

	public function register(): void
	{
		add_action('woocommerce_update_options_payment_gateways_' . $this->checkoutForm->id, [
			$this->checkoutForm,
			'process_admin_options'
		]);

		add_action('woocommerce_update_options_payment_gateways_' . $this->pwi->id, [
			$this->pwi,
			'process_admin_options'
		]);

		add_action('woocommerce_update_options_payment_gateways_' . $this->checkoutForm->id, [
			$this->checkoutForm,
			'admin_overlay_script'
		]);
	}


}