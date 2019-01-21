<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Iyzico_Checkout_For_WooCommerce_Buyer_Protection {

    public function getOverlayScript() {

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
