<?php
namespace WRM\RestApi;

use WP_Error;
use WRM\Services\ReportService;

class Reports {

	public static function register( $ns ) {

		// DASHBOARD
		register_rest_route(
			$ns,
			'reports/dashboard',
			array(
				'methods'             => 'GET',
				'callback'            => array( ReportService::class, 'dashboard' ),
				'permission_callback' => function ( $request ) {
					return self::permission_check( $request, 'wrm_view_dashboard_report' );
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
					return self::permission_check( $request, 'wrm_view_sales_report' );
				},
			)
		);

		// META REPORT
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
	 * Dashboard permission check
	 */
	public static function dashboard_permissions( $request ) {
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
	 * Sales report permission check
	 */
	public static function sales_permissions( $request ) {
		$access = wpac()->access();

		$entity_id = $request->get_param( 'entity' ) ?: null;
		$site_id   = $request->get_param( 'site' ) ?: null;

		// General capability
		if ( ! $access->can( 'view_sales_report' ) ) {
			return new WP_Error(
				'forbidden',
				'You do not have permission to view sales reports.',
				array( 'status' => 403 )
			);
		}

		// Entity-level scope
		if ( $entity_id && ! $access->can( 'view_sales_report', $entity_id ) ) {
			return new WP_Error(
				'forbidden',
				'You are not allowed to access this entity.',
				array( 'status' => 403 )
			);
		}

		// Site-level scope
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
	 * Meta report permission check
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
