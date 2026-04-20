<?php
/**
 * Reports.
 *
 * @package WRM
 */

namespace WRM\RestApi;

use WP_Error;
use WRM\Services\ReportService;

/**
 * Reports.
 *
 * @since 1.0.0
 */
class Reports {

	/**
	 * Register.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $ns Namespace.
	 */
	public static function register( $ns ) {

		// Power Bi API.
		register_rest_route(
			$ns,
			'/reports/bi/daily-sales',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'daily_sales_download_bi' ),
				'permission_callback' => array( self::class, 'validate_api_key' ),
				'args'                => array(
					'from' => array( 'required' => true ),
					'to'   => array( 'required' => true ),
				),
			)
		);

		// DASHBOARD.
		register_rest_route(
			$ns,
			'reports/dashboard',
			array(
				'methods'             => 'GET',
				'callback'            => array( ReportService::class, 'dashboard' ),
				'permission_callback' => function ( $request ) {
					return self::permission_check( $request, 'wrm_view_dashboard' );
				},
			)
		);

		// SALES REPORT.
		register_rest_route(
			$ns,
			'/reports/sales',
			array(
				'methods'             => 'GET',
				'callback'            => array( ReportService::class, 'sales' ),
				'permission_callback' => function ( $request ) {
					return self::permission_check( $request, 'wrm_view_sales' );
				},
			)
		);

		/**
		 * ======================================
		 * DAILY SALES VIEW (NEW - ISOLATED)
		 * ======================================
		 */
		register_rest_route(
			$ns,
			'/reports/daily-sales',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'daily_sales_view' ),
				'permission_callback' => function ( $request ) {
						return self::permission_check( $request, 'wrm_view_daily_sales' );
				},
				'args'                => array(
					'from' => array( 'required' => true ),
					'to'   => array( 'required' => true ),
				),
			)
		);

		/**
		 * ======================================
		 * DAILY SALES EXCEL DOWNLOAD
		 * ======================================
		 */
		register_rest_route(
			$ns,
			'/reports/daily-sales/download',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'daily_sales_download' ),
				'permission_callback' => function ( $request ) {
							return self::permission_check( $request, 'wrm_view_daily_sales' );
				},
				'args'                => array(
					'from' => array( 'required' => true ),
					'to'   => array( 'required' => true ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/reports/daily-sales/download-flat',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'daily_sales_download_flat' ),
				'permission_callback' => function ( $request ) {
							return self::permission_check( $request, 'wrm_view_daily_sales' );
				},
				'args'                => array(
					'from' => array( 'required' => true ),
					'to'   => array( 'required' => true ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/reports/sales/download',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'sales_download' ),
				'permission_callback' => function ( $request ) {
							return self::permission_check( $request, 'wrm_view_sales' );
				},
				'args'                => array(
					'from' => array( 'required' => true ),
					'to'   => array( 'required' => true ),
				),
			)
		);

		// Items REPORT.
		register_rest_route(
			$ns,
			'/reports/item-categories',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'item_categories' ),
				'permission_callback' => function ( $request ) {
					return self::permission_check( $request, 'wrm_view_items' );
				},
			)
		);
		register_rest_route(
			$ns,
			'/reports/items',
			array(
				'methods'             => 'GET',
				'callback'            => array( ReportService::class, 'items' ),
				'permission_callback' => function ( $request ) {
					return self::permission_check( $request, 'wrm_view_items' );
				},
			)
		);

		register_rest_route(
			$ns,
			'/reports/items-interval',
			array(
				'methods'             => 'GET',
				'callback'            => array( ReportService::class, 'items_interval' ),
				'permission_callback' => function ( $request ) {
					return self::permission_check( $request, 'wrm_view_items' );
				},
			)
		);

		register_rest_route(
			$ns,
			'/reports/items-interval/excel-download',
			array(
				'methods'             => 'GET',
				'callback'            => array( ReportService::class, 'items_interval_excel' ),
				'permission_callback' => function ( $request ) {
					return self::permission_check( $request, 'wrm_view_items' );
				},
			)
		);

				register_rest_route(
					$ns,
					'/reports/items-interval/pdf-download',
					array(
						'methods'             => 'GET',
						'callback'            => array( ReportService::class, 'items_interval_pdf' ),
						'permission_callback' => function ( $request ) {
							return self::permission_check( $request, 'wrm_view_items' );
						},
					)
				);

		// META REPORT.
		register_rest_route(
			$ns,
			'/reports/meta',
			array(
				'methods'             => 'GET',
				'callback'            => array( ReportService::class, 'meta' ),
				'permission_callback' => array( self::class, 'meta_permissions' ),
			)
		);
	}

	/**
	 * Item Categories.
	 *
	 * @since 1.0.0
	 *
	 * @param  \WP_REST_Request $request Request.
	 */
	public static function item_categories( \WP_REST_Request $request ) {

		global $wpdb;

		$table = $wpdb->prefix . 'wrm_transaction_items';

		$entity = $request['entity'] ?? 'all';
		$site   = $request['site'] ?? 'all';

		// =========================
		// LOAD ENTITIES & SITES
		// =========================
		$all_sites   = wpac()->sites()->all();
		$permissions = wpac()->permissions();

		$allowed_sites = array();

		foreach ( $all_sites as $s ) {

			// entity filter.
			if ( 'all' !== $entity && (int) $entity !== $s->entity_id ) {
				continue;
			}

			// site filter.
			if ( 'all' !== $site && (int) $site !== (int) $s->site_id ) {
				continue;
			}

			$context = array(
				'entity_id' => $s->entity_id,
				'site_id'   => $s->id,
			);

			if ( ! $permissions->can( 'wrm_view_items', $context ) ) {
				continue;
			}

			$allowed_sites[] = (int) $s->site_id;
		}

		if ( empty( $allowed_sites ) ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'data'    => array(),
				)
			);
		}
		$ids = implode( ',', array_map( 'intval', $allowed_sites ) );

		// =========================
		// QUERY (FILTERED)
		// =========================
		$sql = "
		SELECT
			category_name,
			COUNT(*) AS items_count
			FROM {$table}
			WHERE category_name IS NOT NULL
				AND category_name != ''
				AND site_id IN ($ids)
			GROUP BY category_name
			ORDER BY category_name ASC
		";

		$results = $wpdb->get_results( $sql, ARRAY_A );

		$data = array_map(
			function ( $row ) {
				return array(
					'name'  => $row['category_name'],
					'count' => (int) $row['items_count'],
				);
			},
			$results
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * View Daily Sales.
	 *
	 * @since 1.0.0
	 *
	 * @param  \WP_REST_Request $request Request.
	 */
	public static function daily_sales_view( \WP_REST_Request $request ) {

		$from = sanitize_text_field( $request->get_param( 'from' ) );
		$to   = sanitize_text_field( $request->get_param( 'to' ) );

		if ( ! $from || ! $to ) {
			return new \WP_Error(
				'missing_dates',
				'from and to are required',
				array( 'status' => 400 )
			);
		}

		$entity = sanitize_text_field( $request->get_param( 'entity' ) ?? 'all' );
		$site   = sanitize_text_field( $request->get_param( 'site' ) ?? 'all' );

		$data = ReportService::daily_sales( $from, $to, $entity, $site );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Download Daily Sales.
	 *
	 * @since 1.0.0
	 *
	 * @param  \WP_REST_Request $request Request.
	 */
	public static function daily_sales_download( \WP_REST_Request $request ) {

		$from = sanitize_text_field( $request['from'] );
		$to   = sanitize_text_field( $request['to'] );

		$entity = $request['entity'] ?? 'all';
		$site   = $request['site'] ?? 'all';

		// reuse your existing function.
		ReportService::wrm_generate_sales_excel( $from, $to, $entity, $site );

		exit;
	}

	/**
	 * Download Daily Sales.
	 *
	 * @since 1.0.0
	 *
	 * @param  \WP_REST_Request $request Request.
	 */
	public static function daily_sales_download_flat( \WP_REST_Request $request ) {

		$from = sanitize_text_field( $request['from'] );
		$to   = sanitize_text_field( $request['to'] );

		$entity = $request['entity'] ?? 'all';
		$site   = $request['site'] ?? 'all';

		// reuse your existing function.
		ReportService::wrm_generate_sales_excel_flat( $from, $to, $entity, $site );

		exit;
	}

	/**
	 * BI Daily Sales API (Power BI / Tableau / Excel).
	 *
	 * @since 1.0.0
	 *
	 * @param  \WP_REST_Request $request Request.
	 */
	public static function daily_sales_download_bi( \WP_REST_Request $request ) {

		self::track_api_hit();

		global $wpdb;

		// ======================
		// API KEY CHECK
		// ======================
		$api_key   = sanitize_text_field( $request->get_param( 'api_key' ) );
		$saved_key = get_option( 'wrm_bi_api_key' );

		if ( empty( $api_key ) || $api_key !== $saved_key ) {
			return new \WP_Error(
				'invalid_api_key',
				'Invalid API Key',
				array( 'status' => 403 )
			);
		}

		// ======================
		// INPUTS
		// ======================
		$from   = sanitize_text_field( $request->get_param( 'from' ) );
		$to     = sanitize_text_field( $request->get_param( 'to' ) );
		$entity = sanitize_text_field( $request->get_param( 'entity' ) ?? 'all' );
		$site   = sanitize_text_field( $request->get_param( 'site' ) ?? 'all' );
		$format = strtolower( sanitize_text_field( $request->get_param( 'format' ) ?? 'json' ) );

		if ( empty( $from ) || empty( $to ) ) {
			return new \WP_Error(
				'missing_dates',
				'from and to are required',
				array( 'status' => 400 )
			);
		}

		$table = $wpdb->prefix . 'wrm_transactions';

		// ======================
		// WHERE BUILDER
		// ======================
		$where  = 'WHERE complete_datetime BETWEEN %s AND %s AND complete = 1 AND canceled != 1';
		$params = array(
			$from . ' 00:00:00',
			$to . ' 23:59:59',
		);

		if ( 'all' !== $site ) {
			$where   .= ' AND site_id = %d';
			$params[] = (int) $site;
		}

		// ======================
		// SQL QUERY
		// ======================
		$sql = "
		SELECT
			DATE(complete_datetime) AS date,
			site_id,
			COUNT(*) AS orders,
			SUM(subtotal - discounts) AS net,
			SUM(tax) AS vat,
			SUM(total) AS gross,
			SUM(gratuity) AS gratuity
		FROM {$table}
		{$where}
		GROUP BY DATE(complete_datetime), site_id
		ORDER BY date ASC
	";

		$query = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows  = $wpdb->get_results( $query, ARRAY_A );  // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $rows ) ) {
			return rest_ensure_response(
				array(
					'data' => array(),
				)
			);
		}

		// ======================
		// LOOKUP MAPS (FAST)
		// ======================
		$entities = wpac()->entities()->all();
		$sites    = wpac()->sites()->all();

		$entity_map      = array();
		$site_map        = array();
		$site_entity_map = array();

		foreach ( $entities as $e ) {
			$entity_map[ $e->id ] = $e->name;
		}

		foreach ( $sites as $s ) {
			$site_map[ $s->site_id ]        = $s->name;
			$site_entity_map[ $s->site_id ] = $s->entity_id;
		}

		// ======================
		// ENRICH DATA
		// ======================
		$ordered_rows = array();

		foreach ( $rows as $row ) {

			$site_id = (int) $row['site_id'];

			$entity_id = $site_entity_map[ $site_id ] ?? 0;

			$ordered_rows[] = array(
				'date'      => $row['date'],
				'entity_id' => $entity_id,
				'entity'    => $entity_map[ $entity_id ] ?? '',
				'site_id'   => $site_id,
				'site'      => $site_map[ $site_id ] ?? 'NA',
				'orders'    => (int) $row['orders'],
				'net'       => (float) $row['net'],
				'vat'       => (float) $row['vat'],
				'gross'     => (float) $row['gross'],
				'gratuity'  => (float) $row['gratuity'],
			);
		}

		unset( $row );

		// ======================
		// CSV OUTPUT
		// ======================
		if ( 'csv' === $format ) {

			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=daily_sales_' . gmdate( 'Ymd_His' ) . '.csv' );

			$out = fopen( 'php://output', 'w' );

			fputcsv( $out, array_keys( $ordered_rows[0] ) );
			foreach ( $ordered_rows as $row ) {
				fputcsv( $out, $row );
			}

			fclose( $out );
			exit;
		}

		// ======================
		// JSON OUTPUT (Power BI FRIENDLY)
		// ======================
		return rest_ensure_response( $ordered_rows );
	}

	/**
	 * Download Daily Sales.
	 *
	 * @since 1.0.0
	 *
	 * @param  \WP_REST_Request $request Request.
	 */
	public static function sales_download( \WP_REST_Request $request ) {

		$from = sanitize_text_field( $request['from'] );
		$to   = sanitize_text_field( $request['to'] );

		$entity = $request['entity'] ?? 'all';
		$site   = $request['site'] ?? 'all';

		// reuse your existing function.
		ReportService::wrm_generate_site_performance_excel( $from, $to, $entity, $site );

		exit;
	}

	/**
	 * Dashboard permission check.
	 */
	public static function dashboard_permissions() {
		$access = wpac()->access();

		if ( ! $access->can( 'view_dashboard' ) ) {
			return new WP_Error(
				'forbidden',
				'You do not have permission to access the dashboard.',
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Sales report permission check.
	 *
	 * @param \WP_Request $request Request.
	 */
	public static function sales_permissions( $request ) {
		$access = wpac()->access();

		$entity_id = $request->get_param( 'entity' ) ? $request->get_param( 'entity' ) : null;
		$site_id   = $request->get_param( 'site' ) ? $request->get_param( 'entity' ) : null;

		// General capability.
		if ( ! $access->can( 'view_sales_report' ) ) {
			return new WP_Error(
				'forbidden',
				'You do not have permission to view sales reports.',
				array( 'status' => 403 )
			);
		}

		// Entity-level scope.
		if ( $entity_id && ! $access->can( 'view_sales_report', $entity_id ) ) {
			return new WP_Error(
				'forbidden',
				'You are not allowed to access this entity.',
				array( 'status' => 403 )
			);
		}

		// Site-level scope.
		if ( $site_id && ! $access->can( 'view_sales_report', $site_id ) ) {
			return new WP_Error(
				'forbidden',
				'You are not allowed to access this site.',
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Meta report permission check.
	 *
	 * @param \WP_Request $request Request.
	 * @param string      $capability Capability.
	 */
	public static function permission_check( $request, $capability ) {
		$permission = wpac()->permissions();

		if ( ! $permission->can( $capability ) ) {
			return new WP_Error(
				'forbidden',
				'You do not have ' . $capability . ' permission to access this report.',
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Validate BI API key.
	 *
	 * @param \WP_Request $request Request.
	 */
	public static function validate_api_key( $request ) {

		$api_key = sanitize_text_field( $request->get_param( 'api_key' ) );

		$saved_key = get_option( 'wrm_bi_api_key' );

		if ( empty( $api_key ) || $api_key !== $saved_key ) {

			return new \WP_Error(
				'invalid_api_key',
				'Invalid API Key',
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Track Power BI API Hit.
	 *
	 * Keeps only last 5 days of stats.
	 *
	 * @since 1.0.0
	 */
	private static function track_api_hit() {

		$stats = get_option( 'wrm_api_usage_stats', array() );

		$today = gmdate( 'Y-m-d' );

		// increment today.
		if ( ! isset( $stats[ $today ] ) ) {
			$stats[ $today ] = 0;
		}

		++$stats[ $today ];

		// keep only last 5 days.
		$cutoff = gmdate( 'Y-m-d', strtotime( '-5 days' ) );

		foreach ( $stats as $date => $count ) {
			if ( $date < $cutoff ) {
				unset( $stats[ $date ] );
			}
		}

		update_option( 'wrm_api_usage_stats', $stats );
	}
}
