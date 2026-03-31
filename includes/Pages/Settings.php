<?php
/**
 * Settings page for storing external POS API keys.
 *
 * @package WP_Report_Manager
 */

namespace WRM\Pages;

/**
 * Admin UI for storing POS integration settings (API keys).
 *
 * @package WP_Report_Manager
 */
class Settings {

	/**
	 * Render settings page and handle submission.
	 *
	 * @since 1.0.0
	 */
	public static function page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;

		$entities_table = $wpdb->prefix . 'wrm_entities';
		$sites_table    = $wpdb->prefix . 'wrm_sites';

		$saved_notice = '';
		$error_notice = '';

		// Handle: save API keys.
		if ( isset( $_POST['wrm_save_api_keys'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$nonce = isset( $_POST['wrm_api_keys_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wrm_api_keys_nonce'] ) ) : '';
			if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wrm_api_keys_save' ) ) {
				$error_notice = 'Security check failed for API keys. Please reload and try again.';
			} else {
				$kineya_key     = isset( $_POST['wrm_kineya_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['wrm_kineya_api_key'] ) ) : '';
				$sushi_key      = isset( $_POST['wrm_sushinoya_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['wrm_sushinoya_api_key'] ) ) : '';
				$week_start_day = isset( $_POST['wrm_week_start_day'] ) ? sanitize_text_field( wp_unslash( $_POST['wrm_week_start_day'] ) ) : '';

				update_option( 'wrm_kineya_api_key', $kineya_key );
				update_option( 'wrm_sushinoya_api_key', $sushi_key );
				update_option( 'wrm_week_start_day', $week_start_day );

				$saved_notice = 'Settings saved successfully.';
			}
		}

		// Handle: add company.
		if ( isset( $_POST['wrm_add_company'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$nonce = isset( $_POST['wrm_add_company_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wrm_add_company_nonce'] ) ) : '';
			if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wrm_add_company_save' ) ) {
				$error_notice = 'Security check failed for company. Please reload and try again.';
			} else {
				$company_name = isset( $_POST['wrm_add_company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wrm_add_company_name'] ) ) : '';
				if ( empty( $company_name ) ) {
					$error_notice = 'Please enter a company name.';
				} else {
					$existing_entity_id = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT id FROM {$entities_table} WHERE LOWER(name) = LOWER(%s) LIMIT 1",
							$company_name
						)
					);

					if ( empty( $existing_entity_id ) ) {
						$wpdb->insert(
							$entities_table,
							array( 'name' => $company_name ),
							array( '%s' )
						);
						$saved_notice = 'Company added.';
					} else {
						$saved_notice = 'Company already exists.';
					}
				}
			}
		}

		// Handle: add site.
		if ( isset( $_POST['wrm_add_site'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$nonce = isset( $_POST['wrm_add_site_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wrm_add_site_nonce'] ) ) : '';
			if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wrm_add_site_save' ) ) {
				$error_notice = 'Security check failed for site. Please reload and try again.';
			} else {
				$add_site_id_raw     = isset( $_POST['wrm_add_site_id'] ) ? sanitize_text_field( wp_unslash( $_POST['wrm_add_site_id'] ) ) : '';
				$add_site_company_id = isset( $_POST['wrm_add_site_company_id'] ) ? absint( $_POST['wrm_add_site_company_id'] ) : 0;
				$add_site_name       = isset( $_POST['wrm_add_site_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wrm_add_site_name'] ) ) : '';

				if ( $add_site_company_id <= 0 ) {
					$error_notice = 'Please select a company.';
				} elseif ( empty( $add_site_id_raw ) ) {
					$error_notice = 'Please enter a Site ID.';
				} elseif ( ! ctype_digit( $add_site_id_raw ) ) {
					$error_notice = 'Site ID must be digits only (DB stores it as INT).';
				} elseif ( empty( $add_site_name ) ) {
					$error_notice = 'Please enter a Site name.';
				} else {
					$add_site_id = absint( $add_site_id_raw );
					$wpdb->replace(
						$sites_table,
						array(
							'site_id'   => $add_site_id,
							'site_name' => $add_site_name,
							'entity_id' => $add_site_company_id,
						),
						array(
							'%d',
							'%s',
							'%d',
						)
					);
					$saved_notice = 'Site saved.';
				}
			}
		}

