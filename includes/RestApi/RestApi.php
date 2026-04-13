<?php
/**
 * Rest API.
 *
 * @package WP_Report_Manager
 */

namespace WRM\RestApi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WRM\RestApi\Dashboard;
use WRM\RestApi\Reports;
use WRM\RestApi\Data;
use WRM\RestApi\Fetch;
use WRM\Services\WeekService;


/**
 * Rest API Main Class.
 *
 * @since 1.0.0
 */
class RestApi {

	/**
	 * Initialize the REST API.
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Check if the current user has permission to access the REST API.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 */
	public static function register_routes(): void {

		$ns = 'wrm/v1';
		\WRM\RestApi\Users::register( $ns );
		\WRM\RestApi\Permission::register( $ns );
		\WRM\RestApi\Fetch::register( $ns );
		\WRM\RestApi\Weeks::register( $ns );
		\WRM\RestApi\Data::register( $ns );
		\WRM\RestApi\Reports::register( $ns );
	}







	// REPORTS
	// register_rest_route(
	// $namespace,
	// '/reports/meta',
	// array(
	// 'methods'             => 'GET',
	// 'callback'            => array( Reports::class, 'meta' ),
	// 'permission_callback' => array( self::class, 'permissions' ),
	// )
	// );

	// register_rest_route(
	// $namespace,
	// '/reports/sales',
	// array(
	// 'methods'             => 'GET',
	// 'callback'            => array( Reports::class, 'sales' ),
	// 'permission_callback' => array( self::class, 'permissions' ),
	// )
	// );
	// register_rest_route(
	// $namespace,
	// '/weeks',
	// array(
	// 'methods'             => 'GET',
	// 'callback'            => function () {
	// return WeekService::get_weeks();
	// },
	// 'permission_callback' => array( self::class, 'permissions' ),
	// )
	// );
	// }
}
