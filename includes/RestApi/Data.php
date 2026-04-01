<?php
/**
 * Data REST API endpoint.
 *
 * @package WP_Report_Manager
 */

namespace WRM\RestApi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Data.
 *
 * @since 1.0.0
 */
class Data {

	/**
	 * Register REST API routes for Data.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $ns Namespace for the REST API routes.
	 */
	public static function register( $ns ) {

		register_rest_route(
			$ns,
			'/data',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_data' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			)
		);

		// TB import route.
		register_rest_route(
			$ns,
			'/import/touchbistro',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'import_touchbistro' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			)
		);
	}

	/**
	 * Get transaction data with optional site/date filtering.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return array Data rows + pagination.
	 */
	public static function get_data( $request ) {

		global $wpdb;

		$transactions = $wpdb->prefix . 'wrm_transactions';
		$sites        = $wpdb->prefix . 'wrm_sites';

		$params = $request->get_params();

		$entity = isset( $params['entity'] ) ? sanitize_text_field( $params['entity'] ) : '';
		$site   = isset( $params['site'] ) ? sanitize_text_field( $params['site'] ) : '';
		$from   = isset( $params['from'] ) ? sanitize_text_field( $params['from'] ) : '';
		$to     = isset( $params['to'] ) ? sanitize_text_field( $params['to'] ) : '';

		$page     = isset( $params['page'] ) ? max( 1, absint( $params['page'] ) ) : 1;
		$per_page = 20;
		$offset   = ( $page - 1 ) * $per_page;

		$where = array( '1=1' );
		$args  = array();

		// =====================
		// SITE / ENTITY FILTER
		// =====================
		if ( ! empty( $site ) ) {
			// Specific site selected
			$where[] = 't.site_id = %s';
			$args[]  = $site;
		} elseif ( ! empty( $entity ) ) {
			// No site selected, get all sites for the entity
			$all_sites    = wpac()->sites()->get_all( true );
			$entity_sites = array_filter(
				$all_sites,
				function ( $s ) use ( $entity ) {
					return $s['entity_id'] == $entity;
				}
			);

			$site_ids = wp_list_pluck( $entity_sites, 'site_id' );

			if ( $site_ids ) {
				$placeholders = implode( ',', array_fill( 0, count( $site_ids ), '%s' ) );
				$where[]      = "t.site_id IN ($placeholders)";
				$args         = array_merge( $args, $site_ids );
			} else {
				// No sites for entity, return empty
				return array(
					'data'       => array(),
					'pagination' => array(
						'current'     => $page,
						'total_pages' => 0,
						'total_items' => 0,
						'per_page'    => $per_page,
					),
				);
			}
		}

		// =====================
		// DATE FILTER
		// =====================
		if ( ! empty( $from ) ) {
			$where[] = 't.complete_datetime >= %s';
			$args[]  = $from . ' 00:00:00';
		}

		if ( ! empty( $to ) ) {
			$where[] = 't.complete_datetime <= %s';
			$args[]  = $to . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );

		// =====================
		// COUNT QUERY
		// =====================
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$count_sql = "
			SELECT COUNT(*)
			FROM $transactions AS t
			WHERE $where_sql
		";

		$total = $wpdb->get_var(
			$wpdb->prepare( $count_sql, $args )  //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		$total_pages = ceil( $total / $per_page );

		// =====================
		// DATA QUERY
		// =====================
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = "
			SELECT
				t.id,
				t.transaction_id,
				t.site_id,
				s.site_name,
				t.complete_datetime,
				t.total,
				t.subtotal,
				t.tax,
				t.discounts,
				t.customer_name
			FROM $transactions AS t
			LEFT JOIN $sites AS s ON s.site_id = t.site_id
			WHERE $where_sql
			ORDER BY t.complete_datetime DESC
			LIMIT %d OFFSET %d
		";

		$args[] = $per_page;
		$args[] = $offset;

		$results = $wpdb->get_results(
			$wpdb->prepare( $sql, $args ), //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return array(
			'data'       => $results,
			'pagination' => array(
				'current'     => $page,
				'total_pages' => (int) $total_pages,
				'total_items' => (int) $total,
				'per_page'    => $per_page,
			),
		);
	}

	/**
	 * Import TouchBistro CSV/XLSX file.
	 *
	 * Expects specific column headers:
	 * - Bill Id
	 * - Bill Number
	 * - Order Number
	 * - Date
	 * - Time
	 * - Staff
	 * - Order Type
	 * - Order Type Id
	 * - Sales
	 * - Discount Revenue
	 * - Tax Amount
	 * - Service Charges
	 * - Total Bill
	 *
	 * @since 1.0.0
	 *
	 * @return array Import result with counts of inserted and skipped rows.
	 */
	public static function import_touchbistro( $request ) {

		if ( empty( $_FILES['file']['tmp_name'] ) ) {
			return array(
				'status'  => 'error',
				'message' => 'No file uploaded',
			);
		}

		$file = $_FILES['file']['tmp_name'];
		$ext  = strtolower( pathinfo( $_FILES['file']['name'], PATHINFO_EXTENSION ) );

		$rows = array();

		// -------------------
		// Read CSV or XLSX
		// -------------------
		if ( $ext === 'csv' ) {
			$handle = fopen( $file, 'r' );
			while ( ( $data = fgetcsv( $handle, 0, ',' ) ) !== false ) {
				$rows[] = $data;
			}
			fclose( $handle );
		} elseif ( $ext === 'xlsx' ) {
			if ( ! class_exists( '\PhpOffice\PhpSpreadsheet\IOFactory' ) ) {
				return array(
					'status'  => 'error',
					'message' => 'PhpSpreadsheet not installed',
				);
			}
			$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $file );
			$sheet       = $spreadsheet->getActiveSheet();
			$rows        = $sheet->toArray();
		} else {
			return array(
				'status'  => 'error',
				'message' => 'Unsupported file type',
			);
		}

		if ( empty( $rows ) ) {
			return array(
				'status'  => 'error',
				'message' => 'File empty',
			);
		}

		error_log( 'Starting TouchBistro import with ' . count( $rows ) . ' rows' );

		global $wpdb;
		$table = $wpdb->prefix . 'wrm_transactions';

		// -------------------
		// Normalize headers
		// -------------------
		$header = array_map(
			function ( $h ) {
				return strtolower( trim( $h, " \t\n\r\0\x0B\"" ) ); // lowercase + trim quotes
			},
			$rows[0]
		);
		unset( $rows[0] );

		$inserted = 0;
		$skipped  = 0;

		foreach ( $rows as $row_data ) {

			// Map row
			$row = array_combine( $header, $row_data );

			$transaction_id = isset( $row['bill id'] ) ? sanitize_text_field( $row['bill id'] ) : null;
			$bill_number    = isset( $row['bill number'] ) ? sanitize_text_field( $row['bill number'] ) : null;

			if ( empty( $transaction_id ) || empty( $bill_number ) ) {
				error_log( 'Skipping row: missing transaction_id or bill_number' );
				continue;
			}

			// // Prevent duplicates
			// $exists = $wpdb->get_var(
			// $wpdb->prepare(
			// "SELECT id FROM $table WHERE transaction_id = %s",
			// $transaction_id
			// )
			// );

			// if ( $exists ) {
			// ++$skipped;
			// continue;
			// }

			// -------------------
			// Parse date + time
			// -------------------
			$date     = isset( $row['date'] ) ? explode( ',', $row['date'] )[0] : '';
			$time     = isset( $row['time'] ) ? $row['time'] : '';
			$datetime = date( 'Y-m-d H:i:s', strtotime( $date . ' ' . $time ) );

			// -------------------
			// Calculate values
			// -------------------
			$sales          = floatval( $row['sales'] ?? 0 );
			$tax            = floatval( $row['tax amount'] ?? 0 );
			$service_charge = floatval( $row['service charges'] ?? 0 );
			$discounts      = floatval( $row['discount revenue'] ?? 0 );
			$payment_total  = floatval( $row['payment total'] ?? 0 );

			$subtotal = $sales + $discounts; // subtotal includes discount
			$total    = $sales + $tax + $service_charge; // Total = Sales + Tax + Service
			$gratuity = $payment_total - $total; // Extra payment difference

			// -------------------
			// Eat In / Takeout
			// -------------------
			$order_type_raw = strtolower( trim( $row['order type'] ?? '' ) );
			if ( $order_type_raw === 'dinein' ) {
				$eat_in     = 1;
				$order_type = 'Eat In';
			} elseif ( $order_type_raw === 'takeout' ) {
				$eat_in     = 0;
				$order_type = 'Takeaway';
			} elseif ( $order_type_raw === 'delivery' ) {
				$eat_in     = 0;
				$order_type = 'Delivery';
			} else {
				$eat_in     = null;
				$order_type = $row['order type'] ?? 'Unknown';
			}

			// -------------------
			// Insert into DB
			// -------------------
			$wpdb->replace(
				$table,
				array(
					'transaction_id'    => $transaction_id,
					'site_id'           => 10, // default site_id
					'order_ref'         => sanitize_text_field( $row['bill number'] ?? '' ),
					'order_ref2'        => sanitize_text_field( $row['order number'] ?? '' ),
					'complete_datetime' => $datetime,
					'complete_date'     => date( 'Y-m-d', strtotime( $datetime ) ),
					'complete_time'     => date( 'H:i:s', strtotime( $datetime ) ),
					'clerk_name'        => sanitize_text_field( $row['staff'] ?? '' ),
					'order_type'        => $order_type,
					'channel_id'        => intval( $row['order type id'] ?? 0 ),
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

			++$inserted;
		}

		return array(
			'status'   => 'success',
			'inserted' => $inserted,
			'skipped'  => $skipped,
		);
	}
}
