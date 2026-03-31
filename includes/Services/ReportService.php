<?php

namespace WRM\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReportService {

	public static function meta() {
		global $wpdb;

		$sites = $wpdb->get_results(
			"
			SELECT s.site_id, s.site_name, e.name as company
			FROM {$wpdb->prefix}wrm_sites s
			LEFT JOIN {$wpdb->prefix}wrm_entities e ON e.id = s.entity_id
		",
			ARRAY_A
		);

		$out = array();

		foreach ( $sites as $s ) {
			$out[ $s['company'] ][] = array(
				'id'   => (int) $s['site_id'],
				'name' => $s['site_name'],
			);
		}

		return array( 'companies' => $out );
	}

	public static function sales( $request ) {
		global $wpdb;

		$t = $wpdb->prefix . 'wrm_transactions';

		$from = $request['from'] . ' 00:00:00';
		$to   = $request['to'] . ' 23:59:59';

		$last_from = date( 'Y-m-d H:i:s', strtotime( $from . ' -7 days' ) );
		$last_to   = date( 'Y-m-d H:i:s', strtotime( $to . ' -7 days' ) );

		$entity = $request['entity'] ?? 'all';
		$site   = $request['site'] ?? 'all';

		// =========================
		// LOAD ENTITIES & SITES
		// =========================
		$entities  = wpac()->entities()->get_all( true );
		$all_sites = wpac()->sites()->get_all( true );

		$entity_map = array();
		foreach ( $entities as $e ) {
			$entity_map[ $e['id'] ] = $e['name'];
		}

		$allowed_sites = array();
		$site_name_map = array();

		foreach ( $all_sites as $s ) {
			if ( $entity !== 'all' && $s['entity_id'] != $entity ) {
				continue;
			}
			if ( $site !== 'all' && $s['id'] != $site ) {
				continue;
			}

			$allowed_sites[] = $s['site_id'];

			$entity_name                    = $entity_map[ $s['entity_id'] ] ?? '';
			$ent_short                      = explode( ' ', trim( $entity_name ) )[0] ?? '';
			$site_name_map[ $s['site_id'] ] = trim( $ent_short . ' ' . ( $s['name'] ?? $s['site_title'] ?? '' ) );
		}

		if ( empty( $allowed_sites ) ) {
			return array(
				'sites' => array(),
				'days'  => array(),
			);
		}

		$ids         = implode( ',', array_map( 'intval', $allowed_sites ) );
		$where_sites = " AND site_id IN ($ids) AND complete = 1 AND canceled != 1";

		// =========================
		// FETCH SITE DATA
		// =========================
		$fetch_site_data = function ( $start, $end ) use ( $wpdb, $t, $where_sites ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"
			SELECT
				site_id,
				site_title,
				SUM(CAST(total AS DECIMAL(10,2))) as gross,
				SUM(CAST(subtotal - discounts AS DECIMAL(10,2))) as net,
				SUM(CAST(tax AS DECIMAL(10,2))) as vat,
				SUM(CAST(service_charge AS DECIMAL(10,2))) as gratuity,
				SUM(CAST(discretionary AS DECIMAL(10,2))) as discretionary
			FROM $t
			WHERE complete_datetime BETWEEN %s AND %s
			$where_sites
			GROUP BY site_id, site_title
			",
					$start,
					$end
				),
				ARRAY_A
			);
		};

		$this_sites = $fetch_site_data( $from, $to );
		$last_sites = $fetch_site_data( $last_from, $last_to );

		// =========================
		// BUILD SITE MAP
		// =========================
		$sites = array();

		$populate_sites = function ( $rows, $period = 'this' ) use ( &$sites, $site_name_map ) {
			foreach ( $rows as $s ) {
				$id = $s['site_id'];
				if ( ! isset( $sites[ $id ] ) ) {
					$sites[ $id ] = array(
						'site' => $site_name_map[ $id ] ?? ( $s['site_title'] ?: 'Site ' . $id ),
						'this' => array(
							'net'           => 0,
							'gross'         => 0,
							'vat'           => 0,
							'gratuity'      => 0,
							'discretionary' => 0,
						),
						'last' => array(
							'net'           => 0,
							'gross'         => 0,
							'vat'           => 0,
							'gratuity'      => 0,
							'discretionary' => 0,
						),
					);
				}
				$sites[ $id ][ $period ] = array(
					'net'           => (float) $s['net'],
					'gross'         => (float) $s['gross'],
					'vat'           => (float) $s['vat'],
					'gratuity'      => (float) $s['gratuity'],
					'discretionary' => (float) $s['discretionary'],
				);
			}
		};

		$populate_sites( $this_sites, 'this' );
		$populate_sites( $last_sites, 'last' );

		// =========================
		// DAY DATA
		// =========================
		$dayNames = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );

		$fetch_day_data = function ( $start, $end ) use ( $wpdb, $t, $where_sites ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"
			SELECT
				DAYOFWEEK(complete_datetime)-1 as d,
				SUM(CAST(total AS DECIMAL(10,2))) as gross,
				SUM(CAST(subtotal - discounts AS DECIMAL(10,2))) as net
			FROM $t
			WHERE complete_datetime BETWEEN %s AND %s
			$where_sites
			GROUP BY d
			",
					$start,
					$end
				),
				ARRAY_A
			);
		};

		$this_days = $fetch_day_data( $from, $to );
		$last_days = $fetch_day_data( $last_from, $last_to );

		$days = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$days[ $i ] = array(
				'day'  => $dayNames[ $i ],
				'this' => array(
					'net'   => 0,
					'gross' => 0,
				),
				'last' => array(
					'net'   => 0,
					'gross' => 0,
				),
			);
		}

		foreach ( $this_days as $d ) {
			$i                  = (int) $d['d'];
			$days[ $i ]['this'] = array(
				'net'   => (float) $d['net'],
				'gross' => (float) $d['gross'],
			);
		}

		foreach ( $last_days as $d ) {
			$i                  = (int) $d['d'];
			$days[ $i ]['last'] = array(
				'net'   => (float) $d['net'],
				'gross' => (float) $d['gross'],
			);
		}

		// =========================
		// RETURN FINAL RESPONSE
		// =========================
		return array(
			'sites' => array_values( $sites ),
			'days'  => array_values( $days ),
		);
	}

	// public static function sales( $request ) {
	// global $wpdb;

	// $t = $wpdb->prefix . 'wrm_transactions';

	// $from = $request['from'] . ' 00:00:00';
	// $to   = $request['to'] . ' 23:59:59';

	// $last_from = date( 'Y-m-d H:i:s', strtotime( $from . ' -7 days' ) );
	// $last_to   = date( 'Y-m-d H:i:s', strtotime( $to . ' -7 days' ) );

	// $entity = $request['entity'] ?? 'all';
	// $site   = $request['site'] ?? 'all';

	// =========================
	// LOAD ENTITIES & SITES
	// =========================
	// $entities  = wpac()->entities()->get_all( true );
	// $all_sites = wpac()->sites()->get_all( true );

	// Build entity lookup
	// $entity_map = array();
	// foreach ( $entities as $e ) {
	// $entity_map[ $e['id'] ] = $e['name'];
	// }

	// $allowed_sites = array();
	// $site_name_map = array();

	// foreach ( $all_sites as $s ) {
	// Apply entity filter
	// if ( $entity !== 'all' && $s['entity_id'] != $entity ) {
	// continue;
	// }
	// Apply site filter
	// if ( $site !== 'all' && $s['id'] != $site ) {
	// continue;
	// }

	// $allowed_sites[] = $s['site_id'];

	// Build "EntityShort SiteName"
	// $entity_name                    = $entity_map[ $s['entity_id'] ] ?? '';
	// $ent_short                      = explode( ' ', trim( $entity_name ) )[0] ?? '';
	// $site_name_map[ $s['site_id'] ] = trim( $ent_short . ' ' . ( $s['name'] ?? $s['site_title'] ?? '' ) );
	// }

	// if ( empty( $allowed_sites ) ) {
	// return array(
	// 'sites' => array(),
	// 'days'  => array(),
	// );
	// }

	// $ids         = implode( ',', array_map( 'intval', $allowed_sites ) );
	// $where_sites = " AND site_id IN ($ids) AND complete = 1 AND canceled != 1";

	// =========================
	// FETCH SITE DATA
	// =========================
	// $fetch_site_data = function ( $start, $end ) use ( $wpdb, $t, $where_sites ) {
	// return $wpdb->get_results(
	// $wpdb->prepare(
	// "
	// SELECT
	// site_id,
	// site_title,
	// SUM(CAST(total AS DECIMAL(10,2))) as gross,
	// SUM(CAST(subtotal - discounts - tax + discounts AS DECIMAL(10,2))) as net,
	// SUM(CAST(tax AS DECIMAL(10,2))) as vat,
	// SUM(CAST(service_charge AS DECIMAL(10,2))) as gratuity
	// FROM $t
	// WHERE complete_datetime BETWEEN %s AND %s
	// $where_sites
	// GROUP BY site_id, site_title
	// ",
	// $start,
	// $end
	// ),
	// ARRAY_A
	// );
	// };

	// $this_sites = $fetch_site_data( $from, $to );
	// $last_sites = $fetch_site_data( $last_from, $last_to );

	// =========================
	// BUILD SITE MAP
	// =========================
	// $sites = array();

	// $populate_sites = function ( $rows, $period = 'this' ) use ( &$sites, $site_name_map ) {
	// foreach ( $rows as $s ) {
	// $id = $s['site_id'];
	// if ( ! isset( $sites[ $id ] ) ) {
	// $sites[ $id ] = array(
	// 'site' => $site_name_map[ $id ] ?? ( $s['site_title'] ?: 'Site ' . $id ),
	// 'this' => array(
	// 'net'      => 0,
	// 'gross'    => 0,
	// 'vat'      => 0,
	// 'gratuity' => 0,
	// ),
	// 'last' => array(
	// 'net'      => 0,
	// 'gross'    => 0,
	// 'vat'      => 0,
	// 'gratuity' => 0,
	// ),
	// );
	// }
	// $sites[ $id ][ $period ] = array(
	// 'net'      => (float) $s['net'],
	// 'gross'    => (float) $s['gross'],
	// 'vat'      => (float) $s['vat'],
	// 'gratuity' => (float) $s['gratuity'],
	// );
	// }
	// };

	// $populate_sites( $this_sites, 'this' );
	// $populate_sites( $last_sites, 'last' );

	// =========================
	// DAY DATA
	// =========================
	// $dayNames = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );

	// $fetch_day_data = function ( $start, $end ) use ( $wpdb, $t, $where_sites ) {
	// return $wpdb->get_results(
	// $wpdb->prepare(
	// "
	// SELECT
	// DAYOFWEEK(complete_datetime)-1 as d,
	// SUM(CAST(total AS DECIMAL(10,2))) as gross,
	// SUM(CAST(subtotal - discounts - tax + discounts AS DECIMAL(10,2))) as net
	// FROM $t
	// WHERE complete_datetime BETWEEN %s AND %s
	// $where_sites
	// GROUP BY d
	// ",
	// $start,
	// $end
	// ),
	// ARRAY_A
	// );
	// };

	// $this_days = $fetch_day_data( $from, $to );
	// $last_days = $fetch_day_data( $last_from, $last_to );

	// $days = array();
	// for ( $i = 0; $i < 7; $i++ ) {
	// $days[ $i ] = array(
	// 'day'  => $dayNames[ $i ],
	// 'this' => array(
	// 'net'   => 0,
	// 'gross' => 0,
	// ),
	// 'last' => array(
	// 'net'   => 0,
	// 'gross' => 0,
	// ),
	// );
	// }

	// foreach ( $this_days as $d ) {
	// $i                  = (int) $d['d'];
	// $days[ $i ]['this'] = array(
	// 'net'   => (float) $d['net'],
	// 'gross' => (float) $d['gross'],
	// );
	// }

	// foreach ( $last_days as $d ) {
	// $i                  = (int) $d['d'];
	// $days[ $i ]['last'] = array(
	// 'net'   => (float) $d['net'],
	// 'gross' => (float) $d['gross'],
	// );
	// }

	// =========================
	// RETURN FINAL RESPONSE
	// =========================
	// return array(
	// 'sites' => array_values( $sites ),
	// 'days'  => array_values( $days ),
	// );
	// }

	// public static function sales( $request ) {
	// global $wpdb;

	// $t = $wpdb->prefix . 'wrm_transactions';

	// $from = $request['from'] . ' 00:00:00';
	// $to   = $request['to'] . ' 23:59:59';

	// $last_from = date( 'Y-m-d H:i:s', strtotime( $from . ' -7 days' ) );
	// $last_to   = date( 'Y-m-d H:i:s', strtotime( $to . ' -7 days' ) );

	// =========================
	// SITE - THIS WEEK
	// =========================
	// $this_sites = $wpdb->get_results(
	// $wpdb->prepare(
	// "
	// SELECT
	// site_id,
	// site_title,
	// SUM(CAST(total AS DECIMAL(10,2))) as gross,
	// SUM(CAST(total AS DECIMAL(10,2))) as net,
	// SUM(CAST(tax AS DECIMAL(10,2))) as vat,
	// SUM(CAST(service_charge AS DECIMAL(10,2))) as gratuity
	// FROM $t
	// WHERE complete_datetime BETWEEN %s AND %s
	// GROUP BY site_id, site_title
	// ",
	// $from,
	// $to
	// ),
	// ARRAY_A
	// );

	// =========================
	// SITE - LAST WEEK
	// =========================
	// $last_sites = $wpdb->get_results(
	// $wpdb->prepare(
	// "
	// SELECT
	// site_id,
	// site_title,
	// SUM(CAST(total AS DECIMAL(10,2))) as gross,
	// SUM(CAST(total AS DECIMAL(10,2))) as net,
	// SUM(CAST(tax AS DECIMAL(10,2))) as vat,
	// SUM(CAST(service_charge AS DECIMAL(10,2))) as gratuity
	// FROM $t
	// WHERE complete_datetime BETWEEN %s AND %s
	// GROUP BY site_id, site_title
	// ",
	// $last_from,
	// $last_to
	// ),
	// ARRAY_A
	// );

	// =========================
	// BUILD SITE MAP
	// =========================
	// $sites = array();

	// foreach ( $this_sites as $s ) {
	// $id = $s['site_id'];

	// $sites[ $id ] = array(
	// 'site' => $s['site_title'] ?: 'Site ' . $id,
	// 'this' => array(
	// 'net'      => (float) $s['net'],
	// 'gross'    => (float) $s['gross'],
	// 'vat'      => (float) $s['vat'],
	// 'gratuity' => (float) $s['gratuity'],
	// ),
	// 'last' => array(
	// 'net'      => 0,
	// 'gross'    => 0,
	// 'vat'      => 0,
	// 'gratuity' => 0,
	// ),
	// );
	// }

	// foreach ( $last_sites as $s ) {
	// $id = $s['site_id'];

	// if ( ! isset( $sites[ $id ] ) ) {
	// $sites[ $id ] = array(
	// 'site' => $s['site_title'] ?: 'Site ' . $id,
	// 'this' => array(
	// 'net'      => 0,
	// 'gross'    => 0,
	// 'vat'      => 0,
	// 'gratuity' => 0,
	// ),
	// 'last' => array(
	// 'net'      => 0,
	// 'gross'    => 0,
	// 'vat'      => 0,
	// 'gratuity' => 0,
	// ),
	// );
	// }

	// $sites[ $id ]['last'] = array(
	// 'net'      => (float) $s['net'],
	// 'gross'    => (float) $s['gross'],
	// 'vat'      => (float) $s['vat'],
	// 'gratuity' => (float) $s['gratuity'],
	// );
	// }

	// =========================
	// DAY DATA
	// =========================
	// $dayNames = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );

	// $this_days = $wpdb->get_results(
	// $wpdb->prepare(
	// "
	// SELECT
	// DAYOFWEEK(complete_datetime)-1 as d,
	// SUM(CAST(total AS DECIMAL(10,2))) as gross,
	// SUM(CAST(total AS DECIMAL(10,2))) as net
	// FROM $t
	// WHERE complete_datetime BETWEEN %s AND %s
	// GROUP BY d
	// ",
	// $from,
	// $to
	// ),
	// ARRAY_A
	// );

	// $last_days = $wpdb->get_results(
	// $wpdb->prepare(
	// "
	// SELECT
	// DAYOFWEEK(complete_datetime)-1 as d,
	// SUM(CAST(total AS DECIMAL(10,2))) as gross,
	// SUM(CAST(total AS DECIMAL(10,2))) as net
	// FROM $t
	// WHERE complete_datetime BETWEEN %s AND %s
	// GROUP BY d
	// ",
	// $last_from,
	// $last_to
	// ),
	// ARRAY_A
	// );

	// $days = array();

	// for ( $i = 0; $i < 7; $i++ ) {
	// $days[ $i ] = array(
	// 'day'  => $dayNames[ $i ],
	// 'this' => array(
	// 'net'   => 0,
	// 'gross' => 0,
	// ),
	// 'last' => array(
	// 'net'   => 0,
	// 'gross' => 0,
	// ),
	// );
	// }

	// foreach ( $this_days as $d ) {
	// $i                  = (int) $d['d'];
	// $days[ $i ]['this'] = array(
	// 'net'   => (float) $d['net'],
	// 'gross' => (float) $d['gross'],
	// );
	// }

	// foreach ( $last_days as $d ) {
	// $i                  = (int) $d['d'];
	// $days[ $i ]['last'] = array(
	// 'net'   => (float) $d['net'],
	// 'gross' => (float) $d['gross'],
	// );
	// }

	// return array(
	// 'sites' => array_values( $sites ),
	// 'days'  => array_values( $days ),
	// );
	// }
}