		// Handle: edit company.
		if ( isset( $_POST['wrm_edit_company'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$nonce = isset( $_POST['wrm_edit_company_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wrm_edit_company_nonce'] ) ) : '';
			if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wrm_edit_company_save' ) ) {
				$error_notice = 'Security check failed for company edit.';
			} else {
				$company_id   = isset( $_POST['wrm_edit_company_id'] ) ? absint( $_POST['wrm_edit_company_id'] ) : 0;
				$company_name = isset( $_POST['wrm_edit_company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wrm_edit_company_name'] ) ) : '';

				if ( $company_id <= 0 ) {
					$error_notice = 'Invalid company id.';
				} elseif ( empty( $company_name ) ) {
					$error_notice = 'Company name cannot be empty.';
				} else {
					$wpdb->update(
						$entities_table,
						array(
							'name' => $company_name,
						),
						array(
							'id' => $company_id,
						),
						array( '%s' ),
						array( '%d' )
					);
					$saved_notice = 'Company updated.';
				}
			}
		}

		// Handle: delete company.
		if ( isset( $_POST['wrm_delete_company'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$nonce = isset( $_POST['wrm_delete_company_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wrm_delete_company_nonce'] ) ) : '';
			if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wrm_delete_company_save' ) ) {
				$error_notice = 'Security check failed for company delete.';
			} else {
				$company_id = isset( $_POST['wrm_delete_company_id'] ) ? absint( $_POST['wrm_delete_company_id'] ) : 0;
				if ( $company_id <= 0 ) {
					$error_notice = 'Invalid company id.';
				} else {
					// Remove sites under this company first (logical cascade).
					$wpdb->delete(
						$sites_table,
						array( 'entity_id' => $company_id ),
						array( '%d' )
					);
					$wpdb->delete(
						$entities_table,
						array( 'id' => $company_id ),
						array( '%d' )
					);
					$saved_notice = 'Company deleted.';
				}
			}
		}

		// Handle: edit site.
		if ( isset( $_POST['wrm_edit_site'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$nonce = isset( $_POST['wrm_edit_site_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wrm_edit_site_nonce'] ) ) : '';
			if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wrm_edit_site_save' ) ) {
				$error_notice = 'Security check failed for site edit.';
			} else {
				$site_id        = isset( $_POST['wrm_edit_site_id'] ) ? absint( $_POST['wrm_edit_site_id'] ) : 0;
				$site_name      = isset( $_POST['wrm_edit_site_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wrm_edit_site_name'] ) ) : '';
				$site_entity_id = isset( $_POST['wrm_edit_site_company_id'] ) ? absint( $_POST['wrm_edit_site_company_id'] ) : 0;

				if ( $site_id <= 0 ) {
					$error_notice = 'Invalid site id.';
				} elseif ( $site_entity_id <= 0 ) {
					$error_notice = 'Please select a company.';
				} elseif ( empty( $site_name ) ) {
					$error_notice = 'Site name cannot be empty.';
				} else {
					$wpdb->update(
						$sites_table,
						array(
							'site_name' => $site_name,
							'entity_id' => $site_entity_id,
						),
						array(
							'site_id' => $site_id,
						),
						array( '%s', '%d' ),
						array( '%d' )
					);
					$saved_notice = 'Site updated.';
				}
			}
		}

		// Handle: delete site.
		if ( isset( $_POST['wrm_delete_site'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$nonce = isset( $_POST['wrm_delete_site_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wrm_delete_site_nonce'] ) ) : '';
			if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wrm_delete_site_save' ) ) {
				$error_notice = 'Security check failed for site delete.';
			} else {
				$site_id = isset( $_POST['wrm_delete_site_id'] ) ? absint( $_POST['wrm_delete_site_id'] ) : 0;
				if ( $site_id <= 0 ) {
					$error_notice = 'Invalid site id.';
				} else {
					$wpdb->delete(
						$sites_table,
						array( 'site_id' => $site_id ),
						array( '%d' )
					);
					$saved_notice = 'Site deleted.';
				}
			}
		}

		$kineya_api_key    = (string) get_option( 'wrm_kineya_api_key', '' );
		$sushinoya_api_key = (string) get_option( 'wrm_sushinoya_api_key', '' );

		$entities = $wpdb->get_results(
			"
			SELECT id, name
			FROM {$entities_table}
			ORDER BY name ASC
			",
			ARRAY_A
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sites = $wpdb->get_results(
			"
			SELECT
				s.site_id,
				s.site_name,
				s.entity_id,
				e.name AS entity_name
			FROM {$sites_table} AS s
			LEFT JOIN {$entities_table} AS e ON e.id = s.entity_id
			ORDER BY e.name ASC, s.site_name ASC
			",
			ARRAY_A
		);

		?>
		<div class="wrap">
			<h1>Report Manager ~ Settings</h1>

			<?php if ( ! empty( $saved_notice ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $saved_notice ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! empty( $error_notice ) ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error_notice ); ?></p></div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'wrm_api_keys_save', 'wrm_api_keys_nonce' ); ?>

				<h2>POS API Keys</h2>
				<table class="form-table" role="presentation">
					<tbody>
					<tr>
	<th scope="row">
		<label for="wrm_week_start_day">Week Start Day</label>
	</th>
	<td>
		<select
			id="wrm_week_start_day"
			name="wrm_week_start_day"
			class="regular-text"
		>
			<option value="0" <?php selected( get_option( 'wrm_week_start_day' ), '0' ); ?>>Sunday</option>
			<option value="1" <?php selected( get_option( 'wrm_week_start_day' ), '1' ); ?>>Monday</option>
			<option value="2" <?php selected( get_option( 'wrm_week_start_day' ), '2' ); ?>>Tuesday</option>
			<option value="3" <?php selected( get_option( 'wrm_week_start_day' ), '3' ); ?>>Wednesday</option>
			<option value="4" <?php selected( get_option( 'wrm_week_start_day' ), '4' ); ?>>Thursday</option>
			<option value="5" <?php selected( get_option( 'wrm_week_start_day' ), '5' ); ?>>Friday</option>
			<option value="6" <?php selected( get_option( 'wrm_week_start_day' ), '6' ); ?>>Saturday</option>
		</select>

		<p class="description">
			Select the first day of the week for all reporting (W1 → W52/53 system).
		</p>
	</td>
</tr>
						<tr>
							<th scope="row">
								<label for="wrm_kineya_api_key">Kineya API key</label>
							</th>
							<td>
								<input
									type="text"
									id="wrm_kineya_api_key"
									name="wrm_kineya_api_key"
									class="regular-text"
									value="<?php echo esc_attr( $kineya_api_key ); ?>"
									autocomplete="off"
								/>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="wrm_sushinoya_api_key">Sushinoya API key</label>
							</th>
							<td>
								<input
									type="text"
									id="wrm_sushinoya_api_key"
									name="wrm_sushinoya_api_key"
									class="regular-text"
									value="<?php echo esc_attr( $sushinoya_api_key ); ?>"
									autocomplete="off"
								/>
								<p class="description">Leave empty to skip Sushinoya ingestion.</p>
							</td>
						</tr>
					</tbody>
				</table>

				<p>
					<button type="submit" name="wrm_save_api_keys" class="button button-primary">
						Save API Keys
					</button>
				</p>
			</form>

			<hr />

			<form method="post">
				<?php wp_nonce_field( 'wrm_add_company_save', 'wrm_add_company_nonce' ); ?>

				<h2>Companies</h2>
				<p class="description">Add a company (entity). Site records can be added below.</p>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="wrm_add_company_name">Company name</label>
							</th>
							<td>
								<input
									type="text"
									id="wrm_add_company_name"
									name="wrm_add_company_name"
									class="regular-text"
									value=""
									autocomplete="off"
								/>
							</td>
						</tr>
					</tbody>
				</table>

				<p>
					<button type="submit" name="wrm_add_company" class="button">
						Add Company
					</button>
				</p>
			</form>

			<hr />

			<form method="post">
				<?php wp_nonce_field( 'wrm_add_site_save', 'wrm_add_site_nonce' ); ?>

				<h2>Sites</h2>
				<p class="description">Add a POS site mapping to a company. Used by the Data filters.</p>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">Company</th>
							<td>
								<select id="wrm_add_site_company_id" name="wrm_add_site_company_id">
									<option value="0">Select company</option>
									<?php foreach ( (array) $entities as $e ) : ?>
										<?php
											$entity_id   = isset( $e['id'] ) ? absint( $e['id'] ) : 0;
											$entity_name = isset( $e['name'] ) ? (string) $e['name'] : '';
										?>
										<?php if ( $entity_id > 0 ) : ?>
											<option value="<?php echo esc_attr( (string) $entity_id ); ?>">
												<?php echo esc_html( $entity_name ); ?>
											</option>
										<?php endif; ?>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="wrm_add_site_id">Site ID (from CSV)</label>
							</th>
							<td>
								<input
									type="text"
									id="wrm_add_site_id"
									name="wrm_add_site_id"
									class="small-text"
									value=""
									autocomplete="off"
								/>
								<p class="description">Must be digits only (stored as INT in DB).</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="wrm_add_site_name">Site name</label>
							</th>
							<td>
								<input
									type="text"
									id="wrm_add_site_name"
									name="wrm_add_site_name"
									class="regular-text"
									value=""
									autocomplete="off"
								/>
							</td>
						</tr>
					</tbody>
				</table>

				<p>
					<button type="submit" name="wrm_add_site" class="button">
						Add Site
					</button>
				</p>
			</form>

			<hr />

			<h2>Current Companies</h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>ID</th>
						<th>Name</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $entities ) ) : ?>
						<tr><td colspan="2">No companies yet.</td></tr>
					<?php else : ?>
						<?php foreach ( (array) $entities as $e ) : ?>
							<?php
								$company_id   = absint( $e['id'] ?? 0 );
								$company_name = (string) ( $e['name'] ?? '' );
							?>
							<tr>
								<td><?php echo esc_html( (string) $company_id ); ?></td>
								<td>
									<form method="post" style="display:flex;gap:8px;align-items:center;">
										<input type="hidden" name="wrm_edit_company_id" value="<?php echo esc_attr( (string) $company_id ); ?>" />
										<input
											type="text"
											name="wrm_edit_company_name"
											value="<?php echo esc_attr( $company_name ); ?>"
											style="flex:1;"
										/>
										<?php wp_nonce_field( 'wrm_edit_company_save', 'wrm_edit_company_nonce' ); ?>
										<button type="submit" name="wrm_edit_company" class="button button-small">
											Save
										</button>
									</form>
									<form
										method="post"
										style="margin-top:6px;"
										onsubmit="return confirm('Delete this company and its sites?');"
									>
										<input type="hidden" name="wrm_delete_company_id" value="<?php echo esc_attr( (string) $company_id ); ?>" />
										<?php wp_nonce_field( 'wrm_delete_company_save', 'wrm_delete_company_nonce' ); ?>
										<button type="submit" name="wrm_delete_company" class="button button-small button-secondary">
											Delete
										</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h2 style="margin-top: 25px;">Current Sites</h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>Site ID</th>
						<th>Site Name</th>
						<th>Company</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $sites ) ) : ?>
						<tr><td colspan="4">No sites yet.</td></tr>
					<?php else : ?>
						<?php foreach ( (array) $sites as $s ) : ?>
							<?php
								$site_id           = absint( $s['site_id'] ?? 0 );
								$site_name         = (string) ( $s['site_name'] ?? '' );
								$current_entity_id = absint( $s['entity_id'] ?? 0 );
								$form_id           = 'wrm_edit_site_form_' . (string) $site_id;
							?>
							<tr>
								<td><?php echo esc_html( (string) $site_id ); ?></td>
								<td>
										<input
											type="text"
											name="wrm_edit_site_name"
											form="<?php echo esc_attr( (string) $form_id ); ?>"
											value="<?php echo esc_attr( $site_name ); ?>"
											style="width:100%;"
										/>
								</td>
								<td>
									<select
										name="wrm_edit_site_company_id"
										form="<?php echo esc_attr( (string) $form_id ); ?>"
									>
										<?php foreach ( (array) $entities as $e ) : ?>
											<?php
												$entity_id   = absint( $e['id'] ?? 0 );
												$entity_name = (string) ( $e['name'] ?? '' );
											?>
											<?php if ( $entity_id > 0 ) : ?>
												<option
													value="<?php echo esc_attr( (string) $entity_id ); ?>"
													<?php selected( $entity_id, $current_entity_id ); ?>
												>
													<?php echo esc_html( $entity_name ); ?>
												</option>
											<?php endif; ?>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<form
										id="<?php echo esc_attr( (string) $form_id ); ?>"
										method="post"
										style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;"
									>
										<input type="hidden" name="wrm_edit_site_id" value="<?php echo esc_attr( (string) $site_id ); ?>" />
										<input type="hidden" name="wrm_delete_site_id" value="<?php echo esc_attr( (string) $site_id ); ?>" />
										<?php wp_nonce_field( 'wrm_edit_site_save', 'wrm_edit_site_nonce' ); ?>
										<?php wp_nonce_field( 'wrm_delete_site_save', 'wrm_delete_site_nonce' ); ?>
										<button type="submit" name="wrm_edit_site" class="button button-small">
											Save
										</button>
										<button
											type="submit"
											name="wrm_delete_site"
											class="button button-small button-secondary"
											onclick="return confirm('Delete this site?');"
										>
											Delete
										</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
