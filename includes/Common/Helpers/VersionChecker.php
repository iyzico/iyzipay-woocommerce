<?php

namespace Iyzico\IyzipayWoocommerce\Common\Helpers;

class VersionChecker
{

	public $logger;

	public function __construct()
	{
		$this->logger = new Logger();
	}

	public function check()
	{
		$this->checkPhpVersion();
		$this->checkTlsVersion();
	}

	private function checkPhpVersion()
	{
		$requiredPhpVersion = 7.4;
		if (phpversion() < $requiredPhpVersion) {
			$this->logger->error('Required PHP 7.4 and greater for iyzico WooCommerce Payment Gateway');
		}
	}

	private function checkTlsVersion()
	{
		$tlsVerifier = new TlsVerifier();
		$currentTlsVersion = $tlsVerifier->verifyAndGetVersion();
		$requiredTlsVersion = 1.2;

		if ($currentTlsVersion < $requiredTlsVersion) {
			$this->logger->error('Required TLS 1.2 and greater for iyzico WooCommerce Payment Gateway');
		}
	}
}
