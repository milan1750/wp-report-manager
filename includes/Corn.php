<?php
/**
 * Cron System (Job Generator)
 *
 * Responsibility:
 * - Runs every 5 minutes via WP-Cron
 * - Fetches configured entities
 * - Creates fetch jobs if none exist (pending/running)
 *
 * NOTE:
 * This cron does NOT process jobs.
 * It only generates jobs for the worker system.
 *
 * @package WPAC
 */

namespace WRM;

/**
 * Corn
 *
 * @since 1.0.0
 */
class Corn {

	/**
	 * Initialize cron system.
	 *
	 * - Registers schedule interval
	 * - Ensures cron event is scheduled
	 * - Registers job generator callback
	 *
	 * @return void
	 */
	public static function init() {

		$settings = get_option( 'wrm_auth_settings', array() );

		/**
		 * If cron is disabled in settings:
		 * - Remove scheduled hook to stop background execution
		 */
		if ( empty( $settings['enable_cron'] ) ) {
			wp_clear_scheduled_hook( 'wrm_auto_fetch_worker' );
			return;
		}

		/**
		 * Register custom cron interval: 5 minutes
		 *
		 * WordPress default intervals are limited,
		 * so we extend it with a custom schedule.
		 */
		add_filter(
			'cron_schedules', //phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
			function ( $schedules ) {

				$schedules['five_minutes'] = array(
					'interval' => 300, // 5 minutes
					'display'  => __( 'Every 5 Minutes', 'wrm' ),
				);

				return $schedules;
			}
		);

		/**
		 * Schedule cron event if not already scheduled
		 *
		 * This ensures only one recurring event exists.
		 */
		add_action(
			'init',
			function () {

				if ( ! wp_next_scheduled( 'wrm_auto_fetch_worker' ) ) {
					wp_schedule_event(
						time(),
						'five_minutes',
						'wrm_auto_fetch_worker'
					);
				}
			}
		);

		/**
		 * JOB GENERATOR (CRON EXECUTION)
		 *
		 * Responsibility:
		 * - Load predefined entity list
		 * - Check if a job already exists for each entity
		 * - Insert new job only if none exists
		 *
		 * This prevents duplicate job creation and ensures
		 * a clean queue per entity.
		 */
		add_action(
			'wrm_auto_fetch_worker',
			function () {

				global $wpdb;

				$table = $wpdb->prefix . 'wrm_fetch_jobs';

				/**
				 * List of entities that should be processed automatically.
				 * These slugs must exist in wpac()->entities().
				 */
				$entities = array(
					'kineya_uk_limited',
					'kimchee_company_limited',
					'sushinoya_company_limited',
				);

				foreach ( $entities as $slug ) {

					// Fetch entity by slug.
					$entity = wpac()->entities()->get_by_slug( $slug );

					if ( ! $entity ) {
						// Skip invalid or missing entity.
						continue;
					}

					/**
					 * Check if a job already exists for this entity
					 * and is still active (pending or running)
					 *
					 * This prevents duplicate processing.
					 */
					$exists = $wpdb->get_var(
						$wpdb->prepare(
							// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
							"SELECT id FROM {$table}
							WHERE entity_id = %d
							AND status IN ('pending','running')
							LIMIT 1",
							$entity->id
						)
					);

					if ( $exists ) {
						continue;
					}

					/**
					 * Create new fetch job
					 *
					 * Default behavior:
					 * - from_date = today
					 * - to_date   = today
					 *
					 * Worker will expand logic if needed.
					 */
					$wpdb->insert(
						$table,
						array(
							'entity_id'  => $entity->id,
							'from_date'  => gmdate( 'Y-m-d' ),
							'to_date'    => gmdate( 'Y-m-d' ),
							'status'     => 'pending',
							'progress'   => 0,
							'created_at' => current_time( 'mysql' ),
						)
					);
				}
			}
		);
	}
}
