<?php

namespace Iyzico\IyzipayWoocommerce\Pwi;

use Exception;
use Iyzico\IyzipayWoocommerce\Admin\SettingsPage;
use Iyzico\IyzipayWoocommerce\Checkout\CheckoutSettings;
use Iyzico\IyzipayWoocommerce\Common\Helpers\CookieManager;
use Iyzico\IyzipayWoocommerce\Common\Helpers\DataFactory;
use Iyzico\IyzipayWoocommerce\Common\Helpers\Logger;
use Iyzico\IyzipayWoocommerce\Common\Helpers\PaymentProcessor;
use Iyzico\IyzipayWoocommerce\Common\Helpers\PriceHelper;
use Iyzico\IyzipayWoocommerce\Common\Helpers\RefundProcessor;
use Iyzico\IyzipayWoocommerce\Common\Helpers\SignatureChecker;
use Iyzico\IyzipayWoocommerce\Common\Helpers\TlsVerifier;
use Iyzico\IyzipayWoocommerce\Common\Helpers\VersionChecker;
use Iyzico\IyzipayWoocommerce\Database\DatabaseManager;
use Iyzipay\Model\PayWithIyzicoInitialize;
use Iyzipay\Options;
use Iyzipay\Request\CreatePayWithIyzicoInitializeRequest;
use WC_Payment_Gateway;

class Pwi extends WC_Payment_Gateway {

	public $pwiSettings;
	public $order;
	public $form_fields;
	public $logger;
	public $cookieManager;
	public $versionChecker;
	public $tlsVerifier;
	public $priceHelper;
	public $databaseManager;
	public $checkoutSettings;
	public $pwiDataFactory;
	public $paymentProcessor;
	public $refundProcessor;
	public $signatureChecker;
	public $adminSettings;


	public function __construct() {
		$this->id                 = "pwi";
		$this->method_title       = __( 'Pay with iyzico', 'woocommerce-iyzico' );
		$this->method_description = __( 'Best Payment Solution', 'woocommerce-iyzico' );
		$this->pwiSettings        = new PwiSettings();
		$this->form_fields        = $this->pwiSettings->getFormFields();
		$this->init_settings();
		$settings = $this->pwiSettings->getSettings();

		$this->enabled           = $settings['enabled'];
		$this->title             = $settings['title'];
		$this->description       = $settings['description'];
		$this->order_button_text = $settings['button_text'] ?? '';
		$this->icon              = $settings['icon'] ?? '';
		$this->has_fields        = true;
		$this->supports          = [
			'products',
			'refunds'
		];

		$this->logger           = new Logger();
		$this->cookieManager    = new CookieManager();
		$this->versionChecker   = new VersionChecker( $this->logger );
		$this->tlsVerifier      = new TlsVerifier();
		$this->priceHelper      = new PriceHelper();
		$this->databaseManager  = new DatabaseManager();
		$this->checkoutSettings = new CheckoutSettings();
		$this->signatureChecker = new SignatureChecker();
		$this->adminSettings    = new SettingsPage();

		$this->paymentProcessor = new PaymentProcessor(
			$this->logger,
			$this->priceHelper,
			$this->cookieManager,
			$this->versionChecker,
			$this->tlsVerifier,
			$this->checkoutSettings,
			$this->databaseManager,
			$this->signatureChecker
		);

		$this->pwiDataFactory  = new DataFactory( $this->priceHelper, $this->checkoutSettings, $this->logger );
		$this->refundProcessor = new RefundProcessor();
	}

	public function process_payment( $order_id ) {
		try {
			$this->order = wc_get_order( $order_id );
			$this->order->add_order_note( __( "This order will be processed on the iyzico payment page.", "woocommerce-iyzico" ) );
			$pwiInitialize  = $this->create_payment( $order_id );
			$paymentPageUrl = $pwiInitialize->getPayWithIyzicoPageUrl();

			return $this->redirect_to_iyzico( $paymentPageUrl );
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
		}
	}

	protected function create_payment( $orderId ) {
		$this->versionChecker->check();
		$this->cookieManager->setWooCommerceSessionCookie();

		global $woocommerce;

		$order    = wc_get_order( $orderId );
		$cart     = $woocommerce->cart->get_cart();
		$language = $this->checkoutSettings->findByKey( 'form_language' ) ?? "tr";
		$customer = wp_get_current_user();

		$woocommerce->session->set( 'conversationId', $orderId );
		$woocommerce->session->set( 'customerId', $customer->ID );
		$woocommerce->session->set( 'totalAmount', $order->get_total() );

		$currency = get_woocommerce_currency();

		// Payment Source Settings
		$affiliate     = $this->checkoutSettings->findByKey( 'affiliate_network' );
		$paymentSource = "WOOCOMMERCE|$woocommerce->version|CARRERA-PWI-3.5.8";

		if ( strlen( $affiliate ) > 0 ) {
			$paymentSource = "$paymentSource|$affiliate";
		}

		// Create Request
		$request = new CreatePayWithIyzicoInitializeRequest();
		$request->setLocale( $language );
		$request->setConversationId( $orderId );
		$request->setPrice( $this->pwiDataFactory->createPrice( $order, $cart ) );
		$request->setPaidPrice( $this->priceHelper->priceParser( round( $order->get_total(), 2 ) ) );
		$request->setCurrency( $currency );
		$request->setBasketId( $orderId );
		$request->setPaymentGroup( "PRODUCT" );
		$request->setPaymentSource( $paymentSource );
		$request->setCallbackUrl( add_query_arg( 'wc-api', 'iyzipay', $order->get_checkout_order_received_url() ) );

		// Prepare Checkout Data
		$checkoutData = $this->pwiDataFactory->prepareCheckoutData( $customer, $order, $cart );
		$request->setBuyer( $checkoutData['buyer'] );
		$request->setBillingAddress( $checkoutData['billingAddress'] );
		$request->setShippingAddress( $checkoutData['shippingAddress'] );
		$request->setBasketItems( $checkoutData['basketItems'] );

		// Create Options
		$options = $this->create_options();

		// Check Request Logs Settings
		$isSave = $this->checkoutSettings->findByKey( 'request_log_enabled' );

		$isSave === 'yes' ? $this->logger->info( "PwiInitialize Request: " . $request->toJsonString() ) : null;

		$pwiResponse       = PayWithIyzicoInitialize::create( $request, $options );
		$rawResult         = $pwiResponse->getRawResult();
		$rawResultResponse = json_decode( $rawResult );

		// Check Request Signature
		$conversationId = $pwiResponse->getConversationId();
		$token          = $pwiResponse->getToken();
		$signature      = $rawResultResponse->signature;

		$secretKey           = $options->getSecretKey();
		$calculatedSignature = $this->signatureChecker->calculateHmacSHA256Signature( [
			$conversationId,
			$token
		], $secretKey );

		if ( $signature != $calculatedSignature ) {
			$this->logger->error( "Signature is not valid" );
		}

		return $pwiResponse;
	}

	protected function create_options(): Options {
		$options = new Options();
		$options->setApiKey( $this->checkoutSettings->findByKey( 'api_key' ) );
		$options->setSecretKey( $this->checkoutSettings->findByKey( 'secret_key' ) );
		$options->setBaseUrl( $this->checkoutSettings->findByKey( 'api_type' ) );

		return $options;
	}

	public function redirect_to_iyzico( string $paymentPageUrl ) {
		return [
			'result'   => 'success',
			'redirect' => $paymentPageUrl
		];
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		return $this->refundProcessor->refund( $order_id, $amount );
	}

	public function admin_options() {
		$this->adminSettings->renderAdminOptions();
	}

}
