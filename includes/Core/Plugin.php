<?php

namespace Iyzico\IyzipayWoocommerce\Core;

use Iyzico\IyzipayWoocommerce\Checkout\CheckoutForm;
use Iyzico\IyzipayWoocommerce\Common\Helpers\BlocksSupport;
use Iyzico\IyzipayWoocommerce\Common\Helpers\Logger;
use Iyzico\IyzipayWoocommerce\Common\Hooks\AdminHooks;
use Iyzico\IyzipayWoocommerce\Common\Hooks\PublicHooks;
use Iyzico\IyzipayWoocommerce\Common\Traits\PluginLoader;
use Iyzico\IyzipayWoocommerce\Database\DatabaseManager;
use Iyzico\IyzipayWoocommerce\Pwi\Pwi;

class Plugin
{

    use PluginLoader;

    public static function activate()
    {
        DatabaseManager::createTables();
        
        // Google Products XML cron'u başlat
        if (!\wp_next_scheduled('iyzico_generate_google_products_xml')) {
            \wp_schedule_event(time(), 'daily', 'iyzico_generate_google_products_xml');
        }
        
        // XML dosyası kontrolü - eğer XML dosyası yoksa oluştur ve gönder
        $upload_dir = \wp_upload_dir();
        $xml_file = $upload_dir['basedir'] . '/iyzico-google-products/google-products.xml';
        
        if (!file_exists($xml_file)) {
            // XML oluşturma işlemini asenkron olarak planla (5 saniye sonra)
            \wp_schedule_single_event(time() + 5, 'iyzico_generate_google_products_xml_activation');
        }

        // Set transient to redirect to onboarding page
        \set_transient('iyzico_onboarding_redirect', true, 30);
    }

    public static function deactivate()
    {
        global $wpdb;
        $logger = new Logger();
        DatabaseManager::init($wpdb, $logger);
        DatabaseManager::dropTables();

        \delete_option('iyzico_overlay_token');
        \delete_option('iyzico_overlay_position');
        \delete_option('iyzico_thank_you');
        \delete_option('init_active_webhook_url');
        \delete_option('iyzico_db_version');

        \flush_rewrite_rules();
        // Google Products XML cron'u temizle
        \wp_clear_scheduled_hook('iyzico_generate_google_products_xml');
        \wp_clear_scheduled_hook('iyzico_generate_google_products_xml_activation');
        
        // Google Products XML ile ilgili option'ları temizle
        \delete_option('iyzico_google_products_xml_url');
        \delete_option('iyzico_google_products_xml_last_update');
        \delete_option('iyzico_google_products_last_sent');
        \delete_option('iyzico_google_products_next_send_time');
        \delete_option('iyzico_google_products_retry_data');
    }

    public function run()
    {
        // First load text domain
        \load_plugin_textdomain('iyzico-woocommerce', false, PLUGIN_LANG_PATH);

        // Google Products XML cron event fonksiyonu
        \add_action('iyzico_generate_google_products_xml', function() {
            $xmlGenerator = new \Iyzico\IyzipayWoocommerce\Common\Helpers\GoogleProductsXml();
            $xmlGenerator->generateXml();
        });
        
        // İlk kurulum için XML oluşturma event'i
        \add_action('iyzico_generate_google_products_xml_activation', function() {
            try {
                $xmlGenerator = new \Iyzico\IyzipayWoocommerce\Common\Helpers\GoogleProductsXml();
                $xmlGenerator->generateXml();
            } catch (\Exception $e) {
                $logger = new \Iyzico\IyzipayWoocommerce\Common\Helpers\Logger();
                $logger->error('Iyzico Plugin Activation: XML generation failed - ' . $e->getMessage());
            }
        });

        // Then load dependencies and register hooks
        $this->loadDependencies();
        $this->defineAdminHooks();
        $this->definePublicHooks();
        $this->initPaymentGateway();
        $this->generateWebhookKey();
        $this->checkDatabaseUpdate();

        // Hook onboarding redirect
        \add_action('admin_init', [$this, 'maybeRedirectToOnboarding']);

        BlocksSupport::init();
        HighPerformanceOrderStorageSupport::init();
    }

    private function loadDependencies(): void
    {
        require_once PLUGIN_PATH.'/includes/Common/Helpers/BlocksSupport.php';
        require_once PLUGIN_PATH.'/includes/Common/Helpers/HighPerformanceOrderStorageSupport.php';
        require_once PLUGIN_PATH.'/includes/Common/Helpers/GoogleProductsXml.php';
        require_once PLUGIN_PATH.'/includes/Common/Helpers/PluginUpdateHandler.php';

        require_once PLUGIN_PATH.'/includes/Admin/SettingsPage.php';
        require_once PLUGIN_PATH.'/includes/Admin/OnboardingScreen.php';
        require_once PLUGIN_PATH.'/includes/Common/Hooks/AdminHooks.php';

        require_once PLUGIN_PATH.'/includes/Checkout/CheckoutSettings.php';
        require_once PLUGIN_PATH.'/includes/Common/Helpers/WebhookHelper.php';

        require_once PLUGIN_PATH.'/includes/Common/Hooks/PublicHooks.php';

        require_once PLUGIN_PATH.'/includes/Checkout/CheckoutForm.php';
        require_once PLUGIN_PATH.'/includes/Checkout/BlocksCheckoutMethod.php';

        require_once PLUGIN_PATH.'/includes/Pwi/Pwi.php';
        require_once PLUGIN_PATH.'/includes/Pwi/BlocksPwiMethod.php';
    }

    private function defineAdminHooks()
    {
        if (\is_admin()) {
            \add_filter(
                'plugin_action_links_'.\plugin_basename(PLUGIN_BASEFILE),
                [$this, 'actionLinks']
            );

            $adminHooks = new AdminHooks();
            $adminHooks->register();
        }
    }

    private function definePublicHooks()
    {
        $publicHooks = new PublicHooks();
        $publicHooks->register();
    }

    private function initPaymentGateway()
    {
        \add_filter('woocommerce_payment_gateways', [$this, 'addGateways']);
    }

    private function generateWebhookKey()
    {
        $uniqueUrlId = substr(base64_encode(time().\wp_rand()), 15, 6);
        $iyziUrlId = \get_option("iyzicoWebhookUrlKey");
        if (!$iyziUrlId) {
            \add_option("iyzicoWebhookUrlKey", $uniqueUrlId, '', false);
        }
    }

    public function checkDatabaseUpdate()
    {
        $installed_version = \get_option('iyzico_db_version', '0');

        if (version_compare($installed_version, IYZICO_DB_VERSION, '<')) {
            DatabaseManager::updateTables();
            \update_option('iyzico_db_version', IYZICO_DB_VERSION);
        }
    }

    public function addGateways($methods)
    {
        $methods[] = CheckoutForm::class;
        $methods[] = Pwi::class;

        return $methods;
    }

    public function actionLinks($links): array
    {
        $custom_links = [];

        // Check if text domain is loaded, if not return links without translations
        if (!\is_textdomain_loaded('iyzico-woocommerce')) {
            $custom_links[] = '<a href="'.\admin_url('admin.php?page=wc-settings&tab=checkout&section=iyzico').'">Settings</a>';
            $custom_links[] = '<a target="_blank" href="https://docs.iyzico.com/">Docs</a>';
            $custom_links[] = '<a target="_blank" href="https://iyzico.com/destek/iletisim">Support</a>';
        } else {
            $custom_links[] = '<a href="'.\admin_url('admin.php?page=wc-settings&tab=checkout&section=iyzico').'">'.\esc_html__(
                    'Settings',
                    'iyzico-woocommerce'
                ).'</a>';
            $custom_links[] = '<a target="_blank" href="https://docs.iyzico.com/">'.\esc_html__('Docs',
                    'iyzico-woocommerce').'</a>';
            $custom_links[] = '<a target="_blank" href="https://iyzico.com/destek/iletisim">'.\esc_html__(
                    'Support',
                    'iyzico-woocommerce'
                ).'</a>';
        }

        return array_merge($custom_links, $links);
    }

    /**
     * Maybe redirect to onboarding screen after activation
     */
    public function maybeRedirectToOnboarding()
    {
        // Check if we should redirect
        if (!\get_transient('iyzico_onboarding_redirect')) {
            return;
        }

        // Delete transient
        \delete_transient('iyzico_onboarding_redirect');

        // Don't redirect if doing AJAX or if it's a bulk activation
        if (\wp_doing_ajax() || (isset($_GET['activate-multi']) && $_GET['activate-multi'])) {
            return;
        }

        // Check user capabilities
        if (!\current_user_can('manage_woocommerce')) {
            return;
        }

        // Redirect to onboarding page
        \wp_safe_redirect(\admin_url('admin.php?page=iyzico-onboarding'));
        exit;
    }
}
