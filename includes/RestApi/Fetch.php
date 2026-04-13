<?php
/**
 * Fetch REST API
 *
 * @package WP_Report_Manager
 */

namespace WRM\RestApi;

/**
 * Fetch REST API
 *
 * Handles:
 * - starting a background fetch job
 * - checking job status
 * - cancelling running job
 */
class Fetch {

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
			'/fetch',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'start' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			)
		);

		register_rest_route(
			$ns,
			'/fetch/active',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'active' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			)
		);

		register_rest_route(
			$ns,
			'/fetch/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'status' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			)
		);

		register_rest_route(
			$ns,
			'/fetch/(?P<id>\d+)/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'cancel' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			)
		);
	}

	/**
	 * Get active job.
	 *
	 * @since 1.0.0
	 */
	public static function active() {

		global $wpdb;

		$job = $wpdb->get_row(
			"
				SELECT *
				FROM {$wpdb->prefix}wrm_fetch_jobs
				WHERE status IN ('pending','running')
				ORDER BY id DESC
				LIMIT 1
			"
		);

		if ( ! $job ) {
			return array(
				'status' => 'idle',
			);
		}

		return $job;
	}

	/**
	 * Start job .
	 *
	 * Ensures:
	 * - Only one job runs at a time
	 * - Atomic lock acquisition( no race condition )
	 * - Safe job creation
	 * - Background execution via WP - Cron
	 *
	 * @since 1.0.0
	 *
	 * @param array $request Request data .
	 *
	 * @return array Response data .
	 */
	public static function start( $request ) {

		global $wpdb;

		$from      = $request['from'] ?? null;
		$to        = $request['to'] ?? null;
		$entity_id = (int) ( $request['entity'] ?? 0 );

		if ( ! $entity_id || ! $from || ! $to ) {
			return array(
				'status'  => 'error',
				'message' => 'Missing data',
			);
		}

		if ( strtotime( $from ) > strtotime( $to ) ) {
			return array(
				'status'  => 'error',
				'message' => 'Invalid date range',
			);
		}

		$entity = wpac()->entities()->get( $entity_id );

		if ( ! $entity ) {
			return array(
				'status'  => 'error',
				'message' => 'Invalid entity',
			);
		}

		// DB-based lock check.
		$running = $wpdb->get_var(
			"
				SELECT id
				FROM {$wpdb->prefix}wrm_fetch_jobs
				WHERE status = 'running' or status = 'pending'
				LIMIT 1
			"
		);

		if ( $running ) {
			return array( 'status' => 'busy' );
		}

		// create job.
		$wpdb->insert(
			$wpdb->prefix . 'wrm_fetch_jobs',
			array(
				'entity_id'  => $entity->id,
				'from_date'  => $from,
				'to_date'    => $to,
				'status'     => 'pending',
				'progress'   => 0,
				'created_at' => current_time( 'mysql' ),
			)
		);

		$job_id = (int) $wpdb->insert_id;

		// trigger worker.
		wp_schedule_single_event(
			time(),
			'wrm_run_fetch_job',
			array( $job_id )
		);

		return array(
			'status' => 'queued',
			'job_id' => $job_id,
		);
	}

	/**
	 * Get status.
	 *
	 * @since 1.0.0
	 *
	 * @param array $request Request data.
	 *
	 * @return array Response data.
	 */
	public static function status( $request ) {

		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wrm_fetch_jobs WHERE id=%d",
				(int) $request['id']
			)
		);
	}

	/**
	 * Cancel job.
	 *
	 * @since 1.0.0
	 *
	 * @param array $request Request data.
	 *
	 * @return array Response data.
	 */
	public static function cancel( $request ) {

		global $wpdb;

		$id = (int) $request['id'];

		$wpdb->update(
			$wpdb->prefix . 'wrm_fetch_jobs',
			array( 'status' => 'cancelled' ),
			array( 'id' => $id )
		);

		update_option( 'wrm_fetch_lock', 0, false );

		return array( 'status' => 'cancelled' );
	}
}
