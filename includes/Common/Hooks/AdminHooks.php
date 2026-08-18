<?php

namespace Iyzico\IyzipayWoocommerce\Common\Hooks;

use Iyzico\IyzipayWoocommerce\Checkout\CheckoutForm;
use Iyzico\IyzipayWoocommerce\Pwi\Pwi;
use Iyzico\IyzipayWoocommerce\Admin\OnboardingScreen;

class AdminHooks
{
    private $checkoutForm = null;
    private $pwi = null;

    public function register(): void
    {
        \add_action('woocommerce_update_options_payment_gateways_iyzico', function () {
            $this->getCheckoutForm()->process_admin_options();
        });

        \add_action('woocommerce_update_options_payment_gateways_pwi', function () {
            $this->getPwi()->process_admin_options();
        });

        \add_action('woocommerce_update_options_payment_gateways_iyzico', function () {
            $this->getCheckoutForm()->admin_overlay_script();
        });

        // Register onboarding page
        \add_action('admin_menu', [$this, 'registerOnboardingPage']);

        // Register AJAX handlers
        \add_action('wp_ajax_iyzico_save_api_keys', [$this, 'handleSaveApiKeys']);
    }

    private function getCheckoutForm()
    {
        if ($this->checkoutForm === null) {
            $this->checkoutForm = new CheckoutForm();
        }
        return $this->checkoutForm;
    }

    private function getPwi()
    {
        if ($this->pwi === null) {
            $this->pwi = new Pwi();
        }
        return $this->pwi;
    }

    /**
     * Register hidden onboarding page
     */
    public function registerOnboardingPage()
    {
        \add_submenu_page(
            null, // Parent slug - null makes it hidden from menu
            \__('iyzico Onboarding', 'iyzico-woocommerce'),
            \__('iyzico Onboarding', 'iyzico-woocommerce'),
            'manage_woocommerce',
            'iyzico-onboarding',
            [$this, 'renderOnboardingPage']
        );
    }

    /**
     * Render onboarding page
     */
    public function renderOnboardingPage()
    {
        $onboardingScreen = new OnboardingScreen();
        $onboardingScreen->render();
    }

    /**
     * Handle API keys save via AJAX
     */
    public function handleSaveApiKeys()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !\wp_verify_nonce($_POST['nonce'], 'iyzico_onboarding')) {
            \wp_send_json_error([
                'message' => \__('Security verification failed.', 'iyzico-woocommerce')
            ]);
        }

        // Check user capabilities
        if (!\current_user_can('manage_woocommerce')) {
            \wp_send_json_error([
                'message' => \__('You do not have permission to perform this action.', 'iyzico-woocommerce')
            ]);
        }

        // Validate required fields
        if (empty($_POST['api_key']) || empty($_POST['secret_key']) || empty($_POST['api_type'])) {
            \wp_send_json_error([
                'message' => \__('Please fill in all required fields.', 'iyzico-woocommerce')
            ]);
        }

        // Get current settings
        $settings = \get_option('woocommerce_iyzico_settings', []);

        // Update settings
        $settings['api_key'] = \sanitize_text_field($_POST['api_key']);
        $settings['secret_key'] = \sanitize_text_field($_POST['secret_key']);
        $settings['api_type'] = \sanitize_text_field($_POST['api_type']);
        $settings['enabled'] = 'yes'; // Enable the gateway

        // Save settings
        \update_option('woocommerce_iyzico_settings', $settings);

        \wp_send_json_success([
            'message' => \__('Settings saved successfully!', 'iyzico-woocommerce'),
            'redirect' => \admin_url('admin.php?page=wc-settings&tab=checkout&section=iyzico')
        ]);
    }
}