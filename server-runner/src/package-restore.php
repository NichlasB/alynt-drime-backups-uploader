<?php
/**
 * Server runner package verification, fetch, inspection, and restore staging.
 *
 * @package Alynt_Drime_Backups_Uploader
 */
trait Alynt_Server_Backup_Runner_Package_Restore {
	/**
	 * Verifies one package.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return int Exit code.
	 */
	private function verify_command( array $options ) {
		$package = isset( $options['package'] ) ? (string) $options['package'] : '';
		if ( '' === $package ) {
			$this->error( 'Missing --package=/path/to/archive.tar.gz.' );
			return 1;
		}

		if ( ! $this->verify_package( $package ) ) {
			return 1;
		}

		$this->print_verify_next_steps( $package );

		return 0;
	}

	/**
	 * Inspects a verified package without extracting it.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return int Exit code.
	 */
	private function inspect_command( array $options ) {
		$package = isset( $options['package'] ) ? (string) $options['package'] : '';
		if ( '' === $package ) {
			$this->error( 'Missing --package=/path/to/archive.tar.gz.' );
			return 1;
		}

		if ( ! $this->verify_package( $package ) ) {
			return 1;
		}

		$manifest = $this->read_package_manifest( $package );
		if ( empty( $manifest ) ) {
			return 1;
		}

		$this->line( 'Package ID: ' . $this->manifest_value( $manifest, 'package_id' ) );
		$this->line( 'Producer: ' . $this->manifest_value( $manifest, 'producer' ) );
		$this->line( 'Created At: ' . $this->manifest_value( $manifest, 'created_at' ) );
		$this->line( 'Site URL: ' . $this->manifest_value( $manifest, 'site_url' ) );
		$this->line( 'Archive Format: ' . $this->manifest_value( $manifest, 'archive_format' ) );
		$this->line( 'File Root: ' . $this->manifest_value( $manifest, 'file_root' ) );
		$this->line( 'Database Dump: ' . $this->manifest_value( $manifest, 'database_dump' ) );
		$this->line( 'Backup Type: ' . $this->manifest_value( $manifest, 'backup_type' ) );
		$this->line( 'Database Dump Started: ' . $this->manifest_value( $manifest, 'database_dump_started_at' ) );
		$this->line( 'Database Dump Finished: ' . $this->manifest_value( $manifest, 'database_dump_finished_at' ) );
		$this->line( 'File Archive Started: ' . $this->manifest_value( $manifest, 'file_archive_started_at' ) );
		$this->line( 'Archive Preview:' );

		foreach ( $this->archive_preview( $package ) as $entry ) {
			$this->line( '  ' . $entry );
		}

		$this->print_inspect_next_steps( $package, $manifest );

		return 0;
	}

	/**
	 * Fetches a package and sidecars from Drime into a local directory.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return int Exit code.
	 */
	private function fetch_command( array $options ) {
		$package_id    = isset( $options['package-id'] ) ? trim( (string) $options['package-id'] ) : '';
		$download_path = isset( $options['download-path'] ) ? $this->normalize_path( (string) $options['download-path'] ) : '';
		$folder_hash   = isset( $options['folder-hash'] ) ? trim( (string) $options['folder-hash'] ) : '';
		$workspace_id  = isset( $options['workspace-id'] ) ? max( 0, (int) $options['workspace-id'] ) : 0;
		$token_env     = isset( $options['token-env'] ) ? trim( (string) $options['token-env'] ) : 'ALYNT_DRIME_TOKEN';
		$overwrite     = ! empty( $options['overwrite'] ) && '0' !== (string) $options['overwrite'];

		if ( '' === $package_id ) {
			$this->error( 'Missing --package-id=example-com-YYYYmmdd-HHMMSS.' );
			return 1;
		}

		if ( '' === $folder_hash ) {
			$this->error( 'Missing --folder-hash=DRIME_FOLDER_HASH.' );
			return 1;
		}

		if ( '' === $download_path ) {
			$this->error( 'Missing --download-path=/private/restore/downloads.' );
			return 1;
		}

		if ( ! $this->ensure_directory( $download_path ) || ! is_writable( $download_path ) ) {
			$this->error( 'Download path is not writable.' );
			return 1;
		}

		if ( ! function_exists( 'curl_init' ) ) {
			$this->error( 'The PHP cURL extension is required for Drime fetch.' );
			return 1;
		}

		$token = getenv( $token_env );
		if ( ! is_string( $token ) || '' === trim( $token ) ) {
			$this->error( 'Missing Drime bearer token environment variable: ' . $token_env );
			return 1;
		}

		$names = $this->fetch_package_names( $package_id );
		if ( empty( $names ) ) {
			return 1;
		}

		$entries = $this->list_drime_entries( $workspace_id, $folder_hash, $package_id, trim( $token ) );
		if ( empty( $entries ) ) {
			return 1;
		}

		foreach ( $names as $name ) {
			$entry = $this->find_drime_entry_by_name( $entries, $name );
			if ( empty( $entry['hash'] ) ) {
				$this->error( 'Required remote package file was not found: ' . $name );
				return 1;
			}

			$destination = $download_path . DIRECTORY_SEPARATOR . $name;
			if ( file_exists( $destination ) && ! $overwrite ) {
				$this->error( 'Local file already exists; refusing to overwrite: ' . $destination );
				return 1;
			}

			if ( ! $this->download_drime_entry( (string) $entry['hash'], $destination, trim( $token ) ) ) {
				return 1;
			}

			$this->line( 'Fetched: ' . $name );
		}

		$package = $download_path . DIRECTORY_SEPARATOR . $names[0];
		if ( ! $this->verify_package( $package ) ) {
			return 1;
		}

		$this->line( 'Fetched package verified: ' . $package );
		$this->print_fetch_next_steps( $package );

		return 0;
	}

