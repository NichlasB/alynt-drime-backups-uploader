<?php
/**
 * Shared package guidance, sidecar, verification, and archive-safety helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 */
trait Alynt_Server_Backup_Runner_Package_Support {
	/**
	 * Prints next steps after package verification.
	 *
	 * @param string $package Package path.
	 * @return void
	 */
	private function print_verify_next_steps( $package ) {
		$this->line( '' );
		$this->line( 'Restore guidance:' );
		$this->line( '- Package is intact: ' . basename( $package ) );
		$this->line( '- Next: run inspect to review metadata, timing, and archive preview.' );
		$this->line( '- Then: run stage-restore only if the package matches the intended site.' );
		$this->line( '- Do not import database.sql or overwrite live files without separate approval.' );
	}

	/**
	 * Prints next steps after package inspection.
	 *
	 * @param string              $package Package path.
	 * @param array<string,mixed> $manifest Manifest.
	 * @return void
	 */
	private function print_inspect_next_steps( $package, array $manifest ) {
		$this->line( '' );
		$this->line( 'Restore guidance:' );
		$this->line( '- Confirm package ID: ' . $this->manifest_value( $manifest, 'package_id' ) );
		$this->line( '- Confirm site URL: ' . $this->manifest_value( $manifest, 'site_url' ) );
		$this->line( '- Confirm consistency status: ' . $this->manifest_value( $manifest, 'consistency_status' ) );
		$this->line( '- Next: run stage-restore to extract into a private inspection directory.' );
		$this->line( '- Stop if the package, site URL, timing, or archive preview is unexpected.' );
		$this->line( '- No live restore has been approved by this inspection output.' );
	}

	/**
	 * Prints next steps after a Drime fetch.
	 *
	 * @param string $package Package path.
	 * @return void
	 */
	private function print_fetch_next_steps( $package ) {
		$this->line( '' );
		$this->line( 'Restore guidance:' );
		$this->line( '- Downloaded package and required sidecars are present locally.' );
		$this->line( '- Fetched package path: ' . $package );
		$this->line( '- Next: run inspect against this package path.' );
		$this->line( '- Then: run stage-restore only after the package metadata matches the intended site.' );
	}

	/**
	 * Prints next steps after non-destructive restore staging.
	 *
	 * @param string              $package Package path.
	 * @param string              $restore_dir Restore directory.
	 * @param array<string,mixed> $manifest Manifest.
	 * @return void
	 */
	private function print_stage_restore_next_steps( $package, $restore_dir, array $manifest ) {
		$this->line( 'Restore staging completed.' );
		$this->line( 'Package: ' . basename( $package ) );
		$this->line( 'Package ID: ' . $this->manifest_value( $manifest, 'package_id' ) );
		$this->line( 'Staged at: ' . $restore_dir );
		$this->line( '' );
		$this->line( 'Review next:' );
		$this->line( '1. Open RESTORE_NOTES.txt.' );
		$this->line( '2. Open RESTORE_REPORT.json.' );
		$this->line( '3. Inspect htdocs/ before any file replacement.' );
		$this->line( '4. Inspect database.sql before any database import.' );
		$this->line( '5. Keep production restore steps manual until separately approved.' );
		$this->line( '' );
		$this->line( 'Safety boundary:' );
		$this->line( '- No database import was performed.' );
		$this->line( '- No live WordPress files were overwritten.' );
		$this->line( '- This command staged files for inspection only.' );
	}

	/**
	 * Verifies one package checksum and manifest sidecar.
	 *
	 * @param string $package Package path.
	 * @return bool
	 */
	private function verify_package( $package ) {
		if ( ! is_file( $package ) || ! is_readable( $package ) ) {
			$this->error( 'Package is not readable.' );
			return false;
		}

		$manifest_path = $package . '.manifest.json';
		$checksum_path = $package . '.sha256';
		if ( ! is_readable( $manifest_path ) || ! is_readable( $checksum_path ) ) {
			$this->error( 'Package sidecars are missing or unreadable.' );
			return false;
		}

		$manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
		if ( ! is_array( $manifest ) || empty( $manifest['package_id'] ) ) {
			$this->error( 'Manifest is invalid.' );
			return false;
		}

		$checksum_line = trim( (string) file_get_contents( $checksum_path ) );
		if ( ! preg_match( '/^([a-fA-F0-9]{64})\s+/', $checksum_line, $matches ) ) {
			$this->error( 'Checksum sidecar is invalid.' );
			return false;
		}

		$actual = hash_file( 'sha256', $package );
		if ( strtolower( $matches[1] ) !== $actual ) {
			$this->error( 'Checksum mismatch.' );
			return false;
		}

		$this->line( 'Package verified: ' . $package );
		return true;
	}

	/**
	 * Reads a package manifest sidecar.
	 *
	 * @param string $package Package path.
	 * @return array<string,mixed>
	 */
	private function read_package_manifest( $package ) {
		$manifest_path = $package . '.manifest.json';
		if ( ! is_readable( $manifest_path ) ) {
			$this->error( 'Package manifest sidecar is missing or unreadable.' );
			return array();
		}

		$manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
		if ( ! is_array( $manifest ) ) {
			$this->error( 'Package manifest is invalid JSON.' );
			return array();
		}

		return $manifest;
	}

	/**
	 * Reads a package manifest sidecar without writing errors.
	 *
	 * @param string $package Package path.
	 * @return array<string,mixed>
	 */
	private function read_package_manifest_quiet( $package ) {
		$manifest_path = $package . '.manifest.json';
		if ( ! is_readable( $manifest_path ) ) {
			return array();
		}

		$manifest = json_decode( (string) file_get_contents( $manifest_path ), true );

		return is_array( $manifest ) ? $manifest : array();
	}

