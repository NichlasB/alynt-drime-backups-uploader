<?php
/**
 * Admin page Drime settings rendering.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page Drime settings rendering.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Admin_Page_Drime_Settings {
	/**
	 * Renders Drime settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private function render_drime_settings( array $settings ) {
		?>
		<h2><?php esc_html_e( 'Drime', 'alynt-drime-backups-uploader' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="alynt-api-token"><?php esc_html_e( 'API Token', 'alynt-drime-backups-uploader' ); ?></label></th>
				<td>
					<input id="alynt-api-token" name="alynt_drime_backups_settings[api_token]" type="password" class="regular-text" value="<?php echo esc_attr( '' === $settings['api_token'] ? '' : '************' ); ?>" autocomplete="off" aria-describedby="alynt-api-token-description">
					<p id="alynt-api-token-description" class="description"><?php esc_html_e( 'Enter a Drime bearer token. Leave the masked value unchanged to keep the saved token.', 'alynt-drime-backups-uploader' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="alynt-workspace-id"><?php esc_html_e( 'Workspace ID', 'alynt-drime-backups-uploader' ); ?></label></th>
				<td>
					<input id="alynt-workspace-id" name="alynt_drime_backups_settings[workspace_id]" type="number" min="0" value="<?php echo esc_attr( (string) $settings['workspace_id'] ); ?>" aria-describedby="alynt-workspace-id-description">
					<p id="alynt-workspace-id-description" class="description"><?php esc_html_e( 'Use 0 for your personal/default Drime workspace.', 'alynt-drime-backups-uploader' ); ?></p>
					<div class="alynt-drime-workspace-tools" data-alynt-workspace-browser>
						<button type="button" class="button" data-alynt-workspaces-load><?php esc_html_e( 'Load Drime Workspaces', 'alynt-drime-backups-uploader' ); ?></button>
						<span class="spinner" aria-hidden="true" data-alynt-workspace-spinner></span>
						<label class="screen-reader-text" for="alynt-workspace-select"><?php esc_html_e( 'Choose Drime workspace', 'alynt-drime-backups-uploader' ); ?></label>
						<select id="alynt-workspace-select" data-alynt-workspace-select hidden>
							<?php /* translators: %d: saved Drime workspace ID. */ ?>
							<option value="<?php echo esc_attr( (string) absint( $settings['workspace_id'] ) ); ?>"><?php echo esc_html( 0 === absint( $settings['workspace_id'] ) ? __( 'Personal/default workspace', 'alynt-drime-backups-uploader' ) : sprintf( __( 'Workspace ID %d', 'alynt-drime-backups-uploader' ), absint( $settings['workspace_id'] ) ) ); ?></option>
						</select>
						<div class="alynt-drime-workspace-status" aria-live="polite" aria-atomic="true" data-alynt-workspace-status></div>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="alynt-parent-folder-id"><?php esc_html_e( 'Parent Folder ID', 'alynt-drime-backups-uploader' ); ?></label></th>
				<td>
					<input id="alynt-parent-folder-id" name="alynt_drime_backups_settings[parent_folder_id]" type="number" min="0" value="<?php echo esc_attr( (string) $settings['parent_folder_id'] ); ?>" aria-describedby="alynt-parent-folder-id-description">
					<input id="alynt-parent-folder-hash" name="alynt_drime_backups_settings[parent_folder_hash]" type="hidden" value="<?php echo esc_attr( (string) $settings['parent_folder_hash'] ); ?>">
					<input id="alynt-parent-folder-display-path" name="alynt_drime_backups_settings[parent_folder_display_path]" type="hidden" value="<?php echo esc_attr( (string) $settings['parent_folder_display_path'] ); ?>">
					<p id="alynt-parent-folder-id-description" class="description"><?php esc_html_e( 'Leave empty for the Drime root folder, or browse and select an existing Drime base folder.', 'alynt-drime-backups-uploader' ); ?></p>
					<p class="description" data-alynt-selected-folder>
						<?php
						if ( '' !== (string) $settings['parent_folder_display_path'] ) {
							printf(
								/* translators: %s: selected Drime folder path. */
								esc_html__( 'Selected base folder: %s', 'alynt-drime-backups-uploader' ),
								esc_html( (string) $settings['parent_folder_display_path'] )
							);
						} else {
							esc_html_e( 'Selected base folder: Drime root or manually entered folder ID.', 'alynt-drime-backups-uploader' );
						}
						?>
					</p>
					<div class="alynt-drime-folder-tools" data-alynt-folder-browser>
						<button type="button" class="button" data-alynt-folder-browser-open><?php esc_html_e( 'Browse Drime Folders', 'alynt-drime-backups-uploader' ); ?></button>
						<button type="button" class="button" data-alynt-destination-preview><?php esc_html_e( 'Preview Destination', 'alynt-drime-backups-uploader' ); ?></button>
						<span class="spinner" aria-hidden="true" data-alynt-folder-spinner></span>
						<div class="alynt-drime-folder-status" aria-live="polite" aria-atomic="true" data-alynt-folder-status></div>
						<div class="alynt-drime-folder-panel" hidden data-alynt-folder-panel>
							<div class="alynt-drime-folder-search">
								<label class="screen-reader-text" for="alynt-folder-search"><?php esc_html_e( 'Search Drime folders', 'alynt-drime-backups-uploader' ); ?></label>
								<input id="alynt-folder-search" type="search" class="regular-text" data-alynt-folder-search>
								<button type="button" class="button" data-alynt-folder-search-button><?php esc_html_e( 'Search', 'alynt-drime-backups-uploader' ); ?></button>
							</div>
							<table class="widefat striped alynt-drime-folder-table">
								<caption class="screen-reader-text"><?php esc_html_e( 'Drime folder browser results', 'alynt-drime-backups-uploader' ); ?></caption>
								<thead>
									<tr>
										<th scope="col"><?php esc_html_e( 'Name', 'alynt-drime-backups-uploader' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Path', 'alynt-drime-backups-uploader' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Actions', 'alynt-drime-backups-uploader' ); ?></th>
									</tr>
								</thead>
								<tbody data-alynt-folder-rows></tbody>
							</table>
						</div>
						<div class="alynt-drime-destination-preview" aria-live="polite" aria-atomic="true" data-alynt-destination-status></div>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="alynt-relative-path"><?php esc_html_e( 'Relative Path', 'alynt-drime-backups-uploader' ); ?></label></th>
				<td>
					<input id="alynt-relative-path" name="alynt_drime_backups_settings[relative_path]" type="text" class="regular-text" value="<?php echo esc_attr( (string) $settings['relative_path'] ); ?>" placeholder="<?php echo esc_attr__( '/WPvivid Backups', 'alynt-drime-backups-uploader' ); ?>" aria-describedby="alynt-relative-path-description">
					<p id="alynt-relative-path-description" class="description"><?php esc_html_e( 'Optional subpath under the selected base folder. Missing folders are created only when an upload needs them.', 'alynt-drime-backups-uploader' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}
}
