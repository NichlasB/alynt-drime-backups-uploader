<?php
/**
 * Shared restore filesystem, path-safety, and report helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 */
trait Alynt_Server_Backup_Runner_Filesystem_Security {
	/**
	 * Returns a restore apply report path if reporting is ready.
	 *
	 * @param string $package_id Package ID.
	 * @return string
	 */
	private function restore_apply_report_path( $package_id ) {
		$reports_path = $this->restore_reports_path();
		if ( '' === $reports_path || $this->dangerous_restore_target_path( $reports_path ) || ! $this->ensure_directory( $reports_path ) || ! is_writable( $reports_path ) ) {
			return '';
		}

		return $reports_path . DIRECTORY_SEPARATOR . 'RESTORE_APPLY_REPORT-' . $this->safe_slug( $package_id ) . '-' . gmdate( 'Ymd-His' ) . '.json';
	}

	/**
	 * Returns the staged database dump path for a restore apply.
	 *
	 * @param string $staged_path Staged restore path.
	 * @return string
	 */
	private function restore_apply_database_dump_path( $staged_path ) {
		$report = $this->read_restore_report( $staged_path . DIRECTORY_SEPARATOR . 'RESTORE_REPORT.json' );
		$dump   = isset( $report['database_dump'] ) ? (string) $report['database_dump'] : '';

		if ( ! $this->restore_report_relative_name_is_safe( $dump ) ) {
			return '';
		}

		return $staged_path . DIRECTORY_SEPARATOR . $dump;
	}

	/**
	 * Returns the staged file root path for a restore apply.
	 *
	 * @param string $staged_path Staged restore path.
	 * @return string
	 */
	private function restore_apply_file_root_path( $staged_path ) {
		$report    = $this->read_restore_report( $staged_path . DIRECTORY_SEPARATOR . 'RESTORE_REPORT.json' );
		$file_root = isset( $report['file_root'] ) ? (string) $report['file_root'] : '';

		if ( ! $this->restore_report_relative_name_is_safe( $file_root ) ) {
			return '';
		}

		return $staged_path . DIRECTORY_SEPARATOR . $file_root;
	}

	/**
	 * Replaces the configured WordPress target files from staged files.
	 *
	 * @param string               $source_path Staged file root.
	 * @param string               $target_path Target WordPress path.
	 * @param array<string,string> $allowed_symlinks Exact symlinks permitted during production rollback.
	 * @return bool
	 */
	private function replace_target_files_from_staging( $source_path, $target_path, array $allowed_symlinks = array() ) {
		$target_path = $this->normalize_path( $target_path );

		if ( '' === $source_path || '' === $target_path || $target_path !== $this->wordpress_path() || $this->dangerous_restore_target_path( $target_path ) ) {
			return false;
		}

		if ( ! is_dir( $source_path ) || ! is_readable( $source_path ) || ! $this->directory_has_entries( $source_path ) || ! is_dir( $target_path ) || ! is_writable( $target_path ) ) {
			return false;
		}

		return $this->remove_restore_target_contents( $target_path ) && $this->copy_restore_directory_contents( $source_path, $target_path, $allowed_symlinks );
	}

	/**
	 * Parses a tar verbose symlink entry.
	 *
	 * @param string $line Tar verbose output line.
	 * @return array{path:string,target:string}|array<string,string>
	 */
	private function parse_tar_symlink_entry( $line ) {
		$line = trim( $line );
		if ( '' === $line || 'l' !== $line[0] || false === strpos( $line, ' -> ' ) ) {
			return array();
		}

		list( $left, $target ) = explode( ' -> ', $line, 2 );
		if ( 1 !== preg_match( '/^\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+(.+)$/', trim( $left ), $matches ) ) {
			return array();
		}

		return array(
			'path'   => trim( $matches[1] ),
			'target' => trim( $target ),
		);
	}

