<?php

namespace Iyzico\IyzipayWoocommerce\Pwi;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Iyzico\IyzipayWoocommerce\Checkout\CheckoutSettings;

/**
 * Class BlocksPwiMethod
 *
 * @extends AbstractPaymentMethodType
 */
class BlocksPwiMethod extends AbstractPaymentMethodType
{

	public $gateway;
	public $pwiSettings;
	public $checkoutSettings;
	protected $name = 'pwi';

	public function __construct()
	{
		$this->pwiSettings = new PwiSettings();
		$this->checkoutSettings = new CheckoutSettings();
	}


	public function initialize(): void
	{
		$this->settings = $this->pwiSettings->getSettings();
	}

	public function is_active(): bool
	{
		return !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
	}

	public function get_payment_method_script_handles(): array
	{
		$dependencies = [];
		$version = time();

		$path = plugin_dir_path(PLUGIN_BASEFILE) . 'assets/blocks/woocommerce/blocks.asset.php';

		if (file_exists($path)) {
			$asset = require $path;
			$version = filemtime(plugin_dir_path(PLUGIN_BASEFILE) . 'assets/blocks/woocommerce/blocks.js');
			$dependencies = is_null($asset['dependencies']);
		}

		wp_register_script(
			'wc-pwi-blocks-integration',
			plugin_dir_url(PLUGIN_BASEFILE) . 'assets/blocks/woocommerce/blocks.js',
			$dependencies,
			$version,
			true
		);

		return ['wc-pwi-blocks-integration'];
	}

	public function get_payment_method_data(): array
	{
		$title = $this->settings['title'];
		$description = $this->settings['description'];
		$lang = "TR";
		$image_path = plugin_dir_url(PLUGIN_BASEFILE) . 'assets/images/pwi_tr.png';

		if (strlen($this->checkoutSettings->findByKey('form_language')) > 0) {
			$lang = $this->checkoutSettings->findByKey('form_language');
		}

		if ($lang == "EN") {
			$image_path = plugin_dir_url(PLUGIN_BASEFILE) . 'assets/images/pwi_en.png';
		}

		return [
			'title' => $title,
			'description' => $description,
			'icon' => $image_path
		];
	}
}
