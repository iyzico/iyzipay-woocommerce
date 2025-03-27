<?php

namespace Iyzico\IyzipayWoocommerce\Checkout;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Class BlocksCheckoutMethod
 *
 * @extends AbstractPaymentMethodType
 */
class BlocksCheckoutMethod extends AbstractPaymentMethodType {

	public $gateway;
	public $checkoutSettings;
	protected $name = 'iyzico';

	public function __construct() {
		$this->checkoutSettings = new CheckoutSettings();
	}

	public function initialize(): void {
		$this->settings = $this->checkoutSettings->getSettings();
	}

	public function is_active(): bool {
		return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}

	public function get_payment_method_script_handles(): array {
		$dependencies = [];
		$version      = time();

		$path = plugin_dir_path( PLUGIN_BASEFILE ) . 'assets/blocks/woocommerce/blocks.asset.php';

		if ( file_exists( $path ) ) {
			$asset        = require $path;
			$version      = filemtime( plugin_dir_path( PLUGIN_BASEFILE ) . 'assets/blocks/woocommerce/blocks.js' );
			$dependencies = is_null( $asset['dependencies'] );
		}

		wp_register_script(
			'wc-iyzico-blocks-integration',
			plugin_dir_url( PLUGIN_BASEFILE ) . 'assets/blocks/woocommerce/blocks.js',
			$dependencies,
			$version,
			true
		);

		return [ 'wc-iyzico-blocks-integration' ];
	}

	public function get_payment_method_data(): array {
		$title       = $this->settings['title'];
		$description = $this->settings['description'];
		$image       = plugin_dir_url( PLUGIN_BASEFILE ) . 'assets/images/cards.png';

		return [
			'title'       => $title,
			'description' => $description,
			'icon'        => $image
		];
	}
}
