<?php

namespace Iyzico\IyzipayWoocommerce\Common\Abstracts;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

abstract class AbstractBlocksMethod extends AbstractPaymentMethodType {
	protected $settings;
	protected $name;

	public function __construct() {
		$this->initializeSettings();
	}

	abstract protected function initializeSettings();

	abstract public function initialize(): void;

	public function is_active(): bool {
		return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}

	public function get_payment_method_script_handles(): array {
		$dependencies = [];
		$version      = time();
		$path         = plugin_dir_path( PLUGIN_BASEFILE ) . 'assets/blocks/woocommerce/blocks.asset.php';

		if ( file_exists( $path ) ) {
			$asset        = require $path;
			$version      = filemtime( plugin_dir_path( PLUGIN_BASEFILE ) . 'assets/blocks/woocommerce/blocks.js' );
			$dependencies = $asset['dependencies'] ?? [];
		}

		wp_register_script(
			'wc-' . $this->name . '-blocks-integration',
			plugin_dir_url( PLUGIN_BASEFILE ) . 'assets/blocks/woocommerce/blocks.js',
			$dependencies,
			$version,
			true
		);

		return [ 'wc-' . $this->name . '-blocks-integration' ];
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