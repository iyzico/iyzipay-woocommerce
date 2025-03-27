<?php

namespace Iyzico\IyzipayWoocommerce\Common\Hooks;

use Iyzico\IyzipayWoocommerce\Common\Helpers\WebhookHelper;
use Iyzico\IyzipayWoocommerce\Rest\RestAPI;

class RestHooks {
	private $restApiHandler;
	private $webhookHelper;

	public function __construct() {
		$this->restApiHandler = new RestAPI();
		$this->webhookHelper  = new WebhookHelper();
	}

	public function register(): void {
		add_action( 'rest_api_init', [ $this->restApiHandler, 'addRestRoutes' ] );
		add_action( 'rest_api_init', [ $this->webhookHelper, 'addRoute' ] );
	}

}