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
use Iyzico\IyzipayWoocommerce\Common\Helpers\VersionChecker;
use Iyzico\IyzipayWoocommerce\Database\DatabaseManager;
use Iyzipay\Model\CheckoutFormInitialize;
use Iyzipay\Model\ProtectedOverleyScript;
use Iyzipay\Options;
use Iyzipay\Request\CreateCheckoutFormInitializeRequest;
use Iyzipay\Request\RetrieveProtectedOverleyScriptRequest;
use WC_Payment_Gateway;

class CheckoutForm extends WC_Payment_Gateway
{

    public $checkoutSettings;
    public $order;
    public $form_fields;
    public $supports = [];
    public $has_fields;
    public $cookieManager;
    public $versionChecker;
    public $logger;
    public $priceHelper;
    public $paymentProcessor;
    public $checkoutDataFactory;
    public $checkoutView;
    public $adminSettings;
    public $refundProcessor;
    public $databaseManager;

    public function __construct()
    {
        $this->id = "iyzico";
        $this->method_title = __('iyzico Checkout', 'iyzico-woocommerce');
        $this->method_description = __('Best Payment Solution', 'iyzico-woocommerce');

        $this->checkoutSettings = new CheckoutSettings();
        $this->form_fields = $this->checkoutSettings->getFormFields();
        $this->init_settings();
        $settings = $this->checkoutSettings->getSettings();

        $this->enabled = $settings['enabled'];
        $this->title = $settings['title'];
        $this->description = $settings['description'];
        $this->order_button_text = $settings['button_text'] ?? '';
        $this->icon = $settings['icon'] ?? '';
        $this->has_fields = true;
        $this->supports = [
            'products',
            'refunds'
        ];

        $this->databaseManager = new DatabaseManager();
        $this->logger = new Logger();
        $this->cookieManager = new CookieManager();
        $this->versionChecker = new VersionChecker();
        $this->priceHelper = new PriceHelper();
        $this->paymentProcessor = new PaymentProcessor();
        $this->checkoutDataFactory = new DataFactory();
        $this->checkoutView = new CheckoutView();
        $this->adminSettings = new SettingsPage();
        $this->refundProcessor = new RefundProcessor();

        add_action('woocommerce_before_checkout_form', array($this, 'display_errors'));
    }

    public function admin_overlay_script()
    {
        $overlayScriptRequest = new RetrieveProtectedOverleyScriptRequest();
        $overlayScriptRequest->setLocale($this->checkoutSettings->findByKey('form_language') || "tr");
        $overlayScriptRequest->setConversationId(wp_rand(100000, 999999));
        $overlayScriptRequest->setLocale($this->checkoutSettings->findByKey('overlay_script'));

        $overlayScriptResponse = ProtectedOverleyScript::retrieve($overlayScriptRequest, $this->create_options());

        if ($overlayScriptResponse->getProtectedShopId() !== null) {
            esc_js($overlayScriptResponse->getProtectedShopId());
            update_option('iyzico_overlay_token', $overlayScriptResponse->getProtectedShopId());
        }

        return true;
    }

    protected function create_options()
    {
        $options = new Options();
        $options->setApiKey($this->checkoutSettings->findByKey('api_key'));
        $options->setSecretKey($this->checkoutSettings->findByKey('secret_key'));
        $options->setBaseUrl($this->checkoutSettings->findByKey('api_type'));

        return $options;
    }

