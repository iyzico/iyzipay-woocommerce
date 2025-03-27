<?php

namespace Iyzico\IyzipayWoocommerce\Common\Helpers;

use Iyzico\IyzipayWoocommerce\Common\Abstracts\AbstractLogger;

class Logger extends AbstractLogger
{
	public function info(string $message): void
	{
		$this->log(self::INFO_LOG, 'INFO', $message);
	}
	public function error(string $message): void
	{
		$this->log(self::ERROR_LOG, 'ERROR', $message);
	}
	public function warn(string $message): void
	{
		$this->log(self::WARN_LOG, 'WARNING', $message);
	}
	public function webhook(string $message): void
	{
		$this->log(self::WEBHOOK_LOG, 'WEBHOOK', $message);
	}
}
