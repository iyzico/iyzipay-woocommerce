<?php

namespace Iyzico\IyzipayWoocommerce\Common\Helpers;

use DateTime;
use Exception;
use Iyzico\IyzipayWoocommerce\Checkout\CheckoutSettings;
use Iyzico\IyzipayWoocommerce\Database\DatabaseManager;
use Iyzipay\Model\AmountBaseRefund;
use Iyzipay\Options;
use Iyzipay\Request\AmountBaseRefundRequest;
use WC_Order;

class RefundProcessor {
	private Logger $logger;
	private DatabaseManager $databaseManager;
	private PriceHelper $priceHelper;
	private CheckoutSettings $checkoutSettings;

	public function __construct() {
		$this->logger           = new Logger();
		$this->databaseManager  = new DatabaseManager();
		$this->priceHelper      = new PriceHelper();
		$this->checkoutSettings = new CheckoutSettings();
	}

	public function refund( $orderId, $amount ) {
		$order  = $this->getOrderByOrderId( $orderId );
		$isSave = $this->checkoutSettings->findByKey( 'request_log_enabled' );


		if ( is_null( $order ) ) {
			$this->logger->error( 'RefundProcessor: Order not found for order id ' . $orderId );

			return false;
		}

		$paymentId = $order['payment_id'];

		if ( is_null( $amount ) ) {
			$amount = $order['total_amount'];
		}

		$options = $this->create_options();

		$request = new AmountBaseRefundRequest();
		$request->setPaymentId( $paymentId );
		$request->setConversationId( $orderId );
		$request->setPrice( $this->priceHelper->priceParser( $amount ) );
		$request->setIp( $_SERVER['REMOTE_ADDR'] );


		$response = AmountBaseRefund::create( $request, $options );

		$isSave === 'yes' ? $this->logger->info( "AmountBaseRefund Request: " . print_r( $request, true ) ) : null;
		$isSave === 'yes' ? $this->logger->info( "AmountBaseRefund Response: " . print_r( $response, true ) ) : null;

		if ( $response->getStatus() == 'success' ) {
			$order = new WC_Order( $orderId );
			$order->add_order_note(
				sprintf( __( 'Refunded %s', 'woocommerce-iyzico' ), $amount )
			);

			$this->logger->info( 'RefundProcessor: Refund successful for order ' . $orderId );

			return true;
		}

		return false;
	}

	private function getOrderByOrderId( $orderId ) {
		return $this->databaseManager->findOrderByOrderId( $orderId );
	}

	protected function create_options(): Options {
		$options = new Options();
		$options->setApiKey( $this->checkoutSettings->findByKey( 'api_key' ) );
		$options->setSecretKey( $this->checkoutSettings->findByKey( 'secret_key' ) );
		$options->setBaseUrl( $this->checkoutSettings->findByKey( 'api_type' ) );

		return $options;
	}

	/**
	 * @throws Exception
	 */
	private function isCancellationAvailable( $orderId ): bool {
		$order = $this->getOrderByOrderId( $orderId );

		$orderDate = new DateTime( $order->created_at );
		$now       = new DateTime();
		$interval  = $now->diff( $orderDate );
		if ( $interval->days > 1 ) {
			$this->logger->error( 'RefundProcessor: Order cancellation is not available for order ' . $orderId . ' because it is older than 24 hours.' );

			return false;
		}

		return true;
	}
}