<?php

namespace Iyzico\IyzipayWoocommerce\Common\Helpers;

use Exception;
use Iyzico\IyzipayWoocommerce\Checkout\CheckoutSettings;
use Iyzico\IyzipayWoocommerce\Database\DatabaseManager;
use Iyzipay\Model\CheckoutForm as CheckoutFormModel;
use Iyzipay\Model\Mapper\CheckoutFormMapper;
use Iyzipay\Options;
use Iyzipay\Request\RetrieveCheckoutFormRequest;
use WC_Order;
use WC_Order_Item_Fee;

class PaymentProcessor
{

    protected $logger;
    protected $checkoutSettings;
    protected $databaseManager;
    protected $signatureChecker;


    public function __construct()
    {
        $this->logger = new Logger();
        $this->checkoutSettings = new CheckoutSettings();
        $this->databaseManager = new DatabaseManager();
        $this->signatureChecker = new SignatureChecker();
    }

    public function processCallback(): void
    {
        try {
            $iyziOrder = $this->getIyziOrder();

            if (!is_array($iyziOrder)) {
                throw new Exception(__("Order not found.", "woocommerce-iyzico"));
            }

            $token = $iyziOrder['token'];
            $conversationId = $iyziOrder['conversation_id'];
            $orderId = $iyziOrder['order_id'];

            $checkoutFormResult = $this->retrieveCheckoutForm($token, $conversationId);
            $order = $this->getOrder($orderId);

            $paymentStatus = $checkoutFormResult->getPaymentStatus();

            if ($paymentStatus === "FAILURE") {
                $errorMessage = is_null($checkoutFormResult->getErrorMessage()) ? "Payment failed." : $checkoutFormResult->getErrorMessage();
                $order->update_status("failed", $errorMessage);
                $this->redirectToPaymentPage($errorMessage);
            }

            $checkoutFormResult = CheckoutFormMapper::create($checkoutFormResult)->mapCheckoutForm($checkoutFormResult);

            if (!is_null($checkoutFormResult)) {
                $this->updateIyziOrder($checkoutFormResult, $order);
                $this->addOrderComment($checkoutFormResult, $order);
                $this->saveUserCard($checkoutFormResult);
                $this->checkInstallment($checkoutFormResult, $order);
                $this->saveCardType($checkoutFormResult, $order);
                $this->saveCardAssociation($checkoutFormResult, $order);
                $this->saveCardFamily($checkoutFormResult, $order);
                $this->saveLastFourDigits($checkoutFormResult, $order);
                $this->updateOrder($checkoutFormResult, $order);
                $this->redirectToOrderReceived($checkoutFormResult, $order);
            }
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * @throws Exception
     */
    private function getIyziOrder($token = null)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!empty($_POST['token'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $token = sanitize_text_field(wp_unslash($_POST['token']));
        }

        if (empty($token)) {
            throw new Exception(esc_html__(
                "Payment token is missing. Please try again or contact the store owner if the problem persists.",
                "woocommerce-iyzico"
            ));
        }

        return $this->databaseManager->findOrderByToken($token);
    }

    private function retrieveCheckoutForm($token, $conversationId)
    {
        $options = $this->createOptions();
        $request = new RetrieveCheckoutFormRequest();

        $request->setToken($token);
        $request->setConversationId($conversationId);

        return CheckoutFormModel::retrieve($request, $options);
    }

    protected function createOptions(): Options
    {
        $options = new Options();
        $options->setApiKey($this->checkoutSettings->findByKey('api_key'));
        $options->setSecretKey($this->checkoutSettings->findByKey('secret_key'));
        $options->setBaseUrl($this->checkoutSettings->findByKey('api_type'));

        return $options;
    }

    private function getOrder($orderId)
    {
        $order = wc_get_order($orderId);

        if (!$order) {
            throw new Exception(esc_html__("Order not found.", "woocommerce-iyzico"));
        }

        return $order;
    }

    private function redirectToPaymentPage(string $errorMessage): void
    {
        global $woocommerce;

        if ($woocommerce !== null) {
            $woocommerce->session->set('iyzico_error', $errorMessage);
        }

        $checkoutUrl = wc_get_checkout_url();
        $redirectUrl = add_query_arg([
            'payment' => 'failed',
            'msg' => urlencode($errorMessage)
        ], $checkoutUrl);

        wp_redirect($redirectUrl);
        exit;
    }

    private function updateIyziOrder($checkoutFormResult, $order)
    {
        $orderId = $order->get_id();
        $status = $checkoutFormResult->getStatus();
        $paymentStatus = $checkoutFormResult->getPaymentStatus();
        $paymentId = $checkoutFormResult->getPaymentId();
        $paidPrice = $checkoutFormResult->getPaidPrice();

        $this->databaseManager->updateStatusByOrderId($orderId, $status);
        $this->databaseManager->updatePaymentStatusByOrderId($orderId, $paymentStatus);
        $this->databaseManager->updatePaymentIdByOrderId($orderId, $paymentId);
        $this->databaseManager->updateTotalAmountByOrderId($orderId, $paidPrice);
    }

    private function addOrderComment($checkoutFormResult, $order)
    {
        if ($checkoutFormResult->getStatus() !== "success" || $checkoutFormResult->getPaymentStatus() === "FAILURE") {
            return;
        }

        $message = "Payment ID: " . $checkoutFormResult->getPaymentId() . " Conversation ID: " . $checkoutFormResult->getConversationId();
        $order->add_order_note($message, 0, true);

        if ($this->checkoutSettings->findByKey('api_type') === "https://sandbox-api.iyzipay.com") {
            $message = '<strong><p style="color:red">TEST ÖDEMESİ</a></strong>';
            $order->add_order_note($message, 0, true);
        }
    }

    private function saveUserCard($checkoutFormResult)
    {
        if (isset($checkoutFormResult->cardUserKey)) {
            $customer = wp_get_current_user();

            if ($customer->ID) {
                $cardUserKey = $this->databaseManager->findUserCardKey(
                    $customer->ID,
                    $this->checkoutSettings->findByKey('api_key')
                );

                if ($checkoutFormResult->cardUserKey != $cardUserKey) {
                    $this->databaseManager->saveUserCardKey(
                        $customer->ID,
                        $checkoutFormResult->cardUserKey,
                        $this->checkoutSettings->findByKey('api_key')
                    );
                }
            }
        }
    }

    private function checkInstallment($response, $order)
    {
        if (isset($response) && !empty($response->getInstallment()) && $response->getInstallment() > 1) {
            $orderData = $order->get_data();
            $orderTotal = $orderData['total'];

            $installmentFee = $response->getPaidPrice() - $orderTotal;
            $itemFee = new WC_Order_Item_Fee();
            $itemFee->set_name($response->getInstallment() . " " . __(
                "Installment Commission",
                'woocommerce-iyzico'
            ));
            $itemFee->set_amount($installmentFee);
            $itemFee->set_tax_class('');
            $itemFee->set_tax_status('none');
            $itemFee->set_total($installmentFee);

            $order->add_item($itemFee);
            $order->calculate_totals(true);

            $order->update_meta_data('iyzico_no_of_installment', $response->getInstallment());
            $order->update_meta_data('iyzico_installment_fee', $installmentFee);
        }
    }

    private function saveCardType($response, $order)
    {
        if (isset($response) && !empty($response->getCardType())) {
            $order->update_meta_data('iyzico_card_type', $response->getCardType());
        }
    }

    private function saveCardAssociation($response, $order)
    {
        if (isset($response) && !empty($response->getCardAssociation())) {
            $order->update_meta_data('iyzico_card_association', $response->getCardAssociation());
        }
    }

    private function saveCardFamily($response, $order)
    {
        if (isset($response) && !empty($response->getCardFamily())) {
            $order->update_meta_data('iyzico_card_family', $response->getCardFamily());
        }
    }

    private function saveLastFourDigits($response, $order)
    {
        if (isset($response) && !empty($response->getBinNumber())) {
            $order->update_meta_data('iyzico_last_four_digits', $response->getLastFourDigits());
        }
    }

    private function updateOrder($checkoutFormResult, WC_Order $order)
    {
        $paymentStatus = strtoupper($checkoutFormResult->getPaymentStatus());
        $status = strtoupper($checkoutFormResult->getStatus());

        if ($paymentStatus === 'SUCCESS' && $status === 'SUCCESS') {
            $order->payment_complete();
            $order->save();

            $orderStatus = $this->checkoutSettings->findByKey('order_status');

            if ($orderStatus !== 'default' && !empty($orderStatus)) {
                $order->update_status($orderStatus);
            }
        }

        if ($paymentStatus === "INIT_BANK_TRANSFER" && $status === "SUCCESS") {
            $order->update_status("on-hold");
            $orderMessage = __('iyzico Bank transfer/EFT payment is pending.', 'woocommerce-iyzico');
            $order->add_order_note($orderMessage, 0, true);
        }

        if ($paymentStatus === "PENDING_CREDIT" && $status === "SUCCESS") {
            $order->update_status("on-hold");
            $orderMessage = __('The shopping credit transaction has been initiated.', 'woocommerce-iyzico');
            $order->add_order_note($orderMessage, 0, true);
        }

        if ($paymentStatus === "FAILURE") {
            $order->update_status("failed");
        }
    }

    private function redirectToOrderReceived($checkoutFormResult, WC_Order $order)
    {
        $paymentStatus = strtoupper($checkoutFormResult->getPaymentStatus());
        $status = strtoupper($checkoutFormResult->getStatus());

        if ($status === "SUCCESS" && $paymentStatus !== "FAILURE") {
            $checkoutOrderUrl = $order->get_checkout_order_received_url();
            $redirectUrl = add_query_arg([
                'msg' => 'Thank You',
                'type' => 'woocommerce-message'
            ], $checkoutOrderUrl);

            wp_redirect($redirectUrl);
            exit;
        }
    }

    private function handleException(Exception $e): void
    {
        $this->logger->error('PaymentProcessor.php: ' . $e->getMessage());
        if (WC()->session !== null) {
            WC()->session->set('iyzico_error', $e->getMessage());
            wc_add_notice($e->getMessage(), 'error');
        }
        wp_redirect(wc_get_checkout_url() . '?payment=failed');
        exit;
    }

    public function processWebhook($response)
    {
        try {

            $token = $response['token'];
            $iyziOrder = $this->databaseManager->findOrderByToken($token);
            $orderId = $iyziOrder['order_id'];
            $conversationId = $iyziOrder['conversation_id'];
            $checkoutFormResult = $this->retrieveCheckoutForm($token, $conversationId);
            $paymentStatus = strtoupper($checkoutFormResult->getPaymentStatus());
            $status = strtoupper($checkoutFormResult->getStatus());
            $iyziEventType = strtoupper($response['iyziEventType']);

            $this->databaseManager->updateStatusByOrderId($orderId, $status);
            $this->databaseManager->updatePaymentStatusByOrderId($orderId, $paymentStatus);

            $order = $this->getOrder($orderId);

            if ($iyziEventType === 'CHECKOUT_FORM_AUTH' && $paymentStatus === 'SUCCESS' && $status === 'SUCCESS') {
                $orderMessage = __("This payment was confirmed via webhook.", "woocommerce-iyzico");
                $order->add_order_note($orderMessage, 0, true);
                $order->update_status("processing");
                $order->payment_complete();
                $order->save();

                return http_response_code(200);
            }

            if ($iyziEventType === 'CREDIT_PAYMENT_INIT' && $paymentStatus === 'INIT_CREDIT' && $status === 'SUCCESS') {
                $orderMessage = __("The shopping credit transaction has been initiated.", "woocommerce-iyzico");
                $order->add_order_note($orderMessage, 0, true);
                $order->update_status("on-hold");
                $order->save();

                return http_response_code(200);
            }

            if ($iyziEventType === 'CREDIT_PAYMENT_PENDING' && $paymentStatus === 'PENDING_CREDIT' && $status === 'SUCCESS') {
                $orderMessage = __("Currently in the process of applying for a shopping loan.", "woocommerce-iyzico");
                $order->add_order_note($orderMessage, 0, true);
                $order->update_status("on-hold");
                $order->save();

                return http_response_code(200);
            }

            if ($iyziEventType === 'CREDIT_PAYMENT_AUTH' && $paymentStatus === 'SUCCESS' && $status === 'SUCCESS') {
                $orderMessage = __("The shopping loan transaction was completed successfully.", "woocommerce-iyzico");
                $order->add_order_note($orderMessage, 0, true);
                $order->update_status("processing");
                $order->payment_complete();
                $order->save();

                return http_response_code(200);
            }

            if ($iyziEventType === 'BANK_TRANSFER_AUTH' && $paymentStatus === 'SUCCESS' && $status === 'SUCCESS') {
                $orderMessage = __("The bank transfer transaction was completed successfully.", "woocommerce-iyzico");
                $order->add_order_note($orderMessage, 0, true);
                $order->update_status("processing");
                $order->payment_complete();
                $order->save();

                return http_response_code(200);
            }

            if ($iyziEventType === 'BALANCE' && $paymentStatus === 'SUCCESS' && $status === 'SUCCESS') {
                $orderMessage = __("This payment was confirmed via webhook.", "woocommerce-iyzico");
                $order->add_order_note($orderMessage, 0, true);
                $order->update_status("processing");
                $order->payment_complete();
                $order->save();

                return http_response_code(200);
            }

            if ($iyziEventType === 'BKM_AUTH' && $paymentStatus === 'SUCCESS' && $status === 'SUCCESS') {
                $orderMessage = __("The BKM Express transaction was completed successfully.", "woocommerce-iyzico");
                $order->add_order_note($orderMessage, 0, true);
                $order->update_status("processing");
                $order->payment_complete();
                $order->save();

                return http_response_code(200);
            }
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    public function processWebhookWithSignature($response)
    {
        try {
            $token = $response['token'];
            $iyziOrder = $this->databaseManager->findOrderByToken($token);
            $order = $this->getOrder($iyziOrder['order_id']);
            $iyziEventType = strtoupper($response['iyziEventType']);
            $status = strtoupper($response['status']);

            if ($iyziEventType === 'CHECKOUT_FORM_AUTH' && $status === 'SUCCESS') {
                $orderMessage = __("This payment was confirmed via webhook.", "woocommerce-iyzico");
                $order->add_order_note($orderMessage, 0, true);
                $order->update_status("processing");
                $order->payment_complete();
                $order->save();

                return http_response_code(200);
            }

            if ($iyziEventType === 'CREDIT_PAYMENT_INIT' && $status == 'INIT_CREDIT') {
                $orderMessage = __("The shopping credit transaction has been initiated.", "woocommerce-iyzico");
                $order->add_order_note($orderMessage, 0, true);
                $order->update_status("on-hold");
                $order->save();

                return http_response_code(200);
            }

            if ($iyziEventType === 'CREDIT_PAYMENT_PENDING' && $status === 'PENDING_CREDIT') {
                $orderMessage = __("Currently in the process of applying for a shopping loan.", "woocommerce-iyzico");
                $order->add_order_note($orderMessage, 0, true);
                $order->update_status("on-hold");
                $order->save();

                return http_response_code(200);
            }

            if ($iyziEventType === 'CREDIT_PAYMENT_AUTH' && $status === 'SUCCESS') {
                $orderMessage = __("The shopping loan transaction was completed successfully.", "woocommerce-iyzico");
                $order->add_order_note($orderMessage, 0, true);
                $order->update_status("processing");
                $order->payment_complete();
                $order->save();

                return http_response_code(200);
            }

            if ($iyziEventType === 'BANK_TRANSFER_AUTH' && $status == 'SUCCESS') {
                $orderMessage = __("The bank transfer transaction was completed successfully.", "woocommerce-iyzico");
                $order->add_order_note($orderMessage, 0, true);
                $order->update_status("processing");
                $order->payment_complete();
                $order->save();

                return http_response_code(200);
            }

            if ($iyziEventType === 'BALANCE' && $status === 'SUCCESS') {
                $orderMessage = __(
                    "The balance payment transaction was completed successfully.",
                    "woocommerce-iyzico"
                );
                $order->add_order_note($orderMessage, 0, true);
                $order->update_status("processing");
                $order->payment_complete();
                $order->save();

                return http_response_code(200);
            }

            if ($iyziEventType === 'BKM_AUTH' && $status === 'SUCCESS') {
                $orderMessage = __("The BKM Express transaction was completed successfully.", "woocommerce-iyzico");
                $order->add_order_note($orderMessage, 0, true);
                $order->update_status("processing");
                $order->save();

                return http_response_code(200);
            }
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }
}
