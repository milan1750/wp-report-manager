<?php
/**
 * Users REST API
 *
 * @package WP_Report_Manager
 */

namespace WRM\RestApi;

/**
 * Users REST API
 *
 * Handles:
 * - starting a background fetch job
 * - checking job status
 * - cancelling running job
 */
class Users {

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
	 *
	 * @param string $ns Namespace for the REST API routes.
	 */
	public static function register( $ns ) {

		register_rest_route(
			$ns,
			'/user/me',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'current_user' ),
				'permission_callback' => fn() => is_user_logged_in(),
			)
		);
	}

	/**
	 * Get current logged-in user profile.
	 *
	 * @since 1.0.0
	 */
	public static function current_user() {

		$user = wp_get_current_user();

		if ( ! $user || ! $user->ID ) {
			return array(
				'status'  => 'error',
				'message' => 'Not logged in',
			);
		}

		return array(
			'id'         => $user->ID,
			'username'   => $user->user_login,
			'name'       => $user->display_name,
			'email'      => $user->user_email,
			'roles'      => $user->roles,
			'registered' => $user->user_registered,
			'avatar'     => get_avatar_url( $user->ID ),
		);
	}
}
