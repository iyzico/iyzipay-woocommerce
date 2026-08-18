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

        // Script should load for all configurations except when completely hidden
        if (in_array($position, ['bottomLeft', 'bottomRight', 'onlyOverlayScript', 'onlyProductDetailScript'], true)) {
            // Map config values to script position (left/right)
            $scriptPosition = $position;
            if (in_array($position, ['bottomLeft', 'onlyOverlayScript'], true)) {
                $scriptPosition = 'left';
            } elseif ($position === 'bottomRight') {
                $scriptPosition = 'right';
            }

            wp_add_inline_script(
                'iyzico-overlay-script',
                "window.iyz = { token: '" . esc_js($token) . "', position: '" . esc_js($scriptPosition) . "', ideaSoft: false, pwi: true };",
                'before'
            );

            wp_enqueue_script(
                'iyzico-overlay-script',
                'https://cdn.iyzipay.com/buyer-protection/iyzico-bpo.js',
                [],
                IYZICO_PLUGIN_VERSION,
                true
            );
        }
    }

    /**
     * Add Buyer Protection div elements to the page
     */
    public static function add_buyer_protection_divs()
    {
        $checkoutSettings = new CheckoutSettings();
        $position = $checkoutSettings->findByKey('overlay_script');
        $hasOverlay = in_array($position, ['bottomLeft', 'bottomRight', 'onlyOverlayScript'], true);
        $logoUrl = null;

        // If overlay is disabled completely, do nothing
        if (!$hasOverlay) {
            return;
        }

        if (has_custom_logo()) {
            $custom_logo_id = get_theme_mod('custom_logo');
            if ($custom_logo_id) {
                $logo_data = wp_get_attachment_image_src($custom_logo_id, 'full');
                if ($logo_data) {
                    $logoUrl = $logo_data[0]; // This is the URL
                }
            }
        }
    
        // Build attributes
        $attributes = '';

        if ($position === 'bottomLeft' || $position === 'bottomRight') {

            if ($position === 'bottomLeft') {
                $position = 'left';
            } else {
                $position = 'right';
            }

            $attributes .= ' data-position="' . esc_js($position) . '"';
        }

        if ($logoUrl !== null) {
            $attributes .= ' data-merchant-logo-url="' . esc_js($logoUrl) . '"';
        }

        // Echo single div with all attributes
        echo '<div id="iyzico-bpo1" data-widget data-type="page-overlay"' . $attributes . '></div>';
    }

    /**
     * Add Buyer Protection div for product detail pages
     */
    public static function add_product_detail_div()
    {
        $checkoutSettings = new CheckoutSettings();
        $position = $checkoutSettings->findByKey('overlay_script');

        // Show product detail widget when configured on either side or alone
        if (in_array($position, ['bottomLeft', 'bottomRight', 'onlyProductDetailScript'], true)) {
            echo '<div id="iyzico-bpo2" style="margin-top: 10px;" data-widget data-type="product-detail"></div>';
        }
    }
}