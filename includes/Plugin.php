<?php
/**
 * Customer Post Type - Products.
 *
 * @package WP_Report_Manager
 */

namespace WRM;

use WRM\Corn;
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
		Corn::init();
		add_action( 'wrm_run_fetch_job', array( FetchService::class, 'run' ), 10, 1 );
		add_action( 'wpac_render_app_report', array( self::class, 'wp_report_manager_render_app' ) );
		add_action( 'admin_menu', array( self::class, 'register_menu' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'scripts' ) );

		add_filter(
			'wpac_get_capabilities',
			function ( $caps ) {
						$caps['wrm_view_dashboard'] = array(
							'label'  => 'View Dashboard',
							'module' => 'report',
						);
						$caps['wrm_view_sales']     = array(
							'label'  => 'View Sales',
							'module' => 'report',
						);
						$caps['wrm_view_items']     = array(
							'label'  => 'View Items',
							'module' => 'report',
						);
						$caps['wrm_refresh_data']   = array(
							'label'  => 'Refresh Data',
							'module' => 'report',
						);
						return $caps;
			}
		);
		add_filter(
			'wpac_get_registered_apps',
			function ( $apps ) {
				$apps[] = array(
					'slug' => 'report',
					'name' => 'Report',
					'icon' => WRM_PLUGIN_URL . 'assets/images/report.png',
				);
				return $apps;
			}
		);
	}


	/**
	 * Render Report App.
	 *
	 * @since 1.0.0
	 */
	public static function wp_report_manager_render_app() {
		?>
		<div id="wrm-root">
		</div> <!-- React app will mount here -->
		<?php
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
		// Get the current app from URL.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_app = isset( $_GET['app'] ) ? sanitize_text_field( $_GET['app'] ) : '';

		// Only enqueue for the "report" app.
		if ( 'report' === $current_app ) {
			wp_enqueue_script(
				'wrm-script',
				plugin_dir_url( __DIR__ ) . 'build/index.js',
				array( 'wp-element' ), // WordPress React & ReactDOM.
				filemtime( plugin_dir_path( __DIR__ ) . 'build/index.js' ),
				true
			);

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
					'entities' => wpac()->entities()->all_array(),
					'sites'    => wpac()->sites()->all_array(),
				)
			);
		} elseif ( 'report-manager_page_report-manager-data' === $hook ) {
			wp_enqueue_script( 'jquery' ); // important.
			wp_enqueue_script(
				'wrm-script',
				WRM_PLUGIN_URL . 'assets/js/admin.js',
				array( 'jquery' ),
				WRM_VERSION,
				true
			);
			wp_enqueue_style(
				'wrm-style',
				plugin_dir_url( __DIR__ ) . 'build/style-index.css',
				array(),
				filemtime( plugin_dir_path( __DIR__ ) . 'build/style-index.css' )
			);
			wp_localize_script(
				'wrm-script',
				'WRM_API',
				array(
					'url'      => rest_url( 'wrm/v1/' ),
					'nonce'    => wp_create_nonce( 'wp_rest' ),
					'entities' => wpac()->entities()->get_all(),
					'sites'    => wpac()->sites()->get_all(),
				)
			);
		}
	}

	/**
	 * Plugin Activation.
	 *
	 * @since 1.0.0
	 */
	public static function activate(): void {
		Tables::create_tables();
	}

	/**
	 * Plugin Deactivation.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate(): void {
		// To be implemented.
	}
}