	/**
	 * Extracts a verified package into a non-destructive restore staging directory.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return int Exit code.
	 */
	private function stage_restore_command( array $options ) {
		$package = isset( $options['package'] ) ? (string) $options['package'] : '';
		if ( '' === $package ) {
			$this->error( 'Missing --package=/path/to/archive.tar.gz.' );
			return 1;
		}

		if ( ! $this->verify_package( $package ) ) {
			return 1;
		}

		$manifest = $this->read_package_manifest( $package );
		if ( empty( $manifest['package_id'] ) ) {
			$this->error( 'Manifest package ID is missing.' );
			return 1;
		}

		$restore_base = isset( $options['restore-path'] ) ? $this->normalize_path( (string) $options['restore-path'] ) : $this->restore_path();
		if ( ! $this->ensure_directory( $restore_base ) || ! is_writable( $restore_base ) ) {
			$this->error( 'Restore path is not writable.' );
			return 1;
		}

		$restore_dir = $restore_base . DIRECTORY_SEPARATOR . $this->safe_slug( (string) $manifest['package_id'] );
		if ( file_exists( $restore_dir ) ) {
			$this->error( 'Restore directory already exists; refusing to overwrite: ' . $restore_dir );
			return 1;
		}

		if ( ! mkdir( $restore_dir, 0750, true ) ) {
			$this->error( 'Could not create restore directory.' );
			return 1;
		}

		if ( ! $this->validate_archive_members( $package ) ) {
			$this->error( 'Package failed restore safety validation.' );
			rmdir( $restore_dir );
			return 1;
		}

		$command = 'tar -xzf ' . escapeshellarg( $package ) . ' -C ' . escapeshellarg( $restore_dir );
		$result  = $this->run_shell_command( $command );
		if ( 0 !== $result['exit_code'] ) {
			$this->error( 'Package extraction failed.' );
			$this->error( implode( "\n", $result['output'] ) );
			return 1;
		}

		$notes = array(
			'Package staged for inspection only.',
			'No database import was performed.',
			'No live WordPress files were overwritten.',
			'Package ID: ' . (string) $manifest['package_id'],
			'Created At: ' . $this->manifest_value( $manifest, 'created_at' ),
			'Site URL: ' . $this->manifest_value( $manifest, 'site_url' ),
			'Archive Format: ' . $this->manifest_value( $manifest, 'archive_format' ),
			'File Root: ' . $this->manifest_value( $manifest, 'file_root' ),
			'Database Dump: ' . $this->manifest_value( $manifest, 'database_dump' ),
			'',
			'Recommended inspection:',
			'- Review htdocs/ before any file replacement.',
			'- Review database.sql before any database import.',
			'- Review manifest.json and package sidecars before approving recovery work.',
			'- Keep production restore steps manual until separately approved.',
		);

		$this->write_file( $restore_dir . DIRECTORY_SEPARATOR . 'RESTORE_NOTES.txt', implode( "\n", $notes ) . "\n" );
		if ( ! $this->write_json( $restore_dir . DIRECTORY_SEPARATOR . 'RESTORE_REPORT.json', $this->restore_report( $package, $restore_dir, $manifest ) ) ) {
			$this->error( 'Warning: package was staged, but RESTORE_REPORT.json could not be written.' );
		}

		$this->print_stage_restore_next_steps( $package, $restore_dir, $manifest );

		return 0;
	}

