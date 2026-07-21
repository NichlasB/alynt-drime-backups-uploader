<?php
/**
 * Plugin remote retention action handlers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin remote retention action handlers.
 *
 * @since 0.4.0
 */
trait Alynt_Drime_Backups_Uploader_Plugin_Retention_Actions {
	/**
	 * Previews remote Drime retention candidates.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function handle_preview_remote_retention() {

		$this->verify_admin_action( 'alynt_drime_backups_preview_remote_retention' );

		$candidates = $this->retention->preview();

		$this->logger->event( 'retention', 'info', 'retention_previewed', 'Remote Drime retention previewed.', array( 'candidates' => count( $candidates ) ) );

		$this->redirect( empty( $candidates ) ? 'retention_preview_empty' : 'retention_preview_ready' );
	}

	/**
	 * Runs manual remote Drime retention cleanup.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function handle_run_remote_retention() {

		$this->verify_admin_action( 'alynt_drime_backups_run_remote_retention' );

		$result = $this->retention->cleanup();

		if ( is_wp_error( $result ) || ! empty( $result['failed'] ) ) {

			$this->redirect(
				'retention_failed',
				array(
					'failed'  => is_array( $result ) && isset( $result['failed'] ) ? absint( $result['failed'] ) : 0,
					'trashed' => is_array( $result ) && isset( $result['trashed'] ) ? absint( $result['trashed'] ) : 0,
				)
			);

		}

		$this->redirect( empty( $result['trashed'] ) ? 'retention_nothing_done' : 'retention_done' );
	}
}
