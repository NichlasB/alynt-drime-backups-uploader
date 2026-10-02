<?php
/**
 * Health summary restore-readiness evidence helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds support-safe restore-readiness evidence for dashboard display.
 *
 * @since 0.5.25
 */
trait Alynt_Drime_Backups_Uploader_Health_Summary_Restore_Readiness {
	/**
	 * Builds optional restore-readiness evidence.
	 *
	 * @param array<string,mixed> $uploaded Uploaded registry records.
	 * @return array<string,mixed>
	 */
	private function restore_readiness( array $uploaded ) {
		$candidates = array();

		foreach (
			array(
				'server'  => 'generic_outbox',
				'wpvivid' => 'wpvivid',
			) as $source => $producer
		) {
			$candidate = $this->restore_readiness_candidate( $source, $producer, $uploaded );
			if ( ! empty( $candidate ) ) {
				$candidates[] = $candidate;
			}
		}

		if ( empty( $candidates ) ) {
			return array();
		}

		return array(
			'schema_version' => 1,
			'generated_at'   => gmdate( 'c' ),
			'overall_state'  => $this->restore_readiness_overall_state( $candidates ),
			'candidates'     => $candidates,
		);
	}

	/**
	 * Builds one source-level restore-readiness candidate.
	 *
	 * @param string              $source Source key.
	 * @param string              $producer Producer key.
	 * @param array<string,mixed> $uploaded Uploaded registry records.
	 * @return array<string,mixed>
	 */
	private function restore_readiness_candidate( $source, $producer, array $uploaded ) {
		$records = $this->currently_uploaded_records( $this->records_for_producer( $uploaded, $producer ) );
		$latest  = $this->latest_record( $records );

		if ( empty( $latest ) ) {
			return array();
		}

		if ( 'server' === $source ) {
			return $this->server_restore_readiness_candidate( $latest );
		}

		return $this->wpvivid_restore_readiness_candidate( $latest );
	}

	/**
	 * Builds restore-readiness evidence for server/generic-outbox packages.
	 *
	 * @param array<string,mixed> $record Latest source record.
	 * @return array<string,mixed>
	 */
	private function server_restore_readiness_candidate( array $record ) {
		$created_at  = $this->record_timestamp( $record, 'created_at' );
		$uploaded_at = $this->record_timestamp( $record, 'uploaded_at' );
		$finished_at = $created_at > 0 ? $created_at : $uploaded_at;
		$generic     = $this->generic_outbox_metadata( $record );
		$warnings    = array();

		$has_manifest = isset( $generic['manifest'] ) && is_array( $generic['manifest'] ) && ! empty( $generic['manifest'] );
		$has_checksum = isset( $record['checksum_algorithm'], $record['checksum_value'] )
			&& 'sha256' === sanitize_key( (string) $record['checksum_algorithm'] )
			&& preg_match( '/^[a-f0-9]{64}$/', strtolower( (string) $record['checksum_value'] ) );
		$has_sidecar  = $this->has_generic_remote_sidecar( $generic );

		if ( ! $has_manifest ) {
			$warnings[] = 'manifest_not_reported';
		}

		if ( ! $has_checksum ) {
			$warnings[] = 'checksum_not_reported';
		}

		if ( ! $has_sidecar ) {
			$warnings[] = 'sidecar_missing';
		}

		if ( $finished_at <= 0 ) {
			$warnings[] = 'restore_evidence_incomplete';
		}

		return array(
			'source'                    => 'server',
			'candidate_ref'             => $this->restore_candidate_ref( 'server', $record ),
			'latest_backup_finished_at' => $finished_at > 0 ? gmdate( 'c', $finished_at ) : '',
			'component_state'           => $has_manifest && $has_checksum && $has_sidecar ? 'complete' : 'partial',
			'checksum_state'            => $has_checksum ? 'verified' : 'not_reported',
			'manifest_state'            => $has_manifest ? 'compatible' : 'not_reported',
			'sidecar_state'             => $has_sidecar ? 'present' : 'missing',
			'age_seconds'               => $finished_at > 0 ? max( 0, time() - $finished_at ) : 0,
			'warnings'                  => $warnings,
		);
	}

