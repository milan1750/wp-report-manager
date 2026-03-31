<?php
/**
 * Api Fetcher Class - Fetches data from external APIs and stores in database.
 *
 * @package WP_Report_Manager
 */

namespace WRM;

/**
 * Api Fetcher Class.
 *
 * @since 1.0.0
 */
class ApiFetcher {

	/**
	 * KurveKiosks base sales export URL.
	 */
	public const KURVE_BASE_URL = 'https://app.kurvekiosks.com/partners/api/1/sales_export/';

	/**
	 * Upsert a WRM site row based on imported data.
	 *
	 * @param int      $site_id   POS site id from CSV.
	 * @param string   $site_name Human-readable site name from CSV.
	 * @param int|null $entity_id WRM entity/company id.
	 */
	private static function upsert_site( int $site_id, string $site_name, ?int $entity_id ): void {
		if ( $site_id <= 0 || empty( $entity_id ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wrm_sites';

		$wpdb->replace(
			$table,
			array(
				'site_id'   => $site_id,
				'site_name' => $site_name,
				'entity_id' => $entity_id,
			),
			array(
				'%d',
				'%s',
				'%d',
			)
		);
	}


	/**
	 * Fetch transaction data from API and store in database.
	 *
	 * @since 1.0.0
	 *
	 * @param  string   $start   Format: 'YYYY-MM-DD HH:MM:SS'.
	 * @param  string   $end     Format: 'YYYY-MM-DD HH:MM:SS'.
	 * @param  string   $api_key Kurve API key.
	 * @param  int|null $site_id Optional POS site id to import.
	 * @param  int|null $entity_id WRM entity/company id to associate sites with.
	 */
	public static function fetch_transactions( $start, $end, string $api_key, ?int $site_id = null, ?int $entity_id = null ) {
		global $wpdb;

		$api_url  = self::KURVE_BASE_URL . '?key=' . rawurlencode( $api_key ) . "&from={$start}&to={$end}&type=power_bi&report=sales";
		$response = wp_remote_get( $api_url );
		if ( is_wp_error( $response ) ) {
			return;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return;
		}

		$rows       = self::parse_csv( $body );
		$site_cache = array();

		foreach ( $rows as $t ) {
			if ( empty( $t['transaction_id'] ) ) {
				continue;
			}

			$current_site_id = intval( $t['site_id'] ?? 0 );
			if ( ! empty( $site_id ) && $current_site_id !== $site_id ) {
				continue;
			}

			$transaction_id = substr( trim( $t['transaction_id'] ), 0, 150 );
			$transaction_id = preg_replace( '/[\r\n\t]+/', '', $transaction_id );

			$complete_date     = sanitize_text_field( $t['complete_date'] ?? '' );
			$complete_time     = sanitize_text_field( $t['complete_time'] ?? '' );
			$complete_datetime = trim( $complete_date . ' ' . $complete_time );

			if ( ! empty( $entity_id ) && $current_site_id > 0 ) {
				if ( empty( $site_cache[ $current_site_id ] ) ) {
					self::upsert_site(
						$current_site_id,
						sanitize_text_field( $t['site_title'] ?? '' ),
						$entity_id
					);
					$site_cache[ $current_site_id ] = true;
				}
			}

			// Calculate correct subtotal.
			$discounts = floatval( $t['discounts'] ?? 0 );
			$total     = floatval( $t['total'] ?? 0 );
			$subtotal  = $total + $discounts;

			$wpdb->replace(
				$wpdb->prefix . 'wrm_transactions',
				array(
					'transaction_id'    => sanitize_text_field( $transaction_id ),
					'complete_datetime' => sanitize_text_field( $complete_datetime ),
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
					'subtotal'          => $subtotal,
					'discounts'         => $discounts,
					'tax'               => floatval( $t['tax'] ?? 0 ),
					'total'             => $total,
					'service_charge'    => floatval( $t['service_charge'] ?? 0 ),
				),
				array(
					'%s',
					'%s',
					'%s',
					'%s',
					'%d',
					'%s',
					'%s',
					'%s',
					'%s',
					'%d',
					'%d',
					'%d',
					'%s',
					'%d',
					'%s',
					'%d',
					'%s',
					'%s',
					'%d',
					'%f',
					'%f',
					'%f',
					'%f',
					'%f',
				)
			);
		}
	}

	/**
	 * Fetch transaction items data from API and store in database.
	 *
	 * @since 1.0.0
	 *
	 * @param  string   $start Format: 'YYYY-MM-DD HH:MM:SS'.
	 * @param  string   $end   Format: 'YYYY-MM-DD HH:MM:SS'.
	 * @param  string   $api_key Kurve API key.
	 * @param  int|null $site_id Optional POS site id to import.
	 * @param  int|null $entity_id WRM entity/company id to associate sites with
	 */
	public static function fetch_transaction_items( $start, $end, string $api_key, ?int $site_id = null ) {
		global $wpdb;
		$table    = $wpdb->prefix . 'wrm_transaction_items';
		$api_url  = self::KURVE_BASE_URL . '?key=' . rawurlencode( $api_key ) . "&from={$start}&to={$end}&type=power_bi&report=items";
		$response = wp_remote_get( $api_url );
		if ( is_wp_error( $response ) ) {
			return;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return;
		}

		$rows = self::parse_csv( $body );

		foreach ( $rows as $item ) {
			if ( empty( $item['transaction_id'] ) || empty( $item['product_id'] ) ) {
				continue;
			}

			$current_site_id = intval( $item['site_id'] ?? 0 );
			if ( ! empty( $site_id ) && $current_site_id !== $site_id ) {
				continue;
			}

			$transaction_id = substr( trim( $item['transaction_id'] ), 0, 150 );
			$transaction_id = preg_replace( '/[\r\n\t]+/', '', $transaction_id );

			$data = array(
				'transaction_id' => sanitize_text_field( $transaction_id ),
				'site_id'        => $current_site_id,
				'product_id'     => intval( $item['product_id'] ?? 0 ),
				'category_id'    => intval( $item['category_id'] ?? 0 ),
				'category_name'  => sanitize_text_field( $item['category_name'] ?? '' ),
				'product_title'  => sanitize_text_field( $item['product_title'] ?? '' ),
				'item_title'     => sanitize_text_field( $item['item_title'] ?? '' ),
				'item_type'      => sanitize_text_field( $item['item_type'] ?? '' ),
				'quantity'       => floatval( $item['quantity'] ?? 0 ),
				'price'          => floatval( $item['price'] ?? 0 ),
				'disc_price'     => floatval( $item['disc_price'] ?? 0 ),
				'disc_tax'       => floatval( $item['disc_tax'] ?? 0 ),
				'tax'            => floatval( $item['tax'] ?? 0 ),
				'promo_id'       => intval( $item['promo_id'] ?? 0 ),
				'price_level_id' => intval( $item['price_level_id'] ?? 0 ),
				'tax_id'         => intval( $item['tax_id'] ?? 0 ),
				'item_id'        => sanitize_text_field( $item['item_id'] ?? '' ),
				'voided'         => intval( $item['voided'] ?? 0 ),
				'sale_type'      => sanitize_text_field( $item['sale_type'] ?? '' ),
				'added_datetime' => sanitize_text_field( ( $item['added_date'] ?? '' ) . ' ' . ( $item['added_time'] ?? '' ) ),
			);

			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE transaction_id = %s AND product_id = %d AND site_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$data['transaction_id'],
					$data['product_id'],
					$data['site_id']
				)
			);

			if ( $exists ) {
				$wpdb->update(
					$table,
					$data,
					array( 'id' => $exists ),
					array(
						'%s',
						'%d',
						'%d',
						'%d',
						'%s',
						'%s',
						'%s',
						'%s',
						'%f',
						'%f',
						'%f',
						'%f',
						'%f',
						'%d',
						'%d',
						'%s',
						'%s',
						'%s',
						'%s',
					)
				);
			} else {
				$wpdb->insert(
					$table,
					$data,
					array(
						'%s',
						'%d',
						'%d',
						'%d',
						'%s',
						'%s',
						'%s',
						'%s',
						'%f',
						'%f',
						'%f',
						'%f',
						'%f',
						'%d',
						'%d',
						'%s',
						'%s',
						'%s',
						'%s',
					)
				);
			}
		}
	}

