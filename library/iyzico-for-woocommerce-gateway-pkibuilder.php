<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Iyzico_Checkout_For_WooCommerce_PkiBuilder {

	public function pkiStringGenerate($objectData) {
		
		$pki_value = "[";
		foreach ($objectData as $key => $data) {

			if(is_object($data)) {

				$name = var_export($key, true);
				$name = str_replace("'", "", $name); 
				$pki_value .= $name."=[";

				$end_key = count(get_object_vars($data));
				$count 	 = 0;

				foreach ($data as $key => $value) {

					$count++;
					$name = var_export($key, true);
					$name = str_replace("'", "", $name); 


					$pki_value .= $name."="."".$value;

					if($end_key != $count)
						$pki_value .= ",";
				}

				$pki_value .= "]";

			} else if(is_array($data)) {
				$name = var_export($key, true);
				$name = str_replace("'", "", $name); 

				$pki_value .= $name."=[";

				foreach ($data as $key => $result) {

					$pki_value .= "[";
					
					foreach ($result as $key => $item) {
						$name = var_export($key, true);
						$name = str_replace("'", "", $name); 
					
						$pki_value .= $name."="."".$item;

						if(end($result) != $item) {
							$pki_value .= ",";
						}

						if(end($result) == $item) {
							if(end($data) != $result) {

								$pki_value .= "], ";
							
							} else {

								$pki_value .= "]";
							}
						}
					}
				}

				if(end($data) == $result) 
					$pki_value .= "]";
				
			} else {

				$name = var_export($key, true);
				$name = str_replace("'", "", $name); 
				  

				$pki_value .= $name."="."".$data."";
			}

			if(end($objectData) != $data)
				$pki_value .= ",";
		}

		$pki_value .= "]";

		return $pki_value;
	}

	public function createFormObjectSort($objectData) {


		$form_object = new stdClass();

		$form_object->locale 						= $objectData->locale;
		$form_object->conversationId 				= $objectData->conversationId;
		$form_object->price 						= $objectData->price;
		$form_object->basketId 						= $objectData->basketId;
		$form_object->paymentGroup 					= $objectData->paymentGroup;

		$form_object->buyer = new stdClass();
		$form_object->buyer = $objectData->buyer;

		$form_object->shippingAddress = new stdClass();
		$form_object->shippingAddress = $objectData->shippingAddress;

		$form_object->billingAddress = new stdClass();
		$form_object->billingAddress = $objectData->billingAddress;

		foreach ($objectData->basketItems as $key => $item) {
			
			$form_object->basketItems[$key] = new stdClass();
			$form_object->basketItems[$key] = $item;
			
		}

		$form_object->callbackUrl 			= $objectData->callbackUrl;
		$form_object->paymentSource 		= $objectData->paymentSource;
		$form_object->currency 	  			= $objectData->currency;
		$form_object->paidPrice   			= $objectData->paidPrice;
		$form_object->forceThreeDS 			= $objectData->forceThreeDS;
		$form_object->cardUserKey 			= $objectData->cardUserKey;

		return $form_object;
	}

	public function authorizationGenerate($pkiString,$apiKey,$secretKey,$rand) {

		$hash_value = $apiKey.$rand.$secretKey.$pkiString;
		$hash 		= base64_encode(sha1($hash_value,true));
		
		$authorization 	= 'IYZWS '.$apiKey.':'.$hash;
		
		$authorization_data = array(
			'authorization' => $authorization,
			'rand_value' 	=> $rand
		);
		
		return $authorization_data;
	}

}