<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Iyzico_Checkout_For_WooCommerce_Buyer_Protection {

    public static function iyziCargoTracking($order) {

        $orderId = (int) $order->get_id();

        $cargoCodeValue = 'iyzico_cargo_no_'.$orderId;
        $cargoNameValue = 'iyzico_cargo_name_'.$orderId;

        $cargoTrackingNumber = get_option($cargoCodeValue);
        $cargoNumber = get_option($cargoNameValue);
        $currentOrderStatus = $order->get_status();
        $orderStatusArray = array('processing','on-hold','completed');
        $orderStatus = in_array($currentOrderStatus,$orderStatusArray);
        $paymentMethod = $order->get_payment_method();
        $protectedControl = get_option('iyzico_overlay_token');

        /* Payment Gateways */
        $gateway_controller = WC_Payment_Gateways::instance();
        $all_gateways  = $gateway_controller->payment_gateways();
        $iyzico  = $all_gateways['iyzico']->settings;
        $apiType = $iyzico['api_type'];

        if($apiType == 'https://sandbox-api.iyzipay.com') {
            $protectedControl = true;
        }

        /* Cargo Object */
        $cargoArray = array(array('name' => 'MNG Kargo', 'value' => 1),
                            array('name' => 'Aras Kargo', 'value' => 4),
                            array('name' => 'Yurtiçi Kargo', 'value' => 6),
                            array('name' => 'UPS Kargo', 'value' => 10),
                            array('name' => 'Sürat Kargo', 'value' => 15)
                        );


        foreach ($cargoArray as $key => $cargo) {

            $cargoObject[$key] = new stdClass();
            $cargoObject[$key]->name = $cargo['name'];
            $cargoObject[$key]->value = $cargo['value'];
        }

        $pluginUrl = plugins_url().IYZICO_PLUGIN_NAME;
        ?>
            <?php if($protectedControl && $orderStatus && $paymentMethod == 'iyzico'): ?>


                <h3><?php echo __('iyzico Protected Shopping, Shipping Tracking', 'woocommerce-iyzico'); ?></h3>
                <p><?php echo __('By entering the shipping tracking number of this order, you can ensure your customer have real time order tracking.', 'woocommerce-iyzico'); ?></p>
                <p style="font-weight:bold;margin-bottom:0px !important;"><?php echo __('Shipping Company', 'woocommerce-iyzico'); ?></p>
                <select name="cargoNumber" style="width: 80%;">
                        <option value=""><?php  echo __('Select', 'woocommerce-iyzico'); ?></option>
                    <?php foreach ($cargoObject as $key => $cargo): ?>
                        <option value="<?php echo $cargo->value; ?>" <?php if($cargo->value == (int) $cargoNumber) { echo 'selected';}?>>
                            <?php echo $cargo->name; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p style="font-weight:bold;margin-bottom:0px !important;"><?php echo __('Shipping Tracking Number', 'woocommerce-iyzico'); ?></p>
                <input type="text" style="width: 80%;" name="cargoTrackingNumber" value="<?php echo $cargoTrackingNumber; ?>" placeholder="<?php echo __('Shipping Tracking Number', 'woocommerce-iyzico'); ?>" />
            <?php endif; ?>
        <?php
    }

    public static function iyziCargoTrackingSave($order_id, $cargoTrackingNumber = false, $cargoNumber = false) {

        $orderId = (int) $order_id;

        if(!empty($_POST['cargoNumber']) && !empty($_POST['cargoTrackingNumber'])) {
            $cargoNumber = $_POST['cargoNumber'];
            $cargoTrackingNumber = $_POST['cargoTrackingNumber'];
        }

        $createOrUpdateControl = false;

        /* Empty Post  Control */
        if(empty($cargoNumber) || empty($cargoTrackingNumber)) {

            return;
        }

        $cargoNumber = esc_sql($cargoNumber);
        $cargoTrackingNumber = esc_sql($cargoTrackingNumber);

        $cargoTrackingField = 'iyzico_cargo_no_'.$orderId;
        $cargoNumberField = 'iyzico_cargo_name_'.$orderId;
        $cargoTrackingOption = get_option($cargoTrackingField);
        $cargoNumberOption = get_option($cargoNumberField);


        /* Sleeping Data Control */
        if($cargoNumber == $cargoNumberOption && $cargoTrackingNumber == $cargoTrackingOption) {

            return;
        }

        if(!empty($cargoNumberOption) && !empty($cargoTrackingOption)) {

            $createOrUpdateControl = true;
        }

        $gateway_controller = WC_Payment_Gateways::instance();
        $all_gateways       = $gateway_controller->payment_gateways();
        $iyzico             = $all_gateways['iyzico']->settings;

        $apiKey = $iyzico['api_key'];
        $secretKey = $iyzico['secret_key'];
        $randNumer = rand(1,99999);

        $iyziModel  = new Iyzico_Checkout_For_WooCommerce_Model();
        $paymentId = $iyziModel->findPaymentId($orderId);

        /* Post iyzico */
        $formObjectGenerate = new Iyzico_Checkout_For_WooCommerce_FormObjectGenerate();
        $pkiBuilder  = new Iyzico_Checkout_For_WooCommerce_PkiBuilder();
        $iyzicoRequest = new Iyzico_Checkout_For_WooCommerce_iyzicoRequest();
        $baseUrl = $iyzico['api_type'];

        if($baseUrl == 'https://api.iyzipay.com') {
            $baseUrl = 'https://iyziup.iyzipay.com';
        }

        $cargoObject = $formObjectGenerate->generateCargoTracking($cargoTrackingNumber,$paymentId,$cargoNumber);
        $pkiString = $pkiBuilder->pkiStringGenerate($cargoObject);
        $authorizationData = $pkiBuilder->authorizationGenerate($pkiString,$apiKey,$secretKey,$randNumer);


        $iyzicoJson = json_encode($cargoObject,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $requestResponse = $iyzicoRequest->iyzicoCargoTracking($baseUrl,$iyzicoJson,$authorizationData);

        if(isset($requestResponse->status)) {

            if($requestResponse->status == 'success') {

                if(empty($createOrUpdateControl)) {

                    add_option($cargoNumberField,$cargoNumber,'','no');
                    add_option($cargoTrackingField,$cargoTrackingNumber,'','no');

                } else {

                    update_option($cargoNumberField,$cargoNumber);
                    update_option($cargoTrackingField,$cargoTrackingNumber);
                }

            } else {

                return;
            }
        }

    }

    public static function getOverlayScript() {

        $token             = get_option('iyzico_overlay_token');
        $position          = get_option('iyzico_overlay_position');
        $activePlugins     = get_option('woocommerce_iyzico_settings');

        $overlayScript = false;
        if(is_array($activePlugins))
        {
        if($activePlugins['enabled'] != 'no') {
            if($position != 'hide') {

                $overlayScript = "<script> window.iyz = { token:'".$token."', position:'".$position."',ideaSoft: false, pwi:true};</script>
                    <script src='https://static.iyzipay.com/buyer-protection/buyer-protection.js' type='text/javascript'></script>";
            }
        }
      }
        echo $overlayScript;
    }

    public static function iyzicoOverlayScriptMobileCss(){

        echo '<style>
	                @media screen and (max-width: 380px) {
                        ._1xrVL7npYN5CKybp32heXk {
		                    position: fixed;
			                bottom: 0!important;
    		                top: unset;
    		                left: 0;
    		                width: 100%;
                        }
                    }
	            </style>';
    }

}
