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

		// Items REPORT.
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
}
