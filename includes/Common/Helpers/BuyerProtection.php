<?php

namespace Iyzico\IyzipayWoocommerce\Common\Helpers;

use Iyzico\IyzipayWoocommerce\Checkout\CheckoutSettings;

class BuyerProtection
{
    public static function iyzicoOverlayScriptMobileCss()
    {
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

    function enqueue_iyzico_overlay_script()
    {
        $checkoutSettings = new CheckoutSettings();
        $token = get_option('iyzico_overlay_token');
        $position = $checkoutSettings->findByKey('overlay_script');

        if ($position === 'bottomLeft' || $position === 'bottomRight') {
            wp_add_inline_script(
                'iyzico-overlay-script',
                "window.iyz = { token: '" . esc_js($token) . "', position: '" . esc_js($position) . "', ideaSoft: false, pwi: true };",
                'before'
            );

            wp_enqueue_script(
                'iyzico-overlay-script',
                'https://static.iyzipay.com/buyer-protection/buyer-protection.js',
                [],
                IYZICO_PLUGIN_VERSION,
                true
            );
        }
    }

}
