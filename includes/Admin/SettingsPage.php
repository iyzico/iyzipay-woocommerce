<?php

namespace Iyzico\IyzipayWoocommerce\Admin;

use Iyzico\IyzipayWoocommerce\Checkout\CheckoutSettings;
use Iyzico\IyzipayWoocommerce\Pwi\PwiSettings;
use Iyzico\IyzipayWoocommerce\Rest\RestAPI;

class SettingsPage {

	private $checkoutSettings;
	private $pwiSettings;
	private $restApi;

	public function __construct() {
		$this->checkoutSettings = new CheckoutSettings();
		$this->pwiSettings      = new PwiSettings();
		$this->restApi          = new RestAPI();
	}

	public function renderAdminOptions() {
		?>
        <style>
            .woocommerce-save-button {
                display: none !important;
            }
        </style>
        <h3>
			<?php esc_html_e( 'These payment method settings are made through the admin menu.', 'woocommerce-iyzico' ); ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=iyzico' ) ); ?>">
				<?php esc_html_e( 'Click to go to settings.', 'woocommerce-iyzico' ); ?>
            </a>
        </h3>
		<?php
		$this->getHtmlContent();
	}

	public function getHtmlContent() {
		$html = '<style scoped>
                    @media (max-width:768px){.iyziBrand{position:fixed;bottom:0;top:auto!important;right:0!important}}
                </style>
                <div class="iyziBrandWrap">
                    <div class="iyziBrand" style="clear:both;position:absolute;right: 50px;top:440px;display: flex;flex-direction: column;justify-content: center;">
                        <img src=' . PLUGIN_URL . '/assets/images/iyzico_logo.png style="width: 250px;margin-left: auto;">
                        <p style="text-align:center;"><strong>Version: </strong>3.5.8</p>
                    </div>
                </div>';
		echo $html;
	}

	public function renderPage(): void {
		include_once PLUGIN_DIR_PATH . 'views/index.php';
	}

	public function addAdminMenu(): void {
		add_menu_page(
			'iyzico',
			'iyzico',
			'manage_options',
			'iyzico',
			[ $this, 'renderPage' ],
			PLUGIN_URL . '/assets/images/icon.png',
			59
		);
	}

	public function enqueueAdminAssets( $hook ): void {
		if ( 'toplevel_page_iyzico' !== $hook ) {
			return;
		}

		$this->enqueueAdminScript();
		$this->enqueueAdminStyle();
	}

	public function enqueueAdminScript(): void {
		$asset_file = PLUGIN_DIR_PATH . 'assets/admin/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_script(
			'AdminScript',
			plugins_url( 'assets/admin/index.js', PLUGIN_BASEFILE ),
			$asset['dependencies'],
			$asset['version'],
			true  // Corrected line
		);

		wp_set_script_translations(
			'AdminScript', // script handle
			'woocommerce-iyzico',         // text domain
			plugin_dir_path( __FILE__ ) . 'i18n/languages'
		);

		$this->restApi->localizeScript();
	}

	public function enqueueAdminStyle(): void {
		$asset_file = PLUGIN_DIR_PATH . 'assets/admin/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_style(
			'AdminStyle',
			plugins_url( 'assets/admin/index.css', PLUGIN_BASEFILE ),
			array_filter(
				$asset['dependencies'],
				function ( $style ) {
					return wp_style_is( $style, 'registered' );
				}
			),
			$asset['version']
		);
	}
}