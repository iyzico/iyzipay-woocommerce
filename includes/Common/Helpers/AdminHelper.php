<?php

namespace Iyzico\IyzipayWoocommerce\Common\Helpers;

use Iyzico\IyzipayWoocommerce\Checkout\CheckoutSettings;
use Iyzico\IyzipayWoocommerce\Pwi\PwiSettings;

class AdminHelper {
	public function getSettingsDashboardWidgets(): array {
		$args   = [
			'status'       => [ 'wc-completed', 'wc-processing', 'wc-on-hold' ],
			'date_created' => '>=' . date( 'Y-m-d', strtotime( '-30 days' ) ),
			'return'       => 'ids',
		];
		$orders = wc_get_orders( $args );

		$total_earnings = 0;
		foreach ( $orders as $order_id ) {
			$order          = wc_get_order( $order_id );
			$total_earnings += $order->get_total();
		}

		$total_orders = count( $orders );

		$args_last_month           = [
			'status'       => [ 'wc-completed', 'wc-processing', 'wc-on-hold' ],
			'date_created' => date( 'Y-m-d', strtotime( '-60 days' ) ) . '...' . date( 'Y-m-d', strtotime( '-31 days' ) ),
			'return'       => 'ids',
		];
		$orders_last_month         = wc_get_orders( $args_last_month );
		$total_earnings_last_month = 0;
		foreach ( $orders_last_month as $order_id ) {
			$order                     = wc_get_order( $order_id );
			$total_earnings_last_month += $order->get_total();
		}

		$growth = $total_earnings_last_month > 0
			? ( ( $total_earnings - $total_earnings_last_month ) / $total_earnings_last_month ) * 100
			: 0;

		return [
			[
				'key'     => 'last_30_days_total_balance',
				'title'   => __( "Son 30 Günlük Kazanç", 'woocommerce-iyzico' ),
				'value'   => '₺' . number_format( $total_earnings, 2 ),
				'icon'    => 'DollarSign',
				'visible' => true,
			],
			[
				'key'     => 'growth_from_last_month',
				'title'   => __( "Geçen Aya Göre Büyüme", 'woocommerce-iyzico' ),
				'value'   => number_format( $growth, 2 ) . '%',
				'icon'    => 'TrendingUp',
				'visible' => true,
			],
			[
				'key'     => 'last_30_days_order_count',
				'title'   => __( "Son 30 Gündeki Sipariş Sayısı", 'woocommerce-iyzico' ),
				'value'   => $total_orders,
				'icon'    => 'ShoppingCart',
				'visible' => true,
			],
		];
	}