	/**
	 * Fetch transaction payments data from API and store in database.
	 *
	 * @since 1.0.0
	 *
	 * @param  string   $start Format: 'YYYY-MM-DD HH:MM:SS'.
	 * @param  string   $end   Format: 'YYYY-MM-DD HH:MM:SS'.
	 * @param  string   $api_key Kurve API key.
	 * @param  int|null $site_id Optional POS site id to import.
	 */
	public static function fetch_transaction_payments( $start, $end, string $api_key, ?int $site_id = null ) {
		global $wpdb;
		$table    = $wpdb->prefix . 'wrm_transaction_payments';
		$api_url  = self::KURVE_BASE_URL . '?key=' . rawurlencode( $api_key ) . "&from={$start}&to={$end}&type=power_bi&report=payments";
		$response = wp_remote_get( $api_url );
		if ( is_wp_error( $response ) ) {
			return;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return;
		}

		$rows = self::parse_csv( $body );

		foreach ( $rows as $p ) {
			if ( empty( $p['transaction_id'] ) ) {
				continue;
			}

			$current_site_id = intval( $p['site_id'] ?? 0 );
			if ( ! empty( $site_id ) && $current_site_id !== $site_id ) {
				continue;
			}

			$transaction_id = substr( trim( $p['transaction_id'] ), 0, 150 );
			$transaction_id = preg_replace( '/[\r\n\t]+/', '', $transaction_id );

			$wpdb->replace(
				$table,
				array(
					'transaction_id'   => sanitize_text_field( $transaction_id ),
					'site_id'          => $current_site_id,
					'payment_type'     => sanitize_text_field( $p['type'] ?? '' ),
					'amount'           => floatval( $p['amount'] ?? 0 ),
					'gratuity'         => floatval( $p['gratuity'] ?? 0 ),
					'cashback'         => floatval( $p['cashback'] ?? 0 ),
					'change_amount'    => floatval( $p['change'] ?? 0 ),
					'auth_code'        => sanitize_text_field( $p['auth_code'] ?? '' ),
					'card_scheme'      => sanitize_text_field( $p['card_scheme'] ?? '' ),
					'last4'            => sanitize_text_field( $p['last4'] ?? '' ),
					'canceled'         => intval( $p['canceled'] ?? 0 ),
					'payment_datetime' => sanitize_text_field( ( $p['date'] ?? '' ) . ' ' . ( $p['time'] ?? '' ) ),
					'payment_id'       => sanitize_text_field( $p['payment_id'] ?? '' ),
				),
				array(
					'%s',
					'%d',
					'%s',
					'%f',
					'%f',
					'%f',
					'%f',
					'%s',
					'%s',
					'%s',
					'%d',
					'%s',
					'%s',
				)
			);
		}
	}

