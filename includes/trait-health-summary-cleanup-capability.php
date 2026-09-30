<?php
/**
 * Health summary cleanup capability helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.23
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds redacted cleanup-management capability summaries.
 *
 * @since 0.5.23
 */
trait Alynt_Drime_Backups_Uploader_Health_Summary_Cleanup_Capability {
	/**
	 * Builds a preview-only, redacted cleanup-management capability summary.
	 *
	 * This is intentionally capability reporting only. It must not delete
	 * files, browse paths, call Drime cleanup, or imply cleanup apply support.
	 *
	 * @param bool $remote_actions_enabled Whether V2 actions are opted in.
	 * @return array<string,mixed>
	 */
	private function cleanup_management_capability( $remote_actions_enabled ) {
		$cleanup_preview_enabled = $this->dashboard_connection instanceof Alynt_Drime_Backups_Uploader_Dashboard_Connection && $this->dashboard_connection->is_cleanup_preview_enabled();
		$enabled                 = ! empty( $remote_actions_enabled ) && ! empty( $cleanup_preview_enabled );

		return array(
			'protocol_version'           => Alynt_Drime_Backups_Uploader_Dashboard_Connection::ACTION_PROTOCOL_VERSION,
			'capability_version'         => Alynt_Drime_Backups_Uploader_Dashboard_Connection::CLEANUP_CAPABILITY_VERSION,
			'enabled'                    => (bool) $enabled,
			'preview_supported'          => (bool) $enabled,
			'apply_supported'            => false,
			'supported_categories'       => $enabled ? array( Alynt_Drime_Backups_Uploader_Dashboard_Connection::CLEANUP_CATEGORY_UPLOADER_TEMP ) : array(),
			'requires_fresh_preview'     => true,
			'max_preview_age_seconds'    => Alynt_Drime_Backups_Uploader_Dashboard_Connection::CLEANUP_PREVIEW_MAX_AGE,
			'supported_scope'            => Alynt_Drime_Backups_Uploader_Dashboard_Connection::CLEANUP_SCOPE_SAFE_LOCAL,
			'paths_exposed'              => false,
			'remote_cleanup_available'   => false,
			'cleanup_apply_available'    => false,
			'drime_cleanup_available'    => false,
			'backup_deletion_available'  => false,
			'restore_actions_available'  => false,
			'credential_actions_allowed' => false,
		);
	}
}
