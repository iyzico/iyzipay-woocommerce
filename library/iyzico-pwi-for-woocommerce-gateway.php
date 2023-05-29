<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Iyzico_Pwi_For_WooCommerce_Gateway extends WC_Payment_Gateway {

    public function __construct() {

        $this->id    = 'iyzico_pwi';
        $this->iyziV = '1.0.5';
        $this->method_title = __('Pay with iyzico', 'woocommerce-iyzico');
        $this->method_description = __('Best Payment Solution', 'woocommerce-iyzico');
        $this->has_fields = true;
        $this->order_button_text = __('Pay with iyzico', 'woocommerce-iyzico');
        $this->supports = array('products');

        $this->init_form_fields();
        $this->init_settings();

        $this->enabled     = $this->get_option('enabled');
        $this->title = false;



        if(get_locale() == 'tr_TR') {
            $this->description  = __('iyzico ile Öde-Şimdi Kolay!
                                    Alışverişini ister iyzico bakiyenle, ister saklı kartınla,
                                    ister havale/EFT yöntemi ile kolayca öde; aklına takılan herhangi bir konuda iyzico Korumalı Alışveriş avantajıyla
                                    7/24 canlı destek al.',"woocommerce-iyzico");
        } else {
            $this->description  = __('Your money safe with iyzico!
                                 -Store your iyzico card and enjoy one-click payment.
                                 -All your transactions under the iyzico Buyer Protection guarantee.
                                 -Get live support 24/7.',"woocommerce-iyzico");
        }

        $this->valid_css();


        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
            $this,
            'process_admin_options',
        ) );

        add_action('woocommerce_receipt_iyzico_pwi', array($this, 'iyzico_pwi_payment_redirect'));


    }


    public function init_form_fields() {

        $this->form_fields = Iyzico_Pwi_For_WooCommerce_Fields::IyzicoPwiAdminFields();

    }

    public function valid_css() {

        wp_enqueue_style('style', plugins_url().IYZICO_PLUGIN_NAME.'/media/css/pwi.css',true,'2.0','all');
    }


    public function process_payment($order_id) {

        $order = wc_get_order($order_id);

        return array(
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );

    }


    public function iyzico_pwi_payment_redirect($order_id) {

        $iyzicoBuilder = new Iyzico_Checkout_For_WooCommerce_Gateway();

        $getOrder                  = new WC_Order($order_id);

         $getCurrency = array("TRY", "USD");


        if ($getOrder->get_currency()!='TRY'){

          if ($getOrder->get_currency()!='USD'){

              return;
          }
        }


        $getPwiGenerate = $iyzicoBuilder->iyzico_payment_form($order_id,"pwi");
        header("Location: ".$getPwiGenerate."");

    }


    private  function isMobile() {

        return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);

    }

    public function get_icon() {
        $icon_html = false;
        $pwiImageUrl = "/image/pwi_tr.png?v=2";

        if(get_locale() != 'tr_TR') {
            $pwiImageUrl = "/image/pwi_en.png?v=2";
        }

        if($this->isMobile()) {
            $icon_html .= "<img src=".plugins_url().IYZICO_PLUGIN_NAME. $pwiImageUrl ."/></label> <p>" . __("Pay with iyzico-It’s Easy Now!", 'woocommerce-iyzico') ."</p>";
        } else {
            $icon_html .= "<img src=".plugins_url().IYZICO_PLUGIN_NAME. $pwiImageUrl ."/> <p>" . __("Pay with iyzico-It’s Easy Now!", 'woocommerce-iyzico') ."</p>";
        }

        return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
    }

    public function admin_options() {
        ob_start();
        parent::admin_options();
        $parent_options = ob_get_contents();
        ob_end_clean();

        echo $parent_options;
        $pluginUrl = plugins_url().IYZICO_PLUGIN_NAME;

        $html = '<style scoped>@media (max-width:768px){.iyziBrand{position:fixed;bottom:0;top:auto!important;right:0!important}}</style><div class="iyziBrandWrap"><div class="iyziBrand" style="clear:both;position:absolute;right: 50px;top:250px;display: flex;flex-direction: column;justify-content: center;"><img src='.$pluginUrl.'/image/pwi_tr.png style="width: 200px;margin-left: auto;"><p style="text-align:center;"><strong>V: </strong>'.$this->iyziV.'</p></div></div>';

        echo $html;
    }
}
