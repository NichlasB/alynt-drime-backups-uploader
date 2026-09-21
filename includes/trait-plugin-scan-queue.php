<?php
/**
 * Plugin scan and queue orchestration helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin scan and queue orchestration helpers.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Plugin_Scan_Queue {
	/**
	 * Scans and queues stable files.
	 *
	 * @return array<string,mixed>
	 *
	 * @since 0.1.0
	 */
	public function scan_and_queue() {
		$result = $this->scanner->scan();

		if ( ! empty( $result['errors'] ) ) {
			$this->log_scan_errors( $result['errors'] );
			return $result;
		}

		$queued           = $this->queue_scan_candidates( $result['candidates'] );
		$result['queued'] = $queued;
		if ( $this->queue->last_persistence_failed() ) {
			$message            = __( 'Backup packages were found, but the upload queue could not be saved. Confirm the site database is writable, then scan again.', 'alynt-drime-backups-uploader' );
			$result['errors'][] = $message;
			$this->logger->event( 'scanner', 'error', 'queue_save_failed', $message, array( 'found' => count( $result['candidates'] ) ) );
		}
		$this->logger->event(
			'scanner',
			'info',
			'scan_finished',
			'Backup scan finished.',
			array(
				'found'  => count( $result['candidates'] ),
				'queued' => $queued,
			)
		);

		return $result;
	}

	/**
	 * Logs scan errors.
	 *
	 * @param array<int,string> $errors Errors.
	 * @return void
	 *
	 * @since 0.1.0
	 */
	private function log_scan_errors( array $errors ) {
		foreach ( $errors as $error ) {
			$this->logger->event( 'scanner', 'error', 'scan_error', $error );
		}
	}

	/**
	 * Queues candidates that have not already uploaded.
	 *
	 * @param array<int,array<string,mixed>> $candidates Candidates.
	 * @return int
	 */
	private function queue_scan_candidates( array $candidates ) {
		return $this->queue->add_many( $candidates, $this->registry->get_uploaded(), $this->registry->get_failed() );
	}
}
