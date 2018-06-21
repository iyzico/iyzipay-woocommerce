<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


class Iyzico_Checkout_For_WooCommerce_Gateway extends WC_Payment_Gateway {

    public function __construct() {

        $this->id = 'iyzico';
        $this->method_title = __('iyzico', 'woocommerce-iyzico');
        $this->method_description = __('Easy Checkout');
        $this->has_fields = true;
        $this->order_button_text = __('Pay With Card', 'woocommerce-iyzico');
        $this->supports = array('products');

        $this->init_form_fields();
        $this->init_settings();

        $this->title        = $this->get_option( 'title' );
        $this->description  = $this->get_option( 'description' );
        $this->enabled      = $this->get_option( 'enabled' );
        $this->icon         = plugins_url().IYZICO_PLUGIN_NAME.'/image/cards.png';


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

                add_option('iyzico_overlay_token',$requestResponse->protectedShopId,'','yes'); 
                add_option('iyzico_overlay_position',$this->settings['overlay_script'],'','yes'); 

            } else {

                update_option('iyzico_overlay_token',$requestResponse->protectedShopId); 
                update_option('iyzico_overlay_position',$this->settings['overlay_script']);   
            } 
        
        } else {
           
            if(empty($iyzicoOverlayPosition)) { 
                
                add_option('iyzico_overlay_position',$this->settings['overlay_script'],'','yes'); 
                
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
        $image =  "<img id='iyzico-logo' style='float:right' src=".$pluginUrl."/image/iyzico_logo.png />";
        echo $image;
    }

    public static function getOverlayScript() {

        $token             = get_option('iyzico_overlay_token');
        $position          = get_option('iyzico_overlay_position');
        $activePlugins     = get_option('woocommerce_iyzico_settings');

        $overlayScript = false;

    if($activePlugins['enabled'] != 'no') { 
        if($position != 'hide' && !empty($token)) {

            $overlayScript = "<script> window.iyz = { token:'".$token."', position:'".$position."',ideaSoft: false};</script>
                <script src='https://static.iyzipay.com/buyer-protection/buyer-protection.js' type='text/javascript'></script>";
        }
    }

        echo $overlayScript;
        


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

    public function iyzico_loading_bar($order_id) {

        wp_enqueue_style( 'style', plugins_url().IYZICO_PLUGIN_NAME.'/media/css/iyzico.css',true,'1.1','all');

        echo '<div id="loadingBar">
                <div class="loading">
                </div>
                <div class="brand">
                    <p style="text-align:center;color:#4ec8f1;">iyzico</p>
                </div>
             </div>';
        
    }

    public function iyzico_payment_form($order_id) {

        $this->versionCheck();

        global $woocommerce;

        $getOrder                  = new WC_Order($order_id);
        $customerCart              = $woocommerce->cart->get_cart();
        $apiKey                    = $this->settings['api_key'];
        $secretKey                 = $this->settings['secret_key'];
        $baseUrl                   = $this->settings['api_type'];
        $rand                      = rand(1,99999);
        $user                      = wp_get_current_user();
        $iyzicoConversationId      = WC()->session->set('iyzicoConversationId',$order_id);
        $iyzicoCustomerId          = WC()->session->set('iyzicoCustomerId',$user->ID);
        $totalAmount               = WC()->session->set('iyzicoOrderTotalAmount',$getOrder->get_total());



        $formObjectGenerate        = new Iyzico_Checkout_For_WooCommerce_FormObjectGenerate();
        $pkiBuilder                = new Iyzico_Checkout_For_WooCommerce_PkiBuilder();
        $iyzicoRequest             = new Iyzico_Checkout_For_WooCommerce_iyzicoRequest();

        $iyzico                   = $formObjectGenerate->generateOption($customerCart,$getOrder,$apiKey);
        $iyzico->buyer            = $formObjectGenerate->generateBuyer($getOrder);
        $iyzico->shippingAddress  = $formObjectGenerate->generateShippingAddress($getOrder);
        $iyzico->billingAddress   = $formObjectGenerate->generateBillingAddress($getOrder);
        $iyzico->basketItems      = $formObjectGenerate->generateBasketItems($customerCart,$getOrder);

        $orderObject              = $pkiBuilder->createFormObjectSort($iyzico);
        $pkiString                = $pkiBuilder->pkiStringGenerate($orderObject);
        $authorizationData        = $pkiBuilder->authorizationGenerate($pkiString,$apiKey,$secretKey,$rand);
        

        $iyzicoJson               = json_encode($iyzico,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
 
        $requestResponse          = $iyzicoRequest->iyzicoCheckoutFormRequest($baseUrl,$iyzicoJson,$authorizationData);
        $className                = $this->get_option('form_class');
        $message                  = '<p id="infoBox" style="display:none;">' . __('Thank you for your order, please click the button below to pay with iyzico Checkout.', 'woocommerce-iyzico') . '</p>';

        wp_enqueue_script('script', plugins_url().IYZICO_PLUGIN_NAME.'/media/js/iyzico.js',true,'1.3','all');

        if(isset($requestResponse->status)) {
            if($requestResponse->status == 'success') {
                echo $message;
                echo ' <div style="display:none" id="iyzipay-checkout-form" class='.$className.'>' . $requestResponse->checkoutFormContent . '</div>';
            } else {
                echo $requestResponse->errorMessage;
            }

        } else {
            echo 'Not Connection...';
        }

    }

    public function iyzico_response() {

        global $woocommerce;

        try {


            if(!$_POST['token']) {

               throw new \Exception("Token not found");
            
            }
            
            $conversationId  = WC()->session->get('iyzicoConversationId');
            $customerId      = WC()->session->get('iyzicoCustomerId');
            $orderId         = $conversationId;

            if(empty($orderId)) {
               throw new \Exception("Order not found");
            }

            $token           = $_POST['token'];
            $apiKey          = $this->settings['api_key'];
            $secretKey       = $this->settings['secret_key'];
            $baseUrl         = $this->settings['api_type'];
            $rand            = rand(1,99999);
            

            
            $iyziModel       = new Iyzico_Checkout_For_WooCommerce_Model();


            $responseObjectGenerate    = new Iyzico_Checkout_For_WooCommerce_ResponseObjectGenerate();
            $pkiBuilder                = new Iyzico_Checkout_For_WooCommerce_PkiBuilder();
            $iyzicoRequest             = new Iyzico_Checkout_For_WooCommerce_iyzicoRequest();

            $tokenDetailObject         = $responseObjectGenerate->generateTokenDetailObject($conversationId,$token);
            $pkiString                 = $pkiBuilder->pkiStringGenerate($tokenDetailObject);
            $authorizationData         = $pkiBuilder->authorizationGenerate($pkiString,$apiKey,$secretKey,$rand);
            $tokenDetailObject         = json_encode($tokenDetailObject,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            $requestResponse           = $iyzicoRequest->iyzicoCheckoutFormDetailRequest($baseUrl,$tokenDetailObject,$authorizationData);

            $iyzicoLocalOrder = new stdClass;
            $iyzicoLocalOrder->paymentId     = !empty($requestResponse->paymentId) ? (int) $requestResponse->paymentId : '';
            $iyzicoLocalOrder->orderId       = $orderId;
            $iyzicoLocalOrder->totalAmount   = !empty($requestResponse->paidPrice) ? (float) $requestResponse->paidPrice : '';
            $iyzicoLocalOrder->status        = $requestResponse->paymentStatus; 

            $iyzico_order_insert  = $iyziModel->insertIyzicoOrder($iyzicoLocalOrder);
     
            if($requestResponse->paymentStatus != 'SUCCESS' || $requestResponse->status != 'success' || $orderId != $requestResponse->basketId ) {

                if($requestResponse->status == 'success' && $requestResponse->paymentStatus == 'FAILURE') {
                    throw new Exception('Kartınız için 3D  güvenliği onaylanmamıştır.');
                    
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

            $order = new WC_Order($requestResponse->basketId);
            
            $orderMessage = 'Payment ID: '.$requestResponse->paymentId;
            $order->add_order_note($orderMessage,0,true);
            
            if($baseUrl == 'https://sandbox-api.iyzipay.com') {

                $orderMessage = '<strong><p style="color:red">TEST ÖDEMESİ</a></strong>';
                $order->add_order_note($orderMessage,0,true);
            }

            if (isset($requestResponse->installment) && !empty($requestResponse->installment) && $requestResponse->installment > 1) {


                $totalPrice         = WC()->session->get('iyzicoOrderTotalAmount');
                $installment_fee    = $requestResponse->paidPrice - $totalPrice; 
       
                $order_fee          = new stdClass();
                $order_fee->id      = 'Installment Fee';
                $order_fee->name    = __('Installment Fee', 'woocommerce-iyzico');
                $order_fee->amount  = $installment_fee;
                $order_fee->taxable = false;
                $fee_id = $order->add_fee($order_fee);
                $order->calculate_totals(true);

             

                update_post_meta($order_id, 'iyzico_no_of_installment',$requestResponse->installment);
                update_post_meta($order_id, 'iyzico_installment_fee', $installment_fee);
            }

            /* Session Unset */
            WC()->session->set('iyzicoConversationId',null);
            WC()->session->set('iyzicoCustomerId',null);
            WC()->session->set('iyzicoOrderTotalAmount',null);


            $order->payment_complete();
            $woocommerce->cart->empty_cart();

            $checkoutOrderUrl = $order->get_checkout_order_received_url();

            $redirectUrl = add_query_arg(array('msg' => 'Thank You', 'type' => 'woocommerce-message'), $checkoutOrderUrl);
            wp_redirect($redirectUrl);
            
        } catch (Exception $e) {
    
            $respMsg = $e->getMessage();

            wc_add_notice(__($respMsg, 'woocommerce-message'), 'error');
            $redirectUrl = $woocommerce->cart->get_checkout_url();
            wp_redirect($redirectUrl);
        }

    }

    private function versionCheck() {

        $phpVersion = phpversion();
        $requiredVersion = 5.4;

        if($phpVersion < $requiredVersion) {
            echo 'Required PHP 5.4 and greater for iyzico WooCommerce Payment Gateway';
            exit;
        }
    }
}

