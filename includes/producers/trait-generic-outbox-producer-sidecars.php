<?php
/**
 * Generic outbox sidecar readers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.21
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads manifest, checksum, remote index, and remote catalog sidecars.
 *
 * @since 0.5.21
 */
trait Alynt_Drime_Backups_Uploader_Generic_Outbox_Producer_Sidecars {
	/**
	 * Reads package manifest sidecar metadata.
	 *
	 * @param string $file Archive path.
	 * @return array<string,mixed>
	 */
	private function read_manifest( $file ) {
		foreach ( $this->sidecar_candidates( $file, 'manifest.json' ) as $candidate ) {
			if ( ! is_readable( $candidate ) ) {
				continue;
			}

			$contents = file_get_contents( $candidate );
			$decoded  = false !== $contents ? json_decode( $contents, true ) : null;
			if ( is_array( $decoded ) ) {
				$decoded['_path'] = $candidate;
				return $decoded;
			}
		}

		return array();
	}

	/**
	 * Reads checksum sidecar metadata.
	 *
	 * @param string $file Archive path.
	 * @return array<string,string>
	 */
	private function read_checksum( $file ) {
		foreach ( array( 'sha256', 'sha256sum' ) as $suffix ) {
			foreach ( $this->sidecar_candidates( $file, $suffix ) as $candidate ) {
				if ( ! is_readable( $candidate ) ) {
					continue;
				}

				$contents = trim( (string) file_get_contents( $candidate ) );
				if ( preg_match( '/^([a-fA-F0-9]{64})(?:\s+\*?.*)?$/', $contents, $matches ) ) {
					return array(
						'path'      => $candidate,
						'algorithm' => 'sha256',
						'value'     => strtolower( $matches[1] ),
					);
				}
			}
		}

		return array();
	}

	/**
	 * Reads package remote index sidecar metadata.
	 *
	 * @param string $file Archive path.
	 * @return array<string,mixed>
	 */
	private function read_remote_index( $file ) {
		$sidecar = $this->read_json_sidecar( $file, 'remote-index.json' );
		if ( empty( $sidecar ) ) {
			return array();
		}

		$decoded = $sidecar['data'];

		return array(
			'path'           => (string) $sidecar['path'],
			'schema_version' => isset( $decoded['schema_version'] ) ? (int) $decoded['schema_version'] : 0,
			'index_type'     => isset( $decoded['index_type'] ) && is_scalar( $decoded['index_type'] ) ? (string) $decoded['index_type'] : '',
			'package_count'  => isset( $decoded['package_count'] ) ? (int) $decoded['package_count'] : 0,
		);
	}

	/**
	 * Reads package remote catalog sidecar metadata.
	 *
	 * @param string $file Archive path.
	 * @return array<string,mixed>
	 */
	private function read_remote_catalog( $file ) {
		$sidecar = $this->read_json_sidecar( $file, 'remote-catalog.json' );
		if ( empty( $sidecar ) ) {
			return array();
		}

		$decoded = $sidecar['data'];

		return array(
			'path'           => (string) $sidecar['path'],
			'schema_version' => isset( $decoded['schema_version'] ) ? (int) $decoded['schema_version'] : 0,
			'catalog_type'   => isset( $decoded['catalog_type'] ) && is_scalar( $decoded['catalog_type'] ) ? (string) $decoded['catalog_type'] : '',
			'package_count'  => isset( $decoded['package_count'] ) ? (int) $decoded['package_count'] : 0,
		);
	}

	/**
	 * Reads a JSON sidecar file.
	 *
	 * @param string $file Archive path.
	 * @param string $suffix Sidecar suffix.
	 * @return array{path:string,data:array<string,mixed>}|array{}
	 */
	private function read_json_sidecar( $file, $suffix ) {
		foreach ( $this->sidecar_candidates( $file, $suffix ) as $candidate ) {
			if ( ! is_readable( $candidate ) ) {
				continue;
			}

			$contents = file_get_contents( $candidate );
			$decoded  = false !== $contents ? json_decode( $contents, true ) : null;
			if ( is_array( $decoded ) ) {
				return array(
					'path' => $candidate,
					'data' => $decoded,
				);
			}
		}

		return array();
	}

	/**
	 * Builds possible sidecar paths.
	 *
	 * @param string $file Archive path.
	 * @param string $suffix Sidecar suffix.
	 * @return array<int,string>
	 */
	private function sidecar_candidates( $file, $suffix ) {
		$stem = $this->archive_stem( $file );

		return array_values(
			array_unique(
				array(
					$file . '.' . $suffix,
					$stem . '.' . $suffix,
				)
			)
		);
	}
}
