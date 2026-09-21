<?php
/**
 * Remote action verifier body parsing helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.12
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remote action verifier body parsing helpers.
 *
 * @since 0.5.12
 */
trait Alynt_Drime_Backups_Uploader_Remote_Action_Verifier_Body_Parsing {
	/**
	 * Parses and validates a bounded JSON body.
	 *
	 * @param string $raw_body Raw body.
	 * @return array<string,mixed>|WP_Error
	 */
	private function parse_body( $raw_body ) {
		$raw_body = (string) $raw_body;
		if ( '' === $raw_body || strlen( $raw_body ) > 8192 ) {
			return $this->error( 'action_body_invalid', __( 'The remote action body is invalid.', 'alynt-drime-backups-uploader' ), 400 );
		}

		$body = json_decode( $raw_body, true );
		if ( ! is_array( $body ) ) {
			return $this->error( 'action_body_invalid', __( 'The remote action body is not valid JSON.', 'alynt-drime-backups-uploader' ), 400 );
		}

		$allowed = array( 'protocol_version', 'action_id', 'dashboard_site_public_id', 'site_uuid', 'action_type', 'requested_at', 'expires_at', 'idempotency_key', 'schedule_preview', 'schedule_apply', 'schedule_rollback_preview' );
		$extra   = array_diff( array_keys( $body ), $allowed );
		if ( ! empty( $extra ) ) {
			return $this->error( 'action_body_keys_invalid', __( 'The remote action body contains unsupported fields.', 'alynt-drime-backups-uploader' ), 400 );
		}

		$parsed = array(
			'protocol_version'         => absint( isset( $body['protocol_version'] ) ? $body['protocol_version'] : 0 ),
			'action_id'                => isset( $body['action_id'] ) ? $this->sanitize_uuid( (string) $body['action_id'] ) : '',
			'dashboard_site_public_id' => isset( $body['dashboard_site_public_id'] ) ? $this->sanitize_identifier( (string) $body['dashboard_site_public_id'] ) : '',
			'site_uuid'                => isset( $body['site_uuid'] ) ? $this->sanitize_uuid( (string) $body['site_uuid'] ) : '',
			'action_type'              => isset( $body['action_type'] ) ? sanitize_key( (string) $body['action_type'] ) : '',
			'requested_at'             => isset( $body['requested_at'] ) ? sanitize_text_field( (string) $body['requested_at'] ) : '',
			'expires_at'               => isset( $body['expires_at'] ) ? sanitize_text_field( (string) $body['expires_at'] ) : '',
			'idempotency_key'          => isset( $body['idempotency_key'] ) ? $this->sanitize_identifier( (string) $body['idempotency_key'] ) : '',
		);

		$supported_action_types = array(
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::ACTION_SCAN_UPLOAD_NOW,
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::ACTION_SCHEDULE_PREVIEW,
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::ACTION_SCHEDULE_APPLY,
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::ACTION_SCHEDULE_ROLLBACK_PREVIEW,
		);

		if (
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::ACTION_PROTOCOL_VERSION !== $parsed['protocol_version']
			|| '' === $parsed['action_id']
			|| '' === $parsed['dashboard_site_public_id']
			|| '' === $parsed['site_uuid']
			|| ! in_array( $parsed['action_type'], $supported_action_types, true )
			|| '' === $parsed['idempotency_key']
			|| false === strtotime( $parsed['requested_at'] )
			|| false === strtotime( $parsed['expires_at'] )
		) {
			return $this->error( 'action_intent_invalid', __( 'The remote action intent is not supported by this client site.', 'alynt-drime-backups-uploader' ), 400 );
		}

		if ( Alynt_Drime_Backups_Uploader_Dashboard_Connection::ACTION_SCHEDULE_PREVIEW === $parsed['action_type'] ) {
			if ( empty( $body['schedule_preview'] ) || ! is_array( $body['schedule_preview'] ) ) {
				return $this->error( 'action_schedule_preview_invalid', __( 'The schedule preview request is invalid.', 'alynt-drime-backups-uploader' ), 400 );
			}

			$schedule_preview = $this->parse_schedule_preview( $body['schedule_preview'] );
			if ( is_wp_error( $schedule_preview ) ) {
				return $schedule_preview;
			}

			$parsed['schedule_preview'] = $schedule_preview;
		} elseif ( Alynt_Drime_Backups_Uploader_Dashboard_Connection::ACTION_SCHEDULE_APPLY === $parsed['action_type'] ) {
			if ( ! $this->connection->is_schedule_mutation_enabled() ) {
				return $this->error( 'schedule_apply_unavailable', __( 'Schedule apply is not enabled on this client site.', 'alynt-drime-backups-uploader' ), 403 );
			}

			if ( empty( $body['schedule_apply'] ) || ! is_array( $body['schedule_apply'] ) ) {
				return $this->error( 'action_schedule_apply_invalid', __( 'The schedule apply request is invalid.', 'alynt-drime-backups-uploader' ), 400 );
			}

			$schedule_apply = $this->parse_schedule_apply( $body['schedule_apply'] );
			if ( is_wp_error( $schedule_apply ) ) {
				return $schedule_apply;
			}

			$parsed['schedule_apply'] = $schedule_apply;
		} elseif ( Alynt_Drime_Backups_Uploader_Dashboard_Connection::ACTION_SCHEDULE_ROLLBACK_PREVIEW === $parsed['action_type'] ) {
			if ( ! $this->connection->is_schedule_rollback_preview_enabled() ) {
				return $this->error( 'schedule_rollback_preview_unavailable', __( 'Schedule rollback preview is not enabled on this client site.', 'alynt-drime-backups-uploader' ), 403 );
			}

			if ( empty( $body['schedule_rollback_preview'] ) || ! is_array( $body['schedule_rollback_preview'] ) ) {
				return $this->error( 'action_schedule_rollback_preview_invalid', __( 'The schedule rollback preview request is invalid.', 'alynt-drime-backups-uploader' ), 400 );
			}

			$schedule_rollback_preview = $this->parse_schedule_rollback_preview( $body['schedule_rollback_preview'] );
			if ( is_wp_error( $schedule_rollback_preview ) ) {
				return $schedule_rollback_preview;
			}

			$parsed['schedule_rollback_preview'] = $schedule_rollback_preview;
		} elseif ( array_key_exists( 'schedule_preview', $body ) ) {
			return $this->error( 'action_body_keys_invalid', __( 'The remote action body contains unsupported fields.', 'alynt-drime-backups-uploader' ), 400 );
		} elseif ( array_key_exists( 'schedule_apply', $body ) ) {
			return $this->error( 'action_body_keys_invalid', __( 'The remote action body contains unsupported fields.', 'alynt-drime-backups-uploader' ), 400 );
		} elseif ( array_key_exists( 'schedule_rollback_preview', $body ) ) {
			return $this->error( 'action_body_keys_invalid', __( 'The remote action body contains unsupported fields.', 'alynt-drime-backups-uploader' ), 400 );
		}

		return $parsed;
	}

