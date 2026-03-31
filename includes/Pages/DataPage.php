<?php

namespace WRM\Pages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DataPage {

	public static function page() {
		global $wpdb;
		$entities = wpac()->entities()->get_all( true );
		$sites    = wpac()->sites()->get_all( true );
		?>

		<div class="wrap wrm-wrap">
			<h1>Report Manager</h1>

			<!-- Tabs -->
			<h2 class="nav-tab-wrapper">
				<a href="#tab-data" class="nav-tab nav-tab-active">View Data</a>
				<a href="#tab-refresh" class="nav-tab">Refresh Data</a>
			</h2>

			<!-- VIEW DATA TAB -->
			<div id="tab-data" class="wrm-tab-content" style="display:block;">
				<div class="wrm-filters">
					<select id="wrm-entity">
						<?php
						foreach ( $entities as $e ) :
							?>
							<option value="<?php echo esc_attr( $e['id'] ); ?>">
								<?php echo esc_html( $e['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<select id="wrm-site">
						<option value="">Select Site</option>
						<?php
						foreach ( $sites as $s ) :
							?>
							<option data-entity-id="<?php echo esc_attr( $s['entity_id'] ); ?>" value="<?php echo esc_attr( $s['site_id'] ); ?>">
								<?php echo esc_html( $s['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<input type="date" id="wrm-from">
					<input type="date" id="wrm-to">
					<button id="wrm-load" class="button button-primary">Load</button>
				</div>

				<div class="table-card">
					<h2>Raw Transaction Data</h2>
					<table class="wrm-table" id="wrm-data-table">
						<thead>
							<tr>
								<th>ID</th>
								<th>Transaction ID</th>
								<th>Site</th>
								<th>Date</th>
								<th>Subtotal</th>
								<th>Discounts</th>
								<th>Tax</th>
								<th>Total</th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
					<div id="wrm-pagination" style="margin-top:10px;"></div>
				</div>
			</div>

			<!-- REFRESH DATA TAB -->
			<div id="tab-refresh" class="wrm-tab-content" style="display:none;">
				<div class="wrm-filters">
					<h3>Kurve API Refresh</h3>
					<select id="wrm-refresh-entity">
						<option value="">Select Entity</option>
						<?php foreach ( $entities as $e ) : ?>
							<option value="<?php echo esc_attr( $e['id'] ); ?>">
								<?php echo esc_html( $e['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<input type="date" id="wrm-refresh-from">
					<input type="date" id="wrm-refresh-to">
					<button id="wrm-refresh-kurve" class="button button-primary">Refresh Kurve</button>
				</div>

				<div id="wrm-progress-container" style="margin:20px 0; display:none;">
					<div id="wrm-progress-label">Kurve Data Import Progress</div>
					<div id="wrm-progress-bar-wrapper" style="width:100%; background:#eee; border-radius:8px; overflow:hidden; height:24px;">
						<div id="wrm-progress-bar" style="width:0%; height:100%; background:#4CAF50; text-align:center; color:white; line-height:24px;">0%</div>
					</div>
					<div id="wrm-progress-info" style="margin-top:4px; font-size:0.9em; color:#333;"></div>
				</div>

				<div class="wrm-filters">
					<h3>TB Upload</h3>
					<input type="file" id="wrm-tb-file" accept=".xlsx,.csv">
					<button id="wrm-upload-tb" class="button button-primary">Upload TB</button>
				</div>

				<div id="wrm-refresh-status"></div>
			</div>
		</div>

		<?php
	}
}