	/**
	 * Builds conservative restore-readiness evidence for WPvivid packages.
	 *
	 * @param array<string,mixed> $record Latest source record.
	 * @return array<string,mixed>
	 */
	private function wpvivid_restore_readiness_candidate( array $record ) {
		$created_at  = $this->record_timestamp( $record, 'created_at' );
		$uploaded_at = $this->record_timestamp( $record, 'uploaded_at' );
		$finished_at = $created_at > 0 ? $created_at : $uploaded_at;

		return array(
			'source'                    => 'wpvivid',
			'candidate_ref'             => $this->restore_candidate_ref( 'wpvivid', $record ),
			'latest_backup_finished_at' => $finished_at > 0 ? gmdate( 'c', $finished_at ) : '',
			'component_state'           => 'unknown',
			'checksum_state'            => 'not_reported',
			'manifest_state'            => 'not_reported',
			'sidecar_state'             => 'not_reported',
			'age_seconds'               => $finished_at > 0 ? max( 0, time() - $finished_at ) : 0,
			'warnings'                  => array( 'restore_evidence_incomplete', 'checksum_not_reported', 'manifest_not_reported' ),
		);
	}

	/**
	 * Returns generic outbox metadata from a registry record.
	 *
	 * @param array<string,mixed> $record Registry record.
	 * @return array<string,mixed>
	 */
	private function generic_outbox_metadata( array $record ) {
		$metadata = isset( $record['metadata'] ) && is_array( $record['metadata'] ) ? $record['metadata'] : array();

		return isset( $metadata['generic_outbox'] ) && is_array( $metadata['generic_outbox'] ) ? $metadata['generic_outbox'] : array();
	}

	/**
	 * Returns whether a generic outbox record has support-safe sidecar evidence.
	 *
	 * @param array<string,mixed> $generic Generic outbox metadata.
	 * @return bool
	 */
	private function has_generic_remote_sidecar( array $generic ) {
		foreach ( array( 'remote_catalog', 'remote_index' ) as $key ) {
			if ( ! isset( $generic[ $key ] ) || ! is_array( $generic[ $key ] ) ) {
				continue;
			}

			$count = isset( $generic[ $key ]['package_count'] ) ? absint( $generic[ $key ]['package_count'] ) : 0;
			if ( $count > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Builds a bounded opaque candidate reference.
	 *
	 * @param string              $source Source key.
	 * @param array<string,mixed> $record Registry record.
	 * @return string
	 */
	private function restore_candidate_ref( $source, array $record ) {
		$basis   = array(
			'source'      => sanitize_key( $source ),
			'producer'    => isset( $record['producer_key'] ) ? sanitize_key( (string) $record['producer_key'] ) : '',
			'created_at'  => $this->record_timestamp( $record, 'created_at' ),
			'uploaded_at' => $this->record_timestamp( $record, 'uploaded_at' ),
			'status'      => isset( $record['remote_status'] ) ? sanitize_key( (string) $record['remote_status'] ) : '',
		);
		$encoded = json_encode( $basis );
		$hash    = substr( hash( 'sha256', false === $encoded ? sanitize_key( $source ) : $encoded ), 0, 24 );

		return sanitize_key( $source ) . '-' . $hash;
	}

	/**
	 * Aggregates candidate states into a top-level readiness state.
	 *
	 * @param array<int,array<string,mixed>> $candidates Candidates.
	 * @return string
	 */
	private function restore_readiness_overall_state( array $candidates ) {
		$has_available  = false;
		$has_incomplete = false;

		foreach ( $candidates as $candidate ) {
			if ( isset( $candidate['component_state'], $candidate['checksum_state'], $candidate['manifest_state'], $candidate['sidecar_state'] )
				&& 'complete' === $candidate['component_state']
				&& 'verified' === $candidate['checksum_state']
				&& 'compatible' === $candidate['manifest_state']
				&& 'present' === $candidate['sidecar_state']
			) {
				$has_available = true;
				continue;
			}

			$has_incomplete = true;
		}

		if ( $has_available ) {
			return $has_incomplete ? 'incomplete' : 'evidence_available';
		}

		return $has_incomplete ? 'incomplete' : 'unknown';
	}
}
