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

		$saved_notice = '';
		$error_notice = '';

		// Handle: save API keys.
		if ( isset( $_POST['wrm_save_api_keys'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$nonce = isset( $_POST['wrm_api_keys_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wrm_api_keys_nonce'] ) ) : '';
			if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wrm_api_keys_save' ) ) {
				$error_notice = 'Security check failed for API keys. Please reload and try again.';
			} else {
				$kineya_key     = isset( $_POST['wrm_kineya_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['wrm_kineya_api_key'] ) ) : '';
				$sushinoya_key  = isset( $_POST['wrm_sushinoya_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['wrm_sushinoya_api_key'] ) ) : '';
				$kimchee_key    = isset( $_POST['wrm_kimchee_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['wrm_kimchee_api_key'] ) ) : '';
				$week_start_day = isset( $_POST['wrm_week_start_day'] ) ? sanitize_text_field( wp_unslash( $_POST['wrm_week_start_day'] ) ) : '';
				$tb_username    = isset( $_POST['wrm_tb_username'] ) ? sanitize_text_field( wp_unslash( $_POST['wrm_tb_username'] ) ) : '';
				$tb_password    = isset( $_POST['wrm_tb_password'] ) ? sanitize_text_field( wp_unslash( $_POST['wrm_tb_password'] ) ) : '';

				update_option( 'wrm_kineya_api_key', $kineya_key );
				update_option( 'wrm_sushinoya_api_key', $sushinoya_key );
				update_option( 'wrm_kimchee_api_key', $kimchee_key );
				update_option( 'wrm_week_start_day', $week_start_day );
				update_option( 'wrm_tb_username', $tb_username );
				update_option( 'wrm_tb_password', $tb_password );

				$saved_notice = 'Settings saved successfully.';
			}
		}

		if ( isset( $_POST['wrm_generate_bi_key'] ) ) {

			check_admin_referer( 'wrm_api_keys_save', 'wrm_api_keys_nonce' );

			$key = bin2hex( random_bytes( 32 ) );

			update_option( 'wrm_bi_api_key', $key );
		}

		$kineya_api_key    = (string) get_option( 'wrm_kineya_api_key', '' );
		$sushinoya_api_key = (string) get_option( 'wrm_sushinoya_api_key', '' );
		$kimchee_api_key   = (string) get_option( 'wrm_kimchee_api_key', '' );
		$tb_username       = (string) get_option( 'wrm_tb_username', '' );
		$tb_password       = (string) get_option( 'wrm_tb_password', '' );
		$bi_key            = get_option( 'wrm_bi_api_key' );
		$api_stats         = get_option( 'wrm_api_usage_stats', array() );
		?>

		<div class="wrap">
			<h1>Report Manager ~ Settings</h1>

			<?php if ( ! empty( $saved_notice ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html( $saved_notice ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $error_notice ) ) : ?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo esc_html( $error_notice ); ?></p>
				</div>
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
									class="regular-text">
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
								<label for="wrm_kimchee_api_key">Kimchee API key</label>
							</th>
							<td>
								<input
									type="text"
									id="wrm_kimchee_api_key"
									name="wrm_kimchee_api_key"
									class="regular-text"
									value="<?php echo esc_attr( $kimchee_api_key ); ?>"
									autocomplete="off" />
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
									autocomplete="off" />
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
									autocomplete="off" />
								<p class="description">Leave empty to skip Sushinoya ingestion.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wrm_tb_username">TB Username</label>
							</th>
							<td>
								<input
									type="text"
									id="wrm_tb_username"
									name="wrm_tb_username"
									class="regular-text"
									value="<?php echo esc_attr( $tb_username ); ?>"
									autocomplete="off" />
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wrm_tb_password">TB Password</label>
							</th>
							<td>
								<input
									type="password"
									id="wrm_tb_password"
									name="wrm_tb_password"
									class="regular-text"
									value="<?php echo esc_attr( $tb_password ); ?>"
									autocomplete="off" />
							</td>
						</tr>
						<tr>
							<th>BI API Key</th>
							<td>

								<input type="text"
									value="<?php echo esc_attr( $bi_key ); ?>"
									readonly
									class="regular-text" />

								<p class="description">
									Use this key for Power BI / Excel integrations
								</p>
					</tbody>
				</table>

				<p>
					<button type="submit" name="wrm_save_api_keys" class="button button-primary">
						Save API Keys
					</button>
					<button type="submit" name="wrm_generate_bi_key" class="button">
						Generate Power BI API Key
					</button>
				</p>
			</form>
			<h2>BI API Usage (Last 5 Days)</h2>

			<table class="widefat striped">
				<thead>
					<tr>
						<th>Date</th>
						<th>Hits</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $api_stats as $date => $count ) : ?>
						<tr>
							<td><?php echo esc_html( $date ); ?></td>
							<td><?php echo esc_html( $count ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php
	}
}
