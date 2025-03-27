<?php

namespace Iyzico\IyzipayWoocommerce\Common\Helpers;

use Iyzico\IyzipayWoocommerce\Checkout\CheckoutSettings;
use Iyzico\IyzipayWoocommerce\Database\DatabaseManager;
use Iyzipay\Model\AmountBaseRefund;
use Iyzipay\Options;
use Iyzipay\Request\AmountBaseRefundRequest;
use WC_Order;

class RefundProcessor
{
    private $logger;
    private $databaseManager;
    private $priceHelper;
    private $checkoutSettings;

    public function __construct()
    {
        $this->logger = new Logger();
        $this->databaseManager = new DatabaseManager();
        $this->priceHelper = new PriceHelper();
        $this->checkoutSettings = new CheckoutSettings();
    }

    public function refund($orderId, $amount)
    {
        $ipAddress = '127.0.0.1';

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        $order = $this->getOrderByOrderId($orderId);
        $isSave = $this->checkoutSettings->findByKey('request_log_enabled');


        if (is_null($order)) {
            $this->logger->error('RefundProcessor: Order not found for order id ' . $orderId);

            return false;
        }

        $paymentId = $order['payment_id'];
        $conversationId = $order['conversation_id'];

        if (is_null($amount)) {
            $amount = $order['total_amount'];
        }

        $options = $this->create_options();

        $request = new AmountBaseRefundRequest();
        $request->setPaymentId($paymentId);
        $request->setConversationId($conversationId);
        $request->setPrice($this->priceHelper->priceParser($amount));
        $request->setIp($ipAddress);


        $response = AmountBaseRefund::create($request, $options);

        $isSave === 'yes' ? $this->logger->info("AmountBaseRefund Request: " . wp_json_encode($request, JSON_PRETTY_PRINT)) : null;
        $isSave === 'yes' ? $this->logger->info("AmountBaseRefund Response: " . wp_json_encode($response, JSON_PRETTY_PRINT)) : null;

        if ($response->getStatus() == 'success') {
            $order = new WC_Order($orderId);
            $order->add_order_note(
            /* translators: %s: refunded amount */
                sprintf(__('Refunded %s', 'iyzico-woocommerce'), $amount)
            );

            $this->logger->info('RefundProcessor: Refund successful for order ' . $orderId);

            return true;
        }

        return false;
    }

    private function getOrderByOrderId($orderId)
    {
        return $this->databaseManager->findOrderByOrderId($orderId);
    }

    protected function create_options(): Options
    {
        $options = new Options();
        $options->setApiKey($this->checkoutSettings->findByKey('api_key'));
        $options->setSecretKey($this->checkoutSettings->findByKey('secret_key'));
        $options->setBaseUrl($this->checkoutSettings->findByKey('api_type'));

        return $options;
    }
}