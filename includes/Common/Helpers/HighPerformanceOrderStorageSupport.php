<?php

namespace Iyzico\IyzipayWoocommerce\Core;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

class HighPerformanceOrderStorageSupport
{
	public static function init()
	{
		add_action('before_woocommerce_init', [self::class, 'woocommerce_hpos_compatibility']);
	}

	public static function woocommerce_hpos_compatibility()
	{
		if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
			FeaturesUtil::declare_compatibility('custom_order_tables', PLUGIN_BASEFILE, true);
		}
	}
}
