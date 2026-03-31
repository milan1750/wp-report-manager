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
class KurveApiProvider implements PosProviderInterface {

	/**
	 * Base URL
	 */
	private const BASE_URL = 'https://app.kurvekiosks.com/partners/api/1/sales_export/';

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
	public function fetch_transactions(
		string $start,
		string $end,
		string $api_key,
		?int $site_id = null
	): void {

		$csv = $this->request( 'sales', $api_key, $start, $end );

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
	 * @param  string   $api_key API Key.
	 * @param  int|null $site_id Site ID (for multi-site). Optional.
	 */
	public function fetch_items(
		string $start,
		string $end,
		string $api_key,
		?int $site_id = null
	): void {

		$csv = $this->request( 'items', $api_key, $start, $end );

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
	 * @param  string   $api_key API Key.
	 * @param  int|null $site_id Site ID (for multi-site). Optional.
	 */
	public function fetch_payments(
		string $start,
		string $end,
		string $api_key,
		?int $site_id = null
	): void {

		$csv = $this->request( 'payments', $api_key, $start, $end );

		if ( empty( $csv ) ) {
			return;
		}

		$rows = CsvParser::parse( $csv );

		$this->process_payments( $rows, $site_id );
	}

	/**
	 * Request.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $report Report type: sales, items, payments.
	 * @param  string $api_key API Key.
	 * @param  string $start Start Date.
	 * @param  string $end End Date.
	 *
	 * @return string
	 */
	private function request(
		string $report,
		string $api_key,
		string $start,
		string $end
	): string {

		$url = self::BASE_URL
			. '?key=' . rawurlencode( $api_key )
			. "&from={$start}&to={$end}"
			. "&type=power_bi&report={$report}";

		$response = wp_remote_get( $url );

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
	private function process_transactions( array $rows, ?int $site_id ): void {

		global $wpdb;

		foreach ( $rows as $t ) {

			if ( empty( $t['transaction_id'] ) ) {
				continue;
			}

			$current_site_id = intval( $t['site_id'] ?? 0 );

			if ( $site_id && $current_site_id !== $site_id ) {
				continue;
			}

			$transaction_id = substr( trim( $t['transaction_id'] ), 0, 150 );
			$transaction_id = preg_replace( '/[\r\n\t]+/', '', $transaction_id );

			$complete_date     = sanitize_text_field( $t['complete_date'] ?? '' );
			$complete_time     = sanitize_text_field( $t['complete_time'] ?? '' );
			$complete_datetime = trim( $complete_date . ' ' . $complete_time );

			$wpdb->replace(
				$wpdb->prefix . 'wrm_transactions',
				array(
					'transaction_id'    => $transaction_id,
					'complete_datetime' => $complete_datetime,
					'complete_date'     => $complete_date,
					'complete_time'     => $complete_time,
					'site_id'           => $current_site_id,
					'site_title'        => sanitize_text_field( $t['site_title'] ?? '' ),
					'order_ref'         => sanitize_text_field( $t['order_ref'] ?? '' ),
					'order_ref2'        => sanitize_text_field( $t['order_ref2'] ?? '' ),
					'table_number'      => sanitize_text_field( $t['table'] ?? '' ),
					'table_covers'      => intval( $t['table_covers'] ?? 0 ),
					'complete'          => intval( $t['complete'] ?? 0 ),
					'canceled'          => intval( $t['canceled'] ?? 0 ),
					'order_type'        => sanitize_text_field( $t['order_type'] ?? '' ),
					'clerk_id'          => intval( $t['clerk_id'] ?? 0 ),
					'clerk_name'        => sanitize_text_field( $t['clerk_name'] ?? '' ),
					'channel_id'        => intval( $t['channel_id'] ?? 0 ),
					'channel_name'      => sanitize_text_field( $t['channel_name'] ?? '' ),
					'customer_name'     => sanitize_text_field( $t['customer_name'] ?? '' ),
					'eat_in'            => intval( $t['eat_in'] ?? 0 ),
					'item_qty'          => floatval( $t['item_qty'] ?? 0 ),
					// Subtotal now **excludes tax**.
					'subtotal'          => floatval( $t['subtotal'] ?? 0 )
											? floatval( $t['subtotal'] )
											- floatval( $t['tax'] ?? 0 )
											: 0,
					'discounts'         => floatval( $t['discounts'] ?? 0 ),
					'tax'               => floatval( $t['tax'] ?? 0 ),
					'service_charge'    => floatval( $t['service_charge'] ?? 0 ),
					'total'             => floatval( $t['total'] ?? 0 ),
				)
			);
		}
	}

	/**
	 * Process items.
	 *
	 * @since 1.0.0
	 *
	 * @param  array    $rows Rows.
	 * @param  int|null $site_id Site ID (for multi-site). Optional.
	 */
	private function process_items( array $rows, ?int $site_id ): void {

		global $wpdb;

		$table = $wpdb->prefix . 'wrm_transaction_items';

		foreach ( $rows as $item ) {

			if ( empty( $item['transaction_id'] ) || empty( $item['product_id'] ) ) {
				continue;
			}

			$current_site_id = intval( $item['site_id'] ?? 0 );

			if ( $site_id && $current_site_id !== $site_id ) {
				continue;
			}

			$wpdb->replace(
				$table,
				array(
					'transaction_id' => sanitize_text_field( $item['transaction_id'] ),
					'site_id'        => $current_site_id,
					'product_id'     => intval( $item['product_id'] ),
					'product_title'  => sanitize_text_field( $item['product_title'] ?? '' ),
					'quantity'       => floatval( $item['quantity'] ?? 0 ),
					'price'          => floatval( $item['price'] ?? 0 ),
					'tax'            => floatval( $item['tax'] ?? 0 ),
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

		global $wpdb;

		$table = $wpdb->prefix . 'wrm_transaction_payments';

		foreach ( $rows as $p ) {

			if ( empty( $p['transaction_id'] ) ) {
				continue;
			}

			$current_site_id = intval( $p['site_id'] ?? 0 );

			if ( $site_id && $current_site_id !== $site_id ) {
				continue;
			}

			$wpdb->replace(
				$table,
				array(
					'transaction_id'   => sanitize_text_field( $p['transaction_id'] ),
					'site_id'          => $current_site_id,
					'payment_type'     => sanitize_text_field( $p['type'] ?? '' ),
					'amount'           => floatval( $p['amount'] ?? 0 ),
					'gratuity'         => floatval( $p['gratuity'] ?? 0 ),
					'cashback'         => floatval( $p['cashback'] ?? 0 ),
					'change_amount'    => floatval( $p['change'] ?? 0 ),
					'card_scheme'      => sanitize_text_field( $p['card_scheme'] ?? '' ),
					'last4'            => sanitize_text_field( $p['last4'] ?? '' ),
					'auth_code'        => sanitize_text_field( $p['auth_code'] ?? '' ),
					'canceled'         => intval( $p['canceled'] ?? 0 ),
					'payment_datetime' => sanitize_text_field( ( $p['date'] ?? '' ) . ' ' . ( $p['time'] ?? '' ) ),
					'payment_id'       => sanitize_text_field( $p['payment_id'] ?? '' ),
				)
			);
		}
	}
}
