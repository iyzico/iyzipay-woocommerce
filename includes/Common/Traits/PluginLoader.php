<?php

namespace Iyzico\IyzipayWoocommerce\Common\Traits;

/**
 * PluginLoader
 *
 * @package Iyzico\IyzipayWoocommerce\Common\Traits
 */
trait PluginLoader {
	private static $instance = null;

	public static function getInstance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	abstract public function run();

	public static function init() {
		return static::getInstance()->run();
	}
}
