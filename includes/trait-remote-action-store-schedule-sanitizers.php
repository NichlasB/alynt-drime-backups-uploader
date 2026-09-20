<?php
/**
 * Remote action store schedule sanitization helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.22
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitizes bounded, redacted remote-action schedule details.
 *
 * @since 0.5.22
 */
trait Alynt_Drime_Backups_Uploader_Remote_Action_Store_Schedule_Sanitizers {
	/**
	 * Sanitizes schedule preview details for status-safe action history.
	 *
	 * @param array<string,mixed> $preview Preview details.
	 * @return array<string,mixed>
	 */
	private function safe_schedule_preview( array $preview ) {
		if ( empty( $preview ) ) {
			return array();
		}

		$clean = array(
			'preview_action_id'             => isset( $preview['preview_action_id'] ) ? $this->sanitize_uuid( (string) $preview['preview_action_id'] ) : '',
			'preview_fingerprint'           => isset( $preview['preview_fingerprint'] ) ? $this->sanitize_hash( (string) $preview['preview_fingerprint'] ) : '',
			'schedule_id'                   => isset( $preview['schedule_id'] ) ? sanitize_key( (string) $preview['schedule_id'] ) : '',
			'label'                         => isset( $preview['label'] ) ? $this->safe_summary( (string) $preview['label'] ) : '',
			'owner'                         => isset( $preview['owner'] ) ? sanitize_key( (string) $preview['owner'] ) : '',
			'capability_version'            => isset( $preview['capability_version'] ) ? absint( $preview['capability_version'] ) : 0,
			'current_cadence'               => isset( $preview['current_cadence'] ) ? sanitize_key( (string) $preview['current_cadence'] ) : '',
			'proposed_cadence'              => isset( $preview['proposed_cadence'] ) ? sanitize_key( (string) $preview['proposed_cadence'] ) : '',
			'current_next_run_at'           => isset( $preview['current_next_run_at'] ) ? sanitize_text_field( (string) $preview['current_next_run_at'] ) : '',
			'proposed_next_run_estimate_at' => isset( $preview['proposed_next_run_estimate_at'] ) ? sanitize_text_field( (string) $preview['proposed_next_run_estimate_at'] ) : '',
			'current_schedule_fingerprint'  => isset( $preview['current_schedule_fingerprint'] ) ? $this->sanitize_hash( (string) $preview['current_schedule_fingerprint'] ) : '',
			'preview_created_at'            => isset( $preview['preview_created_at'] ) ? sanitize_text_field( (string) $preview['preview_created_at'] ) : '',
			'preview_expires_at'            => isset( $preview['preview_expires_at'] ) ? sanitize_text_field( (string) $preview['preview_expires_at'] ) : '',
			'would_change'                  => ! empty( $preview['would_change'] ),
			'apply_supported'               => ! empty( $preview['apply_supported'] ),
			'rollback_supported'            => ! empty( $preview['rollback_supported'] ),
			'warnings'                      => array(),
		);

		if ( isset( $preview['warnings'] ) && is_array( $preview['warnings'] ) ) {
			foreach ( $preview['warnings'] as $warning ) {
				$warning = sanitize_key( (string) $warning );
				if ( '' !== $warning ) {
					$clean['warnings'][] = $warning;
				}
			}
			$clean['warnings'] = array_values( array_unique( $clean['warnings'] ) );
		}

		return $clean;
	}

	/**
	 * Sanitizes schedule apply details for status-safe action history.
	 *
	 * @param array<string,mixed> $apply Apply details.
	 * @return array<string,mixed>
	 */
	private function safe_schedule_apply( array $apply ) {
		if ( empty( $apply ) ) {
			return array();
		}

		$clean = array(
			'schedule_id'          => isset( $apply['schedule_id'] ) ? sanitize_key( (string) $apply['schedule_id'] ) : '',
			'label'                => isset( $apply['label'] ) ? $this->safe_summary( (string) $apply['label'] ) : '',
			'owner'                => isset( $apply['owner'] ) ? sanitize_key( (string) $apply['owner'] ) : '',
			'capability_version'   => isset( $apply['capability_version'] ) ? absint( $apply['capability_version'] ) : 0,
			'preview_action_id'    => isset( $apply['preview_action_id'] ) ? $this->sanitize_uuid( (string) $apply['preview_action_id'] ) : '',
			'preview_fingerprint'  => isset( $apply['preview_fingerprint'] ) ? $this->sanitize_hash( (string) $apply['preview_fingerprint'] ) : '',
			'proposed_cadence'     => isset( $apply['proposed_cadence'] ) ? sanitize_key( (string) $apply['proposed_cadence'] ) : '',
			'previous_cadence'     => isset( $apply['previous_cadence'] ) ? sanitize_key( (string) $apply['previous_cadence'] ) : '',
			'applied_cadence'      => isset( $apply['applied_cadence'] ) ? sanitize_key( (string) $apply['applied_cadence'] ) : '',
			'previous_next_run_at' => isset( $apply['previous_next_run_at'] ) ? sanitize_text_field( (string) $apply['previous_next_run_at'] ) : '',
			'applied_next_run_at'  => isset( $apply['applied_next_run_at'] ) ? sanitize_text_field( (string) $apply['applied_next_run_at'] ) : '',
			'changed'              => ! empty( $apply['changed'] ),
			'rollback_available'   => false,
			'rollback_expires_at'  => isset( $apply['rollback_expires_at'] ) ? sanitize_text_field( (string) $apply['rollback_expires_at'] ) : '',
		);

		if ( isset( $apply['rollback_metadata'] ) && is_array( $apply['rollback_metadata'] ) ) {
			$clean['rollback_metadata'] = $this->safe_schedule_rollback_metadata( $apply['rollback_metadata'] );
		}

		return $clean;
	}

	/**
	 * Sanitizes schedule rollback metadata for evidence-only status reporting.
	 *
	 * @since 0.5.20
	 *
	 * @param array<string,mixed> $metadata Rollback metadata.
	 * @return array<string,mixed>
	 */
	private function safe_schedule_rollback_metadata( array $metadata ) {
		if ( empty( $metadata ) ) {
			return array();
		}

		return array(
			'captured'                            => ! empty( $metadata['captured'] ),
			'available'                           => false,
			'reason'                              => isset( $metadata['reason'] ) ? sanitize_key( (string) $metadata['reason'] ) : '',
			'source_action_id'                    => isset( $metadata['source_action_id'] ) ? $this->sanitize_uuid( (string) $metadata['source_action_id'] ) : '',
			'source_preview_action_id'            => isset( $metadata['source_preview_action_id'] ) ? $this->sanitize_uuid( (string) $metadata['source_preview_action_id'] ) : '',
			'schedule_id'                         => isset( $metadata['schedule_id'] ) ? sanitize_key( (string) $metadata['schedule_id'] ) : '',
			'owner'                               => isset( $metadata['owner'] ) ? sanitize_key( (string) $metadata['owner'] ) : '',
			'previous_cadence'                    => isset( $metadata['previous_cadence'] ) ? sanitize_key( (string) $metadata['previous_cadence'] ) : '',
			'applied_cadence'                     => isset( $metadata['applied_cadence'] ) ? sanitize_key( (string) $metadata['applied_cadence'] ) : '',
			'previous_next_run_at'                => isset( $metadata['previous_next_run_at'] ) ? sanitize_text_field( (string) $metadata['previous_next_run_at'] ) : '',
			'applied_next_run_at'                 => isset( $metadata['applied_next_run_at'] ) ? sanitize_text_field( (string) $metadata['applied_next_run_at'] ) : '',
			'current_schedule_fingerprint_before' => isset( $metadata['current_schedule_fingerprint_before'] ) ? $this->sanitize_hash( (string) $metadata['current_schedule_fingerprint_before'] ) : '',
			'current_schedule_fingerprint_after'  => isset( $metadata['current_schedule_fingerprint_after'] ) ? $this->sanitize_hash( (string) $metadata['current_schedule_fingerprint_after'] ) : '',
			'rollback_metadata_fingerprint'       => isset( $metadata['rollback_metadata_fingerprint'] ) ? $this->sanitize_hash( (string) $metadata['rollback_metadata_fingerprint'] ) : '',
			'captured_at'                         => isset( $metadata['captured_at'] ) ? sanitize_text_field( (string) $metadata['captured_at'] ) : '',
			'expires_at'                          => isset( $metadata['expires_at'] ) ? sanitize_text_field( (string) $metadata['expires_at'] ) : '',
		);
	}

