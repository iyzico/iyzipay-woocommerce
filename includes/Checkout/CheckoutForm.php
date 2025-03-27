<?php

namespace Iyzico\IyzipayWoocommerce\Checkout;

use Exception;
use Iyzico\IyzipayWoocommerce\Admin\SettingsPage;
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
use Iyzipay\Model\CheckoutFormInitialize;
use Iyzipay\Model\ProtectedOverleyScript;
use Iyzipay\Options;
use Iyzipay\Request\CreateCheckoutFormInitializeRequest;
use Iyzipay\Request\RetrieveProtectedOverleyScriptRequest;
use WC_Payment_Gateway;

class CheckoutForm extends WC_Payment_Gateway {

	public $checkoutSettings;
	public $order;
	public $form_fields;
	public $supports = [];
	public $has_fields;
	public $cookieManager;
	public $versionChecker;
	public $tlsVerifier;
	public $logger;
	public $priceHelper;
	public $paymentProcessor;
	public $checkoutDataFactory;
	public $checkoutView;
	public $adminSettings;
	public $databaseManager;
	public $refundProcessor;
	public $signatureChecker;

	public function __construct() {
		$this->id                 = "iyzico";
		$this->method_title       = __( 'iyzico Checkout', 'woocommerce-iyzico' );
		$this->method_description = __( 'Best Payment Solution', 'woocommerce-iyzico' );
		$this->checkoutSettings   = new CheckoutSettings();
		$this->form_fields        = $this->checkoutSettings->getFormFields();
		$this->init_settings();
		$settings = $this->checkoutSettings->getSettings();

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
		$this->signatureChecker = new SignatureChecker();

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

		$this->checkoutDataFactory = new DataFactory( $this->priceHelper, $this->checkoutSettings, $this->logger );
		$this->checkoutView        = new CheckoutView( $this->checkoutSettings );
		$this->adminSettings       = new SettingsPage();
		$this->refundProcessor     = new RefundProcessor();
	}

	public function admin_overlay_script() {
		$overlayScriptRequest = new RetrieveProtectedOverleyScriptRequest();
		$overlayScriptRequest->setLocale( $this->checkoutSettings->findByKey( 'form_language' ) || "tr" );
		$overlayScriptRequest->setConversationId( rand( 100000, 999999 ) );
		$overlayScriptRequest->setLocale( $this->checkoutSettings->findByKey( 'overlay_script' ) );

		$overlayScriptResponse = ProtectedOverleyScript::retrieve( $overlayScriptRequest, $this->create_options() );
		$iyzicoOverlayToken    = get_option( 'iyzico_overlay_token' );

		if ( $overlayScriptResponse->getProtectedShopId() !== null ) {
			esc_js( $overlayScriptResponse->getProtectedShopId() );
			if ( empty( $iyzicoOverlayToken ) ) {
				update_option( 'iyzico_overlay_token', $overlayScriptResponse->getProtectedShopId() );
			} else {
				update_option( 'iyzico_overlay_token', $overlayScriptResponse->getProtectedShopId() );
			}
		}

		return true;
	}

	protected function create_options(): Options {
		$options = new Options();
		$options->setApiKey( $this->checkoutSettings->findByKey( 'api_key' ) );
		$options->setSecretKey( $this->checkoutSettings->findByKey( 'secret_key' ) );
		$options->setBaseUrl( $this->checkoutSettings->findByKey( 'api_type' ) );

		return $options;
	}

	public function handle_api_request() {
		if ( isset( $_GET['wc-api'] ) && $_GET['wc-api'] === 'iyzipay' ) {
			$this->paymentProcessor->processCallback();
			exit;
		}
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		return $this->refundProcessor->refund( $order_id, $amount );
	}

