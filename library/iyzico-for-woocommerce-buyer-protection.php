<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Iyzico_Checkout_For_WooCommerce_Buyer_Protection {

    public static function iyziCargoTracking($order) {

        $cargoCodeValue = 'iyzico_cargo_no_'.$order->get_id();
        $cargoNameValue = 'iyzico_cargo_name_'.$order->get_id();

        $cargoTrackingNumber = get_option($cargoCodeValue);
        $cargoNumber = get_option($cargoNameValue);
        $currentOrderStatus = $order->get_status();
        $orderStatusArray = array('processing','on-hold','completed');
        $orderStatus = in_array($currentOrderStatus,$orderStatusArray);
        
        $cargoObject[0] = new stdClass();
        $cargoObject[0]->name = 'MNG Kargo';
        $cargoObject[0]->value = '1';

        $cargoObject[1] = new stdClass();
        $cargoObject[1]->name = 'Aras Kargo';
        $cargoObject[1]->value = '2';

        $cargoObject[2] = new stdClass();
        $cargoObject[2]->name = 'Yurtiçi Kargo';
        $cargoObject[2]->value = '3';

        $cargoObject[3] = new stdClass();
        $cargoObject[3]->name = 'UPS Kargo';
        $cargoObject[3]->value = '4';

        $cargoObject[4] = new stdClass();
        $cargoObject[4]->name = 'Sürat Kargo';
        $cargoObject[4]->value = '5';

        $pluginUrl = plugins_url().IYZICO_PLUGIN_NAME;
        ?>
            <?php if($orderStatus): ?> 

                <h1>iyzico Korumalı Alışveriş</h1>
                <img src="<?php echo $pluginUrl; ?>/image/protected_zihni.png" style="float:right;"/>
                <h3 style="float:left;">Kargo Bilgi Ekranı</h3>
                <select name="cargoNumber" style="width: 100%;">
                        <option value="">Seçiniz</option>
                    <?php foreach ($cargoObject as $key => $cargo): ?>
                        <option value="<?php echo $cargo->value; ?>" <?php if($cargo->value == (int) $cargoNumber) { echo 'selected';}?>>
                            <?php echo $cargo->name; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <br>
                <br>
                <input type="text" style="width: 100%;" name="cargoTrackingNumber" value="<?php echo $cargoTrackingNumber; ?>" placeholder="Kargo numarası:" />
            <?php endif; ?>
        <?php
    }

    public static function iyziCargoTrackingSave($order_id) {

        $cargoNumber = $_POST['cargoNumber'];
        $cargoTrackingNumber = $_POST['cargoTrackingNumber'];
        $createOrUpdateControl = false;

        /* Empty Check Control */
        if(empty($cargoNumber) || empty($cargoTrackingNumber)) {

            return;
        }

        $cargoTrackingField = 'iyzico_cargo_no_'.$order_id;
        $cargoNumberField = 'iyzico_cargo_name_'.$order_id;
        $cargoTrackingOption = get_option($cargoTrackingField);
        $cargoNumberOption = get_option($cargoNumberField);


        /* Sleep Data Check */
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
        $paymentId = $iyziModel->findPaymentId($order_id);

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

                echo $requestResponse->errorMessage;
                exit;
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
