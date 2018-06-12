<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Iyzico_Checkout_For_WooCommerce_FormObjectGenerate {

	public function __construct() {

		$this->helper = new Iyzico_Checkout_For_WooCommerce_Helper();
	}


	public function generateOption($items,$data,$apiKey) {

        $iyziModel       = new Iyzico_Checkout_For_WooCommerce_Model();
        $user            = wp_get_current_user();
 		
		$iyzico = new stdClass();

		$iyzico->locale                       = $this->helper->cutLocale(get_locale());
		$iyzico->conversationId               = $data->get_id();
		$iyzico->price                        = $this->helper->subTotalPriceCalc($items,$data);
		$iyzico->paidPrice                    = $this->helper->priceParser(round($data->get_total(), 2));
		$iyzico->currency                     = $data->get_currency();
		$iyzico->basketId                     = $data->get_id();
		$iyzico->paymentGroup                 = 'PRODUCT';
		$iyzico->forceThreeDS                 = "1";
		$iyzico->callbackUrl                  = add_query_arg('wc-api', 'WC_Gateway_Iyzico', $data->get_checkout_order_received_url());
		$iyzico->cardUserKey                  = $iyziModel->findUserCardKey($user->ID,$apiKey);
		$iyzico->paymentSource                = 'WOOCOMMERCE|'.WOOCOMMERCE_VERSION.'|CARERRA-1.0';

	
		return $iyzico;

	}

	public function generateBuyer($data) {

		$buyer = new stdClass();

        $buyer->id                          = $data->get_id();
        $buyer->name                        = $this->helper->dataCheck($data->get_billing_first_name());
        $buyer->surname                     = $this->helper->dataCheck($data->get_billing_last_name());
        $buyer->identityNumber              = '11111111111';   
        $buyer->email                       = $this->helper->dataCheck($data->get_billing_email());  
        $buyer->gsmNumber                   = $this->helper->dataCheck($data->get_billing_phone());   
        $buyer->registrationDate            = '2018-07-06 11:11:11';
        $buyer->lastLoginDate               = '2018-07-06 11:11:11';
        $buyer->registrationAddress         = $this->helper->dataCheck($data->get_billing_address_1().$data->get_billing_address_2());    
        $buyer->city                        = $this->helper->dataCheck(WC()->countries->states[$data->get_billing_country()][$data->get_billing_state()]);
        $buyer->country                     = $this->helper->dataCheck(WC()->countries->countries[$data->get_billing_country()]);    
        $buyer->zipCode                     = $this->helper->dataCheck($data->get_billing_postcode());  
        $buyer->ip                          = $_SERVER['REMOTE_ADDR'];  

        return $buyer;
	}

	public function generateShippingAddress($data) {

		/* For Virtual Product */
		$city1 		= isset(WC()->countries->states[$data->get_shipping_country()][$data->get_shipping_state()]) ? WC()->countries->states[$data->get_shipping_country()][$data->get_shipping_state()] : ''; 
		$city2 		= isset(WC()->countries->states[$data->get_shipping_state()]) ? WC()->countries->states[$data->get_shipping_state()] : ''; 

		$city 		= $city1.$city2;
		$country 	= isset(WC()->countries->countries[$data->get_shipping_country()]) ? WC()->countries->countries[$data->get_shipping_country()] : '';


		$shippingAddress = new stdClass();
		$address 						   = $this->helper->trimString($data->get_shipping_address_1(),$data->get_shipping_address_2());
		$shippingAddress->address          = $this->helper->dataCheck($address);
		$shippingAddress->zipCode          = $this->helper->dataCheck($data->get_shipping_postcode());
		$shippingAddress->contactName      = $this->helper->dataCheck($data->get_shipping_first_name().$data->get_shipping_first_name());
		$shippingAddress->city             = $this->helper->dataCheck($city);
		$shippingAddress->country          = $this->helper->dataCheck($country);

		return $shippingAddress;

	}

	public function generateBillingAddress($data) {

		$billingAddress = new stdClass();
		$address 						  = $this->helper->trimString($data->get_billing_address_1(),$data->get_billing_address_2());
		$billingAddress->address          = $this->helper->dataCheck($address);
		$billingAddress->zipCode          = $this->helper->dataCheck($data->get_billing_postcode());  
		$billingAddress->contactName      = $this->helper->dataCheck($data->get_billing_first_name().$data->get_billing_last_name());
		$billingAddress->city             = $this->helper->dataCheck(WC()->countries->states[$data->get_billing_country()][$data->get_billing_state()]);
		$billingAddress->country          = $this->helper->dataCheck(WC()->countries->countries[$data->get_billing_country()]);    

		return $billingAddress;
	}

	public function generateBasketItems($items,$order) {

		$itemSize = count($items);
		if(!$itemSize) {

			return $this->oneProductCalc($order);
		} 

		$keyNumber = 0;
		
		foreach ($items as $key => $item) {

			$productId 	= $item['product_id'];
			$product 	= wc_get_product($productId);
			$realPrice  = $this->helper->realPrice($product->get_sale_price(),$product->get_price());

			$basketItems[$keyNumber] = new stdClass();

			$basketItems[$keyNumber]->id                = $item['product_id'];
			$basketItems[$keyNumber]->price             = $this->helper->priceParser(round($realPrice,2));
			$basketItems[$keyNumber]->name              = $product->get_title();
			$basketItems[$keyNumber]->category1         = 'TEST';
			$basketItems[$keyNumber]->itemType          = 'PHYSICAL';

			$keyNumber++;

		}

		$shipping = $order->get_total_shipping() + $order->get_shipping_tax();

		if($shipping) {

			$endKey = count($basketItems);

			$basketItems[$endKey] = new stdClass();

			$basketItems[$endKey]->id                = 11;
			$basketItems[$endKey]->price             = $this->helper->priceParser($shipping);
			$basketItems[$endKey]->name              = 'Cargo';
			$basketItems[$endKey]->category1         = 'Cargo';
			$basketItems[$endKey]->itemType          = 'PHYSICAL';

		}

		return $basketItems;

	}

	public function oneProductCalc($order) {

		$keyNumber = 0;

		$basketItems[$keyNumber] = new stdClass();

		$basketItems[$keyNumber]->id                = $order->get_id();
		$basketItems[$keyNumber]->price             = $this->helper->priceParser(round($order->get_total(), 2));
		$basketItems[$keyNumber]->name              = 'Woocommerce - Custom Order Page';
		$basketItems[$keyNumber]->category1         = 'TEST';
		$basketItems[$keyNumber]->itemType          = 'PHYSICAL';

		return $basketItems;
	}




}