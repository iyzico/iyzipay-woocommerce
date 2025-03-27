<?php

namespace Iyzico\IyzipayWoocommerce\Common\Helpers;

class VersionChecker {

	protected Logger $logger;

	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	public function check() {
		$this->checkPhpVersion();
		$this->checkTlsVersion();
	}

	private function checkPhpVersion() {
		$requiredPhpVersion = 8.2;
		if ( phpversion() < $requiredPhpVersion ) {
			$this->logger->error( 'Required PHP 8.2 and greater for iyzico WooCommerce Payment Gateway' );
		}
	}

	private function checkTlsVersion() {
		$tlsVerifier        = new TlsVerifier();
		$currentTlsVersion  = $tlsVerifier->verifyAndGetVersion();
		$requiredTlsVersion = 1.2;

		if ( $currentTlsVersion < $requiredTlsVersion ) {
			$this->logger->error( 'Required TLS 1.2 and greater for iyzico WooCommerce Payment Gateway' );
		}
	}
}