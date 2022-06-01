<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Iyzico_Checkout_For_WooCommerce_ResponseObjectGenerate {

	public function __construct() {

		$this->helper = new Iyzico_Checkout_For_WooCommerce_Helper();
	}


	public function generateTokenDetailObject($conversationId,$token) {

		$tokenDetail = new stdClass();

		$tokenDetail->locale 			= $this->helper->cutLocale(get_locale());
		$tokenDetail->conversationId 	= $conversationId;
		$tokenDetail->token 			= $token;

		return $tokenDetail;
		
	}
}