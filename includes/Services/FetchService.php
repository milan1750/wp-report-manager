<?php
/**
 * FetchService
 *
 * @package WP_Report_Manager
 */

namespace WRM\Services;

use WRM\Pos\KurveApiProvider;
use WRM\Pos\TBApiProvider;

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

		$table = $wpdb->prefix . 'wrm_fetch_jobs';

		// -----------------------------
		// 1. Get job from DB
		// -----------------------------
		$job = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d", //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$job_id
			)
		);

		if ( ! $job ) {
			return;
		}

		// -----------------------------
		// 2. Cancel check
		// -----------------------------
		if ( 'cancelled' === $job->status ) {
			return;
		}

		// -----------------------------
		// 3. Entity validation
		// -----------------------------
		$entity = wpac()->entities()->get( (int) $job->entity_id );

		if ( ! $entity ) {
			return;
		}

		// -----------------------------
		// 4. API key mapping
		// -----------------------------
		$mapping = array(
			'kineya_uk_limited'         => 'wrm_kineya_api_key',
			'kimchee_company_limited'   => 'wrm_kimchee_api_key',
			'sushinoya_company_limited' => 'wrm_sushinoya_api_key',
		);

		$option_key = $mapping[ $entity->slug ] ?? null;

		if ( ! $option_key ) {
			return;
		}

		$api_key = get_option( $option_key );

		if ( ! $api_key ) {
			return;
		}

		// -----------------------------
		// 5. Determine processing date
		// -----------------------------
		$current = $job->processing_date ? $job->processing_date : $job->from_date;
		$date    = new \DateTime( $current );

		// -----------------------------
		// 6. Call API safely
		// -----------------------------
		try {
			self::fetch_day(
				$date->format( 'Y-m-d' ),
				$api_key
			);
		} catch ( \Exception $e ) {

			// Mark job as failed state (optional improvement).
			$wpdb->update(
				$table,
				array(
					'status'     => 'pending',
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $job_id )
			);

			return;
		}

		// -----------------------------
		// 7. Move to next day
		// -----------------------------
		$date->modify( '+1 day' );
		$next_day = $date->format( 'Y-m-d' );

		// -----------------------------
		// 8. Check completion
		// -----------------------------
		if ( $next_day > $job->to_date ) {

			$wpdb->update(
				$table,
				array(
					'status'     => 'completed',
					'progress'   => 100,
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $job_id )
			);

			return;
		}

		// -----------------------------
		// 9. Progress calculation
		// -----------------------------
		$total = ( new \DateTime( $job->from_date ) )
		->diff( new \DateTime( $job->to_date ) )->days + 1;

		$done = ( new \DateTime( $job->from_date ) )
		->diff( new \DateTime( $next_day ) )->days;

		$progress = (int) ( ( $done / $total ) * 100 );

		// -----------------------------
		// 10. Update job state
		// -----------------------------
		$wpdb->update(
			$table,
			array(
				'status'          => 'running',
				'processing_date' => $next_day,
				'progress'        => $progress,
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'id' => $job_id )
		);

		// -----------------------------
		// 11. Requeue worker
		// -----------------------------
		wp_schedule_single_event(
			time() + 2,
			'wrm_run_fetch_job',
			array( $job_id )
		);
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

		if ( 'kimchee' === $api_key ) {
			$provider = new TBApiProvider();
			$provider->touchbistro_login( get_option( 'wrm_tb_username', '' ), get_option( 'wrm_tb_password' ) );
		} else {
			$provider = new KurveApiProvider();
		}

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
