<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Iyzico_Checkout_For_WooCommerce_Gateway extends WC_Payment_Gateway {

    public function __construct() {

        $this->id = 'iyzico';
        $this->iyziV = '3.1.3';
        $this->method_title = __('iyzico Checkout', 'woocommerce-iyzico');
        $this->method_description = __('Best Payment Solution', 'woocommerce-iyzico');
        $this->has_fields = true;
        $this->order_button_text = __('Pay With Card', 'woocommerce-iyzico');
        $this->supports = array('products');

        $this->init_form_fields();
        $this->init_settings();

        $this->title        = __($this->get_option( 'title' ),'woocommerce-iyzico');
        $this->description  = __($this->get_option( 'description'),'woocommerce-iyzico');
        $this->enabled      = $this->get_option( 'enabled' );
        $this->affiliate    = $this->get_option( 'affiliate_network' );
        $this->icon         = plugins_url().IYZICO_PLUGIN_NAME.'/image/cards.png?v=3';

        add_action('init', array(&$this, 'iyzico_response'));
        add_action('woocommerce_api_wc_gateway_iyzico', array($this, 'iyzico_response'));

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
            $this,
            'process_admin_options',
        ) );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
            $this,
            'admin_overlay_script',
        ) );

        add_action('woocommerce_receipt_iyzico', array($this, 'iyzico_loading_bar'));
        add_action('woocommerce_receipt_iyzico', array($this, 'iyzico_payment_form'));



        if(isset($_GET['section']) && $_GET['section'] == 'iyzico') {

            $this->valid_js();
        }

    }

    public function getApiKey(){
        return $this->settings['api_key'];
    }

    public function admin_overlay_script() {

        $helper                     = new Iyzico_Checkout_For_WooCommerce_Helper();
        $pkiBuilder                 = new Iyzico_Checkout_For_WooCommerce_PkiBuilder();
        $iyzicoRequest              = new Iyzico_Checkout_For_WooCommerce_iyzicoRequest();

        $apiKey                     = $this->settings['api_key'];
        $secretKey                  = $this->settings['secret_key'];
        $baseUrl                    = $this->settings['api_type'];
        $randNumer                  = rand(100000,99999999);

        $overlayObject = new stdClass();
        $overlayObject->locale          = $helper->cutLocale(get_locale());
        $overlayObject->conversationId  = $randNumer;
        $overlayObject->position        = $this->settings['overlay_script'];

        $pkiString                = $pkiBuilder->pkiStringGenerate($overlayObject);
        $authorizationData        = $pkiBuilder->authorizationGenerate($pkiString,$apiKey,$secretKey,$randNumer);


        $iyzicoJson               = json_encode($overlayObject,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

        $requestResponse          = $iyzicoRequest->iyzicoOverlayScriptRequest($baseUrl,$iyzicoJson,$authorizationData);


        $iyzicoOverlayToken    = get_option('iyzico_overlay_token');
        $iyzicoOverlayPosition = get_option('iyzico_overlay_position');


        if(isset($requestResponse->protectedShopId)) {

            $requestResponse->protectedShopId = esc_js($requestResponse->protectedShopId);

            if(empty($iyzicoOverlayToken) && empty($iyzicoOverlayPosition)) {

                add_option('iyzico_overlay_token',$requestResponse->protectedShopId,'','no');
                add_option('iyzico_overlay_position',$this->settings['overlay_script'],'','no');

            } else {

                update_option('iyzico_overlay_token',$requestResponse->protectedShopId);
                update_option('iyzico_overlay_position',$this->settings['overlay_script']);
            }

        } else {

            if(empty($iyzicoOverlayPosition)) {

                add_option('iyzico_overlay_position',$this->settings['overlay_script'],'','no');

            } else {

                update_option('iyzico_overlay_position',$this->settings['overlay_script']);

            }

        }
        return true;

    }



    public function admin_options() {
        ob_start();
        parent::admin_options();
        $parent_options = ob_get_contents();
        ob_end_clean();

        echo $parent_options;
        $pluginUrl = plugins_url().IYZICO_PLUGIN_NAME;

        $html = '<style scoped>@media (max-width:768px){.iyziBrand{position:fixed;bottom:0;top:auto!important;right:0!important}}</style><div class="iyziBrandWrap"><div class="iyziBrand" style="clear:both;position:absolute;right: 50px;top:440px;display: flex;flex-direction: column;justify-content: center;"><img src='.$pluginUrl.'/image/iyzico_logo.png style="width: 250px;margin-left: auto;"><p style="text-align:center;"><strong>V: </strong>'.$this->iyziV.'</p></div></div>';

        echo $html;
    }

    public function valid_js() {

        wp_enqueue_script('script', plugins_url().IYZICO_PLUGIN_NAME.'/media/js/valid_api.js',true,'1.0','all');

    }
    public function init_form_fields() {

        $this->form_fields = Iyzico_Checkout_For_WooCommerce_Fields::iyzicoAdminFields();

    }

    public function process_payment($order_id) {

        $order = wc_get_order($order_id);

        return array(
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );

    }

    public function iyzico_loading_bar() {

        echo '<style>.loading{width:40px;height:40px;background-color:#1E64FF;margin:auto;-webkit-animation:sk-rotateplane 1.2s infinite ease-in-out;animation:sk-rotateplane 1.2s infinite ease-in-out}@-webkit-keyframes sk-rotateplane{0%{-webkit-transform:perspective(120px)}50%{-webkit-transform:perspective(120px) rotateY(180deg)}100%{-webkit-transform:perspective(120px) rotateY(180deg) rotateX(180deg)}}@keyframes sk-rotateplane{0%{transform:perspective(120px) rotateX(0) rotateY(0);-webkit-transform:perspective(120px) rotateX(0) rotateY(0)}50%{transform:perspective(120px) rotateX(-180.1deg) rotateY(0);-webkit-transform:perspective(120px) rotateX(-180.1deg) rotateY(0)}100%{transform:perspective(120px) rotateX(-180deg) rotateY(-179.9deg);-webkit-transform:perspective(120px) rotateX(-180deg) rotateY(-179.9deg)}}</style>';

        echo '<div id="loadingBar">
                <div class="loading">
                </div>
                <div class="brand">
                    <p style="text-align:center;color:#1E64FF;">iyzico</p>
                </div>
             </div>';

    }

    private function setcookieSameSite($name, $value, $expire, $path, $domain, $secure, $httponly) {

        if (PHP_VERSION_ID < 70300) {

            setcookie($name, $value, $expire, "$path; samesite=None", $domain, $secure, $httponly);
        }
        else {
            setcookie($name, $value, [
                'expires' => $expire,
                'path' => $path,
                'domain' => $domain,
                'samesite' => 'None',
                'secure' => $secure,
                'httponly' => $httponly,
            ]);


        }
    }


    public function iyzico_payment_form($order_id,$payMethod = null) {

        $wooCommerceCookieKey = 'wp_woocommerce_session_';
        foreach ($_COOKIE as $name => $value) {
            if (stripos($name,$wooCommerceCookieKey) === 0) {
                $wooCommerceCookieKey = $name;
            }
        }

        $setCookie = $this->setcookieSameSite($wooCommerceCookieKey,$_COOKIE[$wooCommerceCookieKey], time() + 86400, "/", $_SERVER['SERVER_NAME'],true, true);

        $this->versionCheck();

        global $woocommerce;

        $getOrder                  = new WC_Order($order_id);
        $customerCart              = $woocommerce->cart->get_cart();
        $apiKey                    = $this->settings['api_key'];
        $secretKey                 = $this->settings['secret_key'];
        $baseUrl                   = $this->settings['api_type'];
        $language                  = $this->settings['form_language'];
        $rand                      = rand(1,99999);
        $user                      = wp_get_current_user();
        $iyzicoConversationId      = WC()->session->set('iyzicoConversationId',$order_id);
        $iyzicoCustomerId          = WC()->session->set('iyzicoCustomerId',$user->ID);
        $totalAmount               = WC()->session->set('iyzicoOrderTotalAmount',$getOrder->get_total());



        $formObjectGenerate        = new Iyzico_Checkout_For_WooCommerce_FormObjectGenerate();
        $pkiBuilder                = new Iyzico_Checkout_For_WooCommerce_PkiBuilder();
        $iyzicoRequest             = new Iyzico_Checkout_For_WooCommerce_iyzicoRequest();

        if($payMethod != "pwi") {
            $iyzico                   = $formObjectGenerate->generateOption($customerCart,$language,$getOrder,$apiKey,$this->affiliate);
        } else {
            $iyzico                   = $formObjectGenerate->generateOptionPwi($customerCart,$language,$getOrder,$apiKey,$this->affiliate);
        }

        $iyzico->buyer            = $formObjectGenerate->generateBuyer($getOrder);
        $iyzico->shippingAddress  = $formObjectGenerate->generateShippingAddress($getOrder);
        $iyzico->billingAddress   = $formObjectGenerate->generateBillingAddress($getOrder);
        $iyzico->basketItems      = $formObjectGenerate->generateBasketItems($customerCart,$getOrder);

        if($payMethod != "pwi") {
            $orderObject              = $pkiBuilder->createFormObjectSort($iyzico);
        } else {
            $orderObject              = $pkiBuilder->createFormObjectSortPwi($iyzico);
        }

        $pkiString                = $pkiBuilder->pkiStringGenerate($orderObject);

        $authorizationData        = $pkiBuilder->authorizationGenerate($pkiString,$apiKey,$secretKey,$rand);

        $iyzicoJson               = json_encode($iyzico,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);




        if($payMethod != "pwi") {
            $requestResponse          = $iyzicoRequest->iyzicoCheckoutFormRequest($baseUrl,$iyzicoJson,$authorizationData);
        } else {

            $requestResponse          = $iyzicoRequest->iyzicoPwiRequest($baseUrl,$iyzicoJson,$authorizationData);

            return $requestResponse->payWithIyzicoPageUrl;
        }
        $className                = $this->get_option('form_class');

        if(empty($language))
        {
          if ($className == 'popup'){
              $message                  = '<p id="infoBox" style="display:none;">' . __("Thank you for your order, please click the button below to pay with iyzico checkout.", 'woocommerce-iyzico') . '</p>';
          }
          else{
              $message                  = '<p id="infoBox" style="display:none;">' . __('Thank you for your order, please enter your card information in the payment form below to pay with iyzico checkout.', 'woocommerce-iyzico') . '</p>';
          }
        }elseif ($language == 'TR' or $language == 'tr') {

          if ($className == 'popup'){
              $message                  = '<p id="infoBox" style="display:none;">' . 'Siparişiniz için teşekkür ederiz, iyzico ile ödemek için Ödeme Formunu Aç butonuna tıklayarak kart bilgilerinizi girebilirsiniz.'.'</p>';
          }
          else{
              $message                  = '<p id="infoBox" style="display:none;">' . 'Siparişiniz için teşekkür ederiz, iyzico ile ödemek için aşağıdaki ödeme formu alanına kart bilgilerinizi girebilirsiniz.' . '</p>';
          }

        }else {
          if ($className == 'popup'){
              $message                  = '<p id="infoBox" style="display:none;">' . 'Thank you for your order, please click the button below to pay with iyzico checkout.'.'</p>';
          }
          else{
              $message                  = '<p id="infoBox" style="display:none;">' . 'Thank you for your order, please enter your card information in the payment form below to pay with iyzico checkout.' . '</p>';
          }

        }



        echo '<script>jQuery(window).on("load", function(){document.getElementById("loadingBar").style.display="none",document.getElementById("infoBox").style.display="block",document.getElementById("iyzipay-checkout-form").style.display="block"});</script>';

        if(isset($requestResponse->status)) {
            if($requestResponse->status == 'success') {
                echo $message;
                echo ' <div style="display:none" id="iyzipay-checkout-form" class='.$className.'>' . $requestResponse->checkoutFormContent . '</div>';
                echo '<p style="display:none;" id="iyziVersion">'.$this->iyziV.'</p>';
            } else {
                echo $requestResponse->errorMessage;
            }

        } else {
            echo 'Not Connection...';
        }

    }

    public function iyzico_response($webhook = null, $webhookPaymentConversationId = null, $webhookToken = null) {

        global $woocommerce;


        try {

            $token           = $_POST['token'];
            $apiKey          = $this->settings['api_key'];
            $secretKey       = $this->settings['secret_key'];
            $baseUrl         = $this->settings['api_type'];
            $rand            = rand(1,99999);
            $conversationId  = "";

            if ($webhook == 'webhook'){
                $token = $webhookToken;
                $conversationId = $webhookPaymentConversationId;
            }


            $iyziModel       = new Iyzico_Checkout_For_WooCommerce_Model();

            $responseObjectGenerate    = new Iyzico_Checkout_For_WooCommerce_ResponseObjectGenerate();
            $pkiBuilder                = new Iyzico_Checkout_For_WooCommerce_PkiBuilder();
            $iyzicoRequest             = new Iyzico_Checkout_For_WooCommerce_iyzicoRequest();

            $tokenDetailObject         = $responseObjectGenerate->generateTokenDetailObject($conversationId,$token);
            $pkiString                 = $pkiBuilder->pkiStringGenerate($tokenDetailObject);
            $authorizationData         = $pkiBuilder->authorizationGenerate($pkiString,$apiKey,$secretKey,$rand);
            $tokenDetailObject         = json_encode($tokenDetailObject,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            $requestResponse           = $iyzicoRequest->iyzicoCheckoutFormDetailRequest($baseUrl,$tokenDetailObject,$authorizationData);

            $order = new WC_Order($requestResponse->basketId);

            if ($webhook == 'webhook' && $requestResponse->status == 'failure'){
                return new WP_Error( $requestResponse->errorCode, $requestResponse->errorMessage, array( 'status' => 404 ) );
            }

            if ($webhook == 'webhook' && $order->get_status() == 'processing'){
                return http_response_code(200);
            }

            if(!$order->get_payment_method_title()) {
                $order->set_payment_method_title("iyzico ile Öde");
            }

            if($requestResponse->paymentStatus == 'INIT_BANK_TRANSFER' && $requestResponse->status == 'success') {

                $order->update_status("on-hold");

                $orderMessage = 'iyzico Banka havalesi/EFT ödemesi bekleniyor.';
                $order->add_order_note($orderMessage,0,true);

                $woocommerce->cart->empty_cart();
                $checkoutOrderUrl = $order->get_checkout_order_received_url();
                $redirectUrl = add_query_arg(array('msg' => 'Thank You', 'type' => 'woocommerce-message'), $checkoutOrderUrl);
                return wp_redirect($redirectUrl);
            }

            $customerId      = $order->get_customer_id();
            $orderId         = $requestResponse->basketId;


            $iyzicoLocalOrder = new stdClass;
            $iyzicoLocalOrder->paymentId     = !empty($requestResponse->paymentId) ? (int) $requestResponse->paymentId : '';
            $iyzicoLocalOrder->orderId       = $orderId;
            $iyzicoLocalOrder->totalAmount   = !empty($requestResponse->paidPrice) ? (float) $requestResponse->paidPrice : '';
            $iyzicoLocalOrder->status        = $requestResponse->paymentStatus;

            $iyzico_order_insert  = $iyziModel->insertIyzicoOrder($iyzicoLocalOrder);

            if($requestResponse->paymentStatus != 'SUCCESS' || $requestResponse->status != 'success' || $orderId != $requestResponse->basketId ) {

                if($requestResponse->status == 'success' && $requestResponse->paymentStatus == 'FAILURE') {

                    throw new Exception.__("3D security has not been validated for your card.", 'woocommerce-iyzico');

                }
                /* Redirect Error */
                $errorMessage = isset($requestResponse->errorMessage) ? $requestResponse->errorMessage : 'Failed';
                throw new \Exception($errorMessage);
            }


            /* Save Card */
            if(isset($requestResponse->cardUserKey)) {

                if($customerId) {

                    $cardUserKey = $iyziModel->findUserCardKey($customerId,$apiKey);

                    if($requestResponse->cardUserKey != $cardUserKey) {

                        $insertCardUserKey = $iyziModel->insertCardUserKey($customerId,$requestResponse->cardUserKey,$apiKey);

                    }

                }

            }

            $orderMessage = 'Payment ID: '.$requestResponse->paymentId;

            $order->add_order_note($orderMessage,0,true);

            if($baseUrl == 'https://sandbox-api.iyzipay.com') {

                $orderMessage = '<strong><p style="color:red">TEST ÖDEMESİ</a></strong>';
                $order->add_order_note($orderMessage,0,true);
            }

            if (isset($requestResponse->installment) && !empty($requestResponse->installment) && $requestResponse->installment > 1) {

                $orderData = $order->get_data();
                $orderTotal = $orderData['total'];

                $installment_fee    = $requestResponse->paidPrice - $orderTotal;

                $item_fee = new WC_Order_Item_Fee();
                $item_fee->set_name($requestResponse->installment . " " .__("Installment Commission", 'woocommerce-iyzico'));
                $item_fee->set_amount($installment_fee);
                $item_fee->set_tax_class( '' );
                $item_fee->set_tax_status( 'none' );
                $item_fee->set_total($installment_fee);

                $order->add_item( $item_fee );
                $order->calculate_totals(true);

                update_post_meta($order_id, 'iyzico_no_of_installment',$requestResponse->installment);
                update_post_meta($order_id, 'iyzico_installment_fee', $installment_fee);
            }


            $order->payment_complete();

            /* Order Status */
            $orderStatus = $this->settings['order_status'];

            if($orderStatus != 'default' && !empty($orderStatus)) {
                $order->update_status($orderStatus);
            }

            if ($webhook == 'webhook'){
                return http_response_code(200);
            }

            $woocommerce->cart->empty_cart();
            $checkoutOrderUrl = $order->get_checkout_order_received_url();

            $redirectUrl = add_query_arg(array('msg' => 'Thank You', 'type' => 'woocommerce-message'), $checkoutOrderUrl);

            return wp_redirect($redirectUrl);

        } catch (Exception $e) {

            if ($webhook == 'webhook'){
                return new WP_Error( $requestResponse->errorCode, $requestResponse->errorMessage, array( 'status' => 404 ) );
            }

            $respMsg = $e->getMessage();
            $order = new WC_Order($orderId);
            $order->update_status('failed');
            $order->add_order_note($respMsg,0,true);

            wc_add_notice(__($respMsg, 'woocommerce-message'), 'error');
            $redirectUrl = $woocommerce->cart->get_cart_url();
            return wp_redirect($redirectUrl);
        }

    }

    private function versionCheck() {

        $phpVersion = phpversion();
        $requiredPhpVersion = 5.4;
        $helper = new Iyzico_Checkout_For_WooCommerce_Helper();
        $locale = $helper->cutLocale(get_locale());

        /* Required PHP */
        $warningMessage = 'Required PHP 5.4 and greater for iyzico WooCommerce Payment Gateway';
        if($locale == 'tr') {
            $warningMessage = 'iyzico WooCommerce eklentisini çalıştırabilmek için, PHP 5.4 veya üzeri versiyonları kullanmanız gerekmektedir. ';
        }

        if($phpVersion < $requiredPhpVersion) {
            echo $warningMessage;
            exit;
        }

        /* Required WOOCOMMERCE */
        $wooCommerceVersion = WOOCOMMERCE_VERSION;
        $requiredWoocommerceVersion = 3.0;

        $warningMessage = 'Required WooCommerce 3.0 and greater for iyzico WooCommerce Payment Gateway';

        if($locale == 'tr') {
            $warningMessage = 'iyzico WooCommerce eklentisini çalıştırabilmek için, WooCommerce 3.0 veya üzeri versiyonları kullanmanız gerekmektedir. ';
        }

        if($wooCommerceVersion < $requiredWoocommerceVersion) {
            echo $warningMessage;
            exit;
        }

        /* Required TLS */
        $tlsUrl = 'https://api.iyzipay.com';
        $tlsVersion = get_option('iyziTLS');

        if(!$tlsVersion) {

            $result = $this->verifyTLS($tlsUrl);
            if($result) {
                add_option('iyziTLS',1.2,'','no');
                $tlsVersion = get_option('iyziTLS');
            }

        } elseif($tlsVersion != 1.2) {

            $result = $this->verifyTLS($tlsUrl);
            if($result) {
                update_option('iyziTLS',1.2);
                $tlsVersion = get_option('iyziTLS');
            }
        }


        $requiredTlsVersion = 1.2;

        $warningMessage = 'WARNING! Minimum TLS v1.2 will be supported after March 2018. Please upgrade your openssl version to minimum 1.0.1.';

        if($locale == 'tr') {
            $warningMessage = "UYARI! Ödeme formunuzu görüntüleyebilmeniz için, TLS versiyonunuzun minimum TLS v1.2 olması gerekmektedir. Lütfen servis sağlayıcınız ile görüşerek openssl versiyonunuzu minimum 1.0.1'e yükseltin.";
        }

        if($tlsVersion < $requiredTlsVersion) {
            echo $warningMessage;
            exit;
        }
    }

    private function verifyTLS($url) {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url,
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        return $response;
    }
}
