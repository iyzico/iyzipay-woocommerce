<?php

namespace Iyzico\IyzipayWoocommerce\Admin;

class OnboardingScreen
{
    /**
     * Render the onboarding screen
     */
    public function render()
    {
        $logo_url = \esc_url(PLUGIN_URL) . '/assets/images/iyzico_logo.png';
        $step = isset($_GET['step']) ? \sanitize_text_field($_GET['step']) : 'welcome';
        
        ?>
        <div class="wrap iyzico-onboarding-wrap">
            <style>
                .iyzico-onboarding-wrap {
                    max-width: 800px;
                    margin: 50px auto;
                    padding: 20px;
                }
                .iyzico-onboarding-header {
                    text-align: center;
                    margin-bottom: 40px;
                }
                .iyzico-onboarding-header img {
                    max-width: 250px;
                    height: auto;
                }
                .iyzico-onboarding-header h1 {
                    margin-top: 20px;
                    font-size: 28px;
                    color: #23282d;
                }
                .iyzico-onboarding-header p {
                    font-size: 16px;
                    color: #666;
                }
                .iyzico-option-cards {
                    display: flex;
                    gap: 20px;
                    margin: 40px 0;
                }
                .iyzico-option-card {
                    flex: 1;
                    background: #fff;
                    border: 2px solid #ddd;
                    border-radius: 8px;
                    padding: 30px;
                    text-align: center;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    text-decoration: none;
                    color: inherit;
                }
                .iyzico-option-card:hover {
                    border-color: #0073aa;
                    box-shadow: 0 4px 12px rgba(0,115,170,0.1);
                    transform: translateY(-2px);
                }
                .iyzico-option-card h2 {
                    margin: 20px 0 10px;
                    font-size: 20px;
                    color: #23282d;
                }
                .iyzico-option-card p {
                    color: #666;
                    font-size: 14px;
                }
                .iyzico-option-card .dashicons {
                    font-size: 48px;
                    width: 48px;
                    height: 48px;
                    color: #0073aa;
                }
                .iyzico-form-wrap {
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    padding: 40px;
                    margin: 20px 0;
                }
                .iyzico-form-wrap h2 {
                    margin-top: 0;
                    margin-bottom: 30px;
                    font-size: 24px;
                }
                .iyzico-form-row {
                    margin-bottom: 20px;
                }
                .iyzico-form-row label {
                    display: block;
                    margin-bottom: 8px;
                    font-weight: 600;
                    color: #23282d;
                }
                .iyzico-form-row input[type="text"],
                .iyzico-form-row input[type="email"],
                .iyzico-form-row select {
                    width: 100%;
                    padding: 10px;
                    font-size: 14px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                }
                .iyzico-form-row input[type="text"]:focus,
                .iyzico-form-row input[type="email"]:focus,
                .iyzico-form-row select:focus {
                    border-color: #0073aa;
                    outline: none;
                    box-shadow: 0 0 0 1px #0073aa;
                }
                .iyzico-form-row .description {
                    font-size: 13px;
                    color: #666;
                    margin-top: 5px;
                }
                .iyzico-form-actions {
                    margin-top: 30px;
                    display: flex;
                    gap: 10px;
                }
                .iyzico-btn {
                    padding: 12px 24px;
                    font-size: 14px;
                    border-radius: 4px;
                    cursor: pointer;
                    text-decoration: none;
                    display: inline-block;
                    border: none;
                }
                .iyzico-btn-primary {
                    background: #0073aa;
                    color: #fff;
                }
                .iyzico-btn-primary:hover {
                    background: #005a87;
                    color: #fff;
                }
                .iyzico-btn-secondary {
                    background: #f7f7f7;
                    color: #555;
                    border: 1px solid #ddd;
                }
                .iyzico-btn-secondary:hover {
                    background: #e9e9e9;
                }
                .iyzico-message {
                    padding: 12px 20px;
                    margin: 20px 0;
                    border-radius: 4px;
                    display: none;
                }
                .iyzico-message.success {
                    background: #d4edda;
                    border: 1px solid #c3e6cb;
                    color: #155724;
                    display: block;
                }
                .iyzico-message.error {
                    background: #f8d7da;
                    border: 1px solid #f5c6cb;
                    color: #721c24;
                    display: block;
                }
                @media (max-width: 768px) {
                    .iyzico-option-cards {
                        flex-direction: column;
                    }
                }
            </style>

            <div class="iyzico-onboarding-header">
                <img src="<?php echo \esc_url($logo_url); ?>" alt="iyzico Logo">
                <?php if ($step === 'welcome') : ?>
                    <h1><?php \_e('Welcome to iyzico for WooCommerce!', 'iyzico-woocommerce'); ?></h1>
                    <p><?php \_e('Let\'s get you started with accepting payments', 'iyzico-woocommerce'); ?></p>
                <?php endif; ?>
            </div>

            <div id="iyzico-onboarding-content">
                <?php
                switch ($step) {
                    case 'api-keys':
                        $this->renderApiKeysForm();
                        break;
                    default:
                        $this->renderWelcomeScreen();
                        break;
                }
                ?>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Handle API Keys form submission
            $('#iyzico-api-keys-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $submitBtn = $form.find('button[type="submit"]');
                var $message = $('#iyzico-message');
                
                $submitBtn.prop('disabled', true).text('<?php \_e('Saving...', 'iyzico-woocommerce'); ?>');
                $message.hide().removeClass('success error');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'iyzico_save_api_keys',
                        nonce: '<?php echo \wp_create_nonce('iyzico_onboarding'); ?>',
                        api_type: $form.find('[name="api_type"]').val(),
                        api_key: $form.find('[name="api_key"]').val(),
                        secret_key: $form.find('[name="secret_key"]').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            $message.addClass('success').text(response.data.message).show();
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 1500);
                        } else {
                            $message.addClass('error').text(response.data.message).show();
                            $submitBtn.prop('disabled', false).text('<?php \_e('Save Settings', 'iyzico-woocommerce'); ?>');
                        }
                    },
                    error: function() {
                        $message.addClass('error').text('<?php \_e('An error occurred. Please try again.', 'iyzico-woocommerce'); ?>').show();
                        $submitBtn.prop('disabled', false).text('<?php \_e('Save Settings', 'iyzico-woocommerce'); ?>');
                    }
                });
            });

            // Handle Application form submission
            $('#iyzico-application-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $submitBtn = $form.find('button[type="submit"]');
                var $message = $('#iyzico-message');
                
                $submitBtn.prop('disabled', true).text('<?php \_e('Submitting...', 'iyzico-woocommerce'); ?>');
                $message.hide().removeClass('success error');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'iyzico_submit_merchant_application',
                        nonce: '<?php echo \wp_create_nonce('iyzico_onboarding'); ?>',
                        contactName: $form.find('[name="contactName"]').val(),
                        contactSurname: $form.find('[name="contactSurname"]').val(),
                        registrationEmail: $form.find('[name="registrationEmail"]').val(),
                        gsmNumber: $form.find('[name="gsmNumber"]').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            $message.addClass('success').text(response.data.message).show();
                            $form[0].reset();
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 3000);
                        } else {
                            $message.addClass('error').text(response.data.message).show();
                            $submitBtn.prop('disabled', false).text('<?php \_e('Submit Application', 'iyzico-woocommerce'); ?>');
                        }
                    },
                    error: function() {
                        $message.addClass('error').text('<?php \_e('An error occurred. Please try again.', 'iyzico-woocommerce'); ?>').show();
                        $submitBtn.prop('disabled', false).text('<?php \_e('Submit Application', 'iyzico-woocommerce'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render welcome screen with options
     */
    private function renderWelcomeScreen()
    {
        $api_keys_url = \add_query_arg('step', 'api-keys', \admin_url('admin.php?page=iyzico-onboarding'));
        $skip_url = \admin_url('admin.php?page=wc-settings&tab=checkout&section=iyzico&from=WCADMIN_PAYMENT_SETTINGS');
        ?>
        <div class="iyzico-option-cards">
            <a href="<?php echo \esc_url($api_keys_url); ?>" class="iyzico-option-card">
                <span class="dashicons dashicons-admin-network"></span>
                <h2><?php \_e('I Have API Keys', 'iyzico-woocommerce'); ?></h2>
                <p><?php \_e('Configure your existing iyzico API credentials', 'iyzico-woocommerce'); ?></p>
            </a>
            
            <a href="<?php echo \esc_url('https://www.iyzico.com/isim-icin/hesap-olustur'); ?>" class="iyzico-option-card"  target="_blank" rel="noopener noreferrer">
                <span class="dashicons dashicons-edit"></span>
                <h2><?php \_e('I Want to Apply', 'iyzico-woocommerce'); ?></h2>
                <p><?php \_e('Apply for a new iyzico merchant account', 'iyzico-woocommerce'); ?></p>
            </a>
        </div>
        
        <div class="iyzico-form-actions" style="margin-top: 40px; text-align: center;">
            <a href="<?php echo \esc_url($skip_url); ?>" class="iyzico-btn iyzico-btn-secondary"><?php \_e('Skip Setup', 'iyzico-woocommerce'); ?></a>
        </div>
        <?php
    }

    /**
     * Render API Keys configuration form
     */
    private function renderApiKeysForm()
    {
        $back_url = \admin_url('admin.php?page=iyzico-onboarding');
        $skip_url = \admin_url('admin.php?page=wc-settings&tab=checkout&section=iyzico&from=WCADMIN_PAYMENT_SETTINGS');
        ?>
        <div class="iyzico-form-wrap">
            <h2><?php \_e('Configure API Keys', 'iyzico-woocommerce'); ?></h2>
            
            <div id="iyzico-message" class="iyzico-message"></div>
            
            <form id="iyzico-api-keys-form">
                <div class="iyzico-form-row">
                    <label for="api_type"><?php \_e('Environment', 'iyzico-woocommerce'); ?> <span style="color: red;">*</span></label>
                    <select name="api_type" id="api_type" required>
                        <option value="https://sandbox-api.iyzipay.com"><?php \_e('Sandbox / Test', 'iyzico-woocommerce'); ?></option>
                        <option value="https://api.iyzipay.com"><?php \_e('Live', 'iyzico-woocommerce'); ?></option>
                    </select>
                </div>

                <div class="iyzico-form-row">
                    <label for="api_key"><?php \_e('API Key', 'iyzico-woocommerce'); ?> <span style="color: red;">*</span></label>
                    <input type="text" name="api_key" id="api_key" required />
                    <p class="description"><?php \_e('Enter your iyzico API Key', 'iyzico-woocommerce'); ?></p>
                </div>

                <div class="iyzico-form-row">
                    <label for="secret_key"><?php \_e('Secret Key', 'iyzico-woocommerce'); ?> <span style="color: red;">*</span></label>
                    <input type="text" name="secret_key" id="secret_key" required />
                    <p class="description"><?php \_e('Enter your iyzico Secret Key', 'iyzico-woocommerce'); ?></p>
                </div>

                <div class="iyzico-form-actions">
                    <a href="<?php echo \esc_url($back_url); ?>" class="iyzico-btn iyzico-btn-secondary"><?php \_e('Back', 'iyzico-woocommerce'); ?></a>
                    <a href="<?php echo \esc_url($skip_url); ?>" class="iyzico-btn iyzico-btn-secondary"><?php \_e('Skip', 'iyzico-woocommerce'); ?></a>
                    <button type="submit" class="iyzico-btn iyzico-btn-primary"><?php \_e('Save Settings', 'iyzico-woocommerce'); ?></button>
                </div>
            </form>
        </div>
        <?php
    }
}

