<?php
/**
 *
 * Customer wrmt Type - Products
 *
 * @package WP_Report_Manager
 */

namespace WRM;

/**
 *
 * Customer wrmt Type - Products.
 *
 * @package WP_Report_Manager
 */
class Tables {

	/**
	 * Create necessary database tables on plugin activation.
	 *
	 * @since 1.0.0
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		// ===== Transactions Table =====
		$transactions_table = $wpdb->prefix . 'wrm_transactions';
		$sql                = "CREATE TABLE $transactions_table (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        transaction_id VARCHAR(150) NOT NULL UNIQUE,
        site_id INT,
        site_title VARCHAR(150),
        complete_datetime DATETIME,
        complete_date DATE,
        complete_time TIME,
        order_type VARCHAR(50),
        channel_id INT,
        channel_name VARCHAR(100),
        clerk_id INT,
        clerk_name VARCHAR(150),
        customer_name VARCHAR(150),
        eat_in TINYINT(1),
        item_qty INT,
        subtotal DECIMAL(10,2),
        discounts DECIMAL(10,2),
        tax DECIMAL(10,2),
        service_charge DECIMAL(10,2),
        total DECIMAL(10,2),
				gratuity DECIMAL(10,2),
        order_ref VARCHAR(100),
        order_ref2 VARCHAR(100),
        table_number VARCHAR(20),
        table_covers INT,
        complete TINYINT(1),
        canceled TINYINT(1),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY transaction_id_unique (transaction_id),
        KEY idx_site_id (site_id),
        KEY idx_complete_datetime (complete_datetime),
        KEY idx_clerk_id (clerk_id),
        KEY idx_site_date (site_id, complete_date)

    ) $charset_collate;";
		dbDelta( $sql );

		// ===== Transaction Items Table =====
		$items_table = $wpdb->prefix . 'wrm_transaction_items';
		$sql         = "CREATE TABLE $items_table (
				id BIGINT(20) NOT NULL AUTO_INCREMENT,
				transaction_id VARCHAR(150) NOT NULL,
				item_id VARCHAR(150) NOT NULL,
				site_id INT,
				product_id INT,
				product_title VARCHAR(150),
				category_id INT,
				category_name VARCHAR(150),
				item_title VARCHAR(150),
				item_type VARCHAR(50),
				quantity DECIMAL(10,2),
				price DECIMAL(10,2),
				tax DECIMAL(10,2),
				disc_price DECIMAL(10,2),
				disc_tax DECIMAL(10,2),
				voided TINYINT(1),
				sale_type VARCHAR(50),
				added_datetime DATETIME,
				promo_id INT,
				price_level_id INT,
				tax_id INT,
				PRIMARY KEY (id),
				UNIQUE KEY idx_item_id (item_id)
		) $charset_collate;";
		dbDelta( $sql );

		// ===== Transaction Payments Table =====
		$payments_table = $wpdb->prefix . 'wrm_transaction_payments';
		$sql            = "CREATE TABLE $payments_table (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        transaction_id VARCHAR(150) NOT NULL,
        site_id INT,
        payment_type VARCHAR(50),
        amount DECIMAL(10,2),
        gratuity DECIMAL(10,2),
        cashback DECIMAL(10,2),
        change_amount DECIMAL(10,2),
        card_scheme VARCHAR(50),
        last4 VARCHAR(10),
        auth_code VARCHAR(50),
        canceled TINYINT(1),
        payment_datetime DATETIME,
        payment_id VARCHAR(100),
        PRIMARY KEY (id),
        KEY idx_transaction_id (transaction_id),
        KEY idx_payment_type (payment_type),
        KEY idx_payments_site_date (site_id, payment_datetime)
    ) $charset_collate;";
		dbDelta( $sql );

		// ===== Sites Table =====
		$sites_table = $wpdb->prefix . 'wrm_sites';
		$sql         = "CREATE TABLE $sites_table (
        id INT(11) NOT NULL AUTO_INCREMENT,
        site_id INT NOT NULL UNIQUE,
        site_name VARCHAR(150),
        entity_id INT,
        PRIMARY KEY (id)
    ) $charset_collate;";
		dbDelta( $sql );

		// ===== Entities Table =====
		$entities_table = $wpdb->prefix . 'wrm_entities';
		$sql            = "CREATE TABLE $entities_table (
        id INT(11) NOT NULL AUTO_INCREMENT,
        name VARCHAR(150),
        PRIMARY KEY (id)
    ) $charset_collate;";
		dbDelta( $sql );

		// ===== Clerks Table =====
		$clerks_table = $wpdb->prefix . 'wrm_clerks';
		$sql          = "CREATE TABLE $clerks_table (
        id INT(11) NOT NULL AUTO_INCREMENT,
        clerk_id INT,
        clerk_name VARCHAR(150),
        site_id INT,
        PRIMARY KEY (id),
        KEY idx_clerk_id (clerk_id),
        KEY idx_site_id (site_id)
    ) $charset_collate;";
		dbDelta( $sql );

		// ===== Fetch Jobs Table =====
		$fetch_jobs_table = $wpdb->prefix . 'wrm_fetch_jobs';
		$sql              = "CREATE TABLE $fetch_jobs_table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			entity_id BIGINT UNSIGNED NOT NULL,
			from_date DATE NOT NULL,
			to_date DATE NOT NULL,
			processing_date DATE NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			progress INT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY status (status)
		) $charset_collate;";

		dbDelta( $sql );
	}
}
