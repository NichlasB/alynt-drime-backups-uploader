<?php
/**
 * Remote Drime retention service.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Selects and trashes old plugin-owned Drime uploads.
 *
 * @since 0.1.0
 */
class Alynt_Drime_Backups_Uploader_Remote_Retention {
	/**
	 * Settings.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Settings
	 */
	private $settings;

	/**
	 * Client.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Drime_Client
	 */
	private $client;

	/**
	 * Registry.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Backup_Registry
	 */
	private $registry;

	/**
	 * Logger.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Settings        $settings Settings.
	 * @param Alynt_Drime_Backups_Uploader_Drime_Client    $client Client.
	 * @param Alynt_Drime_Backups_Uploader_Backup_Registry $registry Registry.
	 * @param Alynt_Drime_Backups_Uploader_Logger          $logger Logger.
	 *
	 * @since 0.1.0
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Settings $settings, Alynt_Drime_Backups_Uploader_Drime_Client $client, Alynt_Drime_Backups_Uploader_Backup_Registry $registry, Alynt_Drime_Backups_Uploader_Logger $logger ) {
		$this->settings = $settings;
		$this->client   = $client;
		$this->registry = $registry;
		$this->logger   = $logger;
	}

	/**
	 * Returns cleanup candidates without changing remote or local state.
	 *
	 * @return array<int,array<string,mixed>>
	 *
	 * @since 0.1.0
	 */
	public function preview() {
		return $this->candidates( $this->settings->get() );
	}

	/**
	 * Trashes eligible remote files in Drime.
	 *
	 * @return array<string,mixed>|WP_Error
	 *
	 * @since 0.1.0
	 */
	public function cleanup() {
		$settings   = $this->settings->get();
		$candidates = $this->candidates( $settings );
		$summary    = array(
			'candidates' => count( $candidates ),
			'trashed'    => 0,
			'failed'     => 0,
			'skipped'    => 0,
		);

		$this->logger->event( 'retention', 'info', 'retention_started', 'Remote Drime retention started.', array( 'candidates' => count( $candidates ) ) );

		foreach ( $candidates as $candidate ) {
			$this->logger->event(
				'retention',
				'info',
				'retention_candidate_found',
				'Remote retention candidate found.',
				array(
					'signature' => $candidate['signature'],
					'file'      => $candidate['remote_name'],
					'age_days'  => $candidate['age_days'],
				)
			);

			$result = $this->client->trash_file_entry( (int) $candidate['file_entry_id'] );

			if ( is_wp_error( $result ) ) {
				++$summary['failed'];
				$this->registry->mark_remote_retention_status( (string) $candidate['signature'], 'trash_failed', array( 'message' => $result->get_error_message() ) );
				$this->logger->event(
					'retention',
					'error',
					'retention_failed',
					'Remote retention could not trash a Drime file.',
					array(
						'signature' => $candidate['signature'],
						'reason'    => $result->get_error_message(),
					)
				);
				continue;
			}

			if ( ! $this->registry->mark_remote_retention_status( (string) $candidate['signature'], 'trashed' ) ) {
				++$summary['failed'];
				$this->logger->event(
					'retention',
					'error',
					'retention_failed',
					'Remote retention trashed the Drime file but could not update local registry state.',
					array(
						'signature' => $candidate['signature'],
					)
				);
				continue;
			}

			++$summary['trashed'];
			$this->logger->event(
				'retention',
				'info',
				'retention_file_trashed',
				'Remote Drime file moved to trash.',
				array(
					'signature' => $candidate['signature'],
					'file'      => $candidate['remote_name'],
				)
			);
		}

		$this->logger->event( 'retention', 'info', 'retention_finished', 'Remote Drime retention finished.', $summary );

		return $summary;
	}

	/**
	 * Selects eligible retention candidates.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return array<int,array<string,mixed>>
	 */
	private function candidates( array $settings ) {
		if ( empty( $settings['remote_retention_enabled'] ) ) {
			return array();
		}

		$days      = max( 1, absint( $settings['remote_retention_days'] ) );
		$threshold = time() - ( $days * $this->day_in_seconds() );
		$records   = $this->registry->get_uploaded();
		$eligible  = array();

		foreach ( $records as $signature => $record ) {
			if ( ! is_array( $record ) || ! $this->record_is_candidate( $record, $threshold ) ) {
				continue;
			}

			$eligible[] = array(
				'signature'     => (string) $signature,
				'file_entry_id' => $this->file_entry_id( $record ),
				'remote_name'   => isset( $record['remote_name'] ) ? basename( (string) $record['remote_name'] ) : '',
				'uploaded_at'   => absint( $record['uploaded_at'] ),
				'age_days'      => (int) floor( max( 0, time() - absint( $record['uploaded_at'] ) ) / $this->day_in_seconds() ),
			);
		}

		return $eligible;
	}

	/**
	 * Returns whether an uploaded registry record is eligible.
	 *
	 * @param array<string,mixed> $record Record.
	 * @param int                 $threshold Oldest allowed timestamp.
	 * @return bool
	 */
	private function record_is_candidate( array $record, $threshold ) {
		$status = isset( $record['remote_status'] ) ? (string) $record['remote_status'] : 'uploaded';

		if ( in_array( $status, array( 'trashed', 'deleted', 'trash_failed' ), true ) ) {
			return false;
		}

		return $this->file_entry_id( $record ) > 0
			&& ! empty( $record['uploaded_at'] )
			&& absint( $record['uploaded_at'] ) <= $threshold;
	}

	/**
	 * Extracts a Drime file entry ID from an uploaded record.
	 *
	 * @param array<string,mixed> $record Record.
	 * @return int
	 */
	private function file_entry_id( array $record ) {
		if ( empty( $record['drime']['fileEntry'] ) || ! is_array( $record['drime']['fileEntry'] ) || empty( $record['drime']['fileEntry']['id'] ) ) {
			return 0;
		}

		return absint( $record['drime']['fileEntry']['id'] );
	}

	/**
	 * Returns the number of seconds in a day.
	 *
	 * @return int
	 */
	private function day_in_seconds() {
		return defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
	}
}
