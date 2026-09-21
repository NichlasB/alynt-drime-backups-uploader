<?php
/**
 * Generic outbox package normalization helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.22
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds producer-neutral fields and safe metadata to outbox package records.
 *
 * @since 0.5.22
 */
trait Alynt_Drime_Backups_Uploader_Generic_Outbox_Producer_Normalization {
	/**
	 * Adds producer-neutral package fields.
	 *
	 * @param array<string,mixed> $info Scanned file info.
	 * @return array<string,mixed>
	 */
	private function normalize_package( array $info ) {
		$manifest            = $this->read_manifest( (string) $info['path'] );
		$checksum            = $this->read_checksum( (string) $info['path'] );
		$remote_index        = $this->read_remote_index( (string) $info['path'] );
		$remote_catalog      = $this->read_remote_catalog( (string) $info['path'] );
		$manifest_path       = isset( $manifest['_path'] ) ? (string) $manifest['_path'] : '';
		$checksum_path       = isset( $checksum['path'] ) ? (string) $checksum['path'] : '';
		$remote_index_path   = isset( $remote_index['path'] ) ? (string) $remote_index['path'] : '';
		$remote_catalog_path = isset( $remote_catalog['path'] ) ? (string) $remote_catalog['path'] : '';
		$package_id          = isset( $manifest['package_id'] ) && '' !== (string) $manifest['package_id'] ? (string) $manifest['package_id'] : (string) $info['signature'];

		$info['package_id']          = $package_id;
		$info['producer_key']        = $this->key();
		$info['producer_label']      = $this->label();
		$info['filename']            = (string) $info['name'];
		$info['modified_time']       = isset( $info['mtime'] ) ? (int) $info['mtime'] : 0;
		$info['backup_set_id']       = isset( $manifest['backup_set_id'] ) ? (string) $manifest['backup_set_id'] : $package_id;
		$info['backup_set_part']     = '';
		$info['backup_set_total']    = 1;
		$info['manifest_path']       = $manifest_path;
		$info['checksum_path']       = $checksum_path;
		$info['remote_index_path']   = $remote_index_path;
		$info['remote_catalog_path'] = $remote_catalog_path;
		$info['checksum_algorithm']  = isset( $checksum['algorithm'] ) ? (string) $checksum['algorithm'] : '';
		$info['checksum_value']      = isset( $checksum['value'] ) ? (string) $checksum['value'] : '';
		$info['site_url']            = isset( $manifest['site_url'] ) ? (string) $manifest['site_url'] : '';
		$info['created_at']          = isset( $manifest['created_at'] ) ? $this->normalize_timestamp( $manifest['created_at'] ) : 0;
		$info['metadata']            = array(
			'generic_outbox' => array(
				'manifest'       => $this->manifest_metadata( $manifest ),
				'checksum'       => $checksum,
				'remote_index'   => $remote_index,
				'remote_catalog' => $remote_catalog,
			),
		);

		return $info;
	}

	/**
	 * Normalizes manifest timestamps.
	 *
	 * @param mixed $value Timestamp value.
	 * @return int
	 */
	private function normalize_timestamp( $value ) {
		if ( is_numeric( $value ) ) {
			return absint( $value );
		}

		$timestamp = strtotime( (string) $value );

		return false === $timestamp ? 0 : (int) $timestamp;
	}

	/**
	 * Returns manifest metadata without internal helper keys.
	 *
	 * @param array<string,mixed> $manifest Manifest.
	 * @return array<string,mixed>
	 */
	private function manifest_metadata( array $manifest ) {
		unset( $manifest['_path'] );

		return $manifest;
	}
}
