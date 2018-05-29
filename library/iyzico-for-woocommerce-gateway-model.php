<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Iyzico_Checkout_For_WooCommerce_Model {

	public function __construct() {

		$this->database = $GLOBALS['wpdb'];
	}

	public function findUserCardKey($customerId,$apiKey) {

	$table_name = $this->database->prefix .'iyzico_card';
	$fieldName  = 'card_user_key';
 
	$query = $this->database->prepare("
			 	SELECT {$fieldName} FROM {$table_name} 
			 	WHERE  customer_id = %d AND api_key = %s 
			 	ORDER BY iyzico_card_id DESC LIMIT 1;
				",$customerId,$apiKey
			);

	$result = $this->database->get_col($query);



		if(isset($result[0])) {

			return $result[0];

		} else {

			return '';
		}

	}

	public function insertCardUserKey($customerId,$cardUserKey,$apiKey) {

		$insertCardUserKey = $this->database->insert( 
			$this->database->prefix.'iyzico_card', 
			array( 
				'customer_id' 		=> $customerId, 
				'card_user_key' 	=> $cardUserKey, 
				'api_key' 			=> $apiKey
			), 
			array( 
				'%d', 
				'%s', 
				'%s', 
			) 
		);

		return $insertCardUserKey;
	}


	public function insertIyzicoOrder($localOrder) {

		$insertOrder = $this->database->insert( 
			$this->database->prefix.'iyzico_order', 
			array( 
				'payment_id' 	=> $localOrder->orderId, 
				'order_id' 		=> $localOrder->paymentId, 
				'total_amount' 	=> $localOrder->totalAmount,
				'status' 		=> $localOrder->status
			), 
			array( 
				'%d', 
				'%d', 
				'%s', 
				'%s' 
			) 
		);

		return $insertOrder;

	}


}