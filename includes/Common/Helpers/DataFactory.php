<?php

namespace Iyzico\IyzipayWoocommerce\Common\Helpers;

use Iyzico\IyzipayWoocommerce\Checkout\CheckoutSettings;
use Iyzipay\Model\Address;
use Iyzipay\Model\BasketItem;
use Iyzipay\Model\BasketItemType;
use Iyzipay\Model\Buyer;
use stdClass;
use WC_Order;

class DataFactory {
	public $logger;
	protected $priceHelper;
	protected $checkoutSettings;

	public function __construct( PriceHelper $priceHelper, CheckoutSettings $checkoutSettings, Logger $logger ) {
		$this->priceHelper      = $priceHelper;
		$this->checkoutSettings = $checkoutSettings;
		$this->logger           = $logger;
	}

	public function prepareCheckoutData( $customer, WC_Order $order, array $cart ): array {
		$cartHasPhysicalProduct = $this->cartHasPhysicalProduct( $cart );
		$data                   = [
			'buyer'           => $this->createBuyer( $customer, $order ),
			'billingAddress'  => $this->createAddress( $order, 'billing' ),
			'shippingAddress' => $this->createAddress( $order, 'shipping' ),
			'basketItems'     => $this->createBasket( $order, $cart ),
		];

		if ( ! $cartHasPhysicalProduct ) {
			unset( $data['shippingAddress'] );
		}

		return $data;
	}

	protected function cartHasPhysicalProduct( array $cart ): bool {
		foreach ( $cart as $item ) {
			if ( ! $item['data']->is_virtual() ) {
				return true;
			}
		}

		return false;
	}

	protected function createBuyer( $customer, WC_Order $order ): Buyer {
		$buyer = new Buyer();
		$buyer->setId( $this->validateStringVal( $customer->ID ) );
		$buyer->setName( $this->validateStringVal( $order->get_billing_first_name() ) );
		$buyer->setSurname( $this->validateStringVal( $order->get_billing_last_name() ) );
		$buyer->setIdentityNumber( "11111111111" );
		$buyer->setEmail( $this->validateStringVal( $order->get_billing_email() ) );
		$buyer->setRegistrationDate( date( 'Y-m-d H:i:s' ) );
		$buyer->setLastLoginDate( date( 'Y-m-d H:i:s' ) );
		$buyer->setRegistrationAddress( $this->validateStringVal( $order->get_billing_address_1() ) . ' ' . $this->validateStringVal( $order->get_billing_address_2() ) );
		$buyer->setCity( $this->validateStringVal( $order->get_billing_city() ) );
		$buyer->setCountry( $this->validateStringVal( $order->get_billing_country() ) );
		$buyer->setZipCode( $this->validateStringVal( $order->get_billing_postcode() ) );
		$buyer->setIp( $this->validateStringVal( $_SERVER['REMOTE_ADDR'] ) );
		$buyer->setGsmNumber( $this->validateStringVal( $order->get_billing_phone() ) );

		return $buyer;
	}

	protected function validateStringVal( $string ): string {
		if ( empty( $string ) ) {
			return 'UNKNOWN';
		}

		if ( is_null( $string ) ) {
			return 'UNKNOWN';
		}

		if ( strlen( $string ) <= 0 ) {
			return 'UNKNOWN';
		}

		return substr( $string, 0, 249 );
	}

	protected function createAddress( WC_Order $order, string $type ): Address {
		$isTypeBilling = $type === "billing";

		$firstName   = $this->validateStringVal( $isTypeBilling ? $order->get_billing_first_name() : $order->get_shipping_first_name() );
		$lastName    = $this->validateStringVal( $isTypeBilling ? $order->get_billing_last_name() : $order->get_shipping_last_name() );
		$contactName = $firstName . ' ' . $lastName;

		$city        = $this->validateStringVal( $isTypeBilling ? $order->get_billing_city() : $order->get_shipping_city() );
		$country     = $this->validateStringVal( $isTypeBilling ? $order->get_billing_country() : $order->get_shipping_country() );
		$address1    = $this->validateStringVal( $isTypeBilling ? $order->get_billing_address_1() : $order->get_shipping_address_1() );
		$address2    = $this->validateStringVal( $isTypeBilling ? $order->get_billing_address_2() : $order->get_shipping_address_2() );
		$fullAddress = trim( $address1 . ' ' . $address2 );
		$zipCode     = $isTypeBilling ? $order->get_billing_postcode() : $order->get_shipping_postcode();

		$address = new Address();
		$address->setContactName( $contactName );
		$address->setCity( $city );
		$address->setCountry( $country );
		$address->setAddress( $fullAddress );
		$address->setZipCode( $zipCode );

		return $address;
	}

