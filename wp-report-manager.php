<?php
/**
 * Plugin Name: WP Report Manager
 * Plugin URI:  https://milanmalla.com/wp-report-manager
 * Description: A WordPress plugin to generate reports for products with role-based access support.
 * Version:     1.0.0
 * Author:      Milan Malla
 * License:     GPL-2.0-or-later
 * Text Domain: wp-report-manager
 *
 * @package WP_Report_Manager
 */

defined( 'ABSPATH' ) || exit;


/**
 * ------------------------------------------------------------------------
 * PLUGIN CONSTANTS
 * ------------------------------------------------------------------------
 */
define( 'WRM_VERSION', '1.0.0' );
define( 'WRM_PLUGIN_FILE', __FILE__ );
define( 'WRM_PLUGIN_DIR', __DIR__ );
define( 'WRM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Composer autoloader (safe load).
 *
 * NOTE:
 * This plugin should be the ONLY place in your ecosystem
 * that loads shared Composer dependencies.
 */
$autoloader = WRM_PLUGIN_DIR . '/vendor/autoload.php';

if ( is_readable( $autoloader ) ) {
	require_once $autoloader;
} else {

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'WRM: Composer autoload missing. Run composer install.' );
	}

	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>';
			echo 'WP Report Manager: Missing dependencies. Run <code>composer install</code>.';
			echo '</p></div>';
		}
	);

	return;
}
/**
 * ------------------------------------------------------------------------
 * PLUGIN BOOTSTRAP (SAFE INITIALIZATION)
 * ------------------------------------------------------------------------
 *
 * We initialize the plugin ONLY after:
 * - WordPress has loaded all plugins
 * - Composer is safely loaded (if available)
 */
add_action(
	'wpac_plugin_loaded',
	function () {

		// 2. Ensure main plugin class exists before initializing
		if ( ! class_exists( \WRM\Plugin::class ) ) {
			return;
		}

		add_action(
			'init',
			function () {

				\WPAC\Core\PermissionRegistry::register_module(
					'report',
					array(
						'view',
						'create',
						'update',
						'delete',
					),
					'Reports'
				);

				add_filter(
					'wpac_get_capabilities',
					function ( $caps ) {
						$caps['view_report']   = array(
							'label'  => 'View Reports',
							'module' => 'report',
						);
						$caps['create_report'] = array(
							'label'  => 'Create Reports',
							'module' => 'report',
						);
						$caps['update_report'] = array(
							'label'  => 'Update Reports',
							'module' => 'report',
						);
						$caps['delete_report'] = array(
							'label'  => 'Delete Reports',
							'module' => 'report',
						);
						return $caps;
					}
				);
				add_filter(
					'wpac_get_capabilities',
					function ( $caps ) {
						$caps['view_barcode']   = array(
							'label'  => 'View Barcode',
							'module' => 'barcode',
						);
						$caps['create_barcode'] = array(
							'label'  => 'Create Barcode',
							'module' => 'barcode',
						);
						$caps['update_barcode'] = array(
							'label'  => 'Update Barcode',
							'module' => 'barcode',
						);
						$caps['delete_barcode'] = array(
							'label'  => 'Delete Barcode',
							'module' => 'barcode',
						);
						return $caps;
					}
				);
			}
		);

		// Optional global reference for backward compatibility.
		$GLOBALS['wrm'] = \WRM\Plugin::init();
		do_action( 'wrm_plugin_loaded' );
	}
);

/**
 * ------------------------------------------------------------------------
 * ACTIVATION HOOK
 * ------------------------------------------------------------------------
 */
function wrm_activate(): void {
	if ( class_exists( \WRM\Plugin::class ) ) {
		\WRM\Plugin::activate();
	}
}

register_activation_hook( __FILE__, 'wrm_activate' );

/**
 * ------------------------------------------------------------------------
 * DEACTIVATION HOOK
 * ------------------------------------------------------------------------
 */
function wrm_deactivate(): void {
	if ( class_exists( \WRM\Plugin::class ) ) {
		// \WRM\Plugin::deactivate();
	}
}

register_deactivation_hook( __FILE__, 'wrm_deactivate' );
