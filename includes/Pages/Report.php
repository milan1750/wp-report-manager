<?php
/**
 * Settings page for storing external POS API keys.
 *
 * @package WP_Report_Manager
 */

namespace WRM\Pages;

/**
 * Admin UI for storing POS integration settings (API keys).
 *
 * @package WP_Report_Manager
 */
class Report {

	/**
	 * Render settings page and handle submission.
	 *
	 * @since 1.0.0
	 */
	public static function page(): void {
		echo '<div id="wrm-report-root"></div>';
	}
}
