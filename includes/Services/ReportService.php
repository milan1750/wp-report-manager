<?php

namespace WRM\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Dompdf\Dompdf;
use Dompdf\Options;

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
			if ( 'all' !== $entity && (int) $entity !== $s->entity_id ) {
				continue;
			}
			if ( 'all' !== $site && (int) $site !== (int) $s->site_id ) {
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

	/**
	 * Items.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Request $request Request.
	 */
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

			if ( 'all' !== $entity && (int) $entity !== $s->entity_id ) {
				continue;
			}

			if ( 'all' !== $site && (int) $site !== (int) $s->site_id ) {
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

	public static function items_interval( $request ) {
		global $wpdb;

		$t = $wpdb->prefix . 'wrm_transaction_items';

		$interval = max( 1, intval( $request['interval'] ?? 60 ) );

		$entity = $request['entity'] ?? 'all';
		$site   = $request['site'] ?? 'all';

		$categories    = $request['categories'] ?? '';
		$category_list = array_filter( array_map( 'trim', explode( ',', $categories ) ) );

		// ✅ FIX: use local time, not UTC
		$a = $request['interval_a'] ?? gmdate( 'Y-m-d' );
		$b = $request['interval_b'] ?? gmdate( 'Y-m-d' );

		$is_same = ( $a === $b );

		$from = $a . ' 00:00:00';
		$to   = $a . ' 23:59:59';

		$cmp_from = $b . ' 00:00:00';
		$cmp_to   = $b . ' 23:59:59';

		$seconds = $interval * 60;

		/*
		=========================
		SITES + PERMISSION
		=========================
		*/

		$permissions = wpac()->permissions();
		$all_sites   = wpac()->sites()->all();

		$allowed_sites = array();

		foreach ( $all_sites as $s ) {

			if ( 'all' !== $entity && (int) $entity !== (int) $s->entity_id ) {
				continue;
			}

			if ( 'all' !== $site && (int) $site !== (int) $s->site_id ) {
				continue;
			}

			$context = array(
				'entity_id' => $s->entity_id,
				'site_id'   => $s->site_id,
			);

			if ( ! $permissions->can( 'wrm_view_items', $context ) ) {
				continue;
			}

			$allowed_sites[] = (int) $s->site_id;
		}

		if ( empty( $allowed_sites ) ) {
			return array(
				'interval' => $interval,
				'slots'    => array(),
				'items'    => array(),
			);
		}

		$category_sql = '';

		if ( ! empty( $category_list ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $category_list ), '%s' ) );

				$category_sql = $wpdb->prepare(
					" AND category_name IN ($placeholders) ",
					...$category_list
				);
		}

		$ids         = implode( ',', array_map( 'intval', $allowed_sites ) );
		$where_sites = " AND site_id IN ($ids) AND voided != 1 $category_sql";

		/*
		=========================
		SQL (single source of truth)
		=========================
		*/

		$sql = "
			SELECT
				item_title,
				FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(added_datetime)/%d)*%d) AS bucket,
				SUM(quantity) AS qty
			FROM $t
			WHERE added_datetime BETWEEN %s AND %s
			$where_sites
			GROUP BY item_title, bucket
		";

		$this_rows = $wpdb->get_results(
			$wpdb->prepare( $sql, $seconds, $seconds, $from, $to ),
			ARRAY_A
		);

		$last_rows = array();

		if ( ! $is_same ) {
			$last_rows = $wpdb->get_results(
				$wpdb->prepare( $sql, $seconds, $seconds, $cmp_from, $cmp_to ),
				ARRAY_A
			);
		}

		/*
		=========================
		BUILD DATA
		=========================
		*/

		$items    = array();
		$slot_set = array();

		// ✅ FIX: no double bucketing
		$normalize = function ( $bucket ) {
			$ts = strtotime( $bucket );
			return $ts ? date( 'H:i', $ts ) : '00:00';
		};

		$process = function ( $rows, $key ) use ( &$items, &$slot_set, $normalize ) {

			foreach ( $rows as $r ) {

				$item = $r['item_title'];
				$slot = $normalize( $r['bucket'] );

				$slot_set[ $slot ] = true;

				if ( ! isset( $items[ $item ] ) ) {
					$items[ $item ] = array(
						'item_title' => $item,
						'slots'      => array(),
						'row_total'  => array(
							'this' => 0,
							'last' => 0,
						),
					);
				}

				if ( ! isset( $items[ $item ]['slots'][ $slot ] ) ) {
					$items[ $item ]['slots'][ $slot ] = array(
						'this' => 0,
						'last' => 0,
					);
				}

				$items[ $item ]['slots'][ $slot ][ $key ] += (float) $r['qty'];
				$items[ $item ]['row_total'][ $key ]      += (float) $r['qty'];
			}
		};

		$process( $this_rows, 'this' );
		$process( $last_rows, 'last' );

		ksort( $slot_set );

		/*
		=========================
		NORMALIZE EMPTY + TOTALS
		=========================
		*/

		$column_totals = array();

		foreach ( $items as &$item ) {

			foreach ( $slot_set as $slot => $_ ) {

				if ( ! isset( $item['slots'][ $slot ] ) ) {
					$item['slots'][ $slot ] = array(
						'this' => 0,
						'last' => 0,
					);
				}

				$column_totals[ $slot ]['this'] =
				( $column_totals[ $slot ]['this'] ?? 0 ) +
				$item['slots'][ $slot ]['this'];

				$column_totals[ $slot ]['last'] =
				( $column_totals[ $slot ]['last'] ?? 0 ) +
				$item['slots'][ $slot ]['last'];
			}

			ksort( $item['slots'] );
		}

		/*
		=========================
		SORT
		=========================
		*/

		usort(
			$items,
			fn( $a, $b ) =>
				$b['row_total']['this'] <=> $a['row_total']['this']
		);

		/*
		=========================
		RETURN
		=========================
		*/

		return array(
			'interval'      => $interval,
			'slots'         => array_keys( $slot_set ),
			'items'         => array_values( $items ),
			'column_totals' => $column_totals,
		);
	}

	public static function items_interval_excel( $request ) {
		global $wpdb;

		$t = $wpdb->prefix . 'wrm_transaction_items';

		$interval = max( 1, intval( $request['interval'] ?? 60 ) );

		$entity = $request['entity'] ?? 'all';
		$site   = $request['site'] ?? 'all';

		$categories    = $request['categories'] ?? '';
		$category_list = array_filter( array_map( 'trim', explode( ',', $categories ) ) );

		$a = $request['interval_a'] ?? gmdate( 'Y-m-d' );
		$b = $request['interval_b'] ?? gmdate( 'Y-m-d' );

		$is_same = ( $a === $b );

		$from = $a . ' 00:00:00';
		$to   = $a . ' 23:59:59';

		$cmp_from = $b . ' 00:00:00';
		$cmp_to   = $b . ' 23:59:59';

		$seconds = $interval * 60;

		/*
		=========================
		ENTITY + SITE FILTER
		========================= */

		$entities    = wpac()->entities()->all();
		$all_sites   = wpac()->sites()->all();
		$permissions = wpac()->permissions();

		$entity_map = array();
		foreach ( $entities as $e ) {
			$entity_map[ $e->id ] = $e->name;
		}

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

			if ( ! $permissions->can( 'wrm_view_items', $context ) ) {
				continue;
			}

			$allowed_sites[] = (int) $s->site_id;

			$entity_name = $entity_map[ $s->entity_id ] ?? '';
			$short       = explode( ' ', trim( $entity_name ) )[0] ?? '';

			$site_name_map[ $s->site_id ] =
			trim( $short . ' ' . ( $s->name ?? $s->site_title ?? '' ) );
		}

		if ( empty( $allowed_sites ) ) {
			exit;
		}

		uasort( $site_name_map, fn( $a, $b ) => strcasecmp( $a, $b ) );

		$category_sql = '';

		if ( ! empty( $category_list ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $category_list ), '%s' ) );

				$category_sql = $wpdb->prepare(
					" AND category_name IN ($placeholders) ",
					...$category_list
				);
		}

		$ids   = implode( ',', array_map( 'intval', $allowed_sites ) );
		$where = " AND site_id IN ($ids) AND voided != 1 $category_sql";

		/*
		=========================
		FETCH DATA
		========================= */

		$sql = "
			SELECT item_title,
			FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(added_datetime)/%d)*%d) AS bucket,
			SUM(quantity) AS qty
			FROM $t
			WHERE added_datetime BETWEEN %s AND %s
			$where
			GROUP BY item_title, bucket
		";

		$this_rows = $wpdb->get_results(
			$wpdb->prepare( $sql, $seconds, $seconds, $from, $to ),
			ARRAY_A
		);

		$last_rows = $is_same ? array() : $wpdb->get_results(
			$wpdb->prepare( $sql, $seconds, $seconds, $cmp_from, $cmp_to ),
			ARRAY_A
		);

		/*
		=========================
		BUILD DATA
		========================= */

		$items = array();
		$slots = array();

		$normalize = function ( $bucket ) {
			$ts = strtotime( $bucket );
			return $ts ? date( 'H:i', $ts ) : '00:00';
		};

		$process = function ( $rows, $key ) use ( &$items, &$slots, $normalize ) {

			foreach ( $rows as $r ) {

				$item = $r['item_title'];
				$slot = $normalize( $r['bucket'] );

				$slots[ $slot ] = true;

				if ( ! isset( $items[ $item ] ) ) {
					$items[ $item ] = array(
						'item'       => $item,
						'slots'      => array(),
						'total_this' => 0,
						'total_last' => 0,
					);
				}

				if ( ! isset( $items[ $item ]['slots'][ $slot ] ) ) {
					$items[ $item ]['slots'][ $slot ] = array(
						'this' => 0,
						'last' => 0,
					);
				}

				$items[ $item ]['slots'][ $slot ][ $key ] += (float) $r['qty'];
				$items[ $item ][ 'total_' . $key ]        += (float) $r['qty'];
			}
		};

		$process( $this_rows, 'this' );
		$process( $last_rows, 'last' );

		ksort( $slots );
		usort( $items, fn( $a, $b ) => $b['total_this'] <=> $a['total_this'] );

		/*
		=========================
		EXCEL INIT
		========================= */

		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet       = $spreadsheet->getActiveSheet();
		$sheet->setTitle( 'Items Interval' );

		/*
		=========================
		TITLE
		========================= */

		/*
		=========================
		TITLE
		========================= */

		$title = 'Item Sales Report - ' . date( 'd/m/Y', strtotime( $a ) );

		if ( ! $is_same ) {
			$title .= ' Vs ' . date( 'd/m/Y', strtotime( $b ) );
		}

		/* 👉 ADD SITE NAME IF FILTERED */
		if ( $site !== 'all' ) {

			$site_name = $site_name_map[ (int) $site ] ?? null;

			if ( $site_name ) {
				$title .= ' (' . $site_name . ')';
			}
		}

		$sheet->setCellValue( 'A1', $title );
		$sheet->mergeCells( 'A1:Z1' );

		$sheet->getStyle( 'A1' )->applyFromArray(
			array(
				'font'      => array(
					'bold' => true,
					'size' => 14,
				),
				'alignment' => array( 'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER ),
			)
		);

		/*
		=========================
		HEADER
		========================= */

		$row = 3;
		$sheet->setCellValue( "A{$row}", 'Item' );

		$col = 2;

		foreach ( $slots as $slot => $_ ) {

			$c1 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $col );

			if ( $is_same ) {

				$sheet->setCellValue( $c1 . $row, $slot );
				$sheet->setCellValue( $c1 . ( $row + 1 ), 'T' );
				$sheet->mergeCells( "$c1$row:$c1" . ( $row + 1 ) );

				++$col;

			} else {

				$c2 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $col + 1 );

				$sheet->setCellValue( $c1 . $row, $slot );
				$sheet->mergeCells( "$c1$row:$c2$row" );

				$sheet->setCellValue( $c1 . ( $row + 1 ), 'T' );
				$sheet->setCellValue( $c2 . ( $row + 1 ), 'L' );

				$col += 2;
			}
		}

		/*
		=========================
		TOTAL
		========================= */

		$totalA = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $col );

		if ( $is_same ) {

			$sheet->setCellValue( $totalA . $row, 'Total' );
			$sheet->mergeCells( "$totalA$row:$totalA" . ( $row + 1 ) );

		} else {

			$totalB = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $col + 1 );

			$sheet->setCellValue( $totalA . $row, 'Total' );
			$sheet->mergeCells( "$totalA$row:$totalB$row" );

			$sheet->setCellValue( $totalA . ( $row + 1 ), 'T' );
			$sheet->setCellValue( $totalB . ( $row + 1 ), 'L' );
		}

		/*
		=========================
		DATA
		========================= */

		$dataStart = $row + 2;
		$r         = $dataStart;

		foreach ( $items as $item ) {

			$rowData = array( $item['item'] );

			foreach ( $slots as $slot => $_ ) {

				if ( $is_same ) {
					$rowData[] = $item['slots'][ $slot ]['this'] ?? 0;
				} else {
					$rowData[] = $item['slots'][ $slot ]['this'] ?? 0;
					$rowData[] = $item['slots'][ $slot ]['last'] ?? 0;
				}
			}

			$rowData[] = $item['total_this'];

			if ( ! $is_same ) {
				$rowData[] = $item['total_last'];
			}

			$sheet->fromArray( $rowData, null, "A$r" );
			++$r;
		}

		$lastRow = $r - 1;
		$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $col + ( $is_same ? 0 : 1 ) );

		/*
		=========================
		BORDER (FIXED)
		========================= */

		$tableRange = "A{$row}:{$lastCol}{$lastRow}";

		$sheet->getStyle( $tableRange )->applyFromArray(
			array(
				'borders' => array(
					'allBorders' => array(
						'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
						'color'       => array( 'rgb' => '000000' ),
					),
				),
			)
		);

		/*
		=========================
		ALIGNMENT
		========================= */

		$sheet->getStyle( $tableRange )->getAlignment()
		->setHorizontal( \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER )
		->setVertical( \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER );

		$sheet->getStyle( "A{$dataStart}:A{$lastRow}" )
		->getAlignment()
		->setHorizontal( \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT );

		/*
		=========================
		OUTPUT
		========================= */

		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );

		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="items_interval.xlsx"' );
		header( 'Cache-Control: max-age=0' );

		$writer->save( 'php://output' );
		exit;
	}

	public static function items_interval_pdf( $request ) {

		global $wpdb;

		$t = $wpdb->prefix . 'wrm_transaction_items';

		$interval = max( 1, intval( $request['interval'] ?? 60 ) );

		$entity = $request['entity'] ?? 'all';
		$site   = $request['site'] ?? 'all';

		$categories    = $request['categories'] ?? '';
		$category_list = array_filter( array_map( 'trim', explode( ',', $categories ) ) );

		$a = $request['interval_a'] ?? gmdate( 'Y-m-d' );
		$b = $request['interval_b'] ?? gmdate( 'Y-m-d' );

		$is_same = ( $a === $b );

		$from = $a . ' 00:00:00';
		$to   = $a . ' 23:59:59';

		$cmp_from = $b . ' 00:00:00';
		$cmp_to   = $b . ' 23:59:59';

		$seconds = $interval * 60;

		/*
		=========================
			ENTITY + SITE FILTER
		========================= */

		$entities    = wpac()->entities()->all();
		$all_sites   = wpac()->sites()->all();
		$permissions = wpac()->permissions();

		$entity_map = array();
		foreach ( $entities as $e ) {
			$entity_map[ $e->id ] = $e->name;
		}

		$allowed_sites = array();
		$site_name_map = array();

		foreach ( $all_sites as $s ) {

			if ( $entity !== 'all' && (int) $s->entity_id !== (int) $entity ) {
				continue;
			}
			if ( $site !== 'all' && (int) $s->site_id !== (int) $site ) {
				continue;
			}

			if ( ! $permissions->can(
				'wrm_view_items',
				array(
					'entity_id' => $s->entity_id,
					'site_id'   => $s->site_id,
				)
			) ) {
				continue;
			}

			$allowed_sites[] = (int) $s->site_id;

			$entity_name = $entity_map[ $s->entity_id ] ?? '';
			$short       = explode( ' ', trim( $entity_name ) )[0] ?? '';

			$site_name_map[ $s->site_id ] =
			trim( $short . ' ' . ( $s->name ?? $s->site_title ?? '' ) );
		}

		if ( empty( $allowed_sites ) ) {
			exit;
		}

		$category_sql = '';

		if ( ! empty( $category_list ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $category_list ), '%s' ) );

				$category_sql = $wpdb->prepare(
					" AND category_name IN ($placeholders) ",
					...$category_list
				);
		}

		$ids   = implode( ',', array_map( 'intval', $allowed_sites ) );
		$where = " AND site_id IN ($ids) AND voided != 1 $category_sql";

		/*
		=========================
			FETCH DATA
		========================= */

		$sql = "
			SELECT item_title,
			FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(added_datetime)/%d)*%d) AS bucket,
			SUM(quantity) AS qty
			FROM $t
			WHERE added_datetime BETWEEN %s AND %s
			$where
			GROUP BY item_title, bucket
		";

		$this_rows = $wpdb->get_results(
			$wpdb->prepare( $sql, $seconds, $seconds, $from, $to ),
			ARRAY_A
		);

		$last_rows = $is_same ? array() : $wpdb->get_results(
			$wpdb->prepare( $sql, $seconds, $seconds, $cmp_from, $cmp_to ),
			ARRAY_A
		);

		/*
		=========================
			BUILD DATA
		========================= */

		$items = array();
		$slots = array();

		$normalize = function ( $bucket ) {
			$ts = strtotime( $bucket );
			return $ts ? date( 'H:i', $ts ) : '00:00';
		};

		$process = function ( $rows, $key ) use ( &$items, &$slots, $normalize ) {

			foreach ( $rows as $r ) {

				$item = $r['item_title'];
				$slot = $normalize( $r['bucket'] );

				$slots[ $slot ] = true;

				if ( ! isset( $items[ $item ] ) ) {
					$items[ $item ] = array(
						'item'       => $item,
						'slots'      => array(),
						'total_this' => 0,
						'total_last' => 0,
					);
				}

				if ( ! isset( $items[ $item ]['slots'][ $slot ] ) ) {
					$items[ $item ]['slots'][ $slot ] = array(
						'this' => 0,
						'last' => 0,
					);
				}

				$items[ $item ]['slots'][ $slot ][ $key ] += (float) $r['qty'];
				$items[ $item ][ 'total_' . $key ]        += (float) $r['qty'];
			}
		};

		$process( $this_rows, 'this' );
		$process( $last_rows, 'last' );

		ksort( $slots );
		usort( $items, fn( $a, $b ) => $b['total_this'] <=> $a['total_this'] );

		/*
		=========================
		TITLE
		========================= */

		$title = 'Item Sales Report - ' . date( 'd/m/Y', strtotime( $a ) );

		if ( ! $is_same ) {
			$title .= ' Vs ' . date( 'd/m/Y', strtotime( $b ) );
		}

		if ( $site !== 'all' ) {
			$title .= ' (' . ( $site_name_map[ (int) $site ] ?? '' ) . ')';
		}

		/*
		=========================
		HTML BUILD
		========================= */

		$html = "
			<html>
			<head>
			<style>
				body { font-family: Arial; font-size: 11px; }
				h2 { text-align:center; margin-bottom: 5px; }
				.blank { height: 10px; }

				table {
					width: 100%;
					border-collapse: collapse;
				}

				th, td {
					border: 1px solid #000;
					padding: 4px;
					text-align: center;
				}

				th {
					background: #f2f2f2;
				}

				td.item {
					text-align: left;
				}
			</style>
			</head>
			<body>

			<h2>{$title}</h2>
			<div class='blank'></div>

			<table>
			<tr>
				<th>Item</th>";
		foreach ( $slots as $slot => $_ ) {
			$html .= "<th colspan='" . ( $is_same ? 1 : 2 ) . "'>$slot</th>";
		}

				$html .= "<th colspan='" . ( $is_same ? 1 : 2 ) . "'>Total</th></tr>";

				/* sub header */
				$html .= '<tr><th></th>';

		foreach ( $slots as $_ ) {
			if ( $is_same ) {
				$html .= '<th>T</th>';
			} else {
				$html .= '<th>T</th><th>L</th>';
			}
		}

		if ( $is_same ) {
			$html .= '<th>T</th>';
		} else {
			$html .= '<th>T</th><th>L</th>';
		}
			$html .= '</tr>';

			/* rows */
		foreach ( $items as $item ) {

			$html .= "<tr><td class='item'>{$item['item']}</td>";

			foreach ( $slots as $slot => $_ ) {

				$thisVal = $item['slots'][ $slot ]['this'] ?? 0;
				$lastVal = $item['slots'][ $slot ]['last'] ?? 0;

				if ( $is_same ) {
					$html .= "<td>{$thisVal}</td>";
				} else {
					$html .= "<td>{$thisVal}</td><td>{$lastVal}</td>";
				}
			}

			if ( $is_same ) {
				$html .= "<td>{$item['total_this']}</td>";
			} else {
				$html .= "<td>{$item['total_this']}</td><td>{$item['total_last']}</td>";
			}

			$html .= '</tr>';
		}

			$html .= '</table></body></html>';

			/*
			=========================
			DOMPDF RENDER
			========================= */

			$options = new Options();
			$options->set( 'isRemoteEnabled', true );

			$dompdf = new Dompdf( $options );
			$dompdf->loadHtml( $html );
			$dompdf->setPaper( 'A4', 'landscape' );
			$dompdf->render();

			$dompdf->stream( 'items_interval.pdf', array( 'Attachment' => true ) );
			exit;
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
			if ( 'all' !== $entity && (int) $entity !== $s->entity_id ) {
				continue;
			}
			if ( 'all' !== $site && (int) $site !== (int) $s->site_id ) {
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
					'date'    => gmdate( 'd-m-Y', strtotime( $date ) ),
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
					gmdate( 'd-m-Y', strtotime( $date ) ),
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
		header( 'Content-Disposition: attachment; filename="sales_report_' . gmdate( 'd_m_Y', strtotime( $from ) ) . '_to_' . gmdate( 'd_m_Y', strtotime( $to ) ) . '"' );
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
							gmdate( 'd-m-Y', strtotime( $date ) ),
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
		header( 'Content-Disposition: attachment; filename="sales_report_flat_' . gmdate( 'd_m_Y', strtotime( $from ) ) . '_to_' . gmdate( 'd_m_Y', strtotime( $to ) ) . '.xlsx"' );
		header( 'Cache-Control: max-age=0' );

		$writer->save( 'php://output' );
		exit;
	}


	/**
	 * Sales Report.
	 *
	 * @since 1.0.0
	 *
	 * @param string $from From.
	 * @param string $to   To.
	 * @param string $entity Entity.
	 * @param string $site Site.
	 */
	public static function wrm_generate_site_performance_excel( $from, $to, $entity = 'all', $site = 'all' ) {

		global $wpdb;

		$t = $wpdb->prefix . 'wrm_transactions';

		$from_dt = new \DateTime( gmdate( 'Y-m-d', strtotime( str_replace( '/', '-', $from ) ) ) );
		$to_dt   = new \DateTime( gmdate( 'Y-m-d', strtotime( str_replace( '/', '-', $to ) ) ) );

		$from = $from_dt->format( 'Y-m-d' ) . ' 00:00:00';
		$to   = $to_dt->format( 'Y-m-d' ) . ' 23:59:59';

		$last_from = gmdate( 'Y-m-d H:i:s', strtotime( $from . ' -7 days' ) );
		$last_to   = gmdate( 'Y-m-d H:i:s', strtotime( $to . ' -7 days' ) );

		$entities    = wpac()->entities()->all();
		$sites_all   = wpac()->sites()->all();
		$permissions = wpac()->permissions();

		$entity_map = array();
		foreach ( $entities as $e ) {
			$entity_map[ $e->id ] = $e->name;
		}

		$allowed_sites = array();
		$site_name_map = array();

		foreach ( $sites_all as $s ) {

			if ( 'all' !== $entity && (int) $entity !== $s->entity_id ) {
				continue;
			}
			if ( 'all' !== $site && (int) $site !== (int) $s->site_id ) {
				continue;
			}

			if ( ! $permissions->can(
				'wrm_view_sales',
				array(
					'entity_id' => $s->entity_id,
					'site_id'   => $s->site_id,
				)
			) ) {
				continue;
			}

			$allowed_sites[] = (int) $s->site_id;

			$ent   = $entity_map[ $s->entity_id ] ?? '';
			$short = explode( ' ', trim( $ent ) )[0] ?? '';

			$site_name_map[ $s->site_id ] =
			trim( $short . ' ' . ( $s->name ?? $s->site_title ?? '' ) );
		}

		if ( empty( $allowed_sites ) ) {
			exit;
		}

		$ids   = implode( ',', array_map( 'intval', $allowed_sites ) );
		$where = "AND site_id IN ($ids) AND complete = 1 AND canceled != 1";

		// ================= FETCH =================
		$fetch = function ( $start, $end ) use ( $wpdb, $t, $where ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT site_id,
				 SUM(total) as gross,
				 SUM(subtotal - discounts) as net,
				 SUM(tax) as vat,
				 SUM(gratuity) as gratuity
				 FROM $t
				 WHERE complete_datetime BETWEEN %s AND %s
				 $where
				 GROUP BY site_id",
					$start,
					$end
				),
				ARRAY_A
			);
		};

		$this_sites = $fetch( $from, $to );
		$last_sites = $fetch( $last_from, $last_to );

		$sites = array();

		$map = function ( $rows, $key ) use ( &$sites, $site_name_map ) {
			foreach ( $rows as $r ) {
				$id = (int) $r['site_id'];

				if ( ! isset( $sites[ $id ] ) ) {
					$sites[ $id ] = array(
						'site' => $site_name_map[ $id ] ?? "Site $id",
						'this' => array(),
						'last' => array(),
					);
				}

				$sites[ $id ][ $key ] = array(
					'net'   => (float) $r['net'],
					'gross' => (float) $r['gross'],
					'vat'   => (float) $r['vat'],
					'grat'  => (float) $r['gratuity'],
				);
			}
		};

		$map( $this_sites, 'this' );
		$map( $last_sites, 'last' );

		// ================= EXCEL =================
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet       = $spreadsheet->getActiveSheet();
		$sheet->setTitle( 'Site Performance' );

		$sheet->getParent()->getDefaultStyle()
		->getFont()->setName( 'Calibri' )->setSize( 10 );

		$row = 1;

		// TITLE.
		$sheet->setCellValue( "A{$row}", 'Site Performance' );
		$sheet->getStyle( "A{$row}" )->getFont()->setBold( true )->setSize( 12 );

		// HEADER.
		$row = 2;

		$sheet->setCellValue( "A{$row}", 'Site' );
		$sheet->setCellValue( "B{$row}", 'Net Sales' );
		$sheet->setCellValue( "E{$row}", 'Gross Sales' );
		$sheet->setCellValue( "H{$row}", 'VAT / GRATUITY' );

		$sheet->fromArray(
			array(
				'',
				'Current',
				'Previous',
				'Variance %',
				'Current',
				'Previous',
				'Variance %',
				'VAT',
				'VAT %',
				'Gratuity',
				'Eat in Charge',
			),
			null,
			'A' . ( $row + 1 )
		);

		$sheet->mergeCells( "A{$row}:A" . ( $row + 1 ) );
		$sheet->mergeCells( "B{$row}:D{$row}" );
		$sheet->mergeCells( "E{$row}:G{$row}" );
		$sheet->mergeCells( "H{$row}:K{$row}" );

		$sheet->getStyle( "A{$row}:K" . ( $row + 1 ) )
		->applyFromArray(
			array(
				'font'    => array(
					'bold' => true,
					'size' => 10,
				),
				'borders' => array(
					'allBorders' => array(
						'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
					),
				),
			)
		);

		$row += 2;

		// ================= SITE DATA =================
		$start_site = $row;

		$tot = array(
			'nc'   => 0,
			'np'   => 0,
			'gc'   => 0,
			'gp'   => 0,
			'vat'  => 0,
			'grat' => 0,
		);

		foreach ( $sites as $s ) {

			$nc = (float) ( $s['this']['net'] ?? 0 );
			$np = (float) ( $s['last']['net'] ?? 0 );
			$gc = (float) ( $s['this']['gross'] ?? 0 );
			$gp = (float) ( $s['last']['gross'] ?? 0 );

			$net_var   = $np ? ( ( $nc - $np ) / $np ) * 100 : 0;
			$gross_var = $gp ? ( ( $gc - $gp ) / $gp ) * 100 : 0;

			$sheet->fromArray(
				array(
					$s['site'],
					$nc,
					$np,
					$np ? round( $net_var, 1 ) . ' % ' : '0 % ',
					$gc,
					$gp,
					$gp ? round( $gross_var, 1 ) . ' % ' : '0 % ',
					$s['this']['vat'] ?? 0,
					$nc ? round( ( ( $s['this']['vat'] ?? 0 ) / $nc ) * 100, 1 ) . ' % ' : '0 % ',
					$s['this']['grat'] ?? 0,
					( $s['this']['grat'] ?? 0 ) * ( 3.5 / 9 ),
				),
				null,
				"A{$row}"
			);

			$sheet->getStyle( "D{$row}" )
				->applyFromArray( self::get_variance_style( $net_var ) );

			$sheet->getStyle( "G{$row}" )
				->applyFromArray( self::get_variance_style( $gross_var ) );

			$tot['nc']   += $nc;
			$tot['np']   += $np;
			$tot['gc']   += $gc;
			$tot['gp']   += $gp;
			$tot['vat']  += $s['this']['vat'] ?? 0;
			$tot['grat'] += $s['this']['grat'] ?? 0;

			++$row;
		}

		// SITE TOTAL
		$sheet->fromArray(
			array(
				'TOTAL',
				$tot['nc'],
				$tot['np'],
				$tot['np'] ? round( ( ( $tot['nc'] - $tot['np'] ) / $tot['np'] ) * 100, 1 ) . ' % ' : '0 % ',
				$tot['gc'],
				$tot['gp'],
				$tot['gp'] ? round( ( ( $tot['gc'] - $tot['gp'] ) / $tot['gp'] ) * 100, 1 ) . ' % ' : '0 % ',
				$tot['vat'],
				$tot['nc'] ? round( ( $tot['vat'] / $tot['nc'] ) * 100, 1 ) . ' % ' : '0 % ',
				$tot['grat'],
				$tot['grat'] * ( 3.5 / 9 ),
			),
			null,
			"A{$row}"
		);

		$sheet->getStyle( "A{$row}:G{$row}" )
				->applyFromArray(
					array(
						'font'    => array(
							'bold' => true,
							'size' => 10,
						),
						'borders' => array(
							'allBorders' => array(
								'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
							),
						),
					)
				);

		$site_end = $row;

		$sheet->getStyle( "B{$start_site}:K{$site_end}" )
		->getNumberFormat()
		->setFormatCode( '#,##0.00' );

		$row += 3;

		// ================= DAY PERFORMANCE =================
		$sheet->setCellValue( "A{$row}", 'Day Performance' );
		$sheet->getStyle( "A{$row}" )->getFont()->setBold( true )->setSize( 12 );

		++$row;
		$site_start_day = $row;
		$sheet->setCellValue( "A{$row}", 'Day' );
		$sheet->setCellValue( "B{$row}", 'Net Sales' );
		$sheet->setCellValue( "E{$row}", 'Gross Sales' );

		$sheet->fromArray(
			array(
				'',
				'Current',
				'Previous',
				'variance % ',
				'Current',
				'Previous',
				'Variance % ',
			),
			null,
			'A' . ( $row + 1 )
		);

		$sheet->mergeCells( "A{$row}:A" . ( $row + 1 ) );
		$sheet->mergeCells( "B{$row}:D{$row}" );
		$sheet->mergeCells( "E{$row}:G{$row}" );
		$sheet->getStyle( "A{$row}:G" . ( $row + 1 ) )
				->applyFromArray(
					array(
						'font'    => array(
							'bold' => true,
							'size' => 10,
						),
						'borders' => array(
							'allBorders' => array(
								'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
							),
						),
					)
				);

		$row           += 2;
		$day_data_start = $row;

		$days = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );

		$fetch_day = function ( $start, $end ) use ( $wpdb, $t, $where ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT DAYOFWEEK(complete_datetime)-1 as d,
				 SUM(subtotal - discounts) as net,
				 SUM(total) as gross
				 FROM $t
				 WHERE complete_datetime BETWEEN %s AND %s
				 $where
				 GROUP BY d",
					$start,
					$end
				),
				ARRAY_A
			);
		};

		$this_days = $fetch_day( $from, $to );
		$last_days = $fetch_day( $last_from, $last_to );

		$map_day = array_fill(
			0,
			7,
			array(
				'this' => array(
					'net'   => 0,
					'gross' => 0,
				),
				'last' => array(
					'net'   => 0,
					'gross' => 0,
				),
			)
		);

		foreach ( $this_days as $d ) {
			$map_day[ (int) $d['d'] ]['this'] = $d;
		}
		foreach ( $last_days as $d ) {
			$map_day[ (int) $d['d'] ]['last'] = $d;
		}

		$day_totals = array(
			'nc' => 0,
			'np' => 0,
			'gc' => 0,
			'gp' => 0,
		);

		foreach ( $days as $i => $name ) {

			$nc = (float) ( $map_day[ $i ]['this']['net'] ?? 0 );
			$np = (float) ( $map_day[ $i ]['last']['net'] ?? 0 );
			$gc = (float) ( $map_day[ $i ]['this']['gross'] ?? 0 );
			$gp = (float) ( $map_day[ $i ]['last']['gross'] ?? 0 );

			$net_var   = $np ? ( ( $nc - $np ) / $np ) * 100 : 0;
			$gross_var = $gp ? ( ( $gc - $gp ) / $gp ) * 100 : 0;

			$sheet->fromArray(
				array(
					$name,
					$nc,
					$np,
					$np ? round( $net_var, 1 ) . ' % ' : '0 % ',
					$gc,
					$gp,
					$gp ? round( $gross_var, 1 ) . ' % ' : '0 % ',
				),
				null,
				"A{$row}"
			);

			$sheet->getStyle( "D{$row}" )
				->applyFromArray( self::get_variance_style( $net_var ) );

			$sheet->getStyle( "G{$row}" )
				->applyFromArray( self::get_variance_style( $gross_var ) );

			$day_totals['nc'] += $nc;
			$day_totals['np'] += $np;
			$day_totals['gc'] += $gc;
			$day_totals['gp'] += $gp;

			++$row;
		}

		$day_end = $row;

		$sheet->getStyle( "B{$site_start_day}:G{$day_end}" )
		->getNumberFormat()
		->setFormatCode( '#,##0.00' );

		// DAY TOTAL FIXED.
		$sheet->fromArray(
			array(
				'TOTAL',
				$day_totals['nc'],
				$day_totals['np'],
				$day_totals['np'] ? round( ( ( $day_totals['nc'] - $day_totals['np'] ) / $day_totals['np'] ) * 100, 1 ) . ' % ' : '0 % ',
				$day_totals['gc'],
				$day_totals['gp'],
				$day_totals['gp'] ? round( ( ( $day_totals['gc'] - $day_totals['gp'] ) / $day_totals['gp'] ) * 100, 1 ) . ' % ' : '0 % ',
			),
			null,
			"A{$row}"
		);

				$sheet->getStyle( "A{$row}:G{$row}" )
				->applyFromArray(
					array(
						'font'    => array(
							'bold' => true,
							'size' => 10,
						),
						'borders' => array(
							'allBorders' => array(
								'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
							),
						),
					)
				);

		// ================= BORDERS =================
		$sheet->getStyle( "A2:K{$site_end}" )
		->getBorders()->getAllBorders()
		->setBorderStyle( \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN );

		$sheet->getStyle( "A{$site_start_day}:G{$day_end}" )
		->getBorders()->getAllBorders()
		->setBorderStyle( \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN );

		// ================= OUTPUT =================
		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );

		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="site_performance.xlsx"' );
		header( 'Cache-Control: max-age=0' );

		$writer->save( 'php://output' );
		exit;
	}

	/**
	 * Variance Style.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $v Variance.
	 */
	public static function get_variance_style( $v ) {

		if ( $v <= -5 ) {
			return array(
				'fill' => array(
					'fillType'   => 'solid',
					'startColor' => array( 'rgb' => 'F8D7DA' ), // light red (soft danger).
				),
			);
		}

		if ( $v <= -2 ) {
			return array(
				'fill' => array(
					'fillType'   => 'solid',
					'startColor' => array( 'rgb' => 'F5B7B1' ), // soft red.
				),
			);
		}

		if ( $v < 0 ) {
			return array(
				'fill' => array(
					'fillType'   => 'solid',
					'startColor' => array( 'rgb' => 'FDEBD0' ), // light orange.
				),
			);
		}

		if ( 0 === $v ) {
			return array(
				'fill' => array(
					'fillType'   => 'solid',
					'startColor' => array( 'rgb' => 'F2F3F4' ), // neutral light gray.
				),
			);
		}

		if ( $v <= 2 ) {
			return array(
				'fill' => array(
					'fillType'   => 'solid',
					'startColor' => array( 'rgb' => 'D5F5E3' ), // light green.
				),
			);
		}

		if ( $v <= 5 ) {
			return array(
				'fill' => array(
					'fillType'   => 'solid',
					'startColor' => array( 'rgb' => 'ABEBC6' ), // medium soft green.
				),
			);
		}

		return array(
			'fill' => array(
				'fillType'   => 'solid',
				'startColor' => array( 'rgb' => '82E0AA' ), // strong but still soft green.
			),
		);
	}
}