	/**
	 * Sanitizes schedule rollback preview details for status-safe action history.
	 *
	 * @since 0.5.21
	 *
	 * @param array<string,mixed> $preview Rollback preview details.
	 * @return array<string,mixed>
	 */
	private function safe_schedule_rollback_preview( array $preview ) {
		if ( empty( $preview ) ) {
			return array();
		}

		$clean = array(
			'preview_action_id'                     => isset( $preview['preview_action_id'] ) ? $this->sanitize_uuid( (string) $preview['preview_action_id'] ) : '',
			'preview_fingerprint'                   => isset( $preview['preview_fingerprint'] ) ? $this->sanitize_hash( (string) $preview['preview_fingerprint'] ) : '',
			'source_apply_action_id'                => isset( $preview['source_apply_action_id'] ) ? $this->sanitize_uuid( (string) $preview['source_apply_action_id'] ) : '',
			'rollback_metadata_fingerprint'         => isset( $preview['rollback_metadata_fingerprint'] ) ? $this->sanitize_hash( (string) $preview['rollback_metadata_fingerprint'] ) : '',
			'schedule_id'                           => isset( $preview['schedule_id'] ) ? sanitize_key( (string) $preview['schedule_id'] ) : '',
			'label'                                 => isset( $preview['label'] ) ? $this->safe_summary( (string) $preview['label'] ) : '',
			'owner'                                 => isset( $preview['owner'] ) ? sanitize_key( (string) $preview['owner'] ) : '',
			'capability_version'                    => isset( $preview['capability_version'] ) ? absint( $preview['capability_version'] ) : 0,
			'current_cadence'                       => isset( $preview['current_cadence'] ) ? sanitize_key( (string) $preview['current_cadence'] ) : '',
			'applied_cadence'                       => isset( $preview['applied_cadence'] ) ? sanitize_key( (string) $preview['applied_cadence'] ) : '',
			'rollback_cadence'                      => isset( $preview['rollback_cadence'] ) ? sanitize_key( (string) $preview['rollback_cadence'] ) : '',
			'current_next_run_at'                   => isset( $preview['current_next_run_at'] ) ? sanitize_text_field( (string) $preview['current_next_run_at'] ) : '',
			'rollback_next_run_estimate_at'         => isset( $preview['rollback_next_run_estimate_at'] ) ? sanitize_text_field( (string) $preview['rollback_next_run_estimate_at'] ) : '',
			'current_schedule_fingerprint'          => isset( $preview['current_schedule_fingerprint'] ) ? $this->sanitize_hash( (string) $preview['current_schedule_fingerprint'] ) : '',
			'expected_current_schedule_fingerprint' => isset( $preview['expected_current_schedule_fingerprint'] ) ? $this->sanitize_hash( (string) $preview['expected_current_schedule_fingerprint'] ) : '',
			'previous_schedule_fingerprint'         => isset( $preview['previous_schedule_fingerprint'] ) ? $this->sanitize_hash( (string) $preview['previous_schedule_fingerprint'] ) : '',
			'preview_created_at'                    => isset( $preview['preview_created_at'] ) ? sanitize_text_field( (string) $preview['preview_created_at'] ) : '',
			'preview_expires_at'                    => isset( $preview['preview_expires_at'] ) ? sanitize_text_field( (string) $preview['preview_expires_at'] ) : '',
			'would_change'                          => ! empty( $preview['would_change'] ),
			'rollback_apply_supported'              => false,
			'rollback_supported'                    => false,
			'warnings'                              => array(),
		);

		if ( isset( $preview['warnings'] ) && is_array( $preview['warnings'] ) ) {
			foreach ( $preview['warnings'] as $warning ) {
				$warning = sanitize_key( (string) $warning );
				if ( '' !== $warning ) {
					$clean['warnings'][] = $warning;
				}
			}
			$clean['warnings'] = array_values( array_unique( $clean['warnings'] ) );
		}

		return $clean;
	}
}
