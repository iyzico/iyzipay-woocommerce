<?php
/**
 * Plugin Name: iyzico WooCommerce
 * Plugin URI: https://wordpress.org/plugins/iyzico-woocommerce
 * Description: iyzico Payment Gateway for WooCommerce.
 * Author: iyzico
 * Author URI: https://iyzico.com
 * Version: 1.0.3
 * Text Domain: iyzico WooCommerce
 * Domain Path: /i18n/languages/
 *
 */

define('IYZICO_PATH',untrailingslashit( plugin_dir_path( __FILE__ )));
define('IYZICO_LANG_PATH',plugin_basename(dirname(__FILE__)) . '/i18n/languages/');
define('IYZICO_PLUGIN_NAME','/'.plugin_basename(dirname(__FILE__)));



if (!defined('ABSPATH')) {
    exit;
}
if ( ! class_exists( 'Iyzico_CheckoutForm_For_WooCommerce' ) ) {

    class Iyzico_CheckoutForm_For_WooCommerce {

        protected static $instance;
       
        public static function get_instance() {

            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        protected function __construct() {
                        
            add_action('plugins_loaded', array($this,'init'));

        }
      
        public static function IyzicoActive() {

            global $wpdb;
            $table_name = $wpdb->prefix . 'iyzico_order';

            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE $table_name (
                iyzico_order_id int(11) NOT NULL AUTO_INCREMENT,
                payment_id  int(11) NOT NULL,
                order_id int(11) NOT NULL,
                total_amount decimal( 10, 2 ) NOT NULL,
                status varchar(20) NOT NULL,
                created_at  timestamp DEFAULT current_timestamp,
              PRIMARY KEY (iyzico_order_id)
            ) $charset_collate;";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta($sql);

            $table_name2 = $wpdb->prefix . 'iyzico_card';
            $sql = "CREATE TABLE $table_name2 (
                iyzico_card_id int(11) NOT NULL AUTO_INCREMENT,
                customer_id INT(11) NOT NULL,
                card_user_key varchar(50) NOT NULL,
                api_key varchar(50) NOT NULL,
                created_at  timestamp DEFAULT current_timestamp,
               PRIMARY KEY (iyzico_card_id)
            ) $charset_collate;";
            dbDelta($sql);

        }

        public static function IyzicoDeactive() {

            global $wpdb;

            delete_option('iyzico_overlay_token');
            delete_option('iyzico_overlay_position');

            $table_name = $wpdb->prefix . 'iyzico_order';
            $table_name2 = $wpdb->prefix . 'iyzico_card';
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "DROP TABLE IF EXISTS $table_name;";
            $wpdb->query($sql);
            $sql = "DROP TABLE IF EXISTS $table_name2;";
            $wpdb->query($sql);
            flush_rewrite_rules();
        }

        public function init() {
            
            $this->InitIyzicoPaymentGateway();
        }


        public static function installLanguage() {

          load_plugin_textdomain('woocommerce-iyzico',false,IYZICO_LANG_PATH);
        
        }

        public function InitIyzicoPaymentGateway() {

            if ( ! class_exists('WC_Payment_Gateway')) {
                return;
            }

            include_once IYZICO_PATH . '/library/iyzico-for-woocommerce-gateway.php';
            include_once IYZICO_PATH . '/library/iyzico-for-woocommerce-gateway-fields.php';
            include_once IYZICO_PATH . '/library/iyzico-for-woocommerce-gateway-formobjectgenerate.php';
            include_once IYZICO_PATH . '/library/iyzico-for-woocommerce-gateway-helper.php';
            include_once IYZICO_PATH . '/library/iyzico-for-woocommerce-gateway-model.php';
            include_once IYZICO_PATH . '/library/iyzico-for-woocommerce-gateway-pkibuilder.php';
            include_once IYZICO_PATH . '/library/iyzico-for-woocommerce-gateway-iyzicorequest.php';
            include_once IYZICO_PATH . '/library/iyzico-for-woocommerce-gateway-responseobjectgenerate.php';
           
            add_filter('woocommerce_payment_gateways',array($this,'AddIyzicoGateway'));

            
            add_action('wp_footer',array('Iyzico_Checkout_For_WooCommerce_Gateway','getOverlayScript'));


        }   



        public function AddIyzicoGateway($methods) {

            $methods[] = 'Iyzico_Checkout_For_WooCommerce_Gateway';
            return $methods;
        }

    }

Iyzico_CheckoutForm_For_WooCommerce::get_instance();
add_action('plugins_loaded',array('Iyzico_CheckoutForm_For_WooCommerce','installLanguage'));
register_activation_hook(__FILE__, array('Iyzico_CheckoutForm_For_WooCommerce','IyzicoActive'));
register_deactivation_hook(__FILE__,array('Iyzico_CheckoutForm_For_WooCommerce','IyzicoDeactive'));

}

