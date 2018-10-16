<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Iyzico_Checkout_For_WooCommerce_Helper {

	public function subTotalPriceCalc($items,$data) {

		$keyNumber 	= 0;
		$price 		= 0;

		$itemSize = count($items);
		if(!$itemSize) {

			$price = $data->get_total();			
			$price = $this->priceParser($price);
			
			return $price;
		} 


		foreach ($items as  $item) {

			$productId 	= $item['product_id'];
			$product 	= wc_get_product($productId);
			$realPrice  = $this->realPrice($product->get_sale_price(),$product->get_price());


			$price+= round($realPrice, 2);

			$keyNumber++;

		}

		$shipping = $data->get_total_shipping() + $data->get_shipping_tax();

		if($shipping) {

			$price+= $shipping;
		}

		$price = $this->priceParser($price);

		return $price;

	}

	public function cutLocale($locale) {

		$locale = explode('_',$locale);
		$locale = $locale[0];
		// Check for support locales and return 'en' for non supported locales
		$supported_locals = ['en', 'tr'];
		if( !in_array($locale, $supported_locals) ){
			return 'en';
		}
		return $locale;
	}

	public function priceParser($price) {

	    if (strpos($price, ".") === false) {
	        return $price . ".0";
	    }
	    $subStrIndex = 0;
	    $priceReversed = strrev($price);
	    for ($i = 0; $i < strlen($priceReversed); $i++) {
	        if (strcmp($priceReversed[$i], "0") == 0) {
	            $subStrIndex = $i + 1;
	        } else if (strcmp($priceReversed[$i], ".") == 0) {
	            $priceReversed = "0" . $priceReversed;
	            break;
	        } else {
	            break;
	        }
	    }

	    return strrev(substr($priceReversed, $subStrIndex));
	}

	public function callBackUrl($url) {

	}

	public function trimString($address1,$address2) {

		$address = trim($address1)." ".trim($address2);
		
		return $address;
	}


	public function dataCheck($data) {

        if(!$data || $data == ' ') {

            $data = "NOT PROVIDED";
        }

        return $data;

	}

	public function realPrice($salePrice,$regularPrice) {

		if(empty($salePrice)) {

			$salePrice = $regularPrice;
		}

		return $salePrice;

	}

}