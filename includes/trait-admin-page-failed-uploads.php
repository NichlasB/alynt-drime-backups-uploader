<?php
/**
 * Admin failed-upload rendering.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders failed uploads and retry controls.
 *
 * @since 0.5.0
 */
trait Alynt_Drime_Backups_Uploader_Admin_Page_Failed_Uploads {
	/**
	 * Renders failed upload records.
	 *
	 * @param array<string,array<string,mixed>> $failed Failed records.
	 * @return void
	 */
	private function render_failed_uploads( array $failed ) {
		if ( empty( $failed ) ) {
			return;
		}

		?>
		<h3><?php esc_html_e( 'Failed Uploads', 'alynt-drime-backups-uploader' ); ?></h3>
		<table class="widefat striped alynt-drime-backups-failed-uploads">
			<caption class="screen-reader-text"><?php esc_html_e( 'Failed upload details', 'alynt-drime-backups-uploader' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'File', 'alynt-drime-backups-uploader' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Failed', 'alynt-drime-backups-uploader' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Reason', 'alynt-drime-backups-uploader' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'alynt-drime-backups-uploader' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $failed as $signature => $record ) : ?>
					<?php $record = is_array( $record ) ? $record : array(); ?>
					<tr>
						<td><?php echo esc_html( $this->failed_upload_name( $signature, $record ) ); ?></td>
						<td><?php echo esc_html( ! empty( $record['failed_at'] ) ? $this->format_utc_time( absint( $record['failed_at'] ) ) : '' ); ?></td>
						<td><?php echo esc_html( isset( $record['message'] ) ? (string) $record['message'] : '' ); ?></td>
						<td><?php $this->render_failed_upload_retry_button( (string) $signature ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Renders a failed-upload retry button.
	 *
	 * @param string $signature Signature.
	 * @return void
	 */
	private function render_failed_upload_retry_button( $signature ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="alynt_drime_backups_requeue_failed_upload">
			<input type="hidden" name="signature" value="<?php echo esc_attr( $signature ); ?>">
			<?php wp_nonce_field( 'alynt_drime_backups_requeue_failed_upload' ); ?>
			<button
				type="submit"
				class="button button-secondary"
				data-alynt-loading-label="<?php esc_attr_e( 'Requeueing...', 'alynt-drime-backups-uploader' ); ?>"
			>
				<?php esc_html_e( 'Retry Upload', 'alynt-drime-backups-uploader' ); ?>
			</button>
		</form>
		<?php
	}

	/**
	 * Returns a safe failed-upload display name.
	 *
	 * @param string              $signature Signature.
	 * @param array<string,mixed> $record Record.
	 * @return string
	 */
	private function failed_upload_name( $signature, array $record ) {
		if ( ! empty( $record['name'] ) ) {
			return basename( (string) $record['name'] );
		}

		if ( ! empty( $record['path'] ) ) {
			return basename( (string) $record['path'] );
		}

		return $signature;
	}
}
