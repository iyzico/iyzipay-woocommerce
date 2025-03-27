<?php

namespace Iyzico\IyzipayWoocommerce\Common\Hooks;

use Iyzico\IyzipayWoocommerce\Admin\SettingsPage;
use Iyzico\IyzipayWoocommerce\Checkout\CheckoutForm;
use Iyzico\IyzipayWoocommerce\Common\Helpers\BuyerProtection;
use Iyzico\IyzipayWoocommerce\Pwi\Pwi;

class AdminHooks {

	private $page;
	private $checkoutForm;
	private $pwi;
	private $buyerProtection;

	public function __construct() {
		$this->page            = new SettingsPage();
		$this->checkoutForm    = new CheckoutForm();
		$this->buyerProtection = new BuyerProtection();
		$this->pwi             = new Pwi();
	}

	public function register(): void {
		add_action( 'admin_menu', [ $this->page, 'addAdminMenu' ] );
		add_action( 'admin_enqueue_scripts', [ $this->page, 'enqueueAdminAssets' ] );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->checkoutForm->id, [
			$this->checkoutForm,
			'process_admin_options'
		] );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->checkoutForm->id, [
			$this->checkoutForm,
			'admin_overlay_script'
		] );
	}


}