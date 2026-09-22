<?php
/**
 * Remote action worker schedule handler helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.21
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs accepted schedule preview, apply, and rollback-preview actions.
 *
 * @since 0.5.21
 */
trait Alynt_Drime_Backups_Uploader_Remote_Action_Worker_Schedule_Handlers {
	/**
	 * Builds and stores a read-only schedule preview.
	 *
	 * @param array<string,mixed> $record Action record.
	 * @return void
	 */
	private function handle_schedule_preview( array $record ) {
		$this->store->upsert_action( $record, 'running', 'schedule_preview_running', __( 'Remote schedule preview action is running on the client site.', 'alynt-drime-backups-uploader' ) );

		try {
			$preview = $this->schedule_preview_from_record( $record );
			if ( is_wp_error( $preview ) ) {
				$this->store->upsert_action( $record, 'failed', $preview->get_error_code(), __( 'Remote schedule preview could not be completed.', 'alynt-drime-backups-uploader' ) );
				return;
			}

			$this->store->upsert_action( $record, 'succeeded', 'schedule_preview_ready', __( 'Schedule preview is ready. No schedule was changed.', 'alynt-drime-backups-uploader' ), array(), 0, $preview );
		} catch ( Exception $exception ) {
			unset( $exception );
			$this->store->upsert_action( $record, 'failed', 'schedule_preview_failed', __( 'Remote schedule preview failed without storing unsafe error details.', 'alynt-drime-backups-uploader' ) );
		}
	}

	/**
	 * Applies one fresh-previewed Alynt scan/upload schedule cadence.
	 *
	 * @since 0.5.19
	 *
	 * @param array<string,mixed> $record Action record.
	 * @return void
	 */
	private function handle_schedule_apply( array $record ) {
		$this->store->upsert_action( $record, 'running', 'schedule_apply_running', __( 'Remote schedule apply action is running on the client site.', 'alynt-drime-backups-uploader' ) );

		try {
			$apply = $this->schedule_apply_from_record( $record );
			if ( is_wp_error( $apply ) ) {
				$this->store->upsert_action( $record, 'failed', $apply->get_error_code(), __( 'Remote schedule apply could not be completed.', 'alynt-drime-backups-uploader' ) );
				return;
			}

			$this->store->upsert_action( $record, 'succeeded', 'schedule_apply_succeeded', __( 'Schedule apply completed for Alynt scan/upload.', 'alynt-drime-backups-uploader' ), array(), 0, array(), $apply );
		} catch ( Exception $exception ) {
			unset( $exception );
			$this->store->upsert_action( $record, 'failed', 'schedule_apply_failed', __( 'Remote schedule apply failed without storing unsafe error details.', 'alynt-drime-backups-uploader' ) );
		}
	}

	/**
	 * Builds and stores a read-only schedule rollback preview.
	 *
	 * @since 0.5.21
	 *
	 * @param array<string,mixed> $record Action record.
	 * @return void
	 */
	private function handle_schedule_rollback_preview( array $record ) {
		$this->store->upsert_action( $record, 'running', 'schedule_rollback_preview_running', __( 'Remote schedule rollback preview action is running on the client site.', 'alynt-drime-backups-uploader' ) );

		try {
			$preview = $this->schedule_rollback_preview_from_record( $record );
			if ( is_wp_error( $preview ) ) {
				$this->store->upsert_action( $record, 'failed', $preview->get_error_code(), __( 'Remote schedule rollback preview could not be completed.', 'alynt-drime-backups-uploader' ) );
				return;
			}

			$this->store->upsert_action( $record, 'succeeded', 'schedule_rollback_preview_ready', __( 'Schedule rollback preview is ready. No schedule was changed.', 'alynt-drime-backups-uploader' ), array(), 0, array(), array(), $preview );
		} catch ( Exception $exception ) {
			unset( $exception );
			$this->store->upsert_action( $record, 'failed', 'schedule_rollback_preview_failed', __( 'Remote schedule rollback preview failed without storing unsafe error details.', 'alynt-drime-backups-uploader' ) );
		}
	}
}
