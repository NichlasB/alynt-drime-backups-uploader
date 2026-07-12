<?php
/**
 * Local server package retention helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prunes uploaded local server-runner packages.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Uploader_Local_Server_Retention {
	/**
	 * Prunes uploaded generic outbox packages when server-local retention is enabled.
	 *
	 * @return void
	 */
	private function maybe_prune_uploaded_server_packages() {
		$settings = $this->settings->get();
		if ( empty( $settings['server_local_retention_enabled'] ) || ! empty( $settings['delete_local_after_upload'] ) ) {
			return;
		}

		$outbox = isset( $settings['server_outbox_path'] ) ? (string) $settings['server_outbox_path'] : '';
		if ( '' === trim( $outbox ) || ! is_dir( $outbox ) ) {
			return;
		}

		$candidates = $this->uploaded_server_package_candidates( $outbox );
		if ( empty( $candidates ) ) {
			return;
		}

		usort(
			$candidates,
			function ( $a, $b ) {
				if ( $a['sort_time'] === $b['sort_time'] ) {
					return strcmp( $b['path'], $a['path'] );
				}

				return $b['sort_time'] <=> $a['sort_time'];
			}
		);

		$keep  = max( 1, absint( $settings['server_local_retention_keep'] ) );
		$prune = array_slice( $candidates, $keep );

		foreach ( $prune as $candidate ) {
			$this->delete_uploaded_server_package_candidate( $candidate );
		}
	}

	/**
	 * Builds uploaded server package candidates from registry records.
	 *
	 * @param string $outbox Configured server outbox path.
	 * @return array<int,array<string,mixed>>
	 */
	private function uploaded_server_package_candidates( $outbox ) {
		$candidates = array();

		foreach ( $this->registry->get_uploaded() as $signature => $record ) {
			if ( ! is_array( $record ) || ! $this->is_uploaded_server_package_record( $record ) ) {
				continue;
			}

			$path = isset( $record['path'] ) && is_scalar( $record['path'] ) ? (string) $record['path'] : '';
			if ( '' === $path || ! is_file( $path ) || ! $this->is_path_inside_directory( $path, $outbox ) ) {
				continue;
			}

			$mtime        = filemtime( $path );
			$candidates[] = array(
				'signature' => (string) $signature,
				'record'    => $record,
				'path'      => $path,
				'sort_time' => false === $mtime ? ( isset( $record['uploaded_at'] ) ? absint( $record['uploaded_at'] ) : 0 ) : (int) $mtime,
			);
		}

		return $candidates;
	}

	/**
	 * Returns whether an uploaded registry record is eligible server-package evidence.
	 *
	 * @param array<string,mixed> $record Uploaded registry record.
	 * @return bool
	 */
	private function is_uploaded_server_package_record( array $record ) {
		return isset( $record['producer_key'], $record['remote_status'] )
			&& 'generic_outbox' === (string) $record['producer_key']
			&& 'uploaded' === (string) $record['remote_status'];
	}

	/**
	 * Deletes one uploaded local server package candidate and recognized sidecars.
	 *
	 * @param array<string,mixed> $candidate Candidate.
	 * @return void
	 */
	private function delete_uploaded_server_package_candidate( array $candidate ) {
		$path = (string) $candidate['path'];

		if ( ! is_file( $path ) ) {
			return;
		}

		if ( ! wp_delete_file( $path ) ) {
			$this->logger->event( 'filesystem', 'error', 'server_local_retention_delete_failed', 'Uploaded server package pruning failed.', array( 'file' => basename( $path ) ) );
			return;
		}

		$record = isset( $candidate['record'] ) && is_array( $candidate['record'] ) ? $candidate['record'] : array();
		$this->delete_local_sidecars( $record, $path );

		$this->logger->event(
			'filesystem',
			'info',
			'server_local_retention_deleted',
			'Uploaded server package pruned from the local outbox.',
			array(
				'file'      => basename( $path ),
				'signature' => isset( $candidate['signature'] ) ? (string) $candidate['signature'] : '',
			)
		);
	}

	/**
	 * Returns whether a path is inside a configured directory.
	 *
	 * @param string $path Path to check.
	 * @param string $directory Parent directory.
	 * @return bool
	 */
	private function is_path_inside_directory( $path, $directory ) {
		$real_path      = realpath( $path );
		$real_directory = realpath( $directory );

		if ( false === $real_path || false === $real_directory ) {
			return false;
		}

		$real_path      = wp_normalize_path( $real_path );
		$real_directory = untrailingslashit( wp_normalize_path( $real_directory ) );

		return $real_path === $real_directory || 0 === strpos( $real_path, $real_directory . '/' );
	}
}
