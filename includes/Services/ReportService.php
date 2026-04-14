<?php

namespace WRM\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Report Service
 *
 * @since 1.0.0
 */
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


	/**
	 * Dashboard.
	 */
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


	/**
	 * Daily Sales.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $from From.
	 * @param  string $to To.
	 * @param  string $entity Entity.
	 * @param  string $site Site.
	 */
	public static function daily_sales( $from, $to, $entity = 'all', $site = 'all' ) {

		global $wpdb;

		$table = $wpdb->prefix . 'wrm_transactions';

		$from = $from . ' 00:00:00';
		$to   = $to . ' 23:59:59';

		$entities    = wpac()->entities()->all();
		$all_sites   = wpac()->sites()->all();
		$permissions = wpac()->permissions();

		// =========================
		// ENTITY MAP
		// =========================
		$entity_map = array();
		foreach ( $entities as $e ) {
			$entity_map[ $e->id ] = $e->name;
		}

		// =========================
		// FILTER SITES
		// =========================
		$allowed_sites = array();
		$site_name_map = array();

		foreach ( $all_sites as $s ) {

			if ( $entity !== 'all' && (int) $s->entity_id !== (int) $entity ) {
				continue;
			}

			if ( $site !== 'all' && (int) $s->site_id !== (int) $site ) {
				continue;
			}

			$context = array(
				'entity_id' => $s->entity_id,
				'site_id'   => $s->site_id,
			);

			if ( ! $permissions->can( 'wrm_view_daily_sales', $context ) ) {
				continue;
			}

			$allowed_sites[] = (int) $s->site_id;

			$entity_name = $entity_map[ $s->entity_id ] ?? '';
			$short       = explode( ' ', trim( $entity_name ) )[0] ?? '';

			$site_name_map[ $s->site_id ] =
			trim( $short . ' ' . ( $s->name ?? $s->site_title ?? '' ) );
		}

		if ( empty( $allowed_sites ) ) {
			return array(
				'sites' => array(),
				'days'  => array(),
			);
		}

		// =========================
		// ORDER SITES BY NAME
		// =========================
		uasort(
			$site_name_map,
			function ( $a, $b ) {
				return strcasecmp( $a, $b );
			}
		);

		$ids = implode( ',', array_map( 'intval', $allowed_sites ) );

		$where = " AND site_id IN ($ids) AND complete = 1 AND canceled != 1";

		// =========================
		// FETCH DATA
		// =========================
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"
			SELECT
				DATE(complete_datetime) as date,
				site_id,
				SUM(total) as gross,
				SUM(subtotal - discounts) as net,
				SUM(tax) as vat,
				SUM(gratuity) as gratuity
			FROM $table
			WHERE complete_datetime BETWEEN %s AND %s
			$where
			GROUP BY DATE(complete_datetime), site_id
			ORDER BY date ASC
			",
				$from,
				$to
			),
			ARRAY_A
		);

		// =========================
		// BUILD PIVOT
		// =========================
		$data = array();

		foreach ( $rows as $r ) {

			$date    = $r['date'];
			$week    = WeekService::get_week_of_day( $date );
			$site_id = (int) $r['site_id'];

			if ( ! isset( $data[ $date ] ) ) {
				$data[ $date ] = array(
					'date'    => $date,
					'day'     => gmdate( 'l', strtotime( $date ) ),
					'week'    => $week['week'] ?? '',
					'sites'   => array(),
					'overall' => array(
						'net'      => 0,
						'vat'      => 0,
						'gross'    => 0,
						'gratuity' => 0,
					),
				);
			}

			$net                                = (float) $r['net'];
			$vat                                = (float) $r['vat'];
			$gross                              = (float) $r['gross'];
			$gratuity                           = (float) $r['gratuity'];
			$data[ $date ]['sites'][ $site_id ] = array(
				'net'      => $net,
				'vat'      => $vat,
				'gross'    => $gross,
				'gratuity' => $gratuity,
			);

			$data[ $date ]['overall']['net']      += $net;
			$data[ $date ]['overall']['vat']      += $vat;
			$data[ $date ]['overall']['gross']    += $gross;
			$data[ $date ]['overall']['gratuity'] += $gratuity;
		}

		// =========================
		// RETURN (CLEAN JSON)
		// =========================
		return array(
			'sites' => array_values(
				array_map(
					function ( $id ) use ( $site_name_map ) {
						return array(
							'id'   => $id,
							'name' => $site_name_map[ $id ] ?? "Site $id",
						);
					},
					array_keys( $site_name_map )
				)
			),

			'days'  => array_values( $data ),
		);
	}
	/**
	 * Daily Sales Report
	 *
	 * @since 1.0.0
	 *
	 * @param string $from From.
	 * @param string $to   To.
	 * @param string $entity Entity.
	 * @param string $site Site.
	 */
	public static function wrm_generate_sales_excel( $from, $to, $entity = 'all', $site = 'all' ) {

		global $wpdb;

		$table = $wpdb->prefix . 'wrm_transactions';

		$from = gmdate( 'Y-m-d', strtotime( str_replace( '/', '-', $from ) ) ) . ' 00:00:00';
		$to   = gmdate( 'Y-m-d', strtotime( str_replace( '/', '-', $to ) ) ) . ' 23:59:59';

		$entities    = wpac()->entities()->all();
		$sites       = wpac()->sites()->all();
		$permissions = wpac()->permissions();

		// =========================
		// SITE FILTER + PERMISSIONS
		// =========================
		$allowed_site_ids = array();
		$site_to_entity   = array();
		$entity_sites     = array();
		$site_names       = array();

		foreach ( $sites as $s ) {

			if ( 'all' !== $entity && (int) $s->entity_id !== (int) $entity ) {
				continue;
			}
			if ( 'all' !== $site && (int) $s->site_id !== (int) $site ) {
				continue;
			}

			$context = array(
				'entity_id' => $s->entity_id,
				'site_id'   => $s->site_id,
			);

			if ( ! $permissions->can( 'wrm_view_sales', $context ) ) {
				continue;
			}

			$site_id = (int) $s->site_id;

			$allowed_site_ids[]              = $site_id;
			$site_to_entity[ $site_id ]      = (int) $s->entity_id;
			$entity_sites[ $s->entity_id ][] = $site_id;
			$site_names[ $site_id ]          = $s->name;
		}

		if ( empty( $allowed_site_ids ) ) {
			exit;
		}

		$allowed_ids = implode( ',', array_map( 'intval', $allowed_site_ids ) );

		// =========================
		// FETCH DATA
		// =========================
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"
			SELECT
				DATE(complete_datetime) as date,
				site_id,
				SUM(total) as total,
				SUM(subtotal - discounts) as net,
				SUM(tax) as vat,
				SUM(gratuity) as gratuity
			FROM $table
			WHERE complete_datetime BETWEEN %s AND %s
			AND complete = 1
			AND canceled != 1
			AND site_id IN ($allowed_ids)
			GROUP BY DATE(complete_datetime), site_id
			ORDER BY date ASC
			",
				$from,
				$to
			),
			ARRAY_A
		);

		// =========================
		// BUILD DATA
		// =========================
		$data = array();

		foreach ( $rows as $r ) {

			$date      = $r['date'];
			$site_id   = (int) $r['site_id'];
			$entity_id = $site_to_entity[ $site_id ] ?? 0;

			if ( ! isset( $data[ $entity_id ][ $date ] ) ) {
				$data[ $entity_id ][ $date ] = array(
					'sites'   => array(),
					'overall' => array(
						'net'      => 0,
						'vat'      => 0,
						'gross'    => 0,
						'gratuity' => 0,
					),
				);
			}

			$net      = (float) $r['net'];
			$vat      = (float) $r['vat'];
			$gross    = (float) $r['total'] - (float) $r['gratuity'];
			$gratuity = (float) $r['gratuity'];

			$data[ $entity_id ][ $date ]['sites'][ $site_id ] = array(
				'net'      => $net,
				'vat'      => $vat,
				'gross'    => $gross,
				'gratuity' => $gratuity,
			);

			$data[ $entity_id ][ $date ]['overall']['net']      += $net;
			$data[ $entity_id ][ $date ]['overall']['vat']      += $vat;
			$data[ $entity_id ][ $date ]['overall']['gross']    += $gross;
			$data[ $entity_id ][ $date ]['overall']['gratuity'] += $gratuity;
		}

		// =========================
		// INIT EXCEL
		// =========================
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet_index = 0;

		foreach ( $entities as $entity_obj ) {

			$entity_id   = $entity_obj->id;
			$entity_name = $entity_obj->name;

			$sites_for_entity = array_values(
				array_intersect(
					$entity_sites[ $entity_id ] ?? array(),
					$allowed_site_ids
				)
			);

			if ( empty( $sites_for_entity ) ) {
				continue;
			}

			$sheet = 0 === $sheet_index
				? $spreadsheet->getActiveSheet()
				: $spreadsheet->createSheet();

			$sheet->setTitle( substr( $entity_name, 0, 31 ) );

			// =========================
			// HEADERS
			// =========================
			$sheet->setCellValue( 'A1', 'Date' );
			$sheet->setCellValue( 'B1', 'Day' );
			$sheet->setCellValue( 'C1', 'WK' );

			$sheet->mergeCells( 'A1:A2' );
			$sheet->mergeCells( 'B1:B2' );
			$sheet->mergeCells( 'C1:C2' );

			$sheet->setCellValue( 'D1', 'Overall' );
			$sheet->mergeCells( 'D1:G1' );

			$sheet->setCellValue( 'D2', 'Net' );
			$sheet->setCellValue( 'E2', 'VAT' );
			$sheet->setCellValue( 'F2', 'Gross' );
			$sheet->setCellValue( 'G2', 'Gratuity' );

			// =========================
			// SITE HEADERS
			// =========================
			$col = 8;

			foreach ( $sites_for_entity as $site_id ) {

				$start = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $col );
				$end   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $col + 3 );

				$sheet->setCellValue( $start . '1', $site_names[ $site_id ] ?? "Site $site_id" );
				$sheet->mergeCells( "{$start}1:{$end}1" );

				$sheet->setCellValue( $start . '2', 'Net' );
				$sheet->setCellValue( \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $col + 1 ) . '2', 'VAT' );
				$sheet->setCellValue( \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $col + 2 ) . '2', 'Gross' );
				$sheet->setCellValue( \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $col + 3 ) . '2', 'Gratuity' );

				$col += 4;
			}

			// =========================
			// DATA
			// =========================
			$row_num = 3;

			foreach ( $data[ $entity_id ] ?? array() as $date => $row_data ) {

				$week = WeekService::get_week_of_day( $date );

				$row = array(
					$date,
					gmdate( 'l', strtotime( $date ) ),
					$week['week'] ?? '',
					$row_data['overall']['net'],
					$row_data['overall']['vat'],
					$row_data['overall']['gross'],
					$row_data['overall']['gratuity'],
				);

				foreach ( $sites_for_entity as $site_id ) {

					$s = $row_data['sites'][ $site_id ] ?? array();

					$row[] = $s['net'] ?? 0;
					$row[] = $s['vat'] ?? 0;
					$row[] = $s['gross'] ?? 0;
					$row[] = $s['gratuity'] ?? 0;
				}

				$sheet->fromArray( $row, null, "A{$row_num}" );
				++$row_num;
			}

			// =========================
			// FORMATTING FIXES
			// =========================

			$last_row = $sheet->getHighestRow();
			$last_col = $sheet->getHighestColumn();

			// borders.
			$sheet->getStyle( "A1:{$last_col}{$last_row}" )
			->getBorders()
			->getAllBorders()
			->setBorderStyle( \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN );

			// number format (commas + decimals).
			$sheet->getStyle( "D3:ZZ{$last_row}" )
			->getNumberFormat()
			->setFormatCode( '#,##0.00' );

			$sheet->freezePane( 'A3' );

			++$sheet_index;
		}

		// =========================
		// OUTPUT
		// =========================
		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );

		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="sales_report_' . gmdate( 'y_m_d', strtotime( $from ) ) . '"' );
		header( 'Cache-Control: max-age=0' );

		$writer->save( 'php://output' );
		exit;
	}

	/**
	 * Flat Daily Sales Report (Date / Entity / Site rows)
	 *
	 * @since 1.0.0
	 *
	 * @param string $from From.
	 * @param string $to   To.
	 * @param string $entity Entity.
	 * @param string $site Site.
	 */
	public static function wrm_generate_sales_excel_flat( $from, $to, $entity = 'all', $site = 'all' ) {

		global $wpdb;

		$table = $wpdb->prefix . 'wrm_transactions';

		$from = gmdate( 'Y-m-d', strtotime( str_replace( '/', '-', $from ) ) ) . ' 00:00:00';
		$to   = gmdate( 'Y-m-d', strtotime( str_replace( '/', '-', $to ) ) ) . ' 23:59:59';

		$entities    = wpac()->entities()->all();
		$sites       = wpac()->sites()->all();
		$permissions = wpac()->permissions();

		// -------------------------
		// FILTER SITES
		// -------------------------
		$allowed_site_ids = array();
		$site_to_entity   = array();
		$entity_sites     = array();
		$site_names       = array();
		$entity_names     = array();

		foreach ( $sites as $s ) {

			if ( 'all' !== $entity && (int) $s->entity_id !== (int) $entity ) {
				continue;
			}

			if ( 'all' !== $site && (int) $s->site_id !== (int) $site ) {
				continue;
			}

			$context = array(
				'entity_id' => $s->entity_id,
				'site_id'   => $s->site_id,
			);

			if ( ! $permissions->can( 'wrm_view_sales', $context ) ) {
				continue;
			}

			$site_id = (int) $s->site_id;

			$allowed_site_ids[] = $site_id;

			$site_to_entity[ $site_id ]      = (int) $s->entity_id;
			$entity_sites[ $s->entity_id ][] = $site_id;

			$site_names[ $site_id ] = $s->name;
		}

		if ( empty( $allowed_site_ids ) ) {
			exit;
		}

		$allowed_ids = implode( ',', array_map( 'intval', $allowed_site_ids ) );

		// -------------------------
		// FETCH SALES
		// -------------------------
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"
			SELECT
				DATE(complete_datetime) as date,
				site_id,
				SUM(total) as total,
				SUM(subtotal - discounts) as net,
				SUM(tax) as vat,
				SUM(gratuity) as gratuity
			FROM $table
			WHERE complete_datetime BETWEEN %s AND %s
			AND complete = 1
			AND canceled != 1
			AND site_id IN ($allowed_ids)
			GROUP BY DATE(complete_datetime), site_id
			",
				$from,
				$to
			),
			ARRAY_A
		);

		// index: [date][site].
		$data = array();

		foreach ( $rows as $r ) {

			$date    = $r['date'];
			$site_id = (int) $r['site_id'];
			$entity  = $site_to_entity[ $site_id ] ?? 0;

			$data[ $date ][ $entity ][ $site_id ] = array(
				'net'      => (float) $r['net'],
				'vat'      => (float) $r['vat'],
				'gross'    => (float) $r['total'] - (float) $r['gratuity'],
				'gratuity' => (float) $r['gratuity'],
			);
		}

		// -------------------------
		// DATE RANGE BUILDER
		// -------------------------
		$start = new \DateTime( gmdate( 'Y-m-d', strtotime( $from ) ) );
		$end   = new \DateTime( gmdate( 'Y-m-d', strtotime( $to ) ) );

		$period = new \DatePeriod(
			$start,
			new \DateInterval( 'P1D' ),
			$end->modify( '+1 day' ) // include end date.
		);

		// -------------------------
		// EXCEL INIT
		// -------------------------
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet       = $spreadsheet->getActiveSheet();

		$sheet->setTitle( 'Daily Sales' );

		// HEADERS.
		$headers = array( 'Date', 'Entity', 'Site', 'Net', 'VAT', 'Gross', 'Gratuity' );
		$sheet->fromArray( $headers, null, 'A1' );

		$row_num = 2;

		// -------------------------
		// BUILD FLAT ROWS
		// -------------------------
		foreach ( $period as $date_obj ) {

			$date = $date_obj->format( 'Y-m-d' );

			foreach ( $entities as $e ) {

				$entity_id = $e->id;

				$sites_for_entity = $entity_sites[ $entity_id ] ?? array();

				foreach ( $sites_for_entity as $site_id ) {

					$entity_name = $e->name;
					$site_name   = $site_names[ $site_id ] ?? "Site $site_id";

					$record = $data[ $date ][ $entity_id ][ $site_id ] ?? null;

					$sheet->fromArray(
						array(
							$date,
							$entity_name,
							$site_name,
							$record['net'] ?? '',
							$record['vat'] ?? '',
							$record['gross'] ?? '',
							$record['gratuity'] ?? '',
						),
						null,
						"A{$row_num}"
					);

					++$row_num;
				}
			}
		}

		// -------------------------
		// FORMATTING
		// -------------------------
		$last_row = $sheet->getHighestRow();
		$last_col = $sheet->getHighestColumn();

		$sheet->getStyle( "A1:{$last_col}{$last_row}" )
		->getBorders()
		->getAllBorders()
		->setBorderStyle( \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN );

		$sheet->getStyle( "D2:G{$last_row}" )
		->getNumberFormat()
		->setFormatCode( '#,##0.00' );

		$sheet->freezePane( 'A2' );

		// -------------------------
		// OUTPUT
		// -------------------------
		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );

		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="sales_report_flat_' . gmdate( 'y_m_d', strtotime( $from ) ) . '.xlsx"' );
		header( 'Cache-Control: max-age=0' );

		$writer->save( 'php://output' );
		exit;
	}
}
