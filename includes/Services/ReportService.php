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
			$out[ $s->company ][] = array(
				'id'   => (int) $s->site_id,
				'name' => $s->site_name,
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
		$entities  = wpac()->entities()->all();
		$all_sites = wpac()->sites()->all();

		$entity_map = array();
		foreach ( $entities as $e ) {
			$entity_map[ $e->id ] = $e->name;
		}

		$allowed_sites = array();
		$site_name_map = array();
		$permissions   = wpac()->permissions();
		$user_id       = get_current_user_id();

		foreach ( $all_sites as $s ) {
			if ( $entity !== 'all' && $s->entity_id != $entity ) {
				continue;
			}
			if ( $site !== 'all' && $s->site_id != $site ) {
				continue;
			}

			$context = array(
				'entity_id' => $s->entity_id,
				'site_id'   => $s->id,
			);

			if ( ! $permissions->can( 'wrm_view_sales', $context ) ) {
				continue;
			}

			$allowed_sites[] = $s->site_id;

			$entity_name                  = $entity_map[ $s->entity_id ] ?? '';
			$ent_short                    = explode( ' ', trim( $entity_name ) )[0] ?? '';
			$site_name_map[ $s->site_id ] = trim( $ent_short . ' ' . ( $s->name ?? $s->site_title ?? '' ) );
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
                    CAST(SUM(COALESCE(total,0)) AS DECIMAL(10,2)) as gross,
                    SUM(CAST(subtotal - discounts AS DECIMAL(10,2))) as net,
                    SUM(CAST(tax AS DECIMAL(10,2))) as vat,
                    SUM(CAST(gratuity AS DECIMAL(10,2))) as gratuity
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
							'net'      => 0,
							'gross'    => 0,
							'vat'      => 0,
							'gratuity' => 0,
						),
					);
				}
				$sites[ $id ][ $period ] = array(
					'net'      => (float) $s['net'],
					'gross'    => (float) $s['gross'],
					'vat'      => (float) $s['vat'],
					'gratuity' => (float) $s['gratuity'],
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
                    CAST(SUM(COALESCE(total,0)) AS DECIMAL(10,2)) as gross,
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

		return array(
			'sites' => array_values( $sites ),
			'days'  => array_values( $days ),
		);
	}

	public static function items( $request ) {
		global $wpdb;

		$t = $wpdb->prefix . 'wrm_transaction_items';

		$from = $request['from'] . ' 00:00:00';
		$to   = $request['to'] . ' 23:59:59';

		$last_from = date( 'Y-m-d H:i:s', strtotime( $from . ' -7 days' ) );
		$last_to   = date( 'Y-m-d H:i:s', strtotime( $to . ' -7 days' ) );

		$entity = $request['entity'] ?? 'all';
		$site   = $request['site'] ?? 'all';

		// =========================
		// LOAD ENTITIES & SITES
		// =========================
		$entities  = wpac()->entities()->all();
		$all_sites = wpac()->sites()->all();

		$entity_map = array();
		foreach ( $entities as $e ) {
			$entity_map[ $e->id ] = $e->name;
		}

		$allowed_sites = array();
		$site_name_map = array();
		$permissions   = wpac()->permissions();

		foreach ( $all_sites as $s ) {

			if ( $entity !== 'all' && $s->entity_id != $entity ) {
				continue;
			}

			if ( $site !== 'all' && $s->site_id != $site ) {
				continue;
			}

			$context = array(
				'entity_id' => $s->entity_id,
				'site_id'   => $s->id,
			);

			if ( ! $permissions->can( 'wrm_view_items', $context ) ) {
				continue;
			}

			$allowed_sites[] = (int) $s->site_id;

			$entity_name = $entity_map[ $s->entity_id ] ?? '';
			$ent_short   = explode( ' ', trim( $entity_name ) )[0] ?? '';

			$site_name_map[ $s->site_id ] =
			trim( $ent_short . ' ' . ( $s->name ?? $s->site_title ?? '' ) );
		}

		if ( empty( $allowed_sites ) ) {
			return array(
				'sites'      => array(),
				'categories' => array(),
				'items'      => array(),
				'days'       => array(),
			);
		}

		$ids         = implode( ',', array_map( 'intval', $allowed_sites ) );
		$where_sites = " AND site_id IN ($ids) AND voided != 1";

		// =========================
		// FETCH DATA
		// =========================
		$fetch_data = function ( $start, $end ) use ( $wpdb, $t, $where_sites, $site_name_map ) {

			// ================= SITES =================
			$sites = $wpdb->get_results(
				$wpdb->prepare(
					"
				SELECT site_id,
					   SUM(quantity) AS total_qty,
					   SUM(price*quantity) AS gross,
					   SUM(disc_price*quantity) AS net,
					   SUM(price*quantity - disc_price*quantity) AS discount,
					   SUM(tax) AS tax
				FROM $t
				WHERE added_datetime BETWEEN %s AND %s
				$where_sites
				GROUP BY site_id
				",
					$start,
					$end
				),
				ARRAY_A
			);

			// ADD SITE NAME (IMPORTANT FIX)
			foreach ( $sites as &$s ) {
				$s['site_name'] = $site_name_map[ $s['site_id'] ] ?? 'Unknown';
			}

			// ================= CATEGORIES =================
			$categories = $wpdb->get_results(
				$wpdb->prepare(
					"
				SELECT category_id,
					   category_name,
					   SUM(quantity) AS total_qty,
					   SUM(price*quantity) AS gross,
					   SUM(disc_price*quantity) AS net,
					   SUM(price*quantity - disc_price*quantity) AS discount,
					   SUM(tax) AS tax
				FROM $t
				WHERE added_datetime BETWEEN %s AND %s
				$where_sites
				GROUP BY category_id, category_name
				",
					$start,
					$end
				),
				ARRAY_A
			);

			// ================= ITEMS =================
			$items = $wpdb->get_results(
				$wpdb->prepare(
					"
				SELECT item_title,
					   item_type,
					   SUM(quantity) AS total_qty,
					   SUM(price*quantity) AS gross,
					   SUM(disc_price*quantity) AS net,
					   SUM(price*quantity - disc_price*quantity) AS discount,
					   SUM(tax) AS tax
				FROM $t
				WHERE added_datetime BETWEEN %s AND %s
				$where_sites
				GROUP BY item_title, item_type
				ORDER BY total_qty DESC
				LIMIT 50
				",
					$start,
					$end
				),
				ARRAY_A
			);

			// ================= DAYS =================
			$days = $wpdb->get_results(
				$wpdb->prepare(
					"
				SELECT DAYOFWEEK(added_datetime)-1 AS d,
					   SUM(quantity) AS total_qty,
					   SUM(price*quantity) AS gross,
					   SUM(disc_price*quantity) AS net
				FROM $t
				WHERE added_datetime BETWEEN %s AND %s
				$where_sites
				GROUP BY d
				",
					$start,
					$end
				),
				ARRAY_A
			);

			return array(
				'sites'      => $sites,
				'categories' => $categories,
				'items'      => $items,
				'days'       => $days,
			);
		};

		// ================= PERIODS =================
		$this_period = $fetch_data( $from, $to );
		$last_period = $fetch_data( $last_from, $last_to );

		// ================= DAYS NORMALIZATION =================
		$dayNames = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );

		$days = array();

		for ( $i = 0; $i < 7; $i++ ) {
			$days[ $i ] = array(
				'day'  => $dayNames[ $i ],
				'this' => array(
					'quantity' => 0,
					'gross'    => 0,
					'net'      => 0,
				),
				'last' => array(
					'quantity' => 0,
					'gross'    => 0,
					'net'      => 0,
				),
			);
		}

		foreach ( $this_period['days'] as $d ) {
			$i                  = (int) $d['d'];
			$days[ $i ]['this'] = array(
				'quantity' => (float) $d['total_qty'],
				'gross'    => (float) $d['gross'],
				'net'      => (float) $d['net'],
			);
		}

		foreach ( $last_period['days'] as $d ) {
			$i                  = (int) $d['d'];
			$days[ $i ]['last'] = array(
				'quantity' => (float) $d['total_qty'],
				'gross'    => (float) $d['gross'],
				'net'      => (float) $d['net'],
			);
		}

		return array(
			'sites'      => $this_period['sites'],
			'categories' => $this_period['categories'],
			'items'      => $this_period['items'],
			'days'       => array_values( $days ),
			'last'       => $last_period,
		);
	}



	public static function dashboard( $request ) {
		global $wpdb;

		$t  = $wpdb->prefix . 'wrm_transactions';
		$ti = $wpdb->prefix . 'wrm_transaction_items';

		$from   = $request['from'] . ' 00:00:00';
		$to     = $request['to'] . ' 23:59:59';
		$entity = $request['entity'] ?? 'all';
		$site   = $request['site'] ?? 'all';

		$entities    = wpac()->entities()->all( true );
		$all_sites   = wpac()->sites()->all( true );
		$permissions = wpac()->permissions();

		$allowed_sites = array();
		$site_map      = array();
		$entity_map    = array_column( $entities, 'name', 'id' );

		foreach ( $all_sites as $s ) {
			// Filter by entity/site
			if ( $entity !== 'all' && $s->entity_id != $entity ) {
				continue;
			}
			if ( $site !== 'all' && $s->site_id != $site ) {
				continue;
			}

			// Permission check.
			$context = array(
				'entity_id' => $s->entity_id,
				'site_id'   => $s->id,
			);
			if ( ! $permissions->can( 'wrm_view_dashboard', $context ) ) {
				continue;
			}

			$allowed_sites[]         = $s->site_id;
			$entity_name             = $entity_map[ $s->entity_id ] ?? '';
			$ent_short               = explode( ' ', trim( $entity_name ) )[0] ?? '';
			$site_map[ $s->site_id ] = trim( $ent_short . ' ' . ( $s->name ?? $s->site_title ?? '' ) );
		}

		if ( empty( $allowed_sites ) ) {
			return array(
				'kpi'        => array(),
				'trend'      => array(),
				'hourly'     => array(),
				'staff'      => array(),
				'sites_data' => array(),
				'eat_in'     => array(),
				'insights'   => array(),
				'discounts'  => array(),
				'refunds'    => array(),
				'aov'        => 0,
				'top_items'  => array(),
			);
		}

		$ids   = implode( ',', array_map( 'intval', $allowed_sites ) );
		$where = "WHERE site_id IN ($ids) AND complete_datetime BETWEEN %s AND %s";

		// =========================
		// KPIs
		// =========================
		$kpi = $wpdb->get_row(
			$wpdb->prepare(
				"
            SELECT
                COUNT(*) as orders,
                SUM(total) as gross,
                SUM(subtotal - discounts) as net,
                SUM(tax) as vat,
                SUM(gratuity) as gratuity
            FROM $t
            $where AND complete = 1 AND canceled != 1
            ",
				$from,
				$to
			),
			ARRAY_A
		);

		$aov = $kpi['orders'] > 0 ? round( $kpi['net'] / $kpi['orders'], 2 ) : 0;

		// =========================
		// Discounts KPI
		// =========================
		$discounts = $wpdb->get_row(
			$wpdb->prepare(
				"
            SELECT SUM(discounts) as total_discounts,
                   COUNT(*) as orders_with_discounts
            FROM $t
            $where AND complete = 1 AND canceled != 1 AND discounts > 0
            ",
				$from,
				$to
			),
			ARRAY_A
		);

		// =========================
		// Refunds / Cancellations
		// =========================
		$refunds = $wpdb->get_row(
			$wpdb->prepare(
				"
            SELECT COUNT(*) as canceled_orders,
                   SUM(total) as canceled_total
            FROM $t
            $where AND canceled = 1
            ",
				$from,
				$to
			),
			ARRAY_A
		);

		// =========================
		// Trend: daily totals
		// =========================
		$trend = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT DATE(complete_datetime) as date,
                   SUM(subtotal - discounts) as net,
                   SUM(total) as gross
            FROM $t
            $where AND complete = 1 AND canceled != 1
            GROUP BY DATE(complete_datetime)
            ORDER BY date ASC
            ",
				$from,
				$to
			),
			ARRAY_A
		);

		// =========================
		// Hourly breakdown
		// =========================
		$hourly = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT HOUR(complete_time) as hour,
                   SUM(subtotal - discounts) as net,
                   SUM(total) as gross,
                   SUM(gratuity) as gratuity
            FROM $t
            $where AND complete = 1 AND canceled != 1
            GROUP BY HOUR(complete_time)
            ",
				$from,
				$to
			),
			ARRAY_A
		);

		// =========================
		// Staff performance
		// =========================
		$staff = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT clerk_name,
                   SUM(subtotal - discounts) as net,
                   SUM(total) as gross,
                   SUM(gratuity) as gratuity,
                   COUNT(*) as orders
            FROM $t
            $where AND complete = 1 AND canceled != 1
            GROUP BY clerk_id, clerk_name
            ORDER BY orders DESC
            ",
				$from,
				$to
			),
			ARRAY_A
		);

		// =========================
		// Site breakdown
		// =========================
		$sites_data = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT site_id,
                   SUM(subtotal - discounts) as net,
                   SUM(total) as gross,
                   SUM(gratuity) as gratuity,
                   COUNT(*) as orders
            FROM $t
            $where AND complete = 1 AND canceled != 1
            GROUP BY site_id
            ",
				$from,
				$to
			),
			ARRAY_A
		);

		foreach ( $sites_data as &$s ) {
			$s['name'] = $site_map[ $s['site_id'] ?? 'Site ' . $s['site_id'] ];
		}

		// =========================
		// Eat-in vs Take-away
		// =========================
		$eat_in = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT eat_in,
                   SUM(subtotal - discounts) as net,
                   COUNT(*) as orders
            FROM $t
            $where AND complete = 1 AND canceled != 1
            GROUP BY eat_in
            ",
				$from,
				$to
			),
			ARRAY_A
		);

		// =========================
		// Insights
		// =========================
		$insights = array(
			'highest_gross_day' => $wpdb->get_var(
				$wpdb->prepare(
					"
                SELECT DATE(complete_datetime)
                FROM $t
                $where AND complete = 1 AND canceled != 1
                GROUP BY DATE(complete_datetime)
                ORDER BY SUM(total) DESC LIMIT 1
                ",
					$from,
					$to
				)
			),
		);

		return compact(
			'kpi',
			'trend',
			'hourly',
			'staff',
			'sites_data',
			'eat_in',
			'insights',
			'discounts',
			'refunds',
			'aov',
		);
	}
}
