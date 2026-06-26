<?php
/**
 * Admin page settings form rendering.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page settings form rendering.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Admin_Page_Settings {
	/**
	 * Renders the settings form.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param string              $detected_path Detected path.
	 * @return void
	 */
	private function render_settings_form( array $settings, $detected_path ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="alynt_drime_backups_save_settings">
			<?php wp_nonce_field( 'alynt_drime_backups_save_settings' ); ?>
			<?php $this->render_drime_settings( $settings ); ?>
			<?php $this->render_source_settings( $settings, $detected_path ); ?>
			<?php $this->render_behavior_settings( $settings ); ?>
			<?php submit_button( __( 'Save Settings', 'alynt-drime-backups-uploader' ), 'primary', 'submit', true, array( 'data-alynt-loading-label' => __( 'Saving...', 'alynt-drime-backups-uploader' ) ) ); ?>
		</form>
		<?php
	}

	/**
	 * Renders behavior settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private function render_behavior_settings( array $settings ) {
		?>
		<h2><?php esc_html_e( 'Behavior', 'alynt-drime-backups-uploader' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php $this->render_upload_behavior_settings( $settings ); ?>
			<?php $this->render_failure_email_settings( $settings ); ?>
			<?php $this->render_diagnostics_settings( $settings ); ?>
		</table>
		<?php
	}
}
