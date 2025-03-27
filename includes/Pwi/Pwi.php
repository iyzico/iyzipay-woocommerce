<?php

namespace Iyzico\IyzipayWoocommerce\Pwi;

use Exception;
use Iyzico\IyzipayWoocommerce\Admin\SettingsPage;
use Iyzico\IyzipayWoocommerce\Checkout\CheckoutSettings;
use Iyzico\IyzipayWoocommerce\Common\Helpers\CookieManager;
use Iyzico\IyzipayWoocommerce\Common\Helpers\DataFactory;
use Iyzico\IyzipayWoocommerce\Common\Helpers\Logger;
use Iyzico\IyzipayWoocommerce\Common\Helpers\PriceHelper;
use Iyzico\IyzipayWoocommerce\Common\Helpers\RefundProcessor;
use Iyzico\IyzipayWoocommerce\Common\Helpers\SignatureChecker;
use Iyzico\IyzipayWoocommerce\Common\Helpers\VersionChecker;
use Iyzico\IyzipayWoocommerce\Database\DatabaseManager;
use Iyzipay\Model\PayWithIyzicoInitialize;
use Iyzipay\Options;
use Iyzipay\Request\CreatePayWithIyzicoInitializeRequest;
use WC_Payment_Gateway;

class Pwi extends WC_Payment_Gateway
{

    public $pwiSettings;
    public $order;
    public $form_fields;
    public $logger;
    public $cookieManager;
    public $versionChecker;
    public $priceHelper;
    public $checkoutSettings;
    public $pwiDataFactory;
    public $refundProcessor;
    public $signatureChecker;
    public $adminSettings;
    public $databaseManager;


    public function __construct()
    {
        $this->id = "pwi";
        $this->method_title = __('Pay with iyzico', 'iyzico-woocommerce');
        $this->method_description = __('Best Payment Solution', 'iyzico-woocommerce');
        $this->pwiSettings = new PwiSettings();
        $this->form_fields = $this->pwiSettings->getFormFields();
        $this->init_settings();
        $settings = $this->pwiSettings->getSettings();
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
        $this->checkoutSettings = new CheckoutSettings();
        $this->signatureChecker = new SignatureChecker();
        $this->adminSettings = new SettingsPage();

        $this->pwiDataFactory = new DataFactory();
        $this->refundProcessor = new RefundProcessor();
    }

    public function process_payment($order_id)
    {
        try {
            $this->order = wc_get_order($order_id);
            $this->order->set_payment_method('iyzico');
            $this->order->add_order_note(__(
                "This order will be processed on the iyzico payment page.",
                "iyzico-woocommerce"
            ));

            $pwiInitialize = $this->create_payment($order_id);
            if ($pwiInitialize->getStatus() !== 'failure') {
                $paymentPageUrl = $pwiInitialize->getPayWithIyzicoPageUrl();
                return $this->redirect_to_iyzico($paymentPageUrl);
            }
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
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
        $checkoutData = $this->pwiDataFactory->prepareCheckoutData($customer, $order, $cart);
        $currency = get_woocommerce_currency();
        $price = $this->pwiDataFactory->createPrice($order, $cart);
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
        $request = new CreatePayWithIyzicoInitializeRequest();
        $request->setLocale($language);
        $request->setConversationId($conversationId);
        $request->setPrice($price);
        $request->setPaidPrice($paidPrice);
        $request->setCurrency($currency);
        $request->setBasketId($orderId);
        $request->setPaymentGroup("PRODUCT");
        $request->setPaymentSource($paymentSource);
        $request->setCallbackUrl($callbackUrl);

        // Prepare Checkout Data
        $request->setBuyer($checkoutData['buyer']);
        $request->setBillingAddress($checkoutData['billingAddress']);
        $request->setShippingAddress($checkoutData['shippingAddress']);
        $request->setBasketItems($checkoutData['basketItems']);

        // Create Options
        $options = $this->create_options();

        // Check Request Logs Settings
        $isSave = $this->checkoutSettings->findByKey('request_log_enabled');
        $isSave === 'yes' ? $this->logger->info("CheckoutFormInitialize Request: " . $request->toJsonString()) : null;

        // Payment Initialize Request Response
        $response = PayWithIyzicoInitialize::create($request, $options);

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

    protected function create_options(): Options
    {
        $options = new Options();
        $options->setApiKey($this->checkoutSettings->findByKey('api_key'));
        $options->setSecretKey($this->checkoutSettings->findByKey('secret_key'));
        $options->setBaseUrl($this->checkoutSettings->findByKey('api_type'));

        return $options;
    }

    public function redirect_to_iyzico(string $paymentPageUrl)
    {
        return [
            'result' => 'success',
            'redirect' => $paymentPageUrl
        ];
    }

    public function process_refund($order_id, $amount = null, $reason = '')
    {
        return $this->refundProcessor->refund($order_id, $amount);
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
}