	/**
	 * Parse CSV string into array of associative arrays.
	 *
	 * @since 1.0.0
	 *
	 * @param string $csv Raw CSV string.
	 */
	public static function parse_csv( $csv ) {
		$rows = array_map( 'str_getcsv', explode( "\n", trim( $csv ) ) );
		if ( empty( $rows ) ) {
			return array();
		}

		$header = array_shift( $rows );
		$data   = array();

		foreach ( $rows as $row ) {
			if ( count( $row ) !== count( $header ) ) {
				continue;
			}
			$data[] = array_combine( $header, $row );
		}

		return $data;
	}

	/**
	 * Fetch data in daily chunks with progress tracking.
	 *
	 * @param string $from Start date. Format: YYYY-MM-DD.
	 * @param string $to   End date. Format: YYYY-MM-DD.
	 */
	public static function fetch_all( $from, $to ) {
		$api_key = (string) get_option( 'wrm_kineya_api_key', '' );
		if ( empty( $api_key ) ) {
			return;
		}

		$start_date = new \DateTime( $from );
		$end_date   = new \DateTime( $to );

		// Check max 3 months gap.
		$diff = $start_date->diff( $end_date )->days;
		if ( $diff > 92 ) { // ~3 months
			return;
		}

		$period = new \DatePeriod(
			$start_date,
			new \DateInterval( 'P1D' ), // 1 day interval.
			$end_date->modify( '+1 day' ) // inclusive.
		);

		$day_index = 1;
		foreach ( $period as $date ) {
			$day_str = $date->format( 'Y-m-d' );
			$display = $date->format( 'd M Y' );

			// Call ApiFetcher for this single day.
			$start_datetime = $day_str . ' 00:00:00';
			$end_datetime   = $day_str . ' 23:59:59';
			self::fetch_transactions( $start_datetime, $end_datetime, $api_key );
			self::fetch_transaction_items( $start_datetime, $end_datetime, $api_key );
			self::fetch_transaction_payments( $start_datetime, $end_datetime, $api_key );

			++$day_index;
		}
	}
}
