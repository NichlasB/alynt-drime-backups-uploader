<?php
/**
 * Server runner inventory and operator-approved local cleanup behavior.
 *
 * @package Alynt_Drime_Backups_Uploader
 */
trait Alynt_Server_Backup_Runner_Inventory_Cleanup {
	/**
	 * Lists completed packages.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return int Exit code.
	 */
	private function list_packages( array $options ) {
		$packages = glob( $this->outbox_path() . DIRECTORY_SEPARATOR . '*.' . $this->archive_format() );
		if ( ! is_array( $packages ) ) {
			return 0;
		}

		sort( $packages );
		if ( isset( $options['format'] ) && 'json' === (string) $options['format'] ) {
			$this->line(
				json_encode(
					array(
						'schema_version' => 1,
						'generated_at'   => gmdate( 'c' ),
						'archive_format' => $this->archive_format(),
						'package_count'  => count( $packages ),
						'packages'       => array_map( array( $this, 'package_inventory_record' ), $packages ),
					),
					JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
				)
			);

			return 0;
		}

		foreach ( $packages as $package ) {
			$this->line( $package );
		}

		return 0;
	}

	/**
	 * Builds a package inventory record for restore discovery.
	 *
	 * @param string $package Package path.
	 * @param bool   $include_remote_index Whether to read the remote index sidecar.
	 * @return array<string,mixed>
	 */
	private function package_inventory_record( $package, $include_remote_index = true ) {
		$manifest_path        = $package . '.manifest.json';
		$checksum_path        = $package . '.sha256';
		$remote_index_path    = $package . '.remote-index.json';
		$manifest             = $this->read_package_manifest_quiet( $package );
		$checksum             = $this->read_checksum_sidecar( $package );
		$remote_index         = $include_remote_index ? $this->read_remote_index_quiet( $package ) : array();
		$basename             = basename( $package );
		$manifest_present     = is_readable( $manifest_path );
		$checksum_present     = is_readable( $checksum_path );
		$remote_index_present = is_readable( $remote_index_path );
		$manifest_valid       = ! empty( $manifest['package_id'] );
		$checksum_valid       = ! empty( $checksum['value'] );
		$remote_index_valid   = $this->remote_index_is_valid( $remote_index );
		$package_id           = $manifest_valid ? $this->manifest_value( $manifest, 'package_id' ) : $this->package_id_from_archive_name( $basename );

		return array(
			'package_id'           => $package_id,
			'archive_name'         => $basename,
			'archive_size'         => is_file( $package ) ? (int) filesize( $package ) : 0,
			'archive_modified_at'  => is_file( $package ) ? gmdate( 'c', (int) filemtime( $package ) ) : '',
			'manifest_name'        => basename( $manifest_path ),
			'manifest_present'     => $manifest_present,
			'manifest_valid'       => $manifest_valid,
			'checksum_name'        => basename( $checksum_path ),
			'checksum_present'     => $checksum_present,
			'checksum_valid'       => $checksum_valid,
			'remote_index_name'    => basename( $remote_index_path ),
			'remote_index_present' => $remote_index_present,
			'remote_index_valid'   => $remote_index_valid,
			'checksum_algorithm'   => isset( $checksum['algorithm'] ) ? (string) $checksum['algorithm'] : '',
			'checksum_value'       => isset( $checksum['value'] ) ? (string) $checksum['value'] : '',
			'site_url'             => $this->manifest_value( $manifest, 'site_url' ),
			'created_at'           => $this->manifest_value( $manifest, 'created_at' ),
			'producer'             => $this->manifest_value( $manifest, 'producer' ),
			'backup_type'          => $this->manifest_value( $manifest, 'backup_type' ),
			'archive_format'       => $this->manifest_value( $manifest, 'archive_format' ),
			'file_root'            => $this->manifest_value( $manifest, 'file_root' ),
			'database_dump'        => $this->manifest_value( $manifest, 'database_dump' ),
			'consistency_mode'     => $this->manifest_value( $manifest, 'consistency_mode' ),
			'consistency_status'   => $this->manifest_value( $manifest, 'consistency_status' ),
			'verification_ready'   => $manifest_valid && $checksum_valid,
		);
	}

