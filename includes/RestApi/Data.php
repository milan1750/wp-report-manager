<?php
/**
 * Data REST API endpoint.
 *
 * @package WP_Report_Manager
 */

namespace WRM\RestApi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Data.
 *
 * @since 1.0.0
 */
class Data {

	/**
	 * Register REST API routes for Data.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $ns Namespace for the REST API routes.
	 */
	public static function register( $ns ) {

		register_rest_route(
			$ns,
			'/data',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_data' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			)
		);

		// TB import route.
		register_rest_route(
			$ns,
			'/import/touchbistro',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'import_touchbistro' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			)
		);
	}

	/**
	 * Get transaction data with optional site/date filtering.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return array Data rows + pagination.
	 */
	public static function get_data( $request ) {

		global $wpdb;

		$transactions = $wpdb->prefix . 'wrm_transactions';
		$sites        = $wpdb->prefix . 'wrm_sites';

		$params = $request->get_params();

		$entity = isset( $params['entity'] ) ? sanitize_text_field( $params['entity'] ) : '';
		$site   = isset( $params['site'] ) ? sanitize_text_field( $params['site'] ) : '';
		$from   = isset( $params['from'] ) ? sanitize_text_field( $params['from'] ) : '';
		$to     = isset( $params['to'] ) ? sanitize_text_field( $params['to'] ) : '';

		$page     = isset( $params['page'] ) ? max( 1, absint( $params['page'] ) ) : 1;
		$per_page = 20;
		$offset   = ( $page - 1 ) * $per_page;

		$where = array( '1=1' );
		$args  = array();

		// =====================
		// SITE / ENTITY FILTER
		// =====================
		if ( ! empty( $site ) ) {
			// Specific site selected.
			$where[] = 't.site_id = %s';
			$args[]  = $site;
		} elseif ( ! empty( $entity ) ) {
			// No site selected, get all sites for the entity.
			$all_sites    = wpac()->sites()->get_all( true );
			$entity_sites = array_filter(
				$all_sites,
				function ( $s ) use ( $entity ) {
					return $s['entity_id'] == $entity;
				}
			);

			$site_ids = wp_list_pluck( $entity_sites, 'site_id' );

			if ( $site_ids ) {
				$placeholders = implode( ',', array_fill( 0, count( $site_ids ), '%s' ) );
				$where[]      = "t.site_id IN ($placeholders)";
				$args         = array_merge( $args, $site_ids );
			} else {
				// No sites for entity, return empty.
				return array(
					'data'       => array(),
					'pagination' => array(
						'current'     => $page,
						'total_pages' => 0,
						'total_items' => 0,
						'per_page'    => $per_page,
					),
				);
			}
		}

		// =====================
		// DATE FILTER
		// =====================
		if ( ! empty( $from ) ) {
			$where[] = 't.complete_datetime >= %s';
			$args[]  = $from . ' 00:00:00';
		}

		if ( ! empty( $to ) ) {
			$where[] = 't.complete_datetime <= %s';
			$args[]  = $to . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );

		// =====================
		// COUNT QUERY
		// =====================
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$count_sql = "
			SELECT COUNT(*)
			FROM $transactions AS t
			WHERE $where_sql
		";

		$total = $wpdb->get_var(
			$wpdb->prepare( $count_sql, $args )  //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		$total_pages = ceil( $total / $per_page );

		// =====================
		// DATA QUERY
		// =====================
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = "
			SELECT
				t.id,
				t.transaction_id,
				t.site_id,
				s.site_name,
				t.complete_datetime,
				t.total,
				t.subtotal,
				t.tax,
				t.discounts,
				t.customer_name
			FROM $transactions AS t
			LEFT JOIN $sites AS s ON s.site_id = t.site_id
			WHERE $where_sql
			ORDER BY t.complete_datetime DESC
			LIMIT %d OFFSET %d
		";

		$args[] = $per_page;
		$args[] = $offset;

		$results = $wpdb->get_results(
			$wpdb->prepare( $sql, $args ), //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return array(
			'data'       => $results,
			'pagination' => array(
				'current'     => $page,
				'total_pages' => (int) $total_pages,
				'total_items' => (int) $total,
				'per_page'    => $per_page,
			),
		);
	}
}
