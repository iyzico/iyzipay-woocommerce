<?php

namespace Iyzico\IyzipayWoocommerce\Database;

use Exception;
use Iyzico\IyzipayWoocommerce\Common\Helpers\Logger;

class DatabaseManager {
	private static $wpdb;
	private static Logger $logger;

	/**
	 * @param $wpdb
	 * @param Logger $logger
	 *
	 * @return void
	 */
	public static function init( $wpdb, Logger $logger ): void {
		self::$wpdb   = $wpdb;
		self::$logger = $logger;
	}

	private static function ensureInitialized(): void {
		if ( ! isset( self::$wpdb ) || self::$wpdb === null ) {
			global $wpdb;
			self::$wpdb = $wpdb;
		}
		if ( ! isset( self::$logger ) ) {
			self::$logger = new Logger();
		}
	}

	public static function createTables(): void {
		self::ensureInitialized();
		try {
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

			global $wpdb;
			$table_name      = $wpdb->prefix . 'iyzico_order';
			$table_name2     = $wpdb->prefix . 'iyzico_card';
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE $table_name (
                iyzico_order_id int(11) NOT NULL AUTO_INCREMENT,
                payment_id  bigint(11) NOT NULL,
                order_id int(11) NOT NULL,
                total_amount decimal( 10, 2 ) NOT NULL,
                status varchar(20) NOT NULL,
                created_at  timestamp DEFAULT current_timestamp,
              PRIMARY KEY (iyzico_order_id)
            ) $charset_collate;";
			dbDelta( $sql );

			$sql = "CREATE TABLE $table_name2 (
                iyzico_card_id int(11) NOT NULL AUTO_INCREMENT,
                customer_id INT(11) NOT NULL,
                card_user_key varchar(50) NOT NULL,
                api_key varchar(50) NOT NULL,
                created_at  timestamp DEFAULT current_timestamp,
               PRIMARY KEY (iyzico_card_id)
            ) $charset_collate;";
			dbDelta( $sql );

			self::$logger->info( 'Tables created successfully' );
		} catch ( Exception $e ) {
			self::$logger->error( 'Error creating tables: ' . $e->getMessage() );
		}
	}

	public static function dropTables(): void {
		self::ensureInitialized();
		try {
			global $wpdb;
			delete_option( 'iyzico_overlay_token' );
			delete_option( 'iyzico_overlay_position' );
			delete_option( 'iyzico_thank_you' );
			delete_option( 'init_active_webhook_url' );

			$table_name  = $wpdb->prefix . 'iyzico_order';
			$table_name2 = $wpdb->prefix . 'iyzico_card';

			$sql = "DROP TABLE IF EXISTS $table_name;";
			$wpdb->query( $sql );
			$sql = "DROP TABLE IF EXISTS $table_name2;";
			$wpdb->query( $sql );
			flush_rewrite_rules();

			self::$logger->info( 'Tables dropped successfully' );
		} catch ( Exception $e ) {
			self::$logger->error( 'Error dropping tables: ' . $e->getMessage() );
		}
	}

	/**
	 * @param $paymentId * iyzico Response PaymentId
	 * @param $orderId * WooCommerce OrderId
	 * @param $totalAmount * Order Total Amount
	 * @param $status * Order Status
	 *
	 * @return mixed
	 */
	public static function createOrder( $paymentId, $orderId, $totalAmount, $status ) {
		self::ensureInitialized();
		$tableName = self::$wpdb->prefix . 'iyzico_order';

		return self::$wpdb->insert(
			$tableName,
			[
				'payment_id'   => $paymentId,
				'order_id'     => $orderId,
				'total_amount' => $totalAmount,
				'status'       => $status
			],
			[ '%s', '%d', '%f', '%s' ]
		);
	}

	public static function findOrderByOrderId( $orderId ) {
		self::ensureInitialized();
		$tableName = self::$wpdb->prefix . 'iyzico_order';

		$sql = self::$wpdb->prepare( "
			SELECT *
			FROM $tableName
			WHERE order_id = %d
			ORDER BY iyzico_order_id DESC LIMIT 1;
		", $orderId );

		return self::$wpdb->get_row( $sql, ARRAY_A );
	}

	public function findUserCardKey( $customerId, $apiKey ) {
		$tableName = self::$wpdb->prefix . 'iyzico_card';
		$fieldName = 'card_user_key';

		$sql = self::$wpdb->prepare( "
			SELECT $fieldName
			FROM $tableName
			WHERE customer_id = %d AND api_key = %s
			ORDER BY iyzico_card_id DESC LIMIT 1;
		", $customerId, $apiKey );

		$result = self::$wpdb->get_col( $sql );

		return $result[0] ?? null;
	}

	public function saveUserCardKey( $customerId, $cardUserKey, $apiKey ) {
		$tableName = self::$wpdb->prefix . 'iyzico_card';

		return self::$wpdb->insert(
			$tableName,
			[
				'customer_id'   => $customerId,
				'card_user_key' => $cardUserKey,
				'api_key'       => $apiKey
			],
			[ '%d', '%s', '%s' ]
		);
	}


}
