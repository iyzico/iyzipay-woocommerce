<?php

/**
 * iyzipay WooCommerce
 *
 * @package iyzico WooCommerce
 * @author iyzico
 * @copyright 2024 iyzico
 * @license LGPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: iyzico WooCommerce
 * Plugin URI: https://wordpress.org/plugins/iyzico-woocommerce
 * Description: iyzico Payment Gateway for WooCommerce.
 * Version: 3.5.21
 * Requires at least: 6.6.2
 * WC requires at least: 9.3.3
 * Requires PHP: 7.4.33
 * Author: iyzico
 * Author URI: https://iyzico.com
 * Text Domain: woocommerce-iyzico
 * Domain Path: /i18n/languages/
 * License: LGPL v3 or later
 * License URI: http://www.gnu.org/licenses/lgpl-3.0.txt
 * Update URI: https://wordpress.org/plugins/iyzico-woocommerce
 * Requires Plugins: woocommerce
 *
 * Tested up to: 6.6.2
 * WC tested up to: 9.7.1
 */

defined('ABSPATH') || exit;

/**
 * Constants
 *
 * These constants are used to define the plugin version, base file, path, url and language path.
 */
const PLUGIN_VERSION  = '3.5.21';
const IYZICO_PLUGIN_VERSION = '3.5.21';
const IYZICO_DB_VERSION = '3.5.21';
const PLUGIN_BASEFILE = __FILE__;

define('PLUGIN_PATH', untrailingslashit(plugin_dir_path(PLUGIN_BASEFILE)));
define('PLUGIN_URL', untrailingslashit(plugin_dir_url(PLUGIN_BASEFILE)));
define('PLUGIN_LANG_PATH', plugin_basename(dirname(PLUGIN_BASEFILE)) . '/i18n/languages/');
define('PLUGIN_ASSETS_DIR_URL', plugin_dir_url(__FILE__) . 'assets');
define('PLUGIN_DIR_PATH', plugin_dir_path(__FILE__));


/**
 * Composer Autoload
 * This is used to autoload the classes.
 */
if (file_exists(PLUGIN_PATH . '/vendor/autoload.php')) {
    require_once PLUGIN_PATH . '/vendor/autoload.php';
}

/**
 * Plugin Activation and Deactivation
 */
register_activation_hook(PLUGIN_BASEFILE, ['\Iyzico\IyzipayWoocommerce\Core\Plugin', 'activate']);
register_deactivation_hook(PLUGIN_BASEFILE, ['\Iyzico\IyzipayWoocommerce\Core\Plugin', 'deactivate']);
add_action('init', 'loadTextDomain');

/**
 * Load plugin textdomain.
 */
function loadTextDomain()
{
    load_plugin_textdomain('woocommerce-iyzico', false, PLUGIN_LANG_PATH);
}

/**
 * Initialize the plugin
 */
add_action('plugins_loaded', ['\Iyzico\IyzipayWoocommerce\Core\Plugin', 'init']);
