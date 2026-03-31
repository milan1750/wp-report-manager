<?php
/**
 * Customer Post Type - Products.
 *
 * @package WP_Report_Manager
 */

namespace WRM\Interfaces;

/**
 * POS Provider Interface.
 *
 * Defines the contract for POS data fetching implementations.
 *
 * @since 1.0.0
 */
interface PosProviderInterface {

	/**
	 * Transactions.
	 *
	 * @since 1.0.0
	 *
	 * @param  string   $start Start Date.
	 * @param  string   $end End Date.
	 * @param  string   $api_key API Key.
	 * @param  int|null $site_id Site ID (for multi-site). Optional.
	 */
	public function fetch_transactions( string $start, string $end, string $api_key, ?int $site_id = null ): void;

		/**
		 * Items.
		 *
		 * @since 1.0.0
		 *
		 * @param  string   $start Start Date.
		 * @param  string   $end End Date.
		 * @param  string   $api_key API Key.
		 * @param  int|null $site_id Site ID (for multi-site). Optional.
		 */
	public function fetch_items( string $start, string $end, string $api_key, ?int $site_id = null ): void;

		/**
		 * Payments.
		 *
		 * @since 1.0.0
		 *
		 * @param  string   $start Start Date.
		 * @param  string   $end End Date.
		 * @param  string   $api_key API Key.
		 * @param  int|null $site_id Site ID (for multi-site). Optional.
		 */
	public function fetch_payments( string $start, string $end, string $api_key, ?int $site_id = null ): void;
}
