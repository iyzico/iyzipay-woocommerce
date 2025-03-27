<?php

namespace Iyzico\IyzipayWoocommerce\Common\Interfaces;

/**
 * Interface LoggerInterface
 *
 * @package Iyzico\IyzipayWoocommerce\Common\Interfaces
 */
interface LoggerInterface {
	/**
	 * @param string $message
	 *
	 * @return void
	 */
	public function info( string $message ): void;

	/**
	 * @param string $message
	 *
	 * @return void
	 */
	public function error( string $message ): void;

	/**
	 * @param string $message
	 *
	 * @return void
	 */
	public function warn( string $message ): void;
}