	public function getSettingsDashboardCharts(): array {
		$chart_data = [];

		for ( $i = 29; $i >= 0; $i -- ) {
			$date = date( 'Y-m-d', strtotime( '-' . $i . ' days' ) );

			$args   = [
				'status'       => [ 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending', 'wc-failed' ],
				'date_created' => $date,
				'return'       => 'ids',
			];
			$orders = wc_get_orders( $args );

			$total         = count( $orders );
			$status_counts = [
				'completed'  => 0,
				'processing' => 0,
				'pending'    => 0,
				'failed'     => 0,
			];

			foreach ( $orders as $order_id ) {
				$order  = wc_get_order( $order_id );
				$status = $order->get_status();
				if ( isset( $status_counts[ $status ] ) ) {
					$status_counts[ $status ] ++;
				}
			}

			$chart_data[] = [
				'day'        => date( 'd M', strtotime( $date ) ),
				'total'      => $total,
				'completed'  => $status_counts['completed'],
				'processing' => $status_counts['processing'],
				'pending'    => $status_counts['pending'],
				'failed'     => $status_counts['failed'],
			];
		}

		$top_products_data   = $this->getTopProductsData();
		$top_categories_data = $this->getTopCategoriesData();

		return [
			'ordersData'        => $chart_data,
			'topProductsData'   => $top_products_data,
			'topCategoriesData' => $top_categories_data,
		];
	}

	public function getSettings() {
		$checkoutSettings = new CheckoutSettings();
		$pwiSettings      = new PwiSettings();

		return [
			'iyzicoWebhookUrlKey' => get_site_url() . "/wp-json/iyzico/v1/webhook/" . get_option( 'iyzicoWebhookUrlKey' ),
			'checkout'            => $checkoutSettings->getSettings(),
			'pwi'                 => $pwiSettings->getSettings()
		];
	}

	public function saveSettings( $request ) {
		$checkoutSettings = new CheckoutSettings();
		$pwiSettings      = new PwiSettings();

		$request    = $request->get_params();
		$pwi_enable = $request['pwi_enabled'];
		unset( $request['pwi_enabled'] );
		unset( $request['rest_route'] );
		unset( $request['_locale'] );

		$pwi_data            = [];
		$pwi_data["enabled"] = $pwi_enable;

		$checkoutSettings->setSettings( $request );
		$pwiSettings->setSettings( $pwi_data );

		return rest_ensure_response( [
			'success' => true,
		] );
	}

	public function getOrders( $request ) {
		$page     = $request->get_param( 'page' ) ? (int) $request->get_param( 'page' ) : 1;
		$per_page = $request->get_param( 'per_page' ) ? (int) $request->get_param( 'per_page' ) : 10;
		$search   = $request->get_param( 'search' ) ? sanitize_text_field( $request->get_param( 'search' ) ) : '';
		$status   = $request->get_param( 'status' ) ? sanitize_text_field( $request->get_param( 'status' ) ) : '';

		$args = [
			'limit'    => $per_page,
			'offset'   => ( $page - 1 ) * $per_page,
			'paginate' => true,
		];

		if ( ! empty( $status ) && $status !== 'all' ) {
			$args['status'] = [ 'wc-' . $status ];
		} else {
			$args['status'] = [
				'wc-pending',
				'wc-processing',
				'wc-on-hold',
				'wc-completed',
				'wc-cancelled',
				'wc-refunded',
				'wc-failed'
			];
		}

		if ( ! empty( $search ) ) {
			$args['id'] = $search;
		}

		$orders_query = wc_get_orders( $args );
		$orders       = $orders_query->orders;
		$total_orders = $orders_query->total;
		$total_pages  = $orders_query->max_num_pages;

		$orders_data = [];

		foreach ( $orders as $order ) {
			$orders_data[] = [
				'id'       => $order->get_id(),
				'customer' => $order->get_formatted_billing_full_name(),
				'date'     => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
				'total'    => $order->get_formatted_order_total(),
				'status'   => $order->get_status(),
			];
		}

		return rest_ensure_response( [
			'orders'       => $orders_data,
			'total'        => $total_orders,
			'total_pages'  => $total_pages,
			'current_page' => $page,
		] );
	}

	private function getTopProductsData(): array {
		global $wpdb;

		$results = $wpdb->get_results( "
        SELECT order_item_meta.meta_value AS product_id, SUM(order_item_meta_qty.meta_value) AS quantity
        FROM {$wpdb->prefix}woocommerce_order_items AS order_items
        LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
        LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_item_meta_qty ON order_items.order_item_id = order_item_meta_qty.order_item_id
        WHERE order_items.order_item_type = 'line_item'
        AND order_item_meta.meta_key = '_product_id'
        AND order_item_meta_qty.meta_key = '_qty'
        GROUP BY product_id
        ORDER BY quantity DESC
        LIMIT 5
    " );

		$top_products_data = [];
		foreach ( $results as $row ) {
			$product = wc_get_product( $row->product_id );
			if ( $product ) {
				$top_products_data[] = [
					'name'  => $product->get_name(),
					'value' => (int) $row->quantity,
				];
			}
		}

		return $top_products_data;
	}

	private function getTopCategoriesData(): array {
		global $wpdb;

		$results = $wpdb->get_results( "
        SELECT term_taxonomy.term_id, COUNT(*) as count
        FROM {$wpdb->prefix}term_relationships AS term_relationships
        INNER JOIN {$wpdb->prefix}term_taxonomy AS term_taxonomy ON term_relationships.term_taxonomy_id = term_taxonomy.term_taxonomy_id
        INNER JOIN {$wpdb->prefix}posts AS posts ON term_relationships.object_id = posts.ID
        WHERE term_taxonomy.taxonomy = 'product_cat' AND posts.post_type = 'product' AND posts.post_status = 'publish'
        GROUP BY term_taxonomy.term_id
        ORDER BY count DESC
        LIMIT 5
    " );

		$top_categories_data = [];
		foreach ( $results as $row ) {
			$term = get_term( $row->term_id );
			if ( $term ) {
				$top_categories_data[] = [
					'name'  => $term->name,
					'value' => (int) $row->count,
				];
			}
		}

		return $top_categories_data;
	}
}