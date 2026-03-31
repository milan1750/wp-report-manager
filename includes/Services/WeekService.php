<?php

namespace WRM\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WeekService {

	public static function get_weeks(): array {

		$week_start_day = (int) get_option( 'wrm_week_start_day', 1 );
		$today          = new \DateTime( 'now' );

		$build_anchor = function ( int $year ) use ( $week_start_day ) {

			$year_start = new \DateTime( "$year-01-01" );
			$start_dow  = (int) $year_start->format( 'w' );

			$diff = $start_dow - $week_start_day;
			if ( $diff < 0 ) {
				$diff += 7;
			}

			$anchor = clone $year_start;
			$anchor->modify( "-$diff days" );

			return $anchor;
		};

		$year   = (int) $today->format( 'Y' );
		$anchor = $build_anchor( $year );

		$last_week_start = ( clone $anchor )->modify( '+52 weeks' );

		if ( $today >= $last_week_start ) {
			++$year;
			$anchor = $build_anchor( $year );
		}

		$weeks        = array();
		$current_week = null;

		for ( $i = 0; $i < 52; $i++ ) {

			$week_start = ( clone $anchor )->modify( "+$i week" );
			$week_end   = ( clone $week_start )->modify( '+6 days' );

			$next_week_start = ( clone $week_start )->modify( '+7 days' );

			if ( $next_week_start->format( 'Y' ) > $year ) {
				break;
			}

			$is_current = ( $today >= $week_start && $today <= $week_end );

			$week = array(
				'week'       => 'W' . ( $i + 1 ),
				'start'      => $week_start->format( 'Y-m-d' ),
				'end'        => $week_end->format( 'Y-m-d' ),
				'is_current' => $is_current,
			);

			$weeks[] = $week;

			if ( $is_current ) {
				$current_week = $week;
			}
		}

		return array(
			'year'           => $year,
			'week_start_day' => $week_start_day,
			'current_week'   => $current_week,
			'weeks'          => $weeks,
		);
	}
}
