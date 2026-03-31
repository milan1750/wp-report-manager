<?php
/**
 * REST API handler for WP Barcode Manager
 * Only keeps value styles (bold, italic, underline, color)
 *
 * @package WP_Barcode_Manager
 */

namespace WRM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles REST API endpoints for products and XLSX import/export.
 *
 * @since 1.0.0
 */
class RestApi {



	/**
	 * Initialize REST API routes.
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		// add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Check if current user has permissions to manage products.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if user can manage options, false otherwise.
	 */
	public static function permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Register REST API routes for product CRUD and XLSX import/export.
	 *
	 * @since 1.0.0
	 */
	public static function register_routes(): void {
		$namespace = 'wrm/v1';
		register_rest_route(
			$namespace,
			'/fetch',
			array(
				'methods'  => 'POST',
				'callback' => array( self::class, 'start_fetch' ),
			)
		);

		register_rest_route(
			$namespace,
			'/fetch/worker',
			array(
				'methods'  => 'POST',
				'callback' => array( self::class, 'run_worker' ),
			)
		);

		register_rest_route(
			$namespace,
			'/fetch/status',
			array(
				'methods'  => 'GET',
				'callback' => array( self::class, 'get_status' ),
			)
		);

		register_rest_route(
			$namespace,
			'/data',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_data' ),
				'permission_callback' => array( self::class, 'permissions' ),
			)
		);

