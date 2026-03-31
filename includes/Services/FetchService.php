<?php
/**
 * FetchService
 *
 * @package WP_Report_Manager
 */

namespace WRM\Services;

use WRM\Pos\KurveApiProvider;

/**
 * FetchService
 *
 * Handles:
 * - background execution
 * - chunked processing (1 day per run)
 * - progress updates
 * - cancel support
 */
class FetchService {

	/**
	 * Run job.
	 *
	 * @param int $job_id Job ID.
	 */
	public static function run( $job_id ) {

		global $wpdb;

		// Ensure lock.
		if ( (int) get_option( 'wrm_fetch_lock', 0 ) !== (int) $job_id ) {
			return;
		}

		$job = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wrm_fetch_jobs WHERE id=%d",
				$job_id
			)
		);

		if ( ! $job || 'cancelled' === $job->status ) {
			update_option( 'wrm_fetch_lock', 0 );
			return;
		}

		$entity = wpac()->entities()->get_entity( (int) $job->entity_id );

		$mapping = array(
			'kineya_uk_limited'         => 'wrm_kineya_api_key',
			'kimchee_company_limited'   => 'wrm_kimchee_api_key',
			'sushinoya_company_limited' => 'wrm_sushinoya_api_key',
		);

		$api_key = get_option( $mapping[ $entity->slug ] );

		$current = $job->processing_date ? $job->processing_date : $job->from_date;
		$date    = new \DateTime( $current );

		// Cancel check.
		if ( 'cancelled' === $job->status ) {
			update_option( 'wrm_fetch_lock', 0 );
			return;
		}

		// Process ONE day.
		self::fetch_day( $date->format( 'Y-m-d' ), $api_key );

		// Next day.
		$date->modify( '+1 day' );
		$next_day = $date->format( 'Y-m-d' );

		// Complete.
		if ( $next_day > $job->to_date ) {

			$wpdb->update(
				$wpdb->prefix . 'wrm_fetch_jobs',
				array(
					'status'     => 'completed',
					'progress'   => 100,
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $job_id )
			);

			update_option( 'wrm_fetch_lock', 0 );
			return;
		}

		// Progress calc.
		$total = ( new \DateTime( $job->from_date ) )->diff( new \DateTime( $job->to_date ) )->days + 1;
		$done  = ( new \DateTime( $job->from_date ) )->diff( new \DateTime( $next_day ) )->days;

		$progress = (int) ( ( $done / $total ) * 100 );

		$wpdb->update(
			$wpdb->prefix . 'wrm_fetch_jobs',
			array(
				'status'          => 'running',
				'processing_date' => $next_day,
				'progress'        => $progress,
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'id' => $job_id )
		);

		// Requeue.
		wp_schedule_single_event( time() + 2, 'wrm_run_fetch_job', array( $job_id ) );
	}

	/**
	 * Fetch one day's data (transactions, items, payments).
	 *
	 * @param string $day     Format: YYYY-MM-DD.
	 * @param string $api_key API key.
	 */
	private static function fetch_day( string $day, string $api_key ): void {

		$start = $day . ' 00:00:00';
		$end   = $day . ' 23:59:59';

		$provider = new KurveApiProvider();

		// 1. Transactions.
		$provider->fetch_transactions(
			$start,
			$end,
			$api_key
		);

		// 2. Items.
		$provider->fetch_items(
			$start,
			$end,
			$api_key
		);

		// 3. Payments.
		$provider->fetch_payments(
			$start,
			$end,
			$api_key
		);
	}
}