    public function handle_api_request()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['wc-api']) && $_GET['wc-api'] === 'iyzipay') {
            $this->paymentProcessor->processCallback();
            exit;
        }
    }

    public function process_refund($order_id, $amount = null, $reason = '')
    {
        return $this->refundProcessor->refund($order_id, $amount);
    }

    public function process_payment($order_id)
    {
        try {
            $this->order = wc_get_order($order_id);
            $formType = $this->checkoutSettings->findByKey('form_class');

            if ($formType === 'redirect') {
                $this->order->add_order_note(__(
                    "This order will be processed on the iyzico payment page.",
                    "iyzico-woocommerce"
                ));
                $checkoutFormInitialize = $this->create_payment($order_id);
                $paymentPageUrl = $checkoutFormInitialize->getPaymentPageUrl();

                return $this->redirect_to_iyzico($paymentPageUrl);
            }

            return [
                'result' => 'success',
                'redirect' => $this->order->get_checkout_payment_url(true)
            ];
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');

            return ['result' => 'failure'];
        }
    }

    protected function create_payment($orderId)
    {
        $this->versionChecker->check();
        $this->cookieManager->setWooCommerceSessionCookie();

        // Get WC, Customer, Cart, Order, Currency, Checkout Data, Price and PaidPrice
        global $woocommerce;
        $customer = wp_get_current_user();
        $cart = $woocommerce->cart->get_cart();
        $order = wc_get_order($orderId);
        $checkoutData = $this->checkoutDataFactory->prepareCheckoutData($customer, $order, $cart);
        $currency = get_woocommerce_currency();
        $price = $this->checkoutDataFactory->createPrice($order, $cart);
        $paidPrice = $this->priceHelper->priceParser(round($order->get_total(), 2));
        $callbackUrl = add_query_arg('wc-api', 'iyzipay', $order->get_checkout_order_received_url());
        $conversationId = uniqid(strval($orderId));

        // WooCommerce Session Settings
        $woocommerce->session->set('conversationId', $conversationId);
        $woocommerce->session->set('customerId', $customer->ID);
        $woocommerce->session->set('totalAmount', $order->get_total());

        // Payment Source Settings
        $paymentSource = "WOOCOMMERCE|$woocommerce->version|" . IYZICO_PLUGIN_VERSION;
        $affiliate = $this->checkoutSettings->findByKey('affiliate_network');
        if (strlen($affiliate) > 0) {
            $paymentSource = "$paymentSource|$affiliate";
        }

        // Form Language Settings
        $settingsLang = $this->checkoutSettings->findByKey('form_language');
        if ($settingsLang === null || strlen($settingsLang) === 0 || $settingsLang === false) {
            $language = "tr";
        } else {
            $language = strtolower($settingsLang);
        }

        // Create Request
        $request = new CreateCheckoutFormInitializeRequest();
        $request->setLocale($language);
        $request->setConversationId($conversationId);
        $request->setPrice($price);
        $request->setPaidPrice($paidPrice);
        $request->setCurrency($currency);
        $request->setBasketId($orderId);
        $request->setPaymentGroup("PRODUCT");
        $request->setPaymentSource($paymentSource);
        $request->setCallbackUrl($callbackUrl);
        $request->setForceThreeDS("0");
        $request->setBuyer($checkoutData['buyer']);
        $request->setBillingAddress($checkoutData['billingAddress']);
        isset($checkoutData['shippingAddress']) ? $request->setShippingAddress($checkoutData['shippingAddress']) : null;
        $request->setBasketItems($checkoutData['basketItems']);

        // Create Options
        $options = $this->create_options();

        // Check Request Logs Settings
        $isSave = $this->checkoutSettings->findByKey('request_log_enabled');
        $isSave === 'yes' ? $this->logger->info("CheckoutFormInitialize Request: " . $request->toJsonString()) : null;

        // Payment Initialize Request Response
        $response = CheckoutFormInitialize::create($request, $options);

        // Save iyzico Order Table
        $token = $response->getToken();
        $status = $response->getStatus();
        $this->databaseManager->createOrUpdateOrder(
            null,
            $orderId,
            $conversationId,
            $token,
            $paidPrice,
            $status,
            null
        );

        return $response;
    }

    public function redirect_to_iyzico(string $paymentPageUrl)
    {
        if (strlen($paymentPageUrl) === 0) {
            wc_add_notice(__(
                "An unknown error occurred during the payment process. Please try again.",
                "iyzico-woocommerce"
            ), 'error');
            return [
                'result' => 'failure'
            ];
        }

        return [
            'result' => 'success',
            'redirect' => $paymentPageUrl
        ];
    }

    public function checkout_form($orderId)
    {
        $checkoutFormInitialize = $this->create_payment($orderId);
        $this->checkoutView->renderCheckoutForm($checkoutFormInitialize);
    }

    public function display_errors()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['payment']) && $_GET['payment'] === 'failed') {
            $error = WC()->session->get('iyzico_error');

            if ($error) {
                wc_add_notice(
                    '<strong>' . __('Payment Error:', 'iyzico-woocommerce') . '</strong> ' . $error,
                    'error',
                    ['iyzico_error' => true]
                );
                WC()->session->__unset('iyzico_error');
            } else {
                wc_add_notice(
                    __('An unexpected error occurred. Please try again.', 'iyzico-woocommerce'),
                    'error'
                );
            }
        }
    }

    public function admin_options()
    {
        ob_start();
        parent::admin_options();
        $parent_options = ob_get_contents();
        ob_end_clean();

        $allowed_html = [
            'form' => ['method' => [], 'action' => [], 'id' => [], 'class' => [], 'enctype' => []],
            'nav' => ['class' => []],
            'a' => ['href' => [], 'class' => [], 'aria-label' => [], 'target' => [], 'rel' => []],
            'h1' => ['class' => []],
            'h2' => ['class' => []],
            'h3' => ['class' => [], 'id' => []],
            'table' => ['class' => []],
            'tbody' => [],
            'tr' => ['valign' => []],
            'th' => ['scope' => [], 'class' => []],
            'td' => ['class' => []],
            'fieldset' => [],
            'legend' => ['class' => []],
            'label' => ['for' => [], 'class' => []],
            'input' => ['type' => [], 'name' => [], 'value' => [], 'class' => [], 'id' => [], 'placeholder' => [], 'style' => [], 'checked' => [], 'maxlength' => []],
            'select' => ['name' => [], 'class' => [], 'id' => [], 'style' => []],
            'option' => ['value' => [], 'selected' => []],
            'p' => ['class' => [], 'style' => []],
            'strong' => [],
            'span' => ['class' => [], 'aria-label' => [], 'tabindex' => []],
            'button' => ['name' => [], 'class' => [], 'type' => [], 'value' => [], 'disabled' => []],
            'input' => ['type' => [], 'name' => [], 'value' => [], 'id' => [], 'class' => [], 'style' => [], 'checked' => [], 'maxlength' => []],
            'div' => ['class' => [], 'id' => [], 'style' => []],
            'img' => ['src' => [], 'style' => []],
            'link' => ['rel' => [], 'href' => [], 'type' => []],
            'script' => ['src' => [], 'type' => []],
            'style' => [],
        ];

        echo wp_kses($parent_options, $allowed_html);
        $this->adminSettings->getHtmlContent();
    }

    public function load_form()
    {
        wp_enqueue_style(
            'iyzico-loading-style',
            plugin_dir_url(PLUGIN_BASEFILE) . 'assets/css/iyzico-loading.css',
            array(),
            IYZICO_PLUGIN_VERSION
        );
        $this->checkoutView->renderLoadingHtml();
    }
}
