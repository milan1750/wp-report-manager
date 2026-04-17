<?php
/**
 * Weeks.
 *
 * @package WRM
 */

namespace WRM\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Week Service.
 *
 * @since 1.0.0
 */
class WeekService {

	/**
	 * Undocumented function
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
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
			'year'             => $year,
			'week_start_day'   => $week_start_day,
			'current_week'     => $current_week,
			'weeks'            => $weeks,

			// 🔥 NEW: FULL ANALYTICS SYSTEM
			'range_presets'    => self::get_range_presets( $week_start_day ),
			'interval_presets' => self::get_interval_presets(),
		);
	}

	private static function get_range_presets( int $week_start_day ): array {

		$today = new \DateTime( 'now' );

		$format = fn( $d ) => $d->format( 'Y-m-d' );

		// WEEK LOGIC (RESPECTS week_start_day)
		$startOfWeek = self::get_week_start( $today, $week_start_day );
		$endOfWeek   = ( clone $startOfWeek )->modify( '+6 days' );

		$startLastWeek = ( clone $startOfWeek )->modify( '-7 days' );
		$endLastWeek   = ( clone $endOfWeek )->modify( '-7 days' );

		$startMonth = ( clone $today )->modify( 'first day of this month' );
		$endMonth   = clone $today;

		$startLastMonth = ( clone $today )->modify( 'first day of last month' );
		$endLastMonth   = ( clone $today )->modify( 'last day of last month' );

		$startYear     = ( clone $today )->modify( 'first day of january this year' );
		$startLastYear = ( clone $today )->modify( 'first day of january last year' );
		$endLastYear   = ( clone $today )->modify( 'last day of december last year' );

		return array(
			array(
				'key'   => 'today',
				'label' => 'Today',
				'from'  => $format( $today ),
				'to'    => $format( $today ),
			),
			array(
				'key'   => 'yesterday',
				'label' => 'Yesterday',
				'from'  => $format( ( clone $today )->modify( '-1 day' ) ),
				'to'    => $format( ( clone $today )->modify( '-1 day' ) ),
			),
			array(
				'key'   => 'this_week',
				'label' => 'This Week',
				'from'  => $format( $startOfWeek ),
				'to'    => $format( $endOfWeek ),
			),
			array(
				'key'   => 'last_week',
				'label' => 'Last Week',
				'from'  => $format( $startLastWeek ),
				'to'    => $format( $endLastWeek ),
			),
			array(
				'key'   => 'last_7_days',
				'label' => 'Last 7 Days',
				'from'  => $format( ( clone $today )->modify( '-7 days' ) ),
				'to'    => $format( $today ),
			),
			array(
				'key'   => 'last_28_days',
				'label' => 'Last 28 Days',
				'from'  => $format( ( clone $today )->modify( '-28 days' ) ),
				'to'    => $format( $today ),
			),
			array(
				'key'   => 'this_month',
				'label' => 'This Month',
				'from'  => $format( $startMonth ),
				'to'    => $format( $today ),
			),
			array(
				'key'   => 'last_month',
				'label' => 'Last Month',
				'from'  => $format( $startLastMonth ),
				'to'    => $format( $endLastMonth ),
			),
			array(
				'key'   => 'this_year',
				'label' => 'This Year',
				'from'  => $format( $startYear ),
				'to'    => $format( $today ),
			),
			array(
				'key'   => 'last_year',
				'label' => 'Last Year',
				'from'  => $format( $startLastYear ),
				'to'    => $format( $endLastYear ),
			),
			array(
				'key'   => 'all_time',
				'label' => 'All Time',
				'from'  => '2020-01-01',
				'to'    => $format( $today ),
			),
		);
	}

	private static function get_week_start( \DateTime $date, int $week_start_day ): \DateTime {

		$dayOfWeek = (int) $date->format( 'w' ); // 0=Sun

		$diff = $dayOfWeek - $week_start_day;

		if ( $diff < 0 ) {
			$diff += 7;
		}

		$start = clone $date;
		$start->modify( "-{$diff} days" );

		// reset time
		$start->setTime( 0, 0, 0 );

		return $start;
	}

	private static function get_interval_presets(): array {

		$today     = new \DateTime( 'today' );
		$yesterday = new \DateTime( 'yesterday' );

		$same_last_month = ( clone $today )->modify( '-1 month' );
		$same_last_year  = ( clone $today )->modify( '-1 year' );

		return array(
			array(
				'key'   => 'today',
				'label' => 'Today',
				'value' => $today->format( 'Y-m-d' ),
			),
			array(
				'key'   => 'yesterday',
				'label' => 'Yesterday',
				'value' => $yesterday->format( 'Y-m-d' ),
			),
			array(
				'key'   => 'same_last_month',
				'label' => 'Same Date Last Month',
				'value' => $same_last_month->format( 'Y-m-d' ),
			),
			array(
				'key'   => 'same_last_year',
				'label' => 'Same Date Last Year',
				'value' => $same_last_year->format( 'Y-m-d' ),
			),
		);
	}

	/**
	 * Get week of day.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $date Date.
	 *
	 * @return array
	 */
	public static function get_week_of_day( $date ): array {

		$week_start_day = (int) get_option( 'wrm_week_start_day', 1 );

		$target = new \DateTime( $date );
		$year   = (int) $target->format( 'Y' );

		$year_start = new \DateTime( "$year-01-01" );
		$start_dow  = (int) $year_start->format( 'w' );

		// Find anchor (first week start).
		$diff = $start_dow - $week_start_day;
		if ( $diff < 0 ) {
			$diff += 7;
		}

		$anchor = clone $year_start;
		$anchor->modify( "-$diff days" );

		// Find week index.
		$week_index   = 0;
		$current_week = null;

		for ( $i = 0; $i < 52; $i++ ) {

			$week_start = ( clone $anchor )->modify( "+$i week" );
			$week_end   = ( clone $week_start )->modify( '+6 days' );

			if ( $target >= $week_start && $target <= $week_end ) {

				$current_week = array(
					'week'  => 'W' . ( $i + 1 ),
					'start' => $week_start->format( 'Y-m-d' ),
					'end'   => $week_end->format( 'Y-m-d' ),
					'index' => $i + 1,
				);

				break;
			}

			// stop if crossed year.
			if ( $week_start->format( 'Y' ) > $year ) {
				break;
			}
		}

		return $current_week ?? array(
			'week'  => null,
			'start' => null,
			'end'   => null,
			'index' => null,
		);
	}
}
