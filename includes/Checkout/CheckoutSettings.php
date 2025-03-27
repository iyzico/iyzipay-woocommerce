<?php

namespace Iyzico\IyzipayWoocommerce\Checkout;

use Iyzico\IyzipayWoocommerce\Common\Abstracts\Config;

class CheckoutSettings extends Config
{
    public $optionsTableKey = 'woocommerce_iyzico_settings';
    public $form_fields = [];

    public function __construct()
    {
        $webhookUrl = get_site_url() . "/wp-json/iyzico/v1/webhook/" . get_option('iyzicoWebhookUrlKey');

        $this->form_fields = [
            'webhook_url' => [
                'title' => __('Webhook URL', 'woocommerce-iyzico'),
                'type' => 'title',
                'description' => $webhookUrl,
            ],
            'api_type' => [
                'title' => __('Environment', 'woocommerce-iyzico'),
                'type' => 'select',
                'required' => true,
                'default' => 'https://sandbox-api.iyzipay.com',
                'options' => [
                    'https://api.iyzipay.com' => __('Live', 'woocommerce-iyzico'),
                    'https://sandbox-api.iyzipay.com' => __('Sandbox / Test', 'woocommerce-iyzico')
                ],
            ],
            'api_key' => [
                'title' => __('API Key', 'woocommerce-iyzico'),
                'type' => 'text'
            ],
            'secret_key' => [
                'title' => __('Secret Key', 'woocommerce-iyzico'),
                'type' => 'text'
            ],
            'title' => [
                'title' => __('Payment Value', 'woocommerce-iyzico'),
                'type' => 'text',
                'description' => __('This message will show to the user during checkout.', 'woocommerce-iyzico'),
                'default' => __('Pay with Bank/Debit Card', 'woocommerce-iyzico')
            ],
            'description' => [
                'title' => __('Payment Form Description Value', 'woocommerce-iyzico'),
                'type' => 'text',
                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-iyzico'),
                'default' => __('Pay with your credit card or debit card via iyzico.', 'woocommerce-iyzico')
            ],
            'form_class' => [
                'title' => __('Payment Form Design', 'woocommerce-iyzico'),
                'type' => 'select',
                'default' => 'popup',
                'options' => [
                    'responsive' => __('Responsive', 'woocommerce-iyzico'),
                    'popup' => __('Popup', 'woocommerce-iyzico'),
                    'redirect' => __('Redirect', 'woocommerce-iyzico')
                ]
            ],
            'payment_checkout_value' => [
                'title' => __('Payment Checkout Value', 'woocommerce-iyzico'),
                'type' => 'text',
                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-iyzico'),
                'default' => __(
                    'Thank you for your order, please enter your card information in the payment form below to pay with iyzico checkout.',
                    'woocommerce-iyzico'
                ),
                'desc_tip' => true,
            ],
            'order_status' => [
                'title' => __('Order Status', 'woocommerce-iyzico'),
                'type' => 'select',
                'description' => __('Recommended, Default', 'woocommerce-iyzico'),
                'default' => 'default',
                'options' => [
                    'default' => __('Default', 'woocommerce-iyzico'),
                    'pending' => __('Pending', 'woocommerce-iyzico'),
                    'processing' => __('Processing', 'woocommerce-iyzico'),
                    'on-hold' => __('On-Hold', 'woocommerce-iyzico'),
                    'completed' => __('Completed', 'woocommerce-iyzico'),
                    'cancelled' => __('Cancelled', 'woocommerce-iyzico'),
                    'refunded' => __('Refunded', 'woocommerce-iyzico'),
                    'failed' => __('Failed', 'woocommerce-iyzico')
                ]
            ],
            'overlay_script' => [
                'title' => __('Buyer Protection - Logo', 'woocommerce-iyzico'),
                'type' => 'select',
                'required' => false,
                'default' => 'left',
                'options' => [
                    'bottomLeft' => __('Left', 'woocommerce-iyzico'),
                    'bottomRight' => __('Right', 'woocommerce-iyzico'),
                    'hide' => __('Hide', 'woocommerce-iyzico')
                ]
            ],
            'form_language' => [
                'title' => __('Payment Form Language', 'woocommerce-iyzico'),
                'type' => 'select',
                'required' => true,
                'default' => 'TR',
                'options' => [
                    'TR' => __('Turkish', 'woocommerce-iyzico'),
                    'EN' => __('English', 'woocommerce-iyzico')
                ]
            ],
            'affiliate_network' => [
                'title' => __('Affiliate Network', 'woocommerce-iyzico'),
                'type' => 'text',
                'required' => false,
                'description' => __('Payment source for agency', 'woocommerce-iyzico'),
                'default' => '',
                'custom_attributes' => ['maxlength' => 14]
            ],
            'enabled' => [
                'title' => __('Enable/Disable', 'woocommerce-iyzico'),
                'label' => __('Enable iyzico Checkout Form', 'woocommerce-iyzico'),
                'type' => 'checkbox',
                'default' => 'no'
            ],
            'request_log_enabled' => [
                'title' => __('Request Log', 'woocommerce-iyzico'),
                'label' => __('Enable request log', 'woocommerce-iyzico') . " (wp-content/plugins/woocommerce-iyzico/logs_files)",
                'type' => 'checkbox',
                'default' => 'no',
            ]
        ];

        $this->defaultSettings = [];
        foreach ($this->form_fields as $key => $field) {
            $this->defaultSettings[$key] = $field['default'] ?? '';
        }
    }

    public function getFormFields()
    {
        return $this->form_fields;
    }
}