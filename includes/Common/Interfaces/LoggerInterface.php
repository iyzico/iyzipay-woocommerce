<?php

namespace Iyzico\IyzipayWoocommerce\Common\Interfaces;

interface LoggerInterface
{
	public function info(string $message);

	public function error(string $message);

	public function warn(string $message);
}
