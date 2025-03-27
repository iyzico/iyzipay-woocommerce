<?php

namespace Iyzico\IyzipayWoocommerce\Common\Helpers;

class PriceHelper
{

	public function priceParser($price)
	{
		if (strpos($price, ".") === false) {
			return $price . ".0";
		}
		$subStrIndex = 0;
		$priceReversed = strrev($price);
		for ($i = 0; $i < strlen($priceReversed); $i++) {
			if (strcmp($priceReversed[$i], "0") == 0) {
				$subStrIndex = $i + 1;
			} elseif (strcmp($priceReversed[$i], ".") == 0) {
				$priceReversed = "0" . $priceReversed;
				break;
			} else {
				break;
			}
		}

		return strrev(substr($priceReversed, $subStrIndex));
	}

	public function realPrice($salePrice, $regularPrice)
	{
		if (empty($salePrice)) {
			$salePrice = $regularPrice;
		}

		return $salePrice;
	}
}