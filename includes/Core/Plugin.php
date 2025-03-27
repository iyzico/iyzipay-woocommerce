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
use Plugin_Upgrader;

class Plugin
{

    use PluginLoader;

    public static function activate()
    {
        DatabaseManager::createTables();
    }

    public static function deactivate()
    {
        global $wpdb;
        $logger = new Logger();
        DatabaseManager::init($wpdb, $logger);
        DatabaseManager::dropTables();

        delete_option('iyzico_overlay_token');
        delete_option('iyzico_overlay_position');
        delete_option('iyzico_thank_you');
        delete_option('init_active_webhook_url');
        delete_option('iyzico_db_version');

        flush_rewrite_rules();
    }

    public function run()
    {
        $this->loadDependencies();
        $this->setLocale();
        $this->defineAdminHooks();
        $this->definePublicHooks();
        $this->initPaymentGateway();
        $this->generateWebhookKey();
        $this->checkDatabaseUpdate();

        BlocksSupport::init();
        HighPerformanceOrderStorageSupport::init();
    }

    private function loadDependencies(): void
    {
        require_once PLUGIN_PATH . '/includes/Common/Helpers/BlocksSupport.php';
        require_once PLUGIN_PATH . '/includes/Common/Helpers/HighPerformanceOrderStorageSupport.php';

        require_once PLUGIN_PATH . '/includes/Admin/SettingsPage.php';
        require_once PLUGIN_PATH . '/includes/Common/Hooks/AdminHooks.php';

        require_once PLUGIN_PATH . '/includes/Checkout/CheckoutSettings.php';
        require_once PLUGIN_PATH . '/includes/Common/Helpers/WebhookHelper.php';

        require_once PLUGIN_PATH . '/includes/Common/Hooks/PublicHooks.php';

        require_once PLUGIN_PATH . '/includes/Checkout/CheckoutForm.php';
        require_once PLUGIN_PATH . '/includes/Checkout/BlocksCheckoutMethod.php';

        require_once PLUGIN_PATH . '/includes/Pwi/Pwi.php';
        require_once PLUGIN_PATH . '/includes/Pwi/BlocksPwiMethod.php';
    }

    private function setLocale()
    {
        add_action('init', function () {
            load_plugin_textdomain('iyzico-woocommerce', false, PLUGIN_LANG_PATH);
        });
    }

    private function defineAdminHooks()
    {
        if (is_admin()) {
            add_filter(
                'plugin_action_links_' . plugin_basename(PLUGIN_BASEFILE),
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
        add_filter('woocommerce_payment_gateways', [$this, 'addGateways']);
    }

    private function generateWebhookKey()
    {
        $uniqueUrlId = substr(base64_encode(time() . wp_rand()), 15, 6);
        $iyziUrlId = get_option("iyzicoWebhookUrlKey");
        if (!$iyziUrlId) {
            add_option("iyzicoWebhookUrlKey", $uniqueUrlId, '', false);
        }
    }

    public function checkDatabaseUpdate()
    {
        $installed_version = get_option('iyzico_db_version', '0');

        if (version_compare($installed_version, IYZICO_DB_VERSION, '<')) {
            DatabaseManager::updateTables();
            update_option('iyzico_db_version', IYZICO_DB_VERSION);
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
        $custom_links[] = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=iyzico') . '">' . __(
                'Settings',
                'iyzico-woocommerce'
            ) . '</a>';
        $custom_links[] = '<a target="_blank" href="https://docs.iyzico.com/">' . __('Docs', 'iyzico-woocommerce') . '</a>';
        $custom_links[] = '<a target="_blank" href="https://iyzico.com/destek/iletisim">' . __(
                'Support',
                'iyzico-woocommerce'
            ) . '</a>';

        return array_merge($custom_links, $links);
    }
}
