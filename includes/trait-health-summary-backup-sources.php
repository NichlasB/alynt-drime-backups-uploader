<?php
/**
 * Health summary backup-source helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.22
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds redacted per-source backup evidence summaries.
 *
 * @since 0.5.22
 */
trait Alynt_Drime_Backups_Uploader_Health_Summary_Backup_Sources {
	/**
	 * Builds redacted per-source backup freshness evidence.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param array<string,mixed> $queued Queue records.
	 * @param array<string,mixed> $uploaded Uploaded registry records.
	 * @param array<string,mixed> $failed Failed registry records.
	 * @return array<string,array<string,mixed>>
	 */
	private function backup_sources( array $settings, array $queued, array $uploaded, array $failed ) {
		return array(
			'server'  => $this->backup_source_summary(
				'server',
				__( 'Server runner / generic outbox', 'alynt-drime-backups-uploader' ),
				'generic_outbox',
				! empty( $settings['server_outbox_path'] ),
				$settings,
				$queued,
				$uploaded,
				$failed
			),
			'wpvivid' => $this->backup_source_summary(
				'wpvivid',
				__( 'WPvivid', 'alynt-drime-backups-uploader' ),
				'wpvivid',
				! empty( $settings['backup_path_override'] ) || ! empty( $settings['wpvivid_relative_path'] ),
				$settings,
				$queued,
				$uploaded,
				$failed
			),
		);
	}

	/**
	 * Builds one redacted source summary from local registry evidence.
	 *
	 * @param string              $source_key Source key.
	 * @param string              $source_label Source label.
	 * @param string              $producer_key Producer key.
	 * @param bool                $configured Whether the source appears configured.
	 * @param array<string,mixed> $settings Settings.
	 * @param array<string,mixed> $queued Queue records.
	 * @param array<string,mixed> $uploaded Uploaded registry records.
	 * @param array<string,mixed> $failed Failed registry records.
	 * @return array<string,mixed>
	 */
	private function backup_source_summary( $source_key, $source_label, $producer_key, $configured, array $settings, array $queued, array $uploaded, array $failed ) {
		$source_uploaded = $this->records_for_producer( $uploaded, $producer_key );
		$source_failed   = $this->records_for_producer( $failed, $producer_key );
		$source_queued   = $this->records_for_producer( $queued, $producer_key );
		$source_remote   = $this->currently_uploaded_records( $source_uploaded );
		$latest          = $this->latest_record( $source_remote );
		$latest_created  = $this->record_timestamp( $latest, 'created_at' );
		$latest_uploaded = $this->record_timestamp( $latest, 'uploaded_at' );
		$remote_count    = count( $source_remote );
		$inventory_count = $this->latest_inventory_count( $latest, $remote_count );
		$freshness       = $this->freshness_status( (bool) $configured, $latest_uploaded );
		$warnings        = $this->source_warnings( (bool) $configured, count( $source_queued ), $freshness );
		$activity        = $this->source_activity_evidence( (string) $source_key, $settings );

		$summary = array(
			'source_key'                         => sanitize_key( $source_key ),
			'source_label'                       => sanitize_text_field( $source_label ),
			'configured'                         => (bool) $configured,
			'has_upload_evidence'                => ! empty( $source_uploaded ),
			'queued_count'                       => count( $source_queued ),
			'uploaded_count'                     => count( $source_uploaded ),
			'failed_count'                       => count( $source_failed ),
			'remote_registry_count'              => $remote_count,
			'latest_created_at'                  => $latest_created,
			'latest_uploaded_at'                 => $latest_uploaded,
			'latest_upload_age_seconds'          => $latest_uploaded > 0 ? max( 0, time() - $latest_uploaded ) : 0,
			'latest_remote_status'               => isset( $latest['remote_status'] ) ? sanitize_key( (string) $latest['remote_status'] ) : '',
			'latest_inventory_count'             => $inventory_count,
			'latest_inventory_evidence'          => $this->latest_inventory_evidence( $latest, $inventory_count, $remote_count ),
			'latest_source_activity_at'          => $activity['latest_source_activity_at'],
			'latest_source_activity_age_seconds' => $activity['latest_source_activity_at'] > 0 ? max( 0, time() - $activity['latest_source_activity_at'] ) : 0,
			'source_activity_evidence'           => $activity['source_activity_evidence'],
			'local_candidate_count'              => $activity['local_candidate_count'],
			'freshness_status'                   => $freshness,
			'freshness_window_seconds'           => self::BACKUP_FRESHNESS_WINDOW_SECONDS,
			'warning_count'                      => count( $warnings ),
			'warnings'                           => $warnings,
		);

		if ( 'wpvivid' === $source_key ) {
			$summary['schedule_policy'] = $this->wpvivid_schedule_policy();
		}

		return $summary;
	}

	/**
	 * Filters records to one producer.
	 *
	 * @param array<string,mixed> $records Records.
	 * @param string              $producer_key Producer key.
	 * @return array<int,array<string,mixed>>
	 */
	private function records_for_producer( array $records, $producer_key ) {
		$filtered = array();

		foreach ( $records as $record ) {
			if ( ! is_array( $record ) || ! isset( $record['producer_key'] ) || (string) $record['producer_key'] !== (string) $producer_key ) {
				continue;
			}

			$filtered[] = $record;
		}

		return $filtered;
	}

