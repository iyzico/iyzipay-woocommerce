<?php

namespace Iyzico\IyzipayWoocommerce\Database;

use Exception;
use Iyzico\IyzipayWoocommerce\Common\Helpers\Logger;

class DatabaseManager
{
    private static $wpdb;
    private static $logger;

    public static function init($wpdb, Logger $logger): void
    {
        self::$wpdb = $wpdb;
        self::$logger = $logger;
    }

    public static function createTables(): void
    {
        self::ensureInitialized();
        try {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            global $wpdb;
            $table_name = $wpdb->prefix . 'iyzico_order';
            $table_name2 = $wpdb->prefix . 'iyzico_card';
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE $table_name (
                iyzico_order_id int(11) NOT NULL AUTO_INCREMENT,
                payment_id  varchar(50),
                order_id int(11) NOT NULL,
                total_amount decimal( 10, 2 ),
                status varchar(20),
                created_at  timestamp DEFAULT current_timestamp,
              PRIMARY KEY (iyzico_order_id)
            ) $charset_collate;";
            dbDelta($sql);

            $sql = "CREATE TABLE $table_name2 (
                iyzico_card_id int(11) NOT NULL AUTO_INCREMENT,
                customer_id INT(11) NOT NULL,
                card_user_key varchar(50) NOT NULL,
                api_key varchar(50) NOT NULL,
                created_at  timestamp DEFAULT current_timestamp,
               PRIMARY KEY (iyzico_card_id)
            ) $charset_collate;";
            dbDelta($sql);

            self::$logger->info('Tables created successfully');
        } catch (Exception $e) {
            self::$logger->error('Error creating tables: ' . $e->getMessage());
        }
    }

    private static function ensureInitialized(): void
    {
        if (!isset(self::$wpdb) || self::$wpdb === null) {
            global $wpdb;
            self::$wpdb = $wpdb;
        }
        if (!isset(self::$logger)) {
            self::$logger = new Logger();
        }
    }

    public static function updateTables(): void
    {
        self::ensureInitialized();
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'iyzico_order';

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $table_exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $table_name
                )
            );

            if ($table_exists) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $conversation_id_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'conversation_id'");
                if (empty($conversation_id_exists)) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $wpdb->query("ALTER TABLE {$table_name} ADD conversation_id VARCHAR(50) NULL AFTER status");
                }

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $token_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'token'");
                if (empty($token_exists)) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $wpdb->query("ALTER TABLE {$table_name} ADD token VARCHAR(100) NULL AFTER conversation_id");
                }

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $payment_status_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'payment_status'");
                if (empty($payment_status_exists)) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $wpdb->query("ALTER TABLE {$table_name} ADD payment_status VARCHAR(50) NULL AFTER token");
                }

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query("ALTER TABLE {$table_name} MODIFY payment_id VARCHAR(50) NULL");
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query("ALTER TABLE {$table_name} MODIFY conversation_id VARCHAR(50) NULL");
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query("ALTER TABLE {$table_name} MODIFY token VARCHAR(100) NULL");

                self::$logger->info('Table columns added and modified successfully');
            } else {
                self::$logger->error('iyzico_order table does not exist');
            }
        } catch (Exception $e) {
            self::$logger->error('Error updating tables: ' . $e->getMessage());
        }
    }

    public static function dropTables(): void
    {
        self::ensureInitialized();
        try {
            global $wpdb;
            delete_option('iyzico_overlay_token');
            delete_option('iyzico_overlay_position');
            delete_option('iyzico_thank_you');
            delete_option('init_active_webhook_url');

            $table_name = $wpdb->prefix . 'iyzico_order';
            $table_name2 = $wpdb->prefix . 'iyzico_card';

            $sql = "DROP TABLE IF EXISTS $table_name;";
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query($sql);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $sql = "DROP TABLE IF EXISTS $table_name2;";
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query($sql);
            flush_rewrite_rules();

            self::$logger->info('Tables dropped successfully');
        } catch (Exception $e) {
            self::$logger->error('Error dropping tables: ' . $e->getMessage());
        }
    }

    public static function createOrder($paymentId, $orderId, $totalAmount, $status, $conversationId, $token, $paymentStatus)
    {
        self::ensureInitialized();
        $tableName = self::$wpdb->prefix . 'iyzico_order';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        return self::$wpdb->insert(
            $tableName,
            [
                'payment_id' => $paymentId,
                'order_id' => $orderId,
                'total_amount' => $totalAmount,
                'status' => $status,
                'conversation_id' => $conversationId,
                'token' => $token,
                'payment_status' => $paymentStatus
            ],
            ['%s', '%d', '%f', '%s', '%s', '%s', '%s']
        );
    }

    public static function createOrUpdateOrder($paymentId, $orderId, $conversationId, $token, $totalAmount, $status, $paymentStatus)
    {
        try {
            self::ensureInitialized();
            $tableName = self::$wpdb->prefix . 'iyzico_order';

            $existingOrder = self::findOrderByOrderId($orderId);
            if (is_array($existingOrder)) {
                $existingOrderId = $existingOrder['iyzico_order_id'];
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                self::$wpdb->update(
                    $tableName,
                    [
                        'payment_id' => $paymentId,
                        'order_id' => $orderId,
                        'conversation_id' => $conversationId,
                        'token' => $token,
                        'total_amount' => $totalAmount,
                        'status' => $status,
                        'payment_status' => $paymentStatus
                    ],
                    ['iyzico_order_id' => $existingOrderId],
                    ['%s', '%d', '%s', '%s', '%f', '%s', '%s'],
                    ['%d']
                );
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                self::$wpdb->insert(
                    $tableName,
                    [
                        'payment_id' => $paymentId,
                        'order_id' => $orderId,
                        'conversation_id' => $conversationId,
                        'token' => $token,
                        'total_amount' => $totalAmount,
                        'status' => $status,
                        'payment_status' => $paymentStatus
                    ],
                    ['%s', '%d', '%s', '%s', '%f', '%s', '%s']
                );
            }
        } catch (Exception $e) {
            self::$logger->error('Error in createOrUpdateOrder: ' . $e->getMessage());
            return false;
        }
    }

    public static function findOrderByOrderId($orderId)
    {
        self::ensureInitialized();
        $tableName = self::$wpdb->prefix . 'iyzico_order';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = self::$wpdb->prepare("
			SELECT *
			FROM $tableName
			WHERE order_id = %d
			ORDER BY iyzico_order_id DESC LIMIT 1;
		", $orderId);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        return self::$wpdb->get_row($sql, ARRAY_A);
    }

    public static function updateStatusByOrderId($orderId, $status)
    {
        self::ensureInitialized();
        $tableName = self::$wpdb->prefix . 'iyzico_order';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return self::$wpdb->update(
            $tableName,
            ['status' => $status],
            ['order_id' => $orderId],
            ['%s'],
            ['%d']
        );
    }

    public static function updatePaymentStatusByOrderId($orderId, $paymentStatus)
    {
        self::ensureInitialized();
        $tableName = self::$wpdb->prefix . 'iyzico_order';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return self::$wpdb->update(
            $tableName,
            ['payment_status' => $paymentStatus],
            ['order_id' => $orderId],
            ['%s'],
            ['%d']
        );
    }

    public static function updatePaymentIdByOrderId($orderId, $paymentId)
    {
        self::ensureInitialized();
        $tableName = self::$wpdb->prefix . 'iyzico_order';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return self::$wpdb->update(
            $tableName,
            ['payment_id' => $paymentId],
            ['order_id' => $orderId],
            ['%s'],
            ['%d']
        );
    }

    public static function updateTotalAmountByOrderId($orderId, $totalAmount)
    {
        self::ensureInitialized();
        $tableName = self::$wpdb->prefix . 'iyzico_order';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return self::$wpdb->update(
            $tableName,
            ['total_amount' => $totalAmount],
            ['order_id' => $orderId],
            ['%f'],
            ['%d']
        );
    }

    public static function findOrderByToken($token)
    {
        self::ensureInitialized();
        $tableName = self::$wpdb->prefix . 'iyzico_order';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = self::$wpdb->prepare("
			SELECT *
			FROM $tableName
			WHERE token = %s
			ORDER BY iyzico_order_id DESC LIMIT 1;
		", $token);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        return self::$wpdb->get_row($sql, ARRAY_A);
    }

    public function findUserCardKey($customerId, $apiKey)
    {
        $tableName = self::$wpdb->prefix . 'iyzico_card';
        $fieldName = 'card_user_key';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = self::$wpdb->prepare("
			SELECT $fieldName
			FROM $tableName
			WHERE customer_id = %d AND api_key = %s
			ORDER BY iyzico_card_id DESC LIMIT 1;
		", $customerId, $apiKey);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        $result = self::$wpdb->get_col($sql);

        return $result[0] ?? null;
    }

    public function saveUserCardKey($customerId, $cardUserKey, $apiKey)
    {
        $tableName = self::$wpdb->prefix . 'iyzico_card';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return self::$wpdb->insert(
            $tableName,
            [
                'customer_id' => $customerId,
                'card_user_key' => $cardUserKey,
                'api_key' => $apiKey
            ],
            ['%d', '%s', '%s']
        );
    }
}
