<?php

namespace Iyzico\IyzipayWoocommerce\Common\Hooks;

use Iyzico\IyzipayWoocommerce\Common\Helpers\WebhookHelper;

class RestHooks {
	private $webhookHelper;

	public function __construct() {
		$this->webhookHelper  = new WebhookHelper();
	}

	public function register(): void {
		add_action( 'rest_api_init', [ $this->webhookHelper, 'addRoute' ] );
	}

}