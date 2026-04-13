<?php
/**
 * Customer Post Type - Products.
 *
 * @package WP_Report_Manager
 */

namespace WRM\Pos;

use WRM\Interfaces\PosProviderInterface;
use WRM\Pos\CsvParser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kurve Pos Provider.
 *
 * @since 1.0.0
 */
class TBApiProvider implements PosProviderInterface {

	/**
	 * Cookies.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	public $cookies = array();

	/**
	 * Base URL
	 */
	private const BASE_URL = 'https://admin.touchbistro.com/api/frontend/report/v1/venues/35722/reports/export_data';

	/**
	 * TB Login.
	 *
	 * @since 1.0.0
	 *
	 * @param string $username Username.
	 * @param  string $password Password.
	 */
	public function touchbistro_login( $username, $password ) {

		$try = 1;

		while ( $try <= 5 ) {
			$url = 'https://login.touchbistro.com/api/v1/authn';

			$body = wp_json_encode(
				array(
					'username' => $username,
					'password' => $password,
					'options'  => array(
						'warnBeforePasswordExpired' => true,
						'multiOptionalFactorEnroll' => true,
					),
				)
			);

			$response = wp_remote_post(
				$url,
				array(
					'method'  => 'POST',
					'headers' => array(
						'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0',
						'Accept'          => 'application/json',
						'Accept-Language' => 'en',
						'Content-Type'    => 'application/json',
						'Origin'          => 'https://login.touchbistro.com',
						'Connection'      => 'keep-alive',
					),
					'body'    => $body,
					'timeout' => 20,
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response->get_error_message();
			}

			$status  = wp_remote_retrieve_response_code( $response );
			$body    = json_decode( wp_remote_retrieve_body( $response ), true );
			$headers = wp_remote_retrieve_headers( $response );
			$cookies = self::touchbistro_capture_cookies( $body['_links']['next']['href'] );
			if ( ! empty( $cookies['connect.sid'] ) ) {
				$this->cookies = $cookies;
				break;
			}
			sleep( wp_rand( 5, 10 ) );
			++$try;
		}
	}

	/**
	 * Follow redirect and capture cookies like curl -L -c -b.
	 *
	 * @param string $url URL.
	 */
	public static function touchbistro_capture_cookies( $url ) {

		$cookie_jar = array();

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 20,
				'redirection' => 10,
				'headers'     => array(
					'User-Agent' => 'Mozilla/5.0',
				),
				'cookies'     => $cookie_jar,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}

		$cookies = wp_remote_retrieve_cookies( $response );

		$stored = array();

		foreach ( $cookies as $cookie ) {
			$stored[ $cookie->name ] = $cookie->value;
		}

		return $stored;
	}

	/**
	 * Fetch transactions.
	 *
	 * @since 1.0.0
	 *
	 * @param  string   $start Start Date.
	 * @param  string   $end End Date.
	 * @param  string   $api_key API Key.
	 * @param  int|null $site_id Site ID (for multi-site). Optional.
	 */
	public function fetch_transactions( string $start, string $end, string $api_key, ?int $site_id = null ): void {

		$csv = $this->request( 'bills', $api_key, $start, $end, );

		if ( empty( $csv ) ) {
			return;
		}

		$rows = CsvParser::parse( $csv );
		$this->process_transactions( $rows, $site_id );
	}

	/**
	 * Fetch items.
	 *
	 * @since 1.0.0
	 *
	 * @param  string   $start Start Date.
	 * @param  string   $end End Date.
	 * @param  string   $api_key Api Key.
	 * @param  int|null $site_id Site ID (for multi-site). Optional.
	 */
	public function fetch_items(
		string $start,
		string $end,
		string $api_key,
		?int $site_id = null
	): void {

		$csv = $this->request( 'detailed_sales', $api_key, $start, $end, );
		if ( empty( $csv ) ) {
			return;
		}

		$rows = CsvParser::parse( $csv );
		$this->process_items( $rows, $site_id );
	}

	/**
	 * Fetch payments.
	 *
	 * @since 1.0.0
	 *
	 * @param  string   $start Start Date.
	 * @param  string   $end End Date.
	 * @param  string   $api_key Api Key.

	 * @param  int|null $site_id Site ID (for multi-site). Optional.
	 */
	public function fetch_payments( string $start, string $end, string $api_key, ?int $site_id = null ): void {
		// To be implemented later.
	}

