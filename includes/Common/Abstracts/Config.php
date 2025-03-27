<?php

namespace Iyzico\IyzipayWoocommerce\Common\Abstracts;

abstract class Config {
	public $optionsTableKey;
	public $defaultSettings = [];
	public $form_fields = [];

	public function getSettings() {
		$settings = get_option( $this->optionsTableKey, [] );
		$settings = is_array( $settings ) ? $settings : [];

		foreach ( $this->getDefaultSettings() as $key => $value ) {
			if ( false === array_key_exists( $key, $settings ) ) {
				$settings[ $key ] = $value;
			}
		}

		return $settings;
	}

	public function findByKey( string $key ) {
		$settings = $this->getSettings();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : false;
	}

	public function setSettings( mixed $options ) {
		update_option( $this->optionsTableKey, $options );

		if ( method_exists( $this, 'settings_updated' ) ) {
			call_user_func( array( $this, 'settings_updated' ) );
		}

		return true;
	}

	private function getDefaultSettings() {
		$defaultSettings = apply_filters(
			'iyzico_default_settings',
			array(
				'woocommerce_iyzico_settings' => array(
					'enabled'                => 'yes',
					'affiliate_network'      => '',
					'form_language'          => '',
					'order_status'           => 'default',
					'payment_checkout_value' => __( 'Thank you for your order, please enter your card information in the payment form below to pay with iyzico checkout.', 'woocommerce-iyzico' ),
					'title'                  => __( 'Pay with Bank/Debit Card', 'woocommerce-iyzico' ),
					'button_text'            => __( 'Pay With Card', 'woocommerce-iyzico' ),
					'description'            => __( 'Pay with your credit card or debit card via iyzico.', 'woocommerce-iyzico' ),
					'icon'                   => PLUGIN_ASSETS_DIR_URL . '/images/cards_v2.png',
					'success_status'         => 'processing',
					'overlay_script'         => 'left',
					'form_class'             => 'popup',
					'secret_key'             => '',
					'api_key'                => '',
					'api_type'               => 'https://sandbox-api.iyzipay.com',
					'request_log_enabled'    => 'no',
				),
				'woocommerce_pwi_settings'    => array(
					'enabled'     => 'yes',
					'icon'        => PLUGIN_ASSETS_DIR_URL . '/images/iyzico.png',
					'title'       => __( 'Pay with iyzico', 'woocommerce-iyzico' ),
					'button_text' => __( 'Pay with iyzico', 'woocommerce-iyzico' ),
					'description' => __( "Your money safe with iyzico! Store your iyzico card and enjoy one-click payment. All your transactions under the iyzico Buyer Protection guarantee. Get live support 24/7.", 'woocommerce-iyzico' ),
				),
			)
		);

		return array_key_exists( $this->optionsTableKey, (array) $defaultSettings ) ? $defaultSettings[ $this->optionsTableKey ] : [];
	}
}