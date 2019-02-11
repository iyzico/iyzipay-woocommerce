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

                
                <h3>iyzico Korumalı Alışveriş Kargo Takibi</h3>
                <p>Siparişin kargo takip numarasını girerek, müşterinizin anlık kargo takibi yapmasını sağlayabilirsiniz.</p>
                <p style="font-weight:bold;margin-bottom:0px !important;">Kargo Firması</p>
                <select name="cargoNumber" style="width: 80%;">
                        <option value="">Seçiniz</option>
                    <?php foreach ($cargoObject as $key => $cargo): ?>
                        <option value="<?php echo $cargo->value; ?>" <?php if($cargo->value == (int) $cargoNumber) { echo 'selected';}?>>
                            <?php echo $cargo->name; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p style="font-weight:bold;margin-bottom:0px !important;">Kargo Takip Numarası</p>
                <input type="text" style="width: 80%;" name="cargoTrackingNumber" value="<?php echo $cargoTrackingNumber; ?>" placeholder="Kargo numarası:" />
            <?php endif; ?>
        <?php
    }

    public static function iyziCargoTrackingSave($order_id) {

        $orderId = (int) $order_id;

        $cargoNumber = esc_sql($_POST['cargoNumber']);
        $cargoTrackingNumber = esc_sql($_POST['cargoTrackingNumber']);
        $createOrUpdateControl = false;

        /* Empty Post  Control */
        if(empty($cargoNumber) || empty($cargoTrackingNumber)) {

            return;
        }

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

        $cargoObject = $formObjectGenerate->generateCargoTracking($cargoTrackingNumber,$paymentId,$cargoNumber);
        $pkiString = $pkiBuilder->pkiStringGenerate($cargoObject);
        $authorizationData = $pkiBuilder->authorizationGenerate($pkiString,$apiKey,$secretKey,$randNumer);


        $iyzicoJson = json_encode($cargoObject,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $requestResponse = $iyzicoRequest->iyzicoCargoTracking($baseUrl,$iyzicoJson,$authorizationData);

        if(isset($requestResponse->status)) {

            if($requestResponse->status == 'success') {

                if(empty($createOrUpdateControl)) {

                    add_option($cargoNumberField,$cargoNumber,'','yes'); 
                    add_option($cargoTrackingField,$cargoTrackingNumber,'','yes');

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

        if($activePlugins['enabled'] != 'no') { 
            if($position != 'hide' && !empty($token)) {

                $overlayScript = "<script> window.iyz = { token:'".$token."', position:'".$position."',ideaSoft: false};</script>
                    <script src='https://static.iyzipay.com/buyer-protection/buyer-protection.js' type='text/javascript'></script>";
            }
        }

        echo $overlayScript;
    }   

}