	public function process_payment( $order_id ) {
		try {
			$this->order = wc_get_order( $order_id );
			$formType    = $this->checkoutSettings->findByKey( 'form_class' );

			if ( $formType === 'redirect' ) {
				$this->order->add_order_note( __( "This order will be processed on the iyzico payment page.", "woocommerce-iyzico" ) );
				$checkoutFormInitialize = $this->create_payment( $order_id );
				$paymentPageUrl         = $checkoutFormInitialize->getPaymentPageUrl();

				return $this->redirect_to_iyzico( $paymentPageUrl );
			}

			return [
				'result'   => 'success',
				'redirect' => $this->order->get_checkout_payment_url( true )
			];

		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );

			return [ 'result' => 'failure' ];
		}
	}

	protected function create_payment( $orderId ) {
		$this->versionChecker->check();
		$this->cookieManager->setWooCommerceSessionCookie();

		global $woocommerce;

		$order    = wc_get_order( $orderId );
		$cart     = $woocommerce->cart->get_cart();
		$language = empty( $this->checkoutSettings->findByKey( 'form_language' ) ) ? "tr" : $this->checkoutSettings->findByKey( 'form_language' );
		$customer = wp_get_current_user();

		$woocommerce->session->set( 'conversationId', $orderId );
		$woocommerce->session->set( 'customerId', $customer->ID );
		$woocommerce->session->set( 'totalAmount', $order->get_total() );

		$currency = get_woocommerce_currency();

		// Payment Source Settings
		$affiliate     = $this->checkoutSettings->findByKey( 'affiliate_network' );
		$paymentSource = "WOOCOMMERCE|$woocommerce->version|CARRERA-3.5.8";

		if ( strlen( $affiliate ) > 0 ) {
			$paymentSource = "$paymentSource|$affiliate";
		}

		// Create Request
		$request = new CreateCheckoutFormInitializeRequest();
		$request->setLocale( $language );
		$request->setConversationId( $orderId );
		$request->setPrice( $this->checkoutDataFactory->createPrice( $order, $cart ) );
		$request->setPaidPrice( $this->priceHelper->priceParser( round( $order->get_total(), 2 ) ) );
		$request->setCurrency( $currency );
		$request->setBasketId( $orderId );
		$request->setPaymentGroup( "PRODUCT" );
		$request->setPaymentSource( $paymentSource );
		$request->setCallbackUrl( add_query_arg( 'wc-api', 'iyzipay', $order->get_checkout_order_received_url() ) );
		$request->setForceThreeDS( "0" );

		// Prepare Checkout Data
		$checkoutData = $this->checkoutDataFactory->prepareCheckoutData( $customer, $order, $cart );
		$request->setBuyer( $checkoutData['buyer'] );
		$request->setBillingAddress( $checkoutData['billingAddress'] );
		isset( $checkoutData['shippingAddress'] ) ? $request->setShippingAddress( $checkoutData['shippingAddress'] ) : null;
		$request->setBasketItems( $checkoutData['basketItems'] );

		// Create Options
		$options = $this->create_options();

		// Check Request Logs Settings
		$isSave = $this->checkoutSettings->findByKey( 'request_log_enabled' );
		$isSave === 'yes' ? $this->logger->info( "CheckoutFormInitialize Request: " . $request->toJsonString() ) : null;

		$checkoutFormResponse = CheckoutFormInitialize::create( $request, $options );

		$rawResult         = $checkoutFormResponse->getRawResult();
		$rawResultResponse = json_decode( $rawResult );

		$conversationId = $checkoutFormResponse->getConversationId();
		$token          = $checkoutFormResponse->getToken();
		$signature      = $rawResultResponse->signature;

		$secretKey           = $options->getSecretKey();
		$calculatedSignature = $this->signatureChecker->calculateHmacSHA256Signature( [
			$conversationId,
			$token
		], $secretKey );

		if ( $signature != $calculatedSignature ) {
			$this->logger->error( "CheckoutForm.php: conversationId: $conversationId token: $token #Signature is not valid." );
		}

		return $checkoutFormResponse;
	}

	public function redirect_to_iyzico( string $paymentPageUrl ) {
		return [
			'result'   => 'success',
			'redirect' => $paymentPageUrl
		];
	}

	public function checkout_form( $orderId ) {
		$checkoutFormInitialize = $this->create_payment( $orderId );
		$this->checkoutView->renderCheckoutForm( $checkoutFormInitialize );
	}

	public function display_errors() {
		if ( isset( $_GET['payment'] ) && $_GET['payment'] === 'failed' ) {
			$error = WC()->session->get( 'iyzico_error' );
			if ( $error ) {
				wc_add_notice( $error, 'error' );
				WC()->session->set( 'iyzico_error', null );
			} else {
				wc_add_notice( __( "An unknown error occurred during the payment process. Please try again.", "woocommerce-iyzico" ), 'error' );
			}
		}
	}

	public function admin_options() {
		$this->adminSettings->renderAdminOptions();
	}

	public function load_form() {
		wp_enqueue_style( 'iyzico-loading-style', plugin_dir_url( PLUGIN_BASEFILE ) . 'assets/css/iyzico-loading.css' );
		$this->checkoutView->renderLoadingHtml();
	}
}