	/**
	 * Parses and validates the bounded schedule preview request.
	 *
	 * @param array<string,mixed> $preview Preview request.
	 * @return array<string,mixed>|WP_Error
	 */
	private function parse_schedule_preview( array $preview ) {
		$allowed = array( 'schedule_id', 'proposed_cadence', 'capability_version' );
		$extra   = array_diff( array_keys( $preview ), $allowed );
		if ( ! empty( $extra ) ) {
			return $this->error( 'action_schedule_preview_keys_invalid', __( 'The schedule preview request contains unsupported fields.', 'alynt-drime-backups-uploader' ), 400 );
		}

		$parsed = array(
			'schedule_id'        => isset( $preview['schedule_id'] ) ? sanitize_key( (string) $preview['schedule_id'] ) : '',
			'proposed_cadence'   => isset( $preview['proposed_cadence'] ) ? sanitize_key( (string) $preview['proposed_cadence'] ) : '',
			'capability_version' => isset( $preview['capability_version'] ) ? absint( $preview['capability_version'] ) : 0,
		);

		if (
			'alynt_scan_upload' !== $parsed['schedule_id']
			|| 1 !== $parsed['capability_version']
			|| ! in_array( $parsed['proposed_cadence'], array( 'every_15_minutes', 'every_30_minutes', 'hourly' ), true )
		) {
			return $this->error( 'action_schedule_preview_invalid', __( 'The schedule preview request is invalid.', 'alynt-drime-backups-uploader' ), 400 );
		}

		return $parsed;
	}