	/**
	 * Returns records still marked as uploaded remotely.
	 *
	 * @param array<int,array<string,mixed>> $records Records.
	 * @return array<int,array<string,mixed>>
	 */
	private function currently_uploaded_records( array $records ) {
		$uploaded = array();

		foreach ( $records as $record ) {
			$status = isset( $record['remote_status'] ) ? sanitize_key( (string) $record['remote_status'] ) : 'uploaded';
			if ( 'uploaded' === $status ) {
				$uploaded[] = $record;
			}
		}

		return $uploaded;
	}

	/**
	 * Finds the newest uploaded record.
	 *
	 * @param array<int,array<string,mixed>> $records Records.
	 * @return array<string,mixed>
	 */
	private function latest_record( array $records ) {
		$latest      = array();
		$latest_time = 0;

		foreach ( $records as $record ) {
			$uploaded_at = $this->record_timestamp( $record, 'uploaded_at' );
			$created_at  = $this->record_timestamp( $record, 'created_at' );
			$sort_time   = max( $uploaded_at, $created_at );

			if ( $sort_time >= $latest_time ) {
				$latest      = $record;
				$latest_time = $sort_time;
			}
		}

		return $latest;
	}

	/**
	 * Reads a non-negative timestamp from a record.
	 *
	 * @param array<string,mixed> $record Record.
	 * @param string              $key Key.
	 * @return int
	 */
	private function record_timestamp( array $record, $key ) {
		return isset( $record[ $key ] ) ? max( 0, absint( $record[ $key ] ) ) : 0;
	}

	/**
	 * Returns the latest remote inventory count evidence.
	 *
	 * @param array<string,mixed> $record Latest record.
	 * @param int                 $remote_registry_count Remote registry count.
	 * @return int
	 */
	private function latest_inventory_count( array $record, $remote_registry_count ) {
		$metadata = isset( $record['metadata'] ) && is_array( $record['metadata'] ) ? $record['metadata'] : array();
		$generic  = isset( $metadata['generic_outbox'] ) && is_array( $metadata['generic_outbox'] ) ? $metadata['generic_outbox'] : array();

		foreach ( array( 'remote_catalog', 'remote_index' ) as $key ) {
			if ( isset( $generic[ $key ] ) && is_array( $generic[ $key ] ) && isset( $generic[ $key ]['package_count'] ) ) {
				return max( 0, absint( $generic[ $key ]['package_count'] ) );
			}
		}

		return max( 0, (int) $remote_registry_count );
	}

	/**
	 * Returns a compact inventory evidence label.
	 *
	 * @param array<string,mixed> $record Latest record.
	 * @param int                 $inventory_count Inventory count.
	 * @param int                 $remote_registry_count Remote registry count.
	 * @return string
	 */
	private function latest_inventory_evidence( array $record, $inventory_count, $remote_registry_count ) {
		$metadata = isset( $record['metadata'] ) && is_array( $record['metadata'] ) ? $record['metadata'] : array();
		$generic  = isset( $metadata['generic_outbox'] ) && is_array( $metadata['generic_outbox'] ) ? $metadata['generic_outbox'] : array();

		if ( isset( $generic['remote_catalog'] ) && is_array( $generic['remote_catalog'] ) && isset( $generic['remote_catalog']['package_count'] ) ) {
			return 'generic_outbox_remote_catalog';
		}

		if ( isset( $generic['remote_index'] ) && is_array( $generic['remote_index'] ) && isset( $generic['remote_index']['package_count'] ) ) {
			return 'generic_outbox_remote_index';
		}

		if ( $inventory_count > 0 || $remote_registry_count > 0 ) {
			return 'local_upload_registry';
		}

		return '';
	}

	/**
	 * Classifies latest source freshness using a conservative default window.
	 *
	 * @param bool $configured Whether the source appears configured.
	 * @param int  $latest_uploaded_at Latest upload timestamp.
	 * @return string
	 */
	private function freshness_status( $configured, $latest_uploaded_at ) {
		if ( ! $configured && $latest_uploaded_at <= 0 ) {
			return 'not_configured';
		}

		if ( $latest_uploaded_at <= 0 ) {
			return 'no_upload_evidence';
		}

		return ( time() - $latest_uploaded_at ) > self::BACKUP_FRESHNESS_WINDOW_SECONDS ? 'stale' : 'fresh';
	}

	/**
	 * Builds source-specific warning records.
	 *
	 * @param bool   $configured Whether the source appears configured.
	 * @param int    $queued_count Queued source packages.
	 * @param string $freshness Freshness status.
	 * @return array<int,array<string,string>>
	 */
	private function source_warnings( $configured, $queued_count, $freshness ) {
		$warnings = array();

		if ( $configured && 'no_upload_evidence' === $freshness ) {
			$warnings[] = array(
				'code'    => 'source_no_upload_evidence',
				'message' => __( 'This source is configured but has no uploaded backup evidence yet.', 'alynt-drime-backups-uploader' ),
			);
		}

		if ( 'stale' === $freshness ) {
			$warnings[] = array(
				'code'    => 'source_latest_upload_stale',
				'message' => __( 'The latest uploaded backup evidence is older than the default freshness window.', 'alynt-drime-backups-uploader' ),
			);
		}

		if ( $queued_count > 0 ) {
			$warnings[] = array(
				'code'    => 'source_queue_not_empty',
				'message' => __( 'This source has queued backup packages waiting to upload.', 'alynt-drime-backups-uploader' ),
			);
		}

		return $warnings;
	}
}
