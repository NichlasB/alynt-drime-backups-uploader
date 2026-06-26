<?php
/**
 * Admin page notification and diagnostics settings rendering.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page notification and diagnostics settings rendering.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Admin_Page_Notification_Settings {
	/**
	 * Renders failure email notification settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private function render_failure_email_settings( array $settings ) {
		?>
		<tr>
			<th scope="row"><label for="alynt-failure-email-enabled"><?php esc_html_e( 'Failure Emails', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<label><input id="alynt-failure-email-enabled" name="alynt_drime_backups_settings[failure_email_enabled]" type="checkbox" value="1" <?php checked( ! empty( $settings['failure_email_enabled'] ) ); ?> aria-describedby="alynt-failure-email-description"> <?php esc_html_e( 'Email administrators when an upload reaches a final failure state.', 'alynt-drime-backups-uploader' ); ?></label>
				<p id="alynt-failure-email-description" class="description"><?php esc_html_e( 'Emails are plain text and use the site WordPress mail stack.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="alynt-failure-email-recipients"><?php esc_html_e( 'Failure Email Recipients', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<textarea id="alynt-failure-email-recipients" name="alynt_drime_backups_settings[failure_email_recipients]" class="large-text code" rows="3" aria-describedby="alynt-failure-email-recipients-description"><?php echo esc_textarea( (string) $settings['failure_email_recipients'] ); ?></textarea>
				<p id="alynt-failure-email-recipients-description" class="description"><?php esc_html_e( 'Enter one email per line or separate multiple addresses with commas.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders diagnostics settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private function render_diagnostics_settings( array $settings ) {
		$level_labels = array(
			'debug'    => __( 'Debug', 'alynt-drime-backups-uploader' ),
			'info'     => __( 'Info', 'alynt-drime-backups-uploader' ),
			'warning'  => __( 'Warning', 'alynt-drime-backups-uploader' ),
			'error'    => __( 'Error', 'alynt-drime-backups-uploader' ),
			'critical' => __( 'Critical', 'alynt-drime-backups-uploader' ),
		);

		?>
		<tr>
			<th scope="row"><label for="alynt-diagnostics-enabled"><?php esc_html_e( 'Diagnostics', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<label><input id="alynt-diagnostics-enabled" name="alynt_drime_backups_settings[diagnostics_enabled]" type="checkbox" value="1" <?php checked( ! empty( $settings['diagnostics_enabled'] ) ); ?> aria-describedby="alynt-diagnostics-enabled-description"> <?php esc_html_e( 'Store redacted diagnostic events.', 'alynt-drime-backups-uploader' ); ?></label>
				<p id="alynt-diagnostics-enabled-description" class="description"><?php esc_html_e( 'Events are stored locally and exclude tokens, request bodies, and signed URLs.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="alynt-diagnostics-min-level"><?php esc_html_e( 'Diagnostics Level', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<select id="alynt-diagnostics-min-level" name="alynt_drime_backups_settings[diagnostics_min_level]" aria-describedby="alynt-diagnostics-min-level-description">
					<?php foreach ( array_keys( Alynt_Drime_Backups_Uploader_Settings::severity_levels() ) as $level ) : ?>
						<option value="<?php echo esc_attr( $level ); ?>" <?php selected( $settings['diagnostics_min_level'], $level ); ?>><?php echo esc_html( isset( $level_labels[ $level ] ) ? $level_labels[ $level ] : $level ); ?></option>
					<?php endforeach; ?>
				</select>
				<p id="alynt-diagnostics-min-level-description" class="description"><?php esc_html_e( 'Only events at this severity or higher are stored.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="alynt-diagnostics-retention"><?php esc_html_e( 'Diagnostics Retention', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<input id="alynt-diagnostics-retention" name="alynt_drime_backups_settings[diagnostics_retention]" type="number" min="25" max="500" step="25" value="<?php echo esc_attr( (string) $settings['diagnostics_retention'] ); ?>" aria-describedby="alynt-diagnostics-retention-description">
				<p id="alynt-diagnostics-retention-description" class="description"><?php esc_html_e( 'Maximum local diagnostic events to retain.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<?php
	}
}
