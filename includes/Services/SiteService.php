<?php

namespace WRM\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteService {

	public static function upsert( int $site_id, string $site_name, ?int $entity_id ): void {

		if ( $site_id <= 0 || empty( $entity_id ) ) {
			return;
		}

		global $wpdb;

		$wpdb->replace(
			$wpdb->prefix . 'wrm_sites',
			array(
				'site_id'   => $site_id,
				'site_name' => $site_name,
				'entity_id' => $entity_id,
			),
			array( '%d', '%s', '%d' )
		);
	}
}
