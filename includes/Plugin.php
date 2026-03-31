<?php
/**
 * Customer Post Type - Products.
 *
 * @package WP_Report_Manager
 */

namespace WRM;

use WRM\RestApi\RestApi;
use WRM\Pages\DataPage;
use WRM\Pages\Settings;
use WRM\Pages\Report;
use WRM\Services\FetchService;

/**
 * Plugin Main Class.
 *
 * @since 1.0.0
 */
class Plugin {

	/**
	 * Plugin Initialization.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		RestApi::init();
		add_action(
			'wrm_run_fetch_job',
			array( FetchService::class, 'run' ),
			10,
			1
		);
		add_action( 'admin_menu', array( self::class, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'scripts' ) );
	}

	/**
	 * Plugin Page.
	 *
	 * @since 1.0.0
	 */
	public static function page() {
		echo '<div id="wrm-root"></div>';
	}

	/**
	 * Plugin Menu.
	 *
	 * @since 1.0.0
	 */
	public static function register_menu() {
		add_menu_page(
			'Report Manager',
			'Report Manager',
			'manage_options',
			'report-manager',
			array( self::class, 'page' ),   // Callback.
			'dashicons-admin-generic',
			25
		);
		// Submenu: Dashboard (optional but good UX).
		add_submenu_page(
			'report-manager',
			'Dashboard',
			'Dashboard',
			'manage_options',
			'report-manager',
			array( self::class, 'page' )
		);

		// Submenu: Data Page.
		add_submenu_page(
			'report-manager',
			'Data Manager',
			'Data',
			'manage_options',
			'report-manager-data',
			array( DataPage::class, 'page' ) // NEW CALLBACK.
		);
		// Submenu: Settings Page.
		add_submenu_page(
			'report-manager',
			'Reports',
			'Reports',
			'manage_options',
			'report-manager-reports',
			array( Report::class, 'page' )
		);
		// Submenu: Settings Page.
		add_submenu_page(
			'report-manager',
			'Settings',
			'Settings',
			'manage_options',
			'report-manager-settings',
			array( Settings::class, 'page' )
		);
	}

	/**
	 * Plugin Scripts.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $hook Current admin page hook.
	 */
	public static function scripts( $hook ) {
		if ( 'toplevel_page_report-manager' === $hook ) {
			// React app.
			wp_enqueue_script(
				'wrm-script',
				plugin_dir_url( __DIR__ ) . 'build/index.js',
				array(
					'wp-element',      // React & ReactDOM.
					'wp-block-editor', // RichText.
					'wp-components',   // UI components.
					'wp-data',         // state management.
					'wp-i18n',         // localization.
				),
				filemtime( plugin_dir_path( __DIR__ ) . 'build/index.js' ),
				true
			);
			// Gutenberg styles (important).
			wp_enqueue_style( 'wp-edit-blocks' );
			wp_enqueue_style( 'wp-block-editor' );

		} elseif ( 'report-manager_page_report-manager-data' === $hook ) {
			wp_enqueue_script( 'jquery' ); // important.
			wp_enqueue_script(
				'wrm-script',
				WRM_PLUGIN_URL . 'assets/js/admin.js',
				array( 'jquery' ),
				WRM_VERSION,
				true
			);
		}
		// Custom styles.
		wp_enqueue_style(
			'wrm-style',
			plugin_dir_url( __DIR__ ) . 'build/style-index.css',
			array(),
			filemtime( plugin_dir_path( __DIR__ ) . 'build/style-index.css' )
		);

		// Pass REST API info.
		wp_localize_script(
			'wrm-script',
			'WRM_API',
			array(
				'url'      => rest_url( 'wrm/v1/' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'sites'    => wpac()->sites()->get_all( true ),
				'entities' => wpac()->entities()->get_all( true ),
			)
		);
	}

	/**
	 * Plugin Activation.
	 *
	 * @since 1.0.0
	 */
	public static function activate(): void {
		Tables::create_tables();
	}
}
