<?php

namespace WRM\RestApi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dashboard {

	public static function index( $request ) {

		global $wpdb;

		$t = $wpdb->prefix . 'wrm_transactions';

		$params = $request->get_params();

		$from = ! empty( $params['from'] )
			? $params['from'] . ' 00:00:00'
			: date( 'Y-m-01 00:00:00' );

		$to = ! empty( $params['to'] )
			? $params['to'] . ' 23:59:59'
			: date( 'Y-m-d 23:59:59' );

		$sql = "
			SELECT
				COALESCE(SUM(total),0) AS revenue,
				COUNT(id) AS orders
			FROM $t
			WHERE complete_datetime BETWEEN %s AND %s
		";

		$row = $wpdb->get_row( $wpdb->prepare( $sql, $from, $to ), ARRAY_A );

		return array(
			'totals' => array(
				'revenue' => (float) $row['revenue'],
				'orders'  => (int) $row['orders'],
			),
		);
	}
}