	protected function createBasket( WC_Order $order, array $cart ): array {
		$basketItems             = [];
		$isShippingPriceIncluded = $this->orderHasShippingPrice( $order );

		if ( $isShippingPriceIncluded ) {
			$shippingItem = new BasketItem();
			$shippingItem->setId( 'SHIPPING' );
			$shippingItem->setName( 'Shipping' );
			$shippingItem->setCategory1( 'Shipping' );
			$shippingItem->setItemType( BasketItemType::PHYSICAL );
			$shippingPrice = strval( floatval( $order->get_shipping_total() ) + floatval( $order->get_shipping_tax() ) );
			$shippingItem->setPrice( $shippingPrice );
			$basketItems[] = $shippingItem;
		}

		$itemSize = count( $cart );
		if ( ! $itemSize ) {
			return $this->oneProductCalc( $order );
		}

		foreach ( $cart as $item ) {
			$product = $item['data'];
			if ( ! $product ) {
				continue;
			}

			$basketItem = new BasketItem();
			$basketItem->setId( $this->validateStringVal( (string) $item['product_id'] ) );
			$basketItem->setName( $this->validateStringVal( $product->get_name() ) );

			$product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
			$categories = get_the_terms( $product_id, 'product_cat' );

			$category1 = '';
			if ( $categories && ! is_wp_error( $categories ) ) {
				$category_names = wp_list_pluck( $categories, 'name' );
				$category1      = implode( ', ', $category_names );
			}

			$basketItem->setCategory1( $this->validateStringVal( $category1 ) );
			$basketItem->setItemType( $product->is_virtual() ? BasketItemType::VIRTUAL : BasketItemType::PHYSICAL );

			$realPrice = $item['quantity'] * $this->priceHelper->realPrice( $product->get_sale_price(), $product->get_price() );

			$basketItemPrice = $this->priceHelper->priceParser( round( $realPrice, 2 ) );
			$basketItem->setPrice( $basketItemPrice );

			if ( $basketItemPrice > 0 ) {
				$basketItems[] = $basketItem;
			}
		}

		return $basketItems;
	}

	protected function orderHasShippingPrice( WC_Order $order ): bool {
		return $order->get_shipping_total() > 0;
	}

	protected function oneProductCalc( $order ) {
		$keyNumber                 = 0;
		$basketItems[ $keyNumber ] = new stdClass();

		$basketItems[ $keyNumber ]->id        = $order->get_id();
		$basketItems[ $keyNumber ]->price     = $this->priceHelper->priceParser( round( $order->get_total(), 2 ) );
		$basketItems[ $keyNumber ]->name      = 'Woocommerce - Custom Order Page';
		$basketItems[ $keyNumber ]->category1 = 'Custom Order Page';
		$basketItems[ $keyNumber ]->itemType  = 'PHYSICAL';

		return $basketItems;
	}

	public function createPrice( WC_Order $order, array $cart ): string {
		$price                   = 0.00;
		$isShippingPriceIncluded = $this->orderHasShippingPrice( $order );

		if ( $isShippingPriceIncluded ) {
			$shippingPrice = floatval( $order->get_shipping_total() ) + floatval( $order->get_shipping_tax() );
			$price         += $shippingPrice;
		}

		$itemSize = count( $cart );
		if ( ! $itemSize ) {
			$price += round( $order->get_total(), 2 );
		}

		foreach ( $cart as $item ) {
			$product = $item['data'];
			if ( ! $product ) {
				continue;
			}


			$price += round( $item['quantity'] * $this->priceHelper->realPrice( $product->get_sale_price(), $product->get_price() ), 2 );
		}

		return $this->priceHelper->priceParser( round( $price, 2 ) );
	}
}