<?php
/**
 * Permissions.
 *
 * @package WRM
 */

namespace WRM\RestApi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Permission.
 *
 * @since 1.0.0
 */
class Permission {

	/**
	 * Register Rouites.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $ns Namespace.
	 */
	public static function register( $ns ) {

		register_rest_route(
			$ns,
			'/permissions',
			array(
				'methods'             => 'GET',
				'callback'            => function () {

					$access = wpac()->permissions();

					return array(
						'dashboard'   => $access->can( 'wrm_view_dashboard' ),
						'sales'       => $access->can( 'wrm_view_sales' ),
						'items'       => $access->can( 'wrm_view_items' ),
						'data'        => $access->can( 'wrm_refresh_data' ),
						'daily_sales' => $access->can( 'wrm_view_daily_sales' ),
					);
				},
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);
	}
}