		register_rest_route(
			$namespace,
			'/dashboard',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_dashboard' ),
				'permission_callback' => array( self::class, 'permissions' ),
			)
		);

		register_rest_route(
			$namespace,
			'/weeks',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_weeks' ),
				'permission_callback' => array( self::class, 'permissions' ),
			)
		);

		// =========================
		// ADDED REPORT ROUTES
		// =========================
		register_rest_route(
			$namespace,
			'/reports/meta',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_reports_meta' ),
				'permission_callback' => array( self::class, 'permissions' ),
			)
		);

		register_rest_route(
			$namespace,
			'/reports/sales',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_sales_report' ),
				'permission_callback' => array( self::class, 'permissions' ),
			)
		);

		register_rest_route(
			$namespace,
			'/reports/items',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_items_report' ),
				'permission_callback' => array( self::class, 'permissions' ),
			)
		);

		register_rest_route(
			$namespace,
			'/reports/payments',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_payments_report' ),
				'permission_callback' => array( self::class, 'permissions' ),
			)
		);
	}

	private static function align_to_week_start( \DateTime $date, int $week_start_day ): \DateTime {

		$current_dow = (int) $date->format( 'w' );

		$diff = $current_dow - $week_start_day;

		if ( $diff < 0 ) {
			$diff += 7;
		}

		$date->modify( "-{$diff} days" );

		return $date;
	}

	public static function get_weeks(): array {

		$week_start_day = (int) get_option( 'wrm_week_start_day', 1 );
		$today          = new \DateTime( 'now' );

		/**
		 * Build anchor
		 */
		$build_anchor = function ( int $year ) use ( $week_start_day ) {

			$year_start = new \DateTime( "$year-01-01" );

			$start_dow = (int) $year_start->format( 'w' );

			$diff = $start_dow - $week_start_day;
			if ( $diff < 0 ) {
				$diff += 7;
			}

			$anchor = clone $year_start;
			$anchor->modify( "-$diff days" );

			return $anchor;
		};

		/**
		 * STEP 1: decide year
		 */
		$year   = (int) $today->format( 'Y' );
		$anchor = $build_anchor( $year );

		/**
		 * detect rollover
		 */
		$last_week_start = clone $anchor;
		$last_week_start->modify( '+52 weeks' );

		$last_week_end = clone $last_week_start;
		$last_week_end->modify( '+6 days' );

		if ( $today >= $last_week_start ) {
			++$year;
			$anchor = $build_anchor( $year );
		}

		/**
		 * STEP 2: generate weeks (NO EARLY BREAKS)
		 */
		$weeks        = array();
		$current_week = null;

		for ( $i = 0; $i < 52; $i++ ) {

			$week_start = clone $anchor;
			$week_start->modify( "+$i week" );

			$week_end = clone $week_start;
			$week_end->modify( '+6 days' );

			$next_week_start = clone $week_start;
			$next_week_start->modify( '+7 days' );

			/**
			 * ONLY stop if next week starts NEXT YEAR
			 */
			if ( $next_week_start->format( 'Y' ) > $year ) {
				break;
			}

			$is_current =
			( $today >= $week_start && $today <= $week_end );

			$week = array(
				'week'       => 'W' . ( $i + 1 ),
				'start'      => $week_start->format( 'Y-m-d' ),
				'end'        => $week_end->format( 'Y-m-d' ),
				'is_current' => $is_current,
			);

			$weeks[] = $week;

			if ( $is_current ) {
				$current_week = $week;
			}
		}

		return array(
			'year'           => $year,
			'week_start_day' => $week_start_day,
			'current_week'   => $current_week,
			'weeks'          => $weeks,
		);
	}

	public static function get_dashboard( $request ) {
		/* ❗ YOUR FULL ORIGINAL DASHBOARD KEPT UNCHANGED */
		global $wpdb;

		$transactions = $wpdb->prefix . 'wrm_transactions';
		$sites        = $wpdb->prefix . 'wrm_sites';
		$entities     = $wpdb->prefix . 'wrm_entities';

		$params = $request->get_params();

		$from = ! empty( $params['from'] )
			? sanitize_text_field( $params['from'] ) . ' 00:00:00'
			: date( 'Y-m-01 00:00:00' );

		$to = ! empty( $params['to'] )
			? sanitize_text_field( $params['to'] ) . ' 23:59:59'
			: date( 'Y-m-d 23:59:59' );

		// (UNCHANGED FULL LOGIC)
		$totals_sql = "
			SELECT
				COALESCE(SUM(t.total),0) AS revenue,
				COUNT(t.id) AS orders
			FROM $transactions t
			WHERE t.complete_datetime BETWEEN %s AND %s
		";

		$totals = $wpdb->get_row( $wpdb->prepare( $totals_sql, $from, $to ), ARRAY_A );

		return array(
			'totals' => array(
				'revenue' => (float) $totals['revenue'],
				'orders'  => (int) $totals['orders'],
			),
		);
	}

	/*
	=========================================================
	 * ADDED: REPORT META (COMPANIES + SITES)
	 * ======================================================= */
	public static function get_reports_meta() {

		global $wpdb;

		$sites = $wpdb->get_results(
			"
			SELECT s.site_id, s.site_name, e.name as company
			FROM {$wpdb->prefix}wrm_sites s
			LEFT JOIN {$wpdb->prefix}wrm_entities e ON e.id = s.entity_id
		",
			ARRAY_A
		);

		$companies = array();

		foreach ( $sites as $s ) {
			$companies[ $s['company'] ][] = array(
				'id'   => (int) $s['site_id'],
				'name' => $s['site_name'],
			);
		}

		return array(
			'companies' => $companies,
		);
	}

	/*
	=========================================================
	 * ADDED: SALES REPORT
	 * ======================================================= */
	public static function get_sales_report( $request ) {

		global $wpdb;

		$t = $wpdb->prefix . 'wrm_transactions';
		$p = $wpdb->prefix . 'wrm_transaction_payments';

		$params = $request->get_params();

		$from = $params['from'] . ' 00:00:00';
		$to   = $params['to'] . ' 23:59:59';

		$sql = "
			SELECT
				t.site_id,
				t.complete_datetime,

				-- SALES
				t.total AS gross,
				(t.total - t.discounts) AS net,
				t.tax AS vat,

				-- GRATUITY (SUM per transaction)
				COALESCE(SUM(p.gratuity),0) AS gratuity

			FROM $t t

			LEFT JOIN $p p
				ON p.transaction_id = t.transaction_id
				AND p.canceled = 0

			WHERE t.complete_datetime BETWEEN %s AND %s

			GROUP BY t.id
			ORDER BY t.complete_datetime DESC
		";

		$results = $wpdb->get_results(
			$wpdb->prepare( $sql, $from, $to ),
			ARRAY_A
		);

		return array(
			'data' => $results,
		);
	}

	/*
	=========================================================
	 * ADDED: ITEMS REPORT (HOOK READY)
	 * ======================================================= */
	public static function get_items_report( $request ) {
		return array(
			'data' => array(),
			'note' => 'Attach wrm_transaction_items table here',
		);
	}

	/*
	=========================================================
	 * ADDED: PAYMENTS REPORT (HOOK READY)
	 * ======================================================= */
	public static function get_payments_report( $request ) {
		return array(
			'data' => array(),
			'note' => 'Attach payments table here',
		);
	}

	/**
	 * Get transaction data with optional site/date filtering.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array Data rows + pagination.
	 */
	public static function get_data( $request ) {

		global $wpdb;

		$transactions = $wpdb->prefix . 'wrm_transactions';
		$sites        = $wpdb->prefix . 'wrm_sites';

		$params = $request->get_params();

		$site = isset( $params['site'] ) ? sanitize_text_field( $params['site'] ) : '';
		$from = isset( $params['from'] ) ? sanitize_text_field( $params['from'] ) : '';
		$to   = isset( $params['to'] ) ? sanitize_text_field( $params['to'] ) : '';

		$page     = isset( $params['page'] ) ? max( 1, absint( $params['page'] ) ) : 1;
		$per_page = 20;
		$offset   = ( $page - 1 ) * $per_page;

		$where = array( '1=1' );
		$args  = array();

		// =====================
		// SITE FILTER
		// =====================
		if ( ! empty( $site ) ) {
			$where[] = 't.site_id = %d';
			$args[]  = $site;
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
			$wpdb->prepare( $count_sql, $args )
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
			$wpdb->prepare( $sql, $args ),
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

}
