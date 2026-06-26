<?php
/**
 * Admin page upload behavior settings rendering.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page upload behavior settings rendering.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Admin_Page_Upload_Settings {
	/**
	 * Renders upload behavior settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private function render_upload_behavior_settings( array $settings ) {
		$min_chunk_size_mb     = Alynt_Drime_Backups_Uploader_Settings::MIN_MULTIPART_CHUNK_SIZE_MB;
		$max_chunk_size_mb     = Alynt_Drime_Backups_Uploader_Settings::MAX_MULTIPART_CHUNK_SIZE_MB;
		$default_chunk_size_mb = Alynt_Drime_Backups_Uploader_Settings::DEFAULT_MULTIPART_CHUNK_SIZE_MB;

		?>
		<tr>
			<th scope="row"><label for="alynt-duplicate-mode"><?php esc_html_e( 'Duplicate Handling', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<select id="alynt-duplicate-mode" name="alynt_drime_backups_settings[duplicate_mode]" aria-describedby="alynt-duplicate-mode-description">
					<option value="skip" <?php selected( $settings['duplicate_mode'], 'skip' ); ?>><?php esc_html_e( 'Skip existing files', 'alynt-drime-backups-uploader' ); ?></option>
					<option value="rename" <?php selected( $settings['duplicate_mode'], 'rename' ); ?>><?php esc_html_e( 'Rename new uploads', 'alynt-drime-backups-uploader' ); ?></option>
				</select>
				<p id="alynt-duplicate-mode-description" class="description"><?php esc_html_e( 'Choose whether existing Drime filenames are skipped or renamed during upload.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="alynt-auto-scan"><?php esc_html_e( 'Automatic Scanning', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td><label><input id="alynt-auto-scan" name="alynt_drime_backups_settings[auto_scan_enabled]" type="checkbox" value="1" <?php checked( ! empty( $settings['auto_scan_enabled'] ) ); ?>> <?php esc_html_e( 'Scan with WP-Cron every 15 minutes.', 'alynt-drime-backups-uploader' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><label for="alynt-server-cron-expected"><?php esc_html_e( 'Server Cron Expected', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<label><input id="alynt-server-cron-expected" name="alynt_drime_backups_settings[server_cron_expected]" type="checkbox" value="1" <?php checked( ! empty( $settings['server_cron_expected'] ) ); ?> aria-describedby="alynt-server-cron-expected-description"> <?php esc_html_e( 'Remind me if scheduled scans have not been observed from WP-CLI.', 'alynt-drime-backups-uploader' ); ?></label>
				<p id="alynt-server-cron-expected-description" class="description"><?php esc_html_e( 'Use this when the site should be driven by a server cron job instead of visitor traffic. The plugin records runtime evidence; it does not read server cron files.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="alynt-min-file-age"><?php esc_html_e( 'Minimum File Age', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<input id="alynt-min-file-age" name="alynt_drime_backups_settings[min_file_age_seconds]" type="number" min="60" step="60" value="<?php echo esc_attr( (string) $settings['min_file_age_seconds'] ); ?>" aria-describedby="alynt-min-file-age-description">
				<p id="alynt-min-file-age-description" class="description"><?php esc_html_e( 'Enter the minimum age in seconds. Files must also keep the same size across scans before they are queued.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="alynt-multipart-chunk-size"><?php esc_html_e( 'Multipart Chunk Size', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<input id="alynt-multipart-chunk-size" name="alynt_drime_backups_settings[multipart_chunk_size_mb]" type="number" min="<?php echo esc_attr( (string) $min_chunk_size_mb ); ?>" max="<?php echo esc_attr( (string) $max_chunk_size_mb ); ?>" step="1" value="<?php echo esc_attr( (string) $settings['multipart_chunk_size_mb'] ); ?>" aria-describedby="alynt-multipart-chunk-size-description">
				<p id="alynt-multipart-chunk-size-description" class="description">
					<?php
					printf(
						/* translators: %d: recommended multipart chunk size in MB. */
						esc_html__( 'Set the size of each Drime multipart upload part. %d MB is recommended for large backups.', 'alynt-drime-backups-uploader' ),
						(int) $default_chunk_size_mb
					);
					?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="alynt-delete-local"><?php esc_html_e( 'Delete Local Files', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<label><input id="alynt-delete-local" name="alynt_drime_backups_settings[delete_local_after_upload]" type="checkbox" value="1" <?php checked( ! empty( $settings['delete_local_after_upload'] ) ); ?> aria-describedby="alynt-delete-local-description"> <?php esc_html_e( 'Delete local backup files after confirmed Drime upload.', 'alynt-drime-backups-uploader' ); ?></label>
				<p id="alynt-delete-local-description" class="description"><?php esc_html_e( 'For production, enable this only after Drime uploads and restore procedures are verified and your local retention policy allows removing WPvivid files.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="alynt-remote-retention-enabled"><?php esc_html_e( 'Remote Retention', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<label><input id="alynt-remote-retention-enabled" name="alynt_drime_backups_settings[remote_retention_enabled]" type="checkbox" value="1" <?php checked( ! empty( $settings['remote_retention_enabled'] ) ); ?> aria-describedby="alynt-remote-retention-description"> <?php esc_html_e( 'Allow manual cleanup of old Drime files uploaded by this plugin.', 'alynt-drime-backups-uploader' ); ?></label>
				<p id="alynt-remote-retention-description" class="description"><?php esc_html_e( 'Cleanup moves eligible Drime files to trash only. It does not permanently delete remote files or delete local backup files.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="alynt-remote-retention-days"><?php esc_html_e( 'Remote Retention Age', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<input id="alynt-remote-retention-days" name="alynt_drime_backups_settings[remote_retention_days]" type="number" min="<?php echo esc_attr( (string) Alynt_Drime_Backups_Uploader_Settings::MIN_REMOTE_RETENTION_DAYS ); ?>" max="<?php echo esc_attr( (string) Alynt_Drime_Backups_Uploader_Settings::MAX_REMOTE_RETENTION_DAYS ); ?>" step="1" value="<?php echo esc_attr( (string) $settings['remote_retention_days'] ); ?>" aria-describedby="alynt-remote-retention-days-description">
				<p id="alynt-remote-retention-days-description" class="description"><?php esc_html_e( 'Uploaded registry records older than this many days become eligible for manual Drime trash cleanup.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="alynt-max-retries"><?php esc_html_e( 'Maximum Retries', 'alynt-drime-backups-uploader' ); ?></label></th>
			<td>
				<input id="alynt-max-retries" name="alynt_drime_backups_settings[max_retries]" type="number" min="0" max="10" value="<?php echo esc_attr( (string) $settings['max_retries'] ); ?>" aria-describedby="alynt-max-retries-description">
				<p id="alynt-max-retries-description" class="description"><?php esc_html_e( 'Set the number of failed upload attempts before a queued file is removed.', 'alynt-drime-backups-uploader' ); ?></p>
			</td>
		</tr>
		<?php
	}
}
