<?php
/**
 * Shared production evidence, maintenance, action, and report behavior.
 *
 * @package Alynt_Drime_Backups_Uploader
 */
trait Alynt_Server_Backup_Runner_Production_Control {
	/**
	 * Adds checks for production-specific pre-restore evidence and its artifacts.
	 *
	 * @param array<int,array<string,mixed>> $checks Checks.
	 * @param array<string,mixed>            $evidence Evidence.
	 * @param string                         $evidence_path Evidence path.
	 * @param string                         $package_id Package ID.
	 * @param string                         $scope Restore scope.
	 * @param string                         $target_path Target WordPress path.
	 * @param string                         $target_host Target hostname.
	 * @param string                         $target_uuid Target UUID.
	 * @return void
	 */
	private function add_production_pre_restore_evidence_checks( array &$checks, array $evidence, $evidence_path, $package_id, $scope, $target_path, $target_host, $target_uuid ) {
		$pre_backup_path = $this->production_pre_backup_path();
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_evidence_readable', '' !== $evidence_path && is_file( $evidence_path ) && is_readable( $evidence_path ), 'Production pre-restore evidence is readable.' );
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_evidence_valid', ! empty( $evidence ), 'Production pre-restore evidence is valid JSON.' );
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_evidence_type', isset( $evidence['evidence_type'] ) && 'production_pre_restore_backup' === (string) $evidence['evidence_type'], 'Evidence type is production_pre_restore_backup.' );
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_evidence_package_id', '' !== $package_id && isset( $evidence['package_id'] ) && $package_id === (string) $evidence['package_id'], 'Evidence package ID matches the apply report.' );
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_evidence_scope', isset( $evidence['scope'] ) && $scope === (string) $evidence['scope'], 'Evidence scope matches the apply report.' );
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_evidence_target_path', isset( $evidence['target_wordpress_path'] ) && $target_path === $this->normalize_path( (string) $evidence['target_wordpress_path'] ), 'Evidence target path matches the enrolled target.' );
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_evidence_target_site', isset( $evidence['target_site'] ) && $target_host === strtolower( (string) $evidence['target_site'] ), 'Evidence target hostname matches the enrolled target.' );
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_evidence_target_uuid', isset( $evidence['target_site_uuid'] ) && $target_uuid === strtolower( (string) $evidence['target_site_uuid'] ), 'Evidence target UUID matches the enrolled target.' );
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_evidence_timestamp', $this->valid_iso_timestamp( isset( $evidence['generated_at'] ) ? (string) $evidence['generated_at'] : '' ), 'Evidence records a valid generation timestamp.' );
		$evidence_time = isset( $evidence['generated_at'] ) ? strtotime( (string) $evidence['generated_at'] ) : false;
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_evidence_fresh', false !== $evidence_time && $evidence_time <= time() + 300 && time() - $evidence_time <= $this->production_pre_backup_max_age_seconds(), 'Evidence is within the configured production pre-backup freshness window.' );

		if ( $this->restore_scope_includes_database( $scope ) ) {
			$this->add_production_pre_restore_artifact_check( $checks, $evidence, 'database_export_path', 'database_export_sha256', $pre_backup_path, 'database export' );
		}
		if ( $this->restore_scope_includes_files( $scope ) ) {
			$this->add_production_pre_restore_artifact_check( $checks, $evidence, 'file_backup_path', 'file_backup_sha256', $pre_backup_path, 'file backup' );
		}
	}

