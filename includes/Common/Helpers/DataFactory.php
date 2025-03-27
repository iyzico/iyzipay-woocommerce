<?php

namespace Iyzico\IyzipayWoocommerce\Common\Helpers;

use Iyzipay\Model\Address;
use Iyzipay\Model\BasketItem;
use Iyzipay\Model\BasketItemType;
use Iyzipay\Model\Buyer;
use stdClass;
use WC_Order;

class DataFactory
{
    protected $priceHelper;

    public function __construct()
    {
        $this->priceHelper = new PriceHelper();
    }

    public function prepareCheckoutData($customer, WC_Order $order, array $cart)
    {
        $cartHasPhysicalProduct = $this->cartHasPhysicalProduct($cart);
        $data = [
            'buyer' => $this->createBuyer($customer, $order),
            'billingAddress' => $this->createAddress($order, 'billing'),
            'shippingAddress' => $this->createAddress($order, 'shipping'),
            'basketItems' => $this->createBasket($order, $cart),
        ];

        if (!$cartHasPhysicalProduct) {
            unset($data['shippingAddress']);
        }

        return $data;
    }

    protected function cartHasPhysicalProduct(array $cart)
    {
        foreach ($cart as $item) {
            if (!$item['data']->is_virtual()) {
                return true;
            }
        }

        return false;
    }

    protected function createBuyer($customer, WC_Order $order)
    {

        $ipAddress = '127.0.0.1';

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        $buyer = new Buyer();
        $buyer->setId($this->validateStringVal($customer->ID));
        $buyer->setName($this->validateStringVal($order->get_billing_first_name()));
        $buyer->setSurname($this->validateStringVal($order->get_billing_last_name()));
        $buyer->setIdentityNumber("11111111111");
        $buyer->setEmail($this->validateStringVal($order->get_billing_email()));
        $buyer->setRegistrationDate(gmdate('Y-m-d H:i:s'));
        $buyer->setLastLoginDate(gmdate('Y-m-d H:i:s'));
        $buyer->setRegistrationAddress($this->validateStringVal($order->get_billing_address_1()) . ' ' . $this->validateStringVal($order->get_billing_address_2()));
        $buyer->setCity($this->validateStringVal($order->get_billing_city()));
        $buyer->setCountry($this->validateStringVal($order->get_billing_country()));
        $buyer->setZipCode($this->validateStringVal($order->get_billing_postcode()));
        $buyer->setIp($this->validateStringVal($ipAddress));
        $buyer->setGsmNumber($this->validateStringVal($order->get_billing_phone()));

        return $buyer;
    }

    protected function validateStringVal($string)
    {
        if (empty($string)) {
            return 'UNKNOWN';
        }

        if (is_null($string)) {
            return 'UNKNOWN';
        }

        if (strlen($string) <= 0) {
            return 'UNKNOWN';
        }

        return substr($string, 0, 249);
    }

    protected function createAddress(WC_Order $order, string $type)
    {
        $isTypeBilling = $type === "billing";

        $firstName = $this->validateStringVal($isTypeBilling ? $order->get_billing_first_name() : $order->get_shipping_first_name());
        $lastName = $this->validateStringVal($isTypeBilling ? $order->get_billing_last_name() : $order->get_shipping_last_name());
        $contactName = $firstName . ' ' . $lastName;

        $city = $this->validateStringVal($isTypeBilling ? $order->get_billing_city() : $order->get_shipping_city());
        $country = $this->validateStringVal($isTypeBilling ? $order->get_billing_country() : $order->get_shipping_country());
        $address1 = $this->validateStringVal($isTypeBilling ? $order->get_billing_address_1() : $order->get_shipping_address_1());
        $address2 = $this->validateStringVal($isTypeBilling ? $order->get_billing_address_2() : $order->get_shipping_address_2());
        $fullAddress = trim($address1 . ' ' . $address2);
        $zipCode = $isTypeBilling ? $order->get_billing_postcode() : $order->get_shipping_postcode();

        $address = new Address();
        $address->setContactName($contactName);
        $address->setCity($city);
        $address->setCountry($country);
        $address->setAddress($fullAddress);
        $address->setZipCode($zipCode);

        return $address;
    }

    protected function createBasket(WC_Order $order, array $cart)
    {
        $basketItems = [];
        $isShippingPriceIncluded = $this->orderHasShippingPrice($order);

        if ($isShippingPriceIncluded) {
            $shippingItem = new BasketItem();
            $shippingItem->setId('SHIPPING');
            $shippingItem->setName('Shipping');
            $shippingItem->setCategory1('Shipping');
            $shippingItem->setItemType(BasketItemType::PHYSICAL);
            $shippingItemRealPrice = floatval($order->get_shipping_total()) + floatval($order->get_shipping_tax());
            $shippingItemPrice = $this->priceHelper->priceParser(round($shippingItemRealPrice, 2));
            $shippingItem->setPrice($shippingItemPrice);
            $basketItems[] = $shippingItem;
        }

        $itemSize = count($cart);
        if (!$itemSize) {
            return $this->oneProductCalc($order);
        }

        foreach ($cart as $item) {
            $product = $item['data'];
            if (!$product) {
                continue;
            }

            $basketItem = new BasketItem();
            $basketItemId = 'UNKNOWN';

            if (get_class($product) === 'WC_Product_Composite') {
                $basketItemId = $this->validateStringVal($product->get_id());
            }

            if (get_class($product) === 'WC_Product_Variation') {
                $basketItemId = $this->validateStringVal(isset($item['variation_id']) && $item['variation_id'] ? (string)$item['variation_id'] : (string)$item['product_id']);
            }

            if ($basketItemId === 'UNKNOWN') {
                $basketItemId = $this->validateStringVal(isset($item['product_id']) && $item['product_id'] ? (string)$item['product_id'] : (string)$product->get_sku());
            }

            $basketItem->setId($this->validateStringVal($basketItemId));
            $basketItem->setName($this->validateStringVal($product->get_name()));

            $product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
            $categories = get_the_terms($product_id, 'product_cat');

            $category1 = '';
            if ($categories && !is_wp_error($categories)) {
                $category_names = wp_list_pluck($categories, 'name');
                $category1 = implode(', ', $category_names);
            }

            $basketItem->setCategory1($this->validateStringVal($category1));
            $basketItem->setItemType($product->is_virtual() ? BasketItemType::VIRTUAL : BasketItemType::PHYSICAL);

            $realPrice = $item['quantity'] * $this->priceHelper->realPrice(
                    $product->get_sale_price(),
                    $product->get_price()
                );

            $basketItemPrice = $this->priceHelper->priceParser(round($realPrice, 2));
            $basketItem->setPrice($basketItemPrice);

            if ($basketItemPrice > 0) {
                $basketItems[] = $basketItem;
            }
        }

        return $basketItems;
    }

    protected function orderHasShippingPrice(WC_Order $order)
    {
        return $order->get_shipping_total() > 0;
    }

    protected function oneProductCalc($order)
    {
        $keyNumber = 0;
        $basketItems[$keyNumber] = new stdClass();

        $basketItems[$keyNumber]->id = $order->get_id();
        $basketItems[$keyNumber]->price = $this->priceHelper->priceParser(round($order->get_total(), 2));
        $basketItems[$keyNumber]->name = 'Woocommerce - Custom Order Page';
        $basketItems[$keyNumber]->category1 = 'Custom Order Page';
        $basketItems[$keyNumber]->itemType = 'PHYSICAL';

        return $basketItems;
    }

    public function createPrice(array $cart)
    {
        $price = 0.00;
        
        foreach($cart as $item){
            $price += (float)$item->getPrice();
        }

        return $this->priceHelper->priceParser(round($price, 2));
    }
}