	/**
	 * Request.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $report Report type: sales, items, payments.
	 * @param  string $api_key Api Key.
	 * @param  string $start Start Date.
	 * @param  string $end End Date.
	 *
	 * @return string
	 */
	private function request( string $report, string $api_key, string $start, string $end ): string {

		// Get WordPress timezone (e.g. Europe/London).
		$start_dt = new \DateTime( $start, new \DateTimeZone( 'UTC' ) );
		$end_dt   = clone $start_dt;
		$end_dt->modify( '+1 day' );

		$start_ts = $start_dt->getTimestamp();
		$end_ts   = $end_dt->getTimestamp();

		$url = self::BASE_URL
		. "?start={$start_ts}&end={$end_ts}"
		. "&report_name={$report}&type=csv";

		$cookie_objects = array();
		foreach ( $this->cookies as $name => $value ) {
			$cookie_objects[] = new \WP_Http_Cookie(
				array(
					'name'   => $name,
					'value'  => $value,
					'domain' => 'admin.touchbistro.com',
					'path'   => '/',
				)
			);
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
					'Accept'     => 'application/json, text/plain, */*',
				),
				'cookies' => $cookie_objects,
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		return (string) wp_remote_retrieve_body( $response );
	}

	/**
	 * Process transactions.
	 *
	 * @since 1.0.0
	 *
	 * @param  array    $rows Rows.
	 * @param  int|null $site_id Site ID (for multi-site). Optional.
	 */
	private function process_transactions( array $rows, ?int $site_id = 10 ): void {

		global $wpdb;

		$table = $wpdb->prefix . 'wrm_transactions';

		foreach ( $rows as $row ) {

			$transaction_id = isset( $row['Bill Id'] ) ? sanitize_text_field( $row['Bill Id'] ) : null;

			$bill_number = isset( $row['Bill Number'] ) ? sanitize_text_field( $row['Bill Number'] ) : null;

			if ( empty( $transaction_id ) || empty( $bill_number ) ) {
				error_log( 'Skipping row: missing transaction_id or bill_number' );
				continue;
			}

			// -------------------
			// Parse date + time
			// -------------------
			$date     = isset( $row['Date'] ) ? explode( ',', $row['Date'] )[0] : '';
			$time     = isset( $row['Time'] ) ? $row['Time'] : '';
			$datetime = gmdate( 'Y-m-d H:i:s', strtotime( $date . ' ' . $time ) );

			// -------------------
			// Calculate values
			// -------------------
			$sales          = floatval( str_replace( ',', '', $row['Sales'] ?? 0 ) );
			$tax            = floatval( str_replace( ',', '', $row['Tax Amount'] ?? 0 ) );
			$service_charge = floatval( str_replace( ',', '', $row['Service Charges'] ?? 0 ) );
			$discounts      = floatval( str_replace( ',', '', $row['Discount Revenue'] ?? 0 ) );
			$payment_total  = floatval( str_replace( ',', '', $row['Payment Total'] ?? 0 ) );
			$total_bill     = floatval( str_replace( ',', '', $row['Total Bill'] ?? 0 ) );

			$subtotal = $sales + $discounts; // subtotal includes discount.
			$total    = $sales + $tax + $service_charge; // Total = Sales + Tax + Service.
			$gratuity = $total_bill - $total; // Extra payment difference.

			// -------------------
			// Eat In / Takeout
			// -------------------
			$order_type_raw = strtolower( trim( $row['Order Type'] ?? '' ) );
			if ( 'dinein' === $order_type_raw ) {
				$eat_in     = 1;
				$order_type = 'Eat In';
			} elseif ( 'takeout' === $order_type_raw ) {
				$eat_in     = 0;
				$order_type = 'Takeaway';
			} elseif ( 'delivery' === $order_type_raw ) {
				$eat_in     = 0;
				$order_type = 'Delivery';
			} else {
				$eat_in     = null;
				$order_type = $row['Order Type'] ?? 'Unknown';
			}

			// -------------------
			// Insert into DB
			// -------------------
			$wpdb->replace(
				$table,
				array(
					'transaction_id'    => $transaction_id,
					'site_id'           => 10, // default site_id.
					'order_ref'         => sanitize_text_field( $row['Bill Number'] ?? '' ),
					'order_ref2'        => sanitize_text_field( $row['Order Number'] ?? '' ),
					'complete_datetime' => $datetime,
					'complete_date'     => date( 'Y-m-d', strtotime( $datetime ) ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
					'complete_time'     => date( 'H:i:s', strtotime( $datetime ) ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
					'clerk_name'        => sanitize_text_field( $row['Staff'] ?? '' ),
					'order_type'        => $order_type,
					'channel_id'        => intval( $row['Order Type Id'] ?? 0 ),
					'subtotal'          => $subtotal,
					'discounts'         => $discounts,
					'tax'               => $tax,
					'service_charge'    => $service_charge,
					'total'             => $payment_total,
					'gratuity'          => $gratuity,
					'eat_in'            => $eat_in,
					'complete'          => 1,
					'canceled'          => 0,
				),
				array(
					'%s',
					'%d',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%d',
					'%f',
					'%f',
					'%f',
					'%f',
					'%f',
					'%f',
					'%d',
					'%d',
				)
			);
		}
	}

	/**
	 * Process items from TouchBistro CSV.
	 *
	 * @since 1.0.0
	 *
	 * @param  array    $rows Rows from TouchBistro CSV.
	 * @param  int|null $site_id Site ID (for multi-site). Optional.
	 */
	private function process_items( array $rows, ?int $site_id ): void {
		global $wpdb;
		$items_table = $wpdb->prefix . 'wrm_transaction_items';
		$site_id     = 10;

		if ( empty( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$transaction_id = sanitize_text_field( $row['Order ID'] ?? '' );
			$product_title  = sanitize_text_field( $row['Menu Item'] ?? '' );
			$item_id        = sanitize_text_field( $row['Order Item ID'] ?? '' );

			if ( empty( $transaction_id ) || empty( $product_title ) || empty( $item_id ) ) {
				error_log( 'Skipping row: missing transaction_id, product_title, or item_id' );
				continue;
			}

			// Map TouchBistro fields to table columns.
			$data = array(
				'transaction_id' => $transaction_id,
				'item_id'        => $item_id,
				'site_id'        => $site_id,
				'product_id'     => intval( $row['Menu Item ID'] ?? 0 ),
				'product_title'  => $product_title,
				'category_id'    => intval( $row['Menu Group ID'] ?? 0 ),
				'category_name'  => sanitize_text_field( $row['Menu Group'] ?? '' ),
				'item_title'     => $product_title,
				'item_type'      => ( isset( $row['Is Menu Item Modifier'] ) && strtolower( $row['Is Menu Item Modifier'] ) === 'yes' ) ? 'modifier' : 'item',
				'quantity'       => floatval( $row['Quantity'] ?? 0 ),
				'price'          => floatval( $row['Base Price'] ?? 0 ),
				'disc_price'     => floatval( $row['Item Discounts'] ?? 0 ),
				'disc_tax'       => floatval( $row['Item Discounts'] ?? 0 ), // if needed
				'tax'            => floatval( $row['Total Tax'] ?? 0 ),
				'voided'         => ( isset( $row['Is Voided'] ) && strtolower( $row['Is Voided'] ) === 'yes' ) ? 1 : 0,
				'sale_type'      => sanitize_text_field( $row['Order Type'] ?? '' ),
				'added_datetime' => ! empty( $row['Paid Time'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $row['Paid Time'] ) ) : current_time( 'mysql' ),
				'promo_id'       => intval( $row['promo_id'] ?? 0 ),
				'price_level_id' => intval( $row['price_level_id'] ?? 0 ),
				'tax_id'         => intval( $row['tax_id'] ?? 0 ),
			);

			// Insert or replace existing row by item_id
			$wpdb->replace(
				$items_table,
				$data,
				array(
					'%s', // transaction_id
					'%s', // item_id
					'%d', // site_id
					'%d', // product_id
					'%s', // product_title
					'%d', // category_id
					'%s', // category_name
					'%s', // item_title
					'%s', // item_type
					'%f', // quantity
					'%f', // price
					'%f', // disc_price
					'%f', // disc_tax
					'%f', // tax
					'%d', // voided
					'%s', // sale_type
					'%s', // added_datetime
					'%d', // promo_id
					'%d', // price_level_id
					'%d', // tax_id
				)
			);
		}
	}

	/**
	 * Process payments.
	 *
	 * @since 1.0.0
	 *
	 * @param  array    $rows Rows.
	 * @param  int|null $site_id Site ID (for multi-site). Optional.
	 */
	private function process_payments( array $rows, ?int $site_id ): void {
		// To be implemented later.
	}
}