	/**
	 * Returns whether a restore report nested relative path is safe.
	 *
	 * @param string $value Report value.
	 * @return bool
	 */
	private function restore_report_relative_path_is_safe( $value ) {
		$value = trim( str_replace( '\\', '/', $value ) );
		if ( '' === $value || '/' === $value[0] || false !== strpos( $value, ':' ) ) {
			return false;
		}

		foreach ( explode( '/', $value ) as $segment ) {
			if ( '' === $segment || '.' === $segment || '..' === $segment ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns whether a directory has at least one child.
	 *
	 * @param string $path Directory path.
	 * @return bool
	 */
	private function directory_has_entries( $path ) {
		if ( ! is_dir( $path ) ) {
			return false;
		}

		$iterator = new FilesystemIterator( $path, FilesystemIterator::SKIP_DOTS );

		return $iterator->valid();
	}

	/**
	 * Removes target directory contents for a file restore.
	 *
	 * @param string $target_path Target WordPress path.
	 * @return bool
	 */
	private function remove_restore_target_contents( $target_path ) {
		if ( '' === $target_path || $this->dangerous_restore_target_path( $target_path ) || $target_path !== $this->wordpress_path() || ! is_dir( $target_path ) ) {
			return false;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $target_path, FilesystemIterator::SKIP_DOTS ),
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

		return true;
	}

	/**
	 * Copies staged directory contents into a target directory.
	 *
	 * @param string               $source_path Source directory.
	 * @param string               $target_path Target directory.
	 * @param array<string,string> $allowed_symlinks Exact symlinks permitted during production rollback.
	 * @return bool
	 */
	private function copy_restore_directory_contents( $source_path, $target_path, array $allowed_symlinks = array() ) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source_path, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			$relative_path = str_replace( DIRECTORY_SEPARATOR, '/', substr( $item->getPathname(), strlen( $source_path ) + 1 ) );
			$target_item   = $target_path . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative_path );
			if ( $item->isLink() ) {
				$link_target = readlink( $item->getPathname() );
				if ( ! $this->restore_report_relative_path_is_safe( $relative_path ) || false === $link_target || ! isset( $allowed_symlinks[ $relative_path ] ) || (string) $link_target !== $allowed_symlinks[ $relative_path ] || file_exists( $target_item ) || is_link( $target_item ) || ! symlink( (string) $link_target, $target_item ) ) {
					return false;
				}
				continue;
			}

			if ( $item->isDir() ) {
				if ( ! is_dir( $target_item ) && ! mkdir( $target_item, 0755, true ) ) {
					return false;
				}
				continue;
			}

			if ( ! $this->copy_restore_file( $item->getPathname(), $target_item ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Copies one staged restore file.
	 *
	 * Kept as a narrow override boundary for deterministic filesystem failure
	 * testing without exposing a runtime failure switch.
	 *
	 * @param string $source Source file.
	 * @param string $target Target file.
	 * @return bool
	 */
	protected function copy_restore_file( $source, $target ) {
		return copy( $source, $target );
	}

	/**
	 * Returns every symlink in a private rollback tree after safe path validation.
	 *
	 * @param string $source_path Extracted rollback file root.
	 * @return array<string,string>|false
	 */
	private function production_symlink_map( $source_path ) {
		$symlinks = array();
		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $source_path, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ( $iterator as $item ) {
				if ( ! $item->isLink() ) {
					continue;
				}

				$relative_path = str_replace( DIRECTORY_SEPARATOR, '/', substr( $item->getPathname(), strlen( $source_path ) + 1 ) );
				$link_target   = readlink( $item->getPathname() );
				if ( ! $this->restore_report_relative_path_is_safe( $relative_path ) || false === $link_target || '' === trim( (string) $link_target ) || false !== strpos( (string) $link_target, "\0" ) ) {
					return false;
				}
				$symlinks[ $relative_path ] = (string) $link_target;
			}
		} catch ( UnexpectedValueException $exception ) {
			return false;
		}
		ksort( $symlinks );

		return $symlinks;
	}

	/**
	 * Adds a restore dry-run check record.
	 *
	 * @param array<int,array<string,mixed>> $checks Checks.
	 * @param string                         $name Check name.
	 * @param bool                           $passed Whether check passed.
	 * @param string                         $message Check message.
	 * @return void
	 */
	private function add_restore_dry_run_check( array &$checks, $name, $passed, $message ) {
		$checks[] = array(
			'name'    => $name,
			'passed'  => (bool) $passed,
			'message' => $message,
		);
	}

	/**
	 * Adds a RESTORE_REPORT.json field check.
	 *
	 * @param array<int,array<string,mixed>> $checks Checks.
	 * @param array<string,mixed>            $report Restore report.
	 * @param string                         $key Report key.
	 * @param mixed                          $expected Expected value.
	 * @return void
	 */
	private function add_restore_dry_run_report_check( array &$checks, array $report, $key, $expected ) {
		$this->add_restore_dry_run_check(
			$checks,
			'restore_report_' . $key,
			array_key_exists( $key, $report ) && $report[ $key ] === $expected,
			'RESTORE_REPORT.json records ' . $key . ' as ' . var_export( $expected, true ) . '.'
		);
	}

	/**
	 * Reads a staged restore report.
	 *
	 * @param string $report_path Report path.
	 * @return array<string,mixed>
	 */
	private function read_restore_report( $report_path ) {
		if ( ! is_readable( $report_path ) ) {
			return array();
		}

		$report = json_decode( (string) file_get_contents( $report_path ), true );

		return is_array( $report ) ? $report : array();
	}

	/**
	 * Returns whether a restore scope includes files.
	 *
	 * @param string $scope Restore scope.
	 * @return bool
	 */
	private function restore_scope_includes_files( $scope ) {
		return in_array( $scope, array( 'files', 'files-and-database' ), true );
	}

	/**
	 * Returns whether a restore scope includes database.
	 *
	 * @param string $scope Restore scope.
	 * @return bool
	 */
	private function restore_scope_includes_database( $scope ) {
		return in_array( $scope, array( 'database', 'files-and-database' ), true );
	}

	/**
	 * Returns whether a restore report path name is a safe single segment.
	 *
	 * @param string $value Report value.
	 * @return bool
	 */
	private function restore_report_relative_name_is_safe( $value ) {
		$value = trim( $value );

		return '' !== $value && false === strpos( $value, '/' ) && false === strpos( $value, '\\' ) && '.' !== $value && '..' !== $value;
	}

	/**
	 * Returns whether a path is within a directory.
	 *
	 * @param string $base Base directory.
	 * @param string $path Child path.
	 * @return bool
	 */
	private function path_is_within_directory( $base, $path ) {
		$base = $this->normalize_path( $base );
		$path = $this->normalize_path( $path );

		return '' !== $base && ( $path === $base || 0 === strpos( $path, $base . '/' ) );
	}

	/**
	 * Returns whether a path resolves within a canonical directory.
	 *
	 * @param string $base Base directory.
	 * @param string $path Candidate path.
	 * @return bool
	 */
	private function path_is_within_directory_canonical( $base, $path ) {
		$base = $this->canonical_path( $base );
		$path = $this->canonical_path( $path );

		return '' !== $base && '' !== $path && $this->path_is_within_directory( $base, $path );
	}

	/**
	 * Returns whether a path resolves outside a canonical directory.
	 *
	 * @param string $base Base directory.
	 * @param string $path Candidate path.
	 * @return bool
	 */
	private function path_is_outside_directory_canonical( $base, $path ) {
		$base = $this->canonical_path( $base );
		$path = $this->canonical_path( $path );

		return '' !== $base && '' !== $path && ! $this->path_is_within_directory( $base, $path );
	}

	/**
	 * Returns whether two paths resolve to different canonical locations.
	 *
	 * @param string $left Left path.
	 * @param string $right Right path.
	 * @return bool
	 */
	private function canonical_paths_differ( $left, $right ) {
		$left  = $this->canonical_path( $left );
		$right = $this->canonical_path( $right );

		return '' !== $left && '' !== $right && $left !== $right;
	}

	/**
	 * Resolves an existing path or a path whose immediate parent exists.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private function canonical_path( $path ) {
		$resolved = realpath( $path );
		if ( false !== $resolved ) {
			return $this->path_comparison_value( $resolved );
		}

		$parent = realpath( dirname( $path ) );
		if ( false === $parent ) {
			return '';
		}

		return $this->path_comparison_value( $parent . DIRECTORY_SEPARATOR . basename( $path ) );
	}

	/**
	 * Normalizes a path for platform-aware comparisons.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private function path_comparison_value( $path ) {
		$path = $this->normalize_path( $path );

		return 'Windows' === PHP_OS_FAMILY ? strtolower( $path ) : $path;
	}

	/**
	 * Returns whether a restore target path is too broad to restore into.
	 *
	 * @param string $path Target path.
	 * @return bool
	 */
	private function dangerous_restore_target_path( $path ) {
		$path  = $this->normalize_path( $path );
		$lower = strtolower( $path );

		if ( in_array( $lower, array( '', '/', '/var', '/var/www' ), true ) ) {
			return true;
		}

		return 1 === preg_match( '/^[a-z]:$/', $lower ) || 1 === preg_match( '/^[a-z]:\/$/', $lower );
	}

	/**
	 * Checks whether a path exists as writable directory, or its parent can receive it.
	 *
	 * @param string $path Path.
	 * @return bool
	 */
	private function path_or_parent_is_writable_directory( $path ) {
		if ( is_dir( $path ) ) {
			return is_writable( $path );
		}

		$parent = dirname( $path );

		return '' !== $parent && is_dir( $parent ) && is_writable( $parent );
	}

	/**
	 * Counts failed restore checks.
	 *
	 * @param array<int,array<string,mixed>> $checks Checks.
	 * @return int
	 */
	private function restore_check_failure_count( array $checks ) {
		$failure_count = 0;
		foreach ( $checks as $check ) {
			if ( empty( $check['passed'] ) ) {
				++$failure_count;
			}
		}

		return $failure_count;
	}

}
