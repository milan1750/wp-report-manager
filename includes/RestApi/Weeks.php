<?php
/**
 * Weeks REST API endpoint.
 *
 * @package WP_Report_Manager
 */

namespace WRM\RestApi;

use WRM\Services\WeekService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Weeks.
 *
 * @since 1.0.0
 */
class Weeks {

	/**
	 * Register REST API routes for Weeks.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $ns Namespace for the REST API routes.
	 */
	public static function register( $ns ) {

		register_rest_route(
			$ns,
			'/weeks',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'index' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle the request to fetch all weeks.
	 *
	 * @since 1.0.0
	 */
	public static function index() {
		return WeekService::get_weeks();
	}
}
