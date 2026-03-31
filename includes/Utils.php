<?php

namespace WRM\Utils;

/**
 * Week calculation engine for WRM plugin.
 * Supports custom week start + fiscal week system.
 */
class Week_Calculator {

	/**
	 * Default week start (0 = Sunday).
	 * Later you can load per-company setting.
	 */
	public static function get_week_start_day(): int {
		return 0;
	}

	/**
	 * Get week start date for a given date.
	 */
	public static function get_week_start_date( string $date, int $week_start_day = 0 ): \DateTime {

		$dt = new \DateTime( $date );

		$current_dow = (int) $dt->format( 'w' );

		$diff = $current_dow - $week_start_day;

		if ( $diff < 0 ) {
			$diff += 7;
		}

		$dt->modify( "-{$diff} days" );

		return $dt;
	}

	/**
	 * Get week range (start + end).
	 */
	public static function get_week_range( string $date, int $week_start_day = 0 ): array {

		$start = self::get_week_start_date( $date, $week_start_day );

		$end = clone $start;
		$end->modify( '+6 days' );

		return array(
			'start' => $start->format( 'Y-m-d' ),
			'end'   => $end->format( 'Y-m-d' ),
		);
	}

	/**
	 * Get week number based on anchor year start.
	 */
	public static function get_week_number(
		string $date,
		string $year_start,
		int $week_start_day = 0
	): int {

		$date_start   = self::get_week_start_date( $date, $week_start_day );
		$anchor_start = self::get_week_start_date( $year_start, $week_start_day );

		$diff_days = (int) $anchor_start->diff( $date_start )->days;
		$weeks     = intdiv( $diff_days, 7 );

		if ( $date_start < $anchor_start ) {
			$weeks = -$weeks;
		}

		return $weeks + 1;
	}

	/**
	 * MAIN: Current week with rollover logic.
	 *
	 * RULE:
	 * If current week is NOT complete → move to next year W1.
	 */
	public static function get_current_week_data(): array {

		$year_start = (string) get_option( 'wrm_year_start', '' );

		if ( empty( $year_start ) ) {
			$year_start = date( 'Y-01-01' );
		}

		$today = new \DateTime();

		$week_start_day = self::get_week_start_day();

		$current_range = self::get_week_range( $today->format( 'Y-m-d' ), $week_start_day );

		$week_number = self::get_week_number(
			$today->format( 'Y-m-d' ),
			$year_start,
			$week_start_day
		);

		$end = new \DateTime( $current_range['end'] );
		$now = new \DateTime();

		$is_complete = $now >= $end;

		/**
		 * 🚨 YOUR BUSINESS RULE:
		 * If last week is NOT complete → roll to next year
		 */
		if ( ! $is_complete ) {

			$next_year_start = ( new \DateTime( $year_start ) )
				->modify( '+1 year' )
				->format( 'Y-m-d' );

			return array(
				'week'   => 'W1',
				'start'  => $next_year_start,
				'end'    => ( new \DateTime( $next_year_start ) )
								->modify( '+6 days' )
								->format( 'Y-m-d' ),
				'year'   => (int) date( 'Y', strtotime( $next_year_start ) ),
				'rolled' => true,
			);
		}

		return array(
			'week'   => 'W' . $week_number,
			'start'  => $current_range['start'],
			'end'    => $current_range['end'],
			'year'   => (int) date( 'Y' ),
			'rolled' => false,
		);
	}

	/**
	 * Generate full W1–W52/53 list for UI dropdown.
	 */
	public static function get_year_weeks( string $year_start ): array {

		$weeks          = array();
		$week_start_day = self::get_week_start_day();

		$start = self::get_week_start_date( $year_start, $week_start_day );

		for ( $i = 1; $i <= 53; $i++ ) {

			$week_start = clone $start;
			$week_start->modify( '+' . ( ( $i - 1 ) * 7 ) . ' days' );

			$week_end = clone $week_start;
			$week_end->modify( '+6 days' );

			// stop if we cross into next year
			if ( $week_start->format( 'Y' ) !== $start->format( 'Y' ) ) {
				break;
			}

			$weeks[] = array(
				'week'  => 'W' . $i,
				'start' => $week_start->format( 'Y-m-d' ),
				'end'   => $week_end->format( 'Y-m-d' ),
			);
		}

		return $weeks;
	}
}