	/**
	 * Builds a remote-safe package index for Drime restore discovery.
	 *
	 * @param string $package Package path.
	 * @return array<string,mixed>
	 */
	private function remote_package_index( $package ) {
		$package_record                         = $this->package_inventory_record( $package, false );
		$package_record['remote_index_present'] = true;
		$package_record['remote_index_valid']   = true;

		return array(
			'schema_version' => 1,
			'index_type'     => 'single_package_restore_index',
			'generated_at'   => gmdate( 'c' ),
			'archive_format' => $this->archive_format(),
			'package_count'  => 1,
			'packages'       => array( $package_record ),
			'restore_policy' => array(
				'requires_archive_manifest_checksum' => true,
				'destructive_restore_automated'     => false,
				'manual_restore_required'           => true,
			),
		);
	}

	/**
	 * Builds a remote-safe package catalog snapshot for Drime restore discovery.
	 *
	 * @return array<string,mixed>
	 */
	private function remote_catalog_snapshot() {
		$packages = glob( $this->outbox_path() . DIRECTORY_SEPARATOR . '*.' . $this->archive_format() );
		if ( ! is_array( $packages ) ) {
			$packages = array();
		}

		sort( $packages );

		return array(
			'schema_version' => 1,
			'catalog_type'   => 'folder_package_catalog_snapshot',
			'generated_at'   => gmdate( 'c' ),
			'archive_format' => $this->archive_format(),
			'package_count'  => count( $packages ),
			'packages'       => array_map( array( $this, 'package_inventory_record' ), $packages ),
			'restore_policy' => array(
				'requires_archive_manifest_checksum' => true,
				'destructive_restore_automated'     => false,
				'manual_restore_required'           => true,
			),
		);
	}

