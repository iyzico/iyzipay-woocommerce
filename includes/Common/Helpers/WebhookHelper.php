<?php

namespace Iyzico\IyzipayWoocommerce\Common\Helpers;

use Iyzico\IyzipayWoocommerce\Checkout\CheckoutSettings;
use WP_Error;

class WebhookHelper
{
    private $checkoutSettings;
    private $paymentProcessor;
    private $logger;
    private $tlsVerifier;

    public function __construct()
    {
        $this->logger = new Logger();
        $this->tlsVerifier = new TlsVerifier();
        $this->checkoutSettings = new CheckoutSettings();
        $this->paymentProcessor = new PaymentProcessor();
    }

    public function addRoute(): void
    {
        $webhookID = get_option('iyzicoWebhookUrlKey');

        if (!$webhookID) {
            $webhookID = substr(base64_encode(time() . wp_rand()), 15, 6);
            update_option('iyzicoWebhookUrlKey', $webhookID);
        }

        register_rest_route('iyzico/v1', "/webhook/{$webhookID}", [
            'methods' => 'POST',
            'callback' => [$this, 'processWebhook'],
            'permission_callback' => '__return_true',
        ]);
    }

    private function handleSuccessfulPayment($data, $isValidateSignature = false)
    {
        if ($isValidateSignature) {
            $this->logger->webhook("USE X-IYZ-SIGNATURE-V3: " . wp_json_encode($data, JSON_PRETTY_PRINT));
            return $this->paymentProcessor->processWebhookWithSignature($data);
        }

        $this->logger->webhook("NOT USE X-IYZ-SIGNATURE-V3: " . wp_json_encode($data, JSON_PRETTY_PRINT));

        return $this->paymentProcessor->processWebhook($data);
    }

    public function processWebhook($request)
    {
        $headers = getallheaders();
        $possibleKeys = [
            'X-IYZ-SIGNATURE-V3',
            'X-Iyz-Signature-V3',
            'x-iyz-signature-v3',
            'x_iyz_signature_v3',
        ];

        $iyzicoSignature = null;
        $key = null;

        foreach ($possibleKeys as $possibleKey) {
            if (isset($headers[$possibleKey])) {
                $iyzicoSignature = $headers[$possibleKey];
                $key = $possibleKey;
                break;
            }
        }

        if ($key !== null) {
            switch ($key) {
                case 'X-IYZ-SIGNATURE-V3':
                case 'X-Iyz-Signature-V3':
                case 'x-iyz-signature-v3':
                case 'x_iyz_signature_v3':
                    $this->processWebhookV3($request, $iyzicoSignature);
                    break;
                default:
                    $this->processWebhookDefault($request);
                    break;
            }
        } else {
            $this->processWebhookDefault($request);
        }
    }

    public function processWebhookV3($request, $iyzicoSignature)
    {
        $params = wp_parse_args($request->get_json_params());
        $secretKey = $this->checkoutSettings->findByKey('secret_key');

        $requiredParams = ['iyziEventType', 'iyziPaymentId', 'token', 'paymentConversationId', 'status'];
        foreach ($requiredParams as $param) {
            if (empty($params[$param])) {
                $this->logger->webhook("Error, missing param: $param");

                return new WP_Error('missing_param', "Error, missing param: $param", array('status' => 400));
            }
        }

        $iyziEventType = sanitize_text_field($params['iyziEventType']);
        $iyziPaymentId = sanitize_text_field($params['iyziPaymentId']);
        $token = sanitize_text_field($params['token']);
        $paymentConversationId = sanitize_text_field($params['paymentConversationId']);
        $status = sanitize_text_field($params['status']);
        $key = $secretKey . $iyziEventType . $iyziPaymentId . $token . $paymentConversationId . $status;
        $hmac256Signature = bin2hex(hash_hmac('sha256', $key, $secretKey, true));

        if ($iyzicoSignature === $hmac256Signature) {
            $data = [
                'token' => $token,
                'iyziEventType' => $iyziEventType,
                'paymentConversationId' => $paymentConversationId,
                'status' => $status,
            ];

            return $this->handleSuccessfulPayment($data, true);
        } else {
            $this->logger->webhook('X-IYZ-SIGNATURE-V3 invalid signature.');

            return new WP_Error('signature_not_valid', 'Error, invalid signature value.', array('status' => 404));
        }
    }

    public function processWebhookDefault($request)
    {
        $params = wp_parse_args($request->get_json_params());
        $requiredParams = ['iyziEventType', 'token', 'paymentConversationId'];
        foreach ($requiredParams as $param) {
            if (empty($params[$param])) {
                $this->logger->webhook("Error, missing param: $param");

                return new WP_Error('missing_param', "Error, missing param: $param", array('status' => 400));
            }
        }

        $data = [
            'iyziEventType' => sanitize_text_field($params['iyziEventType']),
            'token' => sanitize_text_field($params['token']),
            'paymentConversationId' => sanitize_text_field($params['paymentConversationId']),
        ];

        return $this->handleSuccessfulPayment($data);
    }
}