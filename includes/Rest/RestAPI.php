<?php

namespace Iyzico\IyzipayWoocommerce\Rest;

use Iyzico\IyzipayWoocommerce\Common\Helpers\AdminHelper;

class RestAPI {
	private $adminHelper;

	public function __construct() {
		$this->adminHelper = new AdminHelper();
	}

	public function localizeScript(): void {
		wp_localize_script(
			'AdminScript',
			'iyzicoRestApi',
			[
				'SettingsDashboardWidgetsUrl' => $this->get_rest_url( 'SettingsDashboardWidgetsRoute' ),
				'SettingsDashboardChartsUrl'  => $this->get_rest_url( 'SettingsDashboardChartsRoute' ),
				'GetOrdersUrl'                => $this->get_rest_url( 'getOrdersRoute' ),
				'SaveSettingsUrl'             => $this->get_rest_url( 'saveSettingsRoute' ),
				'SettingsUrl'                 => $this->get_rest_url( 'getSettingsRoute' ),
				'LocalizationsUrl'            => $this->get_rest_url( 'GetLocalizationsRoute' ),
				'nonce'                       => wp_create_nonce( 'wp_rest' )
			]
		);
	}

	private function get_rest_url( $route ): string {
		return esc_url_raw( rest_url( "iyzico/v1/{$route}" ) );
	}

	public function addRestRoutes(): void {
		$routes = [
			'SettingsDashboardWidgetsRoute' => [ 'GET', 'getSettingsDashboardWidgetsMethod' ],
			'SettingsDashboardChartsRoute'  => [ 'GET', 'getSettingsDashboardChartsMethod' ],
			'getOrdersRoute'                => [ 'GET', 'getOrdersMethod' ],
			'saveSettingsRoute'             => [ 'POST', 'saveSettingsMethod' ],
			'getSettingsRoute'              => [ 'GET', 'getSettingsMethod' ],
			'GetLocalizationsRoute'         => [ 'GET', 'getLocalizationsMethod' ],
		];

		foreach ( $routes as $route => $config ) {
			register_rest_route( 'iyzico/v1', "/{$route}", [
				'methods'             => $config[0],
				'callback'            => [ $this, $config[1] ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				}
			] );
		}
	}

	public function getSettingsDashboardWidgetsMethod() {
		return rest_ensure_response( $this->adminHelper->getSettingsDashboardWidgets() );
	}

	public function getSettingsDashboardChartsMethod() {
		return rest_ensure_response( $this->adminHelper->getSettingsDashboardCharts() );
	}

	public function getSettingsMethod() {
		return rest_ensure_response( $this->adminHelper->getSettings() );
	}

	public function saveSettingsMethod( $request ) {
		return rest_ensure_response( $this->adminHelper->saveSettings( $request ) );
	}

	public function getOrdersMethod( $request ) {
		return rest_ensure_response( $this->adminHelper->getOrders( $request ) );
	}

	public function getLocalizationsMethod() {
		$locale    = get_locale();
		$file_path = PLUGIN_DIR_PATH . 'i18n/languages/localizations.json';
		$data      = file_get_contents( $file_path );
		$json_data = json_decode( $data, true );

		if ( isset( $json_data[ $locale ] ) ) {
			return rest_ensure_response( $json_data[ $locale ] );
		} else {
			return rest_ensure_response( $json_data['tr_TR'] );
		}

	}


}