<?php
/**
 * Reports REST API
 *
 * @package WP_Report_Manager
 */

namespace WRM\RestApi;

use WRM\Services\ReportService;

/**
 * Reports REST API
 *
 * Handles:
 * - starting a background fetch job
 * - checking job status
 * - cancelling running job
 */
class Reports {

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
	 *
	 * @param string $ns Namespace for the REST API routes.
	 */
	public static function register( $ns ) {

		// REPORTS.
		register_rest_route(
			$ns,
			'/dashboard',
			array(
				'methods'             => 'GET',
				'callback'            => array( Dashboard::class, 'index' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			)
		);

		register_rest_route(
			$ns,
			'/reports/sales',
			array(
				'methods'             => 'GET',
				'callback'            => array( ReportService::class, 'sales' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			)
		);

		// REPORTS.
		register_rest_route(
			$ns,
			'/reports/meta',
			array(
				'methods'             => 'GET',
				'callback'            => array( ReportService::class, 'meta' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			)
		);
	}
}