	/**
	 * Parses and validates the bounded schedule apply request.
	 *
	 * @since 0.5.19
	 *
	 * @param array<string,mixed> $apply Apply request.
	 * @return array<string,mixed>|WP_Error
	 */
	private function parse_schedule_apply( array $apply ) {
		$allowed = array( 'schedule_id', 'proposed_cadence', 'capability_version', 'preview_action_id', 'preview_fingerprint' );
		$extra   = array_diff( array_keys( $apply ), $allowed );
		if ( ! empty( $extra ) ) {
			return $this->error( 'action_schedule_apply_keys_invalid', __( 'The schedule apply request contains unsupported fields.', 'alynt-drime-backups-uploader' ), 400 );
		}

		$parsed = array(
			'schedule_id'         => isset( $apply['schedule_id'] ) ? sanitize_key( (string) $apply['schedule_id'] ) : '',
			'proposed_cadence'    => isset( $apply['proposed_cadence'] ) ? sanitize_key( (string) $apply['proposed_cadence'] ) : '',
			'capability_version'  => isset( $apply['capability_version'] ) ? absint( $apply['capability_version'] ) : 0,
			'preview_action_id'   => isset( $apply['preview_action_id'] ) ? $this->sanitize_uuid( (string) $apply['preview_action_id'] ) : '',
			'preview_fingerprint' => isset( $apply['preview_fingerprint'] ) ? $this->sanitize_hash( (string) $apply['preview_fingerprint'] ) : '',
		);

		if (
			'alynt_scan_upload' !== $parsed['schedule_id']
			|| 1 !== $parsed['capability_version']
			|| ! in_array( $parsed['proposed_cadence'], array( 'every_15_minutes', 'every_30_minutes', 'hourly' ), true )
			|| '' === $parsed['preview_action_id']
			|| '' === $parsed['preview_fingerprint']
		) {
			return $this->error( 'action_schedule_apply_invalid', __( 'The schedule apply request is invalid.', 'alynt-drime-backups-uploader' ), 400 );
		}

		return $parsed;
	}

	/**
	 * Parses and validates the bounded schedule rollback preview request.
	 *
	 * @since 0.5.21
	 *
	 * @param array<string,mixed> $preview Rollback preview request.
	 * @return array<string,mixed>|WP_Error
	 */
	private function parse_schedule_rollback_preview( array $preview ) {
		$allowed = array( 'schedule_id', 'source_apply_action_id', 'rollback_metadata_fingerprint', 'capability_version' );
		$extra   = array_diff( array_keys( $preview ), $allowed );
		if ( ! empty( $extra ) ) {
			return $this->error( 'action_schedule_rollback_preview_keys_invalid', __( 'The schedule rollback preview request contains unsupported fields.', 'alynt-drime-backups-uploader' ), 400 );
		}

		$parsed = array(
			'schedule_id'                   => isset( $preview['schedule_id'] ) ? sanitize_key( (string) $preview['schedule_id'] ) : '',
			'source_apply_action_id'        => isset( $preview['source_apply_action_id'] ) ? $this->sanitize_uuid( (string) $preview['source_apply_action_id'] ) : '',
			'rollback_metadata_fingerprint' => isset( $preview['rollback_metadata_fingerprint'] ) ? $this->sanitize_hash( (string) $preview['rollback_metadata_fingerprint'] ) : '',
			'capability_version'            => isset( $preview['capability_version'] ) ? absint( $preview['capability_version'] ) : 0,
		);

		if (
			'alynt_scan_upload' !== $parsed['schedule_id']
			|| 1 !== $parsed['capability_version']
			|| '' === $parsed['source_apply_action_id']
			|| '' === $parsed['rollback_metadata_fingerprint']
		) {
			return $this->error( 'action_schedule_rollback_preview_invalid', __( 'The schedule rollback preview request is invalid.', 'alynt-drime-backups-uploader' ), 400 );
		}

		return $parsed;
	}
}
