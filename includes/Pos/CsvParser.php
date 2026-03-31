<?php
/**
 * Customer Post Type - Products.
 *
 * @package WP_Report_Manager
 */

namespace WRM\Pos;

/**
 * POS Provider Interface.
 *
 * Defines the contract for POS data fetching implementations.
 *
 * @since 1.0.0
 */
class CsvParser {


	/**
	 * Parse CSV string into an array of associative arrays.
	 *
	 * @since 1.0.0
	 *
	 * @param string $csv CSV string to parse.
	 * @return array Parsed data as an array of associative arrays.
	 */
	public static function parse( string $csv ): array {

		$rows = array_map( 'str_getcsv', explode( "\n", trim( $csv ) ) );

		if ( empty( $rows ) ) {
			return array();
		}

		$header = array_shift( $rows );
		$data   = array();

		foreach ( $rows as $row ) {
			if ( count( $row ) !== count( $header ) ) {
				continue;
			}

			$data[] = array_combine( $header, $row );
		}

		return $data;
	}
}
