<?php


class IyzicoWebhook{

    private $paymentConversationId;
    private $token;
    private $iyziEventType;

    public function __construct() {
        $this->namespace     = 'iyzico/v1';
        $this->resource_name = 'webhook/'. self::getIyziUrlId();
    }

    public static function iyzicoRegisterRestRoutes() {
        $controller = new IyzicoWebhook();
        $controller->registerRoutes();
    }

    // Register our routes.
    public function registerRoutes() {
        register_rest_route( $this->namespace, '/' . $this->resource_name, array(
            // Here we register the readable endpoint for collections.
            'methods'   => 'POST',
            'callback' => array($this, 'orderControlViaWebhook'),
            'permission_callback' => '__return_true',
        ) );
    }

    // Control Order
    function orderControlViaWebhook($request) {

        $iyzicoSignature = $request->get_header('x-iyz-signature');
        $params = wp_parse_args( $request->get_json_params());

        if (isset($params['iyziEventType']) && isset($params['token']) && isset($params['paymentConversationId'])){

            $this->paymentConversationId = $params['paymentConversationId'];
            $this->token = $params['token'];
            $this->iyziEventType = $params['iyziEventType'];

            if ($iyzicoSignature){

                $iyzicoSettings = get_option('woocommerce_iyzico_settings');

                $createIyzicoSignature = base64_encode(sha1($iyzicoSettings['secret_key'] . $this->iyziEventType . $this->token, true));

                if ($iyzicoSignature == $createIyzicoSignature){
                    return $this->iyzicoWebhookResponse();
                }
                else{
                    return new WP_Error( 'signature_not_valid', 'X-IYZ-SIGNATURE geçersiz', array( 'status' => 404 ) );

                }
            }

            else{
                return $this->iyzicoWebhookResponse();
            }
        }
        else{
            return new WP_Error( 'invalid_parameters', 'Gönderilen parametreler geçersiz', array( 'status' => 404 ) );
        }

    }

    public static function getIyziUrlId(){
        if (!get_option(IYZICO_WEBHOOK_URL_KEY)){
            add_action( 'admin_notices', array( self::class, 'webhookAdminNoticeWarning') );
            return;
        }
        else{
            return get_option(IYZICO_WEBHOOK_URL_KEY);
        }
    }

    public function iyzicoWebhookResponse(){

        $iyzicoGateway = new Iyzico_Checkout_For_WooCommerce_Gateway();
        $responseCode = $iyzicoGateway->iyzico_response('webhook', $this->paymentConversationId, $this->token);

        return $responseCode;
    }

    function webhookAdminNoticeWarning() {
        $class = 'notice notice-warning is-dismissible';
        $message = __( "Webhook URL Error! Please, re-install the iyzico plugin after delete the iyzico plugin. Send an email to entegrasyon@iyzico.com", 'woocommerce-iyzico' );

        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
    }


}


