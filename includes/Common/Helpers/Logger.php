<?php

namespace Iyzico\IyzipayWoocommerce\Common\Helpers;

use Iyzico\IyzipayWoocommerce\Common\Abstracts\AbstractLogger;

/**
 * Class Logger
 *
 * @package Iyzico\IyzipayWoocommerce\Common\Helpers
 */
class Logger extends AbstractLogger {

	/**
	 * @param string $message
	 *
	 * @return void
	 */
	public function info( string $message ): void {
		$this->log( self::INFO_LOG, 'INFO', $message );
	}

	/**
	 * @param string $message
	 *
	 * @return void
	 */

	public function error( string $message ): void {
		$this->log( self::ERROR_LOG, 'ERROR', $message );
	}

	/**
	 * @param string $message
	 *
	 * @return void
	 */
	public function warn( string $message ): void {
		$this->log( self::WARN_LOG, 'WARNING', $message );
	}

	/**
	 * @param string $message
	 *
	 * @return void
	 */
	public function webhook( string $message ): void {
		$this->log( self::WEBHOOK_LOG, 'WEBHOOK', $message );
	}
}
