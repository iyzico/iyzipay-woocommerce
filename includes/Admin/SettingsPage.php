<?php

namespace Iyzico\IyzipayWoocommerce\Admin;

class SettingsPage
{
    public function getHtmlContent()
    {
        $logo_url = esc_url(PLUGIN_URL) . '/assets/images/iyzico_logo.png';

        $html = '<style>
            @media (max-width:768px){.iyziBrand{position:fixed;bottom:0;top:auto!important;right:0!important}}
            .iyziBrandLogo {
                background-image: url("' . $logo_url . '");
                background-size: contain;
                background-repeat: no-repeat;
                background-position: center;
                width: 250px;
                height: 100px;
                margin-left: auto;
            }
        </style>
        <div class="iyziBrandWrap">
            <div class="iyziBrand" style="clear:both;position:absolute;right: 50px;top:440px;display: flex;flex-direction: column;justify-content: center;">
                <div class="iyziBrandLogo"></div>
                <p style="text-align:center;"><strong>Version: </strong>' . esc_html(IYZICO_PLUGIN_VERSION) . '</p>
            </div>
        </div>';

        $allowed_html = [
            'style' => [],
            'div' => ['class' => [], 'id' => [], 'style' => []],
            'p' => ['style' => []],
            'strong' => [],
        ];

        echo wp_kses($html, $allowed_html);
    }
}