	/**
	 * Reads a remote package index without writing errors.
	 *
	 * @param string $package Package path.
	 * @return array<string,mixed>
	 */
	private function read_remote_index_quiet( $package ) {
		$index_path = $package . '.remote-index.json';
		if ( ! is_readable( $index_path ) ) {
			return array();
		}

		$index = json_decode( (string) file_get_contents( $index_path ), true );

		return is_array( $index ) ? $index : array();
	}

	/**
	 * Checks whether a decoded remote package index has the expected shape.
	 *
	 * @param array<string,mixed> $index Index.
	 * @return bool
	 */
	private function remote_index_is_valid( array $index ) {
		return 1 === (int) ( isset( $index['schema_version'] ) ? $index['schema_version'] : 0 )
			&& isset( $index['index_type'] )
			&& 'single_package_restore_index' === (string) $index['index_type']
			&& ! empty( $index['packages'] )
			&& is_array( $index['packages'] );
	}

	/**
	 * Reads checksum sidecar metadata without hashing package bytes.
	 *
	 * @param string $package Package path.
	 * @return array<string,string>
	 */
	private function read_checksum_sidecar( $package ) {
		$checksum_path = $package . '.sha256';
		if ( ! is_readable( $checksum_path ) ) {
			return array();
		}

		$checksum_line = trim( (string) file_get_contents( $checksum_path ) );
		if ( ! preg_match( '/^([a-fA-F0-9]{64})\s+/', $checksum_line, $matches ) ) {
			return array();
		}

		return array(
			'algorithm' => 'sha256',
			'value'     => strtolower( $matches[1] ),
		);
	}

	/**
	 * Derives a package ID from the archive basename.
	 *
	 * @param string $archive_name Archive basename.
	 * @return string
	 */
	private function package_id_from_archive_name( $archive_name ) {
		$suffix = '.' . $this->archive_format();
		if ( substr( $archive_name, -strlen( $suffix ) ) === $suffix ) {
			return substr( $archive_name, 0, -strlen( $suffix ) );
		}

		return $archive_name;
	}

	/**
	 * Returns a printable manifest value.
	 *
	 * @param array<string,mixed> $manifest Manifest.
	 * @param string              $key Key.
	 * @return string
	 */
	private function manifest_value( array $manifest, $key ) {
		return isset( $manifest[ $key ] ) && is_scalar( $manifest[ $key ] ) ? (string) $manifest[ $key ] : '';
	}

	/**
	 * Returns a small archive listing preview.
	 *
	 * @param string $package Package path.
	 * @return array<int,string>
	 */
	private function archive_preview( $package ) {
		$handle = popen( 'tar -tzf ' . escapeshellarg( $package ) . ' 2>&1', 'r' );
		if ( false === $handle ) {
			return array();
		}

		$entries = array();
		while ( ! feof( $handle ) && count( $entries ) < 40 ) {
			$line = fgets( $handle );
			if ( false === $line ) {
				break;
			}

			$line = trim( $line );
			if ( '' !== $line ) {
				$entries[] = $line;
			}
		}

		pclose( $handle );

		return $entries;
	}

	/**
	 * Builds required package filenames for fetch.
	 *
	 * @param string $package_id Package ID.
	 * @return array<int,string>
	 */
	private function fetch_package_names( $package_id ) {
		$slug = $this->safe_slug( $package_id );
		if ( $slug !== $package_id ) {
			$this->error( 'Package ID contains unsupported characters.' );
			return array();
		}

		$archive = $package_id . '.' . $this->archive_format();

		return array(
			$archive,
			$archive . '.manifest.json',
			$archive . '.sha256',
		);
	}


	/**
	 * Validates archive members before restore extraction.
	 *
	 * @param string $package Package path.
	 * @return bool
	 */
	private function validate_archive_members( $package ) {
		$list_result = $this->run_shell_command( 'tar -tzf ' . escapeshellarg( $package ) );
		if ( 0 !== $list_result['exit_code'] ) {
			$this->error( 'Could not list package archive members.' );
			$this->error( implode( "\n", $list_result['output'] ) );
			return false;
		}

		foreach ( $list_result['output'] as $entry ) {
			if ( ! $this->is_safe_archive_member_name( $entry ) ) {
				$this->error( 'Unsafe archive member path: ' . $entry );
				return false;
			}
		}

		$verbose_result = $this->run_shell_command( 'tar -tvzf ' . escapeshellarg( $package ) );
		if ( 0 !== $verbose_result['exit_code'] ) {
			$this->error( 'Could not inspect package archive member types.' );
			$this->error( implode( "\n", $verbose_result['output'] ) );
			return false;
		}

		foreach ( $verbose_result['output'] as $entry ) {
			if ( preg_match( '/^[lh]/', ltrim( $entry ) ) ) {
				$this->error( 'Archive links are not allowed in restore staging: ' . $entry );
				return false;
			}
		}

		return true;
	}

	/**
	 * Checks whether an archive member name is safe to extract into a staging directory.
	 *
	 * @param string $entry Archive member name.
	 * @return bool
	 */
	private function is_safe_archive_member_name( $entry ) {
		$entry = trim( str_replace( '\\', '/', $entry ) );
		if ( '' === $entry || false !== strpos( $entry, "\0" ) ) {
			return false;
		}

		if ( '/' === $entry[0] || preg_match( '/^[A-Za-z]:\//', $entry ) ) {
			return false;
		}

		$parts = explode( '/', rtrim( $entry, '/' ) );
		foreach ( $parts as $part ) {
			if ( '' === $part || '.' === $part || '..' === $part ) {
				return false;
			}
		}

		return true;
	}

}