	/**
	 * Prints read-only cleanup candidates for local package artifacts.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return int Exit code.
	 */
	private function cleanup_preview_command( array $options ) {
		$older_than_days = isset( $options['older-than-days'] ) ? max( 0, (int) $options['older-than-days'] ) : 14;
		$preview         = $this->cleanup_preview_data( $older_than_days );

		if ( isset( $options['format'] ) && 'json' === (string) $options['format'] ) {
			$this->line( json_encode( $preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

			return 0;
		}

		$this->line( 'Cleanup preview only. No files were deleted.' );
		$this->line( 'Older than days: ' . (string) $preview['older_than_days'] );
		$this->line( 'Outbox candidates: ' . (string) $preview['outbox']['candidate_count'] );
		foreach ( $preview['outbox']['candidates'] as $candidate ) {
			$this->line( '- outbox package: ' . $candidate['archive_name'] . ' (age_days=' . (string) $candidate['age_days'] . ')' );
		}

		$this->line( 'Restore staging candidates: ' . (string) $preview['restore_staging']['candidate_count'] );
		foreach ( $preview['restore_staging']['candidates'] as $candidate ) {
			$this->line( '- restore directory: ' . $candidate['directory_name'] . ' (age_days=' . (string) $candidate['age_days'] . ')' );
		}

		return 0;
	}

	/**
	 * Builds read-only cleanup preview data.
	 *
	 * @param int $older_than_days Minimum age.
	 * @return array<string,mixed>
	 */
	private function cleanup_preview_data( $older_than_days ) {
		$cutoff_timestamp = time() - ( $older_than_days * self::DAY_IN_SECONDS );
		$outbox           = $this->cleanup_preview_outbox_candidates( $cutoff_timestamp );
		$restore_staging  = $this->cleanup_preview_restore_candidates( $cutoff_timestamp );

		return array(
			'schema_version'                => 1,
			'generated_at'                  => gmdate( 'c' ),
			'older_than_days'               => $older_than_days,
			'cutoff_timestamp'              => $cutoff_timestamp,
			'cutoff_at'                     => gmdate( 'c', $cutoff_timestamp ),
			'outbox'                        => array(
				'candidate_count' => count( $outbox ),
				'candidates'      => $outbox,
			),
			'restore_staging'               => array(
				'candidate_count' => count( $restore_staging ),
				'candidates'      => $restore_staging,
			),
			'total_candidate_count'         => count( $outbox ) + count( $restore_staging ),
			'destructive_actions_performed' => false,
		);
	}

	/**
	 * Finds local outbox packages that are old enough for operator review.
	 *
	 * @param int $cutoff_timestamp Cutoff Unix timestamp.
	 * @return array<int,array<string,mixed>>
	 */
	private function cleanup_preview_outbox_candidates( $cutoff_timestamp ) {
		$packages = glob( $this->outbox_path() . DIRECTORY_SEPARATOR . '*.' . $this->archive_format() );
		if ( ! is_array( $packages ) ) {
			return array();
		}

		sort( $packages );

		$candidates = array();
		foreach ( $packages as $package ) {
			$modified_at = is_file( $package ) ? (int) filemtime( $package ) : 0;
			if ( 0 === $modified_at || $modified_at > $cutoff_timestamp ) {
				continue;
			}

			$record                     = $this->package_inventory_record( $package );
			$record['age_days']         = $this->age_days( $modified_at );
			$record['suggested_action'] = 'operator_review';
			$candidates[]               = $record;
		}

		return $candidates;
	}

	/**
	 * Finds restore staging directories that are old enough for operator review.
	 *
	 * @param int $cutoff_timestamp Cutoff Unix timestamp.
	 * @return array<int,array<string,mixed>>
	 */
	private function cleanup_preview_restore_candidates( $cutoff_timestamp ) {
		$directories = glob( $this->restore_path() . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR );
		if ( ! is_array( $directories ) ) {
			return array();
		}

		sort( $directories );

		$candidates = array();
		foreach ( $directories as $directory ) {
			$modified_at = is_dir( $directory ) ? (int) filemtime( $directory ) : 0;
			if ( 0 === $modified_at || $modified_at > $cutoff_timestamp ) {
				continue;
			}

			$candidates[] = array(
				'directory_name'         => basename( $directory ),
				'modified_at'            => gmdate( 'c', $modified_at ),
				'age_days'               => $this->age_days( $modified_at ),
				'restore_notes_present'  => is_readable( $directory . DIRECTORY_SEPARATOR . 'RESTORE_NOTES.txt' ),
				'restore_report_present' => is_readable( $directory . DIRECTORY_SEPARATOR . 'RESTORE_REPORT.json' ),
				'suggested_action'       => 'operator_review',
			);
		}

		return $candidates;
	}

	/**
	 * Deletes old local artifacts after explicit operator confirmation.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return int Exit code.
	 */
	private function cleanup_command( array $options ) {
		$confirm = isset( $options['confirm'] ) ? trim( (string) $options['confirm'] ) : '';
		if ( 'delete-local-artifacts' !== $confirm ) {
			$this->error( 'Refusing cleanup without --confirm=delete-local-artifacts.' );
			return 1;
		}

		$older_than_days = isset( $options['older-than-days'] ) ? max( 0, (int) $options['older-than-days'] ) : 14;
		$preview         = $this->cleanup_preview_data( $older_than_days );
		$outbox          = $this->delete_outbox_cleanup_candidates( $preview['outbox']['candidates'] );
		$restore_staging = $this->delete_restore_cleanup_candidates( $preview['restore_staging']['candidates'] );
		$failure_count   = count( $outbox['failures'] ) + count( $restore_staging['failures'] );

		$result = array(
			'schema_version'                => 1,
			'generated_at'                  => gmdate( 'c' ),
			'older_than_days'               => $older_than_days,
			'confirmation'                  => $confirm,
			'outbox'                        => $outbox,
			'restore_staging'               => $restore_staging,
			'total_candidate_count'         => $preview['total_candidate_count'],
			'failure_count'                 => $failure_count,
			'destructive_actions_performed' => true,
		);

		if ( isset( $options['format'] ) && 'json' === (string) $options['format'] ) {
			$this->line( json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

			return 0 === $failure_count ? 0 : 1;
		}

		$this->line( 'Local cleanup completed.' );
		$this->line( 'Older than days: ' . (string) $older_than_days );
		$this->line( 'Outbox packages deleted: ' . (string) $outbox['deleted_package_count'] );
		$this->line( 'Outbox files deleted: ' . (string) $outbox['deleted_file_count'] );
		$this->line( 'Restore staging directories deleted: ' . (string) $restore_staging['deleted_directory_count'] );
		$this->line( 'Failures: ' . (string) $failure_count );

		return 0 === $failure_count ? 0 : 1;
	}

	/**
	 * Deletes confirmed outbox cleanup candidates.
	 *
	 * @param array<int,array<string,mixed>> $candidates Candidates.
	 * @return array<string,mixed>
	 */
	private function delete_outbox_cleanup_candidates( array $candidates ) {
		$result = array(
			'candidate_count'        => count( $candidates ),
			'deleted_package_count'  => 0,
			'deleted_file_count'     => 0,
			'deleted'                => array(),
			'failures'               => array(),
		);

		foreach ( $candidates as $candidate ) {
			$archive_name = isset( $candidate['archive_name'] ) ? (string) $candidate['archive_name'] : '';
			$deleted      = $this->delete_outbox_package_set( $archive_name );
			if ( ! empty( $deleted['failure_reason'] ) ) {
				$result['failures'][] = $deleted;
				continue;
			}

			++$result['deleted_package_count'];
			$result['deleted_file_count'] += $deleted['deleted_file_count'];
			$result['deleted'][]           = $deleted;
		}

		return $result;
	}

	/**
	 * Deletes one archive and its known server-runner sidecars.
	 *
	 * @param string $archive_name Archive basename.
	 * @return array<string,mixed>
	 */
	private function delete_outbox_package_set( $archive_name ) {
		if ( ! $this->safe_cleanup_child_name( $archive_name ) ) {
			return $this->cleanup_failure( $archive_name, 'unsafe_archive_name' );
		}

		$archive = $this->outbox_path() . DIRECTORY_SEPARATOR . $archive_name;
		if ( ! $this->path_is_expected_child( $this->outbox_path(), $archive, $archive_name ) || ! is_file( $archive ) ) {
			return $this->cleanup_failure( $archive_name, 'archive_missing_or_outside_outbox' );
		}

		$deleted_files = array();
		foreach ( $this->outbox_cleanup_paths( $archive ) as $path ) {
			if ( ! file_exists( $path ) ) {
				continue;
			}

			if ( ! is_file( $path ) || ! $this->path_is_expected_child( $this->outbox_path(), $path, basename( $path ) ) ) {
				return $this->cleanup_failure( $archive_name, 'unsafe_outbox_file' );
			}

			if ( ! unlink( $path ) ) {
				return $this->cleanup_failure( $archive_name, 'delete_failed' );
			}

			$deleted_files[] = basename( $path );
		}

		return array(
			'archive_name'        => $archive_name,
			'deleted_file_count'  => count( $deleted_files ),
			'deleted_file_names'  => $deleted_files,
			'failure_reason'      => '',
		);
	}

	/**
	 * Returns the archive and known sidecar paths for local cleanup.
	 *
	 * @param string $archive Archive path.
	 * @return array<int,string>
	 */
	private function outbox_cleanup_paths( $archive ) {
		$paths = array();
		foreach ( array( '.manifest.json', '.sha256', '.sha256sum', '.remote-index.json', '.remote-catalog.json' ) as $suffix ) {
			$paths[] = $archive . $suffix;
		}
		$paths[] = $archive;

		return $paths;
	}

	/**
	 * Deletes confirmed restore staging cleanup candidates.
	 *
	 * @param array<int,array<string,mixed>> $candidates Candidates.
	 * @return array<string,mixed>
	 */
	private function delete_restore_cleanup_candidates( array $candidates ) {
		$result = array(
			'candidate_count'           => count( $candidates ),
			'deleted_directory_count'   => 0,
			'deleted'                   => array(),
			'failures'                  => array(),
		);

		foreach ( $candidates as $candidate ) {
			$directory_name = isset( $candidate['directory_name'] ) ? (string) $candidate['directory_name'] : '';
			$deleted        = $this->delete_restore_staging_directory( $directory_name );
			if ( ! empty( $deleted['failure_reason'] ) ) {
				$result['failures'][] = $deleted;
				continue;
			}

			++$result['deleted_directory_count'];
			$result['deleted'][] = $deleted;
		}

		return $result;
	}

	/**
	 * Deletes one restore staging directory under the configured restore path.
	 *
	 * @param string $directory_name Directory basename.
	 * @return array<string,mixed>
	 */
	private function delete_restore_staging_directory( $directory_name ) {
		if ( ! $this->safe_cleanup_child_name( $directory_name ) ) {
			return $this->cleanup_failure( $directory_name, 'unsafe_directory_name' );
		}

		$directory = $this->restore_path() . DIRECTORY_SEPARATOR . $directory_name;
		if ( ! $this->path_is_expected_child( $this->restore_path(), $directory, $directory_name ) || ! is_dir( $directory ) ) {
			return $this->cleanup_failure( $directory_name, 'directory_missing_or_outside_restore_path' );
		}

		if ( ! $this->remove_restore_staging_directory( $directory ) ) {
			return $this->cleanup_failure( $directory_name, 'delete_failed' );
		}

		return array(
			'directory_name' => $directory_name,
			'failure_reason' => '',
		);
	}

	/**
	 * Removes a restore staging directory after restore-path containment checks.
	 *
	 * @param string $directory Directory path.
	 * @return bool
	 */
	private function remove_restore_staging_directory( $directory, $restore_path = '' ) {
		$restore_path = '' !== $restore_path ? $restore_path : $this->restore_path();
		$normalized   = $this->normalize_path( $directory );
		$base         = $this->normalize_path( $restore_path );
		if ( '' === $normalized || '' === $base || 0 !== strpos( $normalized, $base . '/' ) || ! is_dir( $directory ) || is_link( $directory ) || ! $this->path_is_within_directory_canonical( $base, $normalized ) ) {
			return false;
		}

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ( $iterator as $item ) {
				if ( $item->isLink() || $item->isFile() ) {
					if ( ! unlink( $item->getPathname() ) ) {
						return false;
					}
					continue;
				}

				if ( $item->isDir() && ! rmdir( $item->getPathname() ) ) {
					return false;
				}
			}
		} catch ( Throwable $exception ) {
			unset( $exception );
			return false;
		}

		return rmdir( $directory );
	}

	/**
	 * Builds a cleanup failure record.
	 *
	 * @param string $name Name.
	 * @param string $reason Reason.
	 * @return array<string,mixed>
	 */
	private function cleanup_failure( $name, $reason ) {
		return array(
			'name'           => $name,
			'failure_reason' => $reason,
		);
	}

	/**
	 * Checks whether a cleanup candidate basename is safe to reconstruct.
	 *
	 * @param string $name Basename.
	 * @return bool
	 */
	private function safe_cleanup_child_name( $name ) {
		return '' !== $name
			&& '.' !== $name
			&& '..' !== $name
			&& false === strpos( $name, '/' )
			&& false === strpos( $name, '\\' )
			&& basename( $name ) === $name;
	}

	/**
	 * Checks whether a path is the expected direct child of a base path.
	 *
	 * @param string $base Base path.
	 * @param string $path Child path.
	 * @param string $name Expected basename.
	 * @return bool
	 */
	private function path_is_expected_child( $base, $path, $name ) {
		$base = $this->normalize_path( $base );
		$path = $this->normalize_path( $path );

		return '' !== $base && $path === $base . '/' . $name;
	}

	/**
	 * Calculates whole days elapsed since a timestamp.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return int
	 */
	private function age_days( $timestamp ) {
		return max( 0, (int) floor( ( time() - $timestamp ) / self::DAY_IN_SECONDS ) );
	}

}