	/**
	 * Builds a non-destructive restore staging report.
	 *
	 * @param string              $package Package path.
	 * @param string              $restore_dir Restore staging directory.
	 * @param array<string,mixed> $manifest Manifest.
	 * @return array<string,mixed>
	 */
	private function restore_report( $package, $restore_dir, array $manifest ) {
		$checksum      = $this->read_checksum_sidecar( $package );
		$file_root     = $this->manifest_value( $manifest, 'file_root' );
		$database_dump = $this->manifest_value( $manifest, 'database_dump' );
		$staged_integrity = array(
			'schema_version' => 1,
			'algorithm'      => 'sha256',
			'file_tree'     => $this->staged_file_tree_integrity( $restore_dir . DIRECTORY_SEPARATOR . $file_root ),
			'database_dump' => $this->staged_file_integrity( $restore_dir . DIRECTORY_SEPARATOR . $database_dump ),
		);

		return array(
			'schema_version'             => 1,
			'generated_at'               => gmdate( 'c' ),
			'status'                     => 'staged_for_inspection',
			'package_id'                 => $this->manifest_value( $manifest, 'package_id' ),
			'archive_name'               => basename( $package ),
			'archive_size'               => is_file( $package ) ? (int) filesize( $package ) : 0,
			'manifest_name'              => basename( $package . '.manifest.json' ),
			'checksum_name'              => basename( $package . '.sha256' ),
			'checksum_algorithm'         => isset( $checksum['algorithm'] ) ? (string) $checksum['algorithm'] : '',
			'checksum_value'             => isset( $checksum['value'] ) ? (string) $checksum['value'] : '',
			'site_id'                    => $this->manifest_value( $manifest, 'site_id' ),
			'site_uuid'                  => $this->manifest_value( $manifest, 'site_uuid' ),
			'site_url'                   => $this->manifest_value( $manifest, 'site_url' ),
			'created_at'                 => $this->manifest_value( $manifest, 'created_at' ),
			'producer'                   => $this->manifest_value( $manifest, 'producer' ),
			'producer_version'           => $this->manifest_value( $manifest, 'producer_version' ),
			'backup_type'                => $this->manifest_value( $manifest, 'backup_type' ),
			'archive_format'             => $this->manifest_value( $manifest, 'archive_format' ),
			'file_root'                  => $file_root,
			'database_dump'              => $database_dump,
			'staged_integrity'           => $staged_integrity,
			'consistency_mode'           => $this->manifest_value( $manifest, 'consistency_mode' ),
			'consistency_status'         => $this->manifest_value( $manifest, 'consistency_status' ),
			'restore_directory_name'     => basename( $restore_dir ),
			'package_verified'           => true,
			'archive_members_safe'       => true,
			'extracted_for_inspection'   => true,
			'database_imported'          => false,
			'live_files_overwritten'     => false,
			'manual_restore_required'    => true,
			'recommended_next_steps'     => array(
				'Review RESTORE_NOTES.txt.',
				'Review htdocs before any file replacement.',
				'Review database.sql before any database import.',
				'Keep production restore steps manual until separately approved.',
			),
		);
	}

	/**
	 * Returns a deterministic integrity record for an extracted directory tree.
	 *
	 * @param string $root Directory root.
	 * @return array<string,mixed>
	 */
	private function staged_file_tree_integrity( $root ) {
		$result = array(
			'valid'           => false,
			'sha256'          => '',
			'file_count'      => 0,
			'directory_count' => 0,
			'symlink_count'   => 0,
			'total_bytes'     => 0,
		);
		if ( '' === $root || ! is_dir( $root ) || ! is_readable( $root ) ) {
			return $result;
		}

		$records = array();
		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ( $iterator as $item ) {
				$path     = $item->getPathname();
				$relative = ltrim( substr( $this->normalize_path( $path ), strlen( $this->normalize_path( $root ) ) ), '/' );
				if ( '' === $relative ) {
					return $result;
				}

				if ( $item->isLink() ) {
					$target = readlink( $path );
					if ( false === $target || false !== strpos( (string) $target, "\0" ) ) {
						return $result;
					}
					$records[] = "link\0" . $relative . "\0" . (string) $target;
					++$result['symlink_count'];
					continue;
				}

				if ( $item->isDir() ) {
					$records[] = "directory\0" . $relative;
					++$result['directory_count'];
					continue;
				}

				if ( ! $item->isFile() || ! is_readable( $path ) ) {
					return $result;
				}
				$file_hash = hash_file( 'sha256', $path );
				if ( false === $file_hash ) {
					return $result;
				}
				$size      = (int) $item->getSize();
				$records[] = "file\0" . $relative . "\0" . $size . "\0" . $file_hash;
				++$result['file_count'];
				$result['total_bytes'] += $size;
			}
		} catch ( RuntimeException $exception ) {
			return $result;
		}

		sort( $records, SORT_STRING );
		$context = hash_init( 'sha256' );
		foreach ( $records as $record ) {
			hash_update( $context, strlen( $record ) . ':' . $record );
		}
		$result['sha256'] = hash_final( $context );
		$result['valid']  = true;

		return $result;
	}

	/**
	 * Returns an integrity record for one extracted regular file.
	 *
	 * @param string $path File path.
	 * @return array<string,mixed>
	 */
	private function staged_file_integrity( $path ) {
		$result = array(
			'valid'       => false,
			'sha256'      => '',
			'total_bytes' => 0,
		);
		if ( '' === $path || ! is_file( $path ) || is_link( $path ) || ! is_readable( $path ) ) {
			return $result;
		}

		$hash = hash_file( 'sha256', $path );
		if ( false === $hash ) {
			return $result;
		}

		$result['valid']       = true;
		$result['sha256']      = $hash;
		$result['total_bytes'] = (int) filesize( $path );

		return $result;
	}

}
