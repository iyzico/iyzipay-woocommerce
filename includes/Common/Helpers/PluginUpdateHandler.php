<?php

namespace Iyzico\IyzipayWoocommerce\Common\Helpers;

class PluginUpdateHandler
{
    /**
     * Handle plugin updates to ensure XML functionality works
     */
    public static function handlePluginUpdate($upgrader, $hook_extra)
    {
        // Sadece plugin güncellemelerini kontrol et
        if (!isset($hook_extra['action']) || $hook_extra['action'] !== 'update') {
            return;
        }

        if (!isset($hook_extra['type']) || $hook_extra['type'] !== 'plugin') {
            return;
        }

        // Sadece bu eklentinin güncellendiğini kontrol et
        $plugin_file = plugin_basename(PLUGIN_BASEFILE);
        if (!isset($hook_extra['plugins']) || !in_array($plugin_file, $hook_extra['plugins'])) {
            return;
        }

        // XML hook'larını yeniden kaydet
        self::ensureXmlHooksRegistered();
        
        // XML cron job'ını yeniden planla
        self::rescheduleXmlCron();
        
        // Logger ile bilgi ver
        if (class_exists('\Iyzico\IyzipayWoocommerce\Common\Helpers\Logger')) {
            $logger = new Logger();
            $logger->info('Iyzico Plugin Update: XML hooks re-registered after update');
        }
    }

    /**
     * Ensure XML hooks are registered
     */
    private static function ensureXmlHooksRegistered()
    {
        // Hook'ların kayıtlı olup olmadığını kontrol et
        if (!has_action('iyzico_generate_google_products_xml')) {
            add_action('iyzico_generate_google_products_xml', function() {
                try {
                    $xmlGenerator = new GoogleProductsXml();
                    $xmlGenerator->generateXml();
                } catch (Exception $e) {
                    $logger = new Logger();
                    $logger->error('Iyzico Google XML: Cron generation failed - ' . $e->getMessage());
                }
            });
        }

        if (!has_action('iyzico_generate_google_products_xml_activation')) {
            add_action('iyzico_generate_google_products_xml_activation', function() {
                try {
                    $xmlGenerator = new GoogleProductsXml();
                    $xmlGenerator->generateXml();
                } catch (Exception $e) {
                    $logger = new Logger();
                    $logger->error('Iyzico Plugin Activation: XML generation failed - ' . $e->getMessage());
                }
            });
        }
    }

    /**
     * Reschedule XML cron job
     */
    private static function rescheduleXmlCron()
    {
        // Mevcut cron job'ı temizle
        wp_clear_scheduled_hook('iyzico_generate_google_products_xml');
        
        // Yeni cron job planla
        if (!wp_next_scheduled('iyzico_generate_google_products_xml')) {
            wp_schedule_event(time(), 'daily', 'iyzico_generate_google_products_xml');
        }
    }
} 