	/**
	 * Adds a contained and hash-verified production recovery artifact check.
	 *
	 * @param array<int,array<string,mixed>> $checks Checks.
	 * @param array<string,mixed>            $evidence Evidence.
	 * @param string                         $path_key Artifact path key.
	 * @param string                         $hash_key Artifact hash key.
	 * @param string                         $pre_backup_path Private evidence root.
	 * @param string                         $label Artifact label.
	 * @return void
	 */
	private function add_production_pre_restore_artifact_check( array &$checks, array $evidence, $path_key, $hash_key, $pre_backup_path, $label ) {
		$artifact_path = isset( $evidence[ $path_key ] ) ? $this->normalize_path( (string) $evidence[ $path_key ] ) : '';
		$expected_hash = isset( $evidence[ $hash_key ] ) ? strtolower( (string) $evidence[ $hash_key ] ) : '';
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_' . $path_key . '_contained', '' !== $artifact_path && $this->path_is_within_directory_canonical( $pre_backup_path, $artifact_path ), 'Production pre-restore ' . $label . ' is inside the private pre-backup path.' );
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_' . $path_key . '_readable', '' !== $artifact_path && is_file( $artifact_path ) && is_readable( $artifact_path ), 'Production pre-restore ' . $label . ' is readable.' );
		$this->add_restore_dry_run_check( $checks, 'production_pre_restore_' . $hash_key . '_valid', 1 === preg_match( '/^[a-f0-9]{64}$/', $expected_hash ) && '' !== $artifact_path && is_file( $artifact_path ) && hash_equals( $expected_hash, hash_file( 'sha256', $artifact_path ) ), 'Production pre-restore ' . $label . ' SHA-256 matches the evidence record.' );
	}

	/**
	 * Runs one fixed target-changing WP-CLI action used by restore control.
	 *
	 * @param string $target_path WordPress path.
	 * @param string $subcommand Fixed action subcommand.
	 * @return array{exit_code:int,output:array<int,string>}
	 */
	private function run_wp_cli_action( $target_path, $subcommand ) {
		$allowed = array( 'maintenance-mode activate', 'maintenance-mode deactivate' );
		if ( ! in_array( $subcommand, $allowed, true ) ) {
			return array( 'exit_code' => 1, 'output' => array( 'Unsupported WP-CLI restore action.' ) );
		}

		$command = escapeshellarg( $this->wp_cli_path() )
			. ' --path=' . escapeshellarg( $target_path )
			. ' ' . $subcommand
			. ' --skip-plugins --skip-themes';

		return $this->run_shell_command( $command );
	}

	/**
	 * Re-establishes the core maintenance marker after a failed file write.
	 *
	 * @param string $target_path WordPress path.
	 * @return bool
	 */
	protected function ensure_production_maintenance_marker( $target_path ) {
		$target_path = $this->normalize_path( $target_path );
		if ( '' === $target_path || $target_path !== $this->wordpress_path() || $this->dangerous_restore_target_path( $target_path ) || ! is_dir( $target_path ) || ! is_writable( $target_path ) ) {
			return false;
		}

		$marker = $target_path . DIRECTORY_SEPARATOR . '.maintenance';
		if ( is_link( $marker ) ) {
			return false;
		}
		if ( is_file( $marker ) ) {
			return true;
		}

		$content = "<?php\n\$upgrading = " . time() . ";\n";
		$temp    = $marker . '.alynt-' . str_replace( '.', '', uniqid( '', true ) );
		$written = file_put_contents( $temp, $content );
		if ( strlen( $content ) !== $written || ! chmod( $temp, 0644 ) || ! rename( $temp, $marker ) ) {
			if ( is_file( $temp ) && ! is_link( $temp ) ) {
				unlink( $temp );
			}
			return false;
		}

		return true;
	}

	/**
	 * Redacts sensitive keys and values from restore reports.
	 *
	 * @param mixed $value Value.
	 * @param string $key Current key.
	 * @return mixed
	 */
	private function redact_restore_report_data( $value, $key = '' ) {
		if ( '' !== $key && preg_match( '/(?:token|secret|password|authorization|cookie|nonce|salt|private[_-]?key|signed[_-]?url)/i', $key ) ) {
			return '[redacted]';
		}

		if ( is_array( $value ) ) {
			$redacted = array();
			foreach ( $value as $item_key => $item_value ) {
				$redacted[ $item_key ] = $this->redact_restore_report_data( $item_value, (string) $item_key );
			}
			return $redacted;
		}

		if ( is_string( $value ) ) {
			$value = preg_replace( '/\bBearer\s+[A-Za-z0-9._~-]+/i', 'Bearer [redacted]', $value );
			$value = preg_replace( '/([?&](?:token|key|signature|sig|expires)=)[^&\s]+/i', '$1[redacted]', $value );
		}

		return $value;
	}

}
