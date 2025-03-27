<?php

namespace Iyzico\IyzipayWoocommerce\Common\Helpers;

class SignatureChecker
{
	function calculateHmacSHA256Signature($params, $secretKey): string
	{
		$dataToSign = implode(':', $params);
		$mac = hash_hmac('sha256', $dataToSign, $secretKey, true);

		return bin2hex($mac);
	}

}