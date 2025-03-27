<?php

namespace Iyzico\IyzipayWoocommerce\Core;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

/**
 * Class HighPerformanceOrderStorageSupport
 *
 * @package Iyzico\IyzipayWoocommerce\Core
 */
class HighPerformanceOrderStorageSupport {
	/**
	 * Initialize the class
	 */
	public static function init(): void {
		add_action( 'before_woocommerce_init', [ self::class, 'woocommerce_hpos_compatibility' ] );
	}

	/**
	 * Declare compatibility with WooCommerce High Performance Order Storage
	 */
	public static function woocommerce_hpos_compatibility(): void {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', PLUGIN_BASEFILE, true );
		}
	}
}
