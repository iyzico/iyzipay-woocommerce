<?php

namespace Iyzico\IyzipayWoocommerce\Common\Abstracts;

use Iyzico\IyzipayWoocommerce\Common\Interfaces\LoggerInterface;

/**
 * Class AbstractLogger
 *
 * @package Iyzico\IyzipayWoocommerce\Common\Abstracts
 */
abstract class AbstractLogger implements LoggerInterface {
	protected const INFO_LOG = 'iyzico_info.log';
	protected const ERROR_LOG = 'iyzico_error.log';
	protected const WARN_LOG = 'iyzico_warn.log';
	protected const WEBHOOK_LOG = 'iyzico_webhook.log';
	protected string $logDir;

	/**
	 * AbstractLogger constructor.
	 *
	 * @param string $logDir
	 */
	public function __construct( string $logDir = '' ) {
		$this->logDir = $logDir ?: PLUGIN_PATH . '/log_files/';
		$this->ensureLogDirectoryExists();
	}

	/**
	 * @return void
	 */
	protected function ensureLogDirectoryExists(): void {
		if ( ! file_exists( $this->logDir ) ) {
			mkdir( $this->logDir, 0755, true );
			$this->createHtaccess();
		}
	}

	/**
	 * @return void
	 */
	protected function createHtaccess(): void {
		$htaccessContent = "Deny from all\n";
		file_put_contents( $this->logDir . '.htaccess', $htaccessContent );
	}

	/**
	 * @param string $message
	 *
	 * @return void
	 */
	abstract public function info( string $message ): void;

	/**
	 * @param string $message
	 *
	 * @return void
	 */
	abstract public function error( string $message ): void;

	/**
	 * @param string $message
	 *
	 * @return void
	 */
	abstract public function warn( string $message ): void;

	/**
	 * @param string $message
	 *
	 * @return void
	 */
	abstract public function webhook( string $message ): void;

	/**
	 * @param string $file
	 * @param string $level
	 * @param string $message
	 *
	 * @return void
	 */
	protected function log( string $file, string $level, string $message ): void {
		$timestamp  = date( 'Y-m-d H:i:s' );
		$logMessage = "[$timestamp] [$level] $message" . PHP_EOL;

		$filePath = $this->logDir . $file;
		file_put_contents( $filePath, $logMessage, FILE_APPEND | LOCK_EX );
	}
}
