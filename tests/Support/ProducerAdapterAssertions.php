<?php
/**
 * Producer adapter test assertions.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

trait Alynt_Drime_Backups_Uploader_Test_Producer_Adapter_Assertions {
	/**
	 * Asserts that a candidate has the normalized producer package shape.
	 *
	 * @param array<string,mixed> $candidate Candidate package record.
	 * @param string              $producer_key Expected producer key.
	 * @param string              $producer_label Expected producer label.
	 * @return void
	 */
	private function assert_normalized_producer_candidate( array $candidate, $producer_key, $producer_label ) {
		foreach ( array( 'signature', 'path', 'name', 'producer_key', 'producer_label', 'package_id', 'filename', 'backup_set_id', 'manifest_path', 'checksum_path', 'remote_index_path', 'checksum_algorithm', 'checksum_value', 'site_url' ) as $key ) {
			$this->assertArrayHasKey( $key, $candidate );
			$this->assertIsString( $candidate[ $key ] );
		}

		foreach ( array( 'size', 'mtime', 'modified_time', 'backup_set_total', 'created_at' ) as $key ) {
			$this->assertArrayHasKey( $key, $candidate );
			$this->assertIsInt( $candidate[ $key ] );
		}

		$this->assertSame( $producer_key, $candidate['producer_key'] );
		$this->assertSame( $producer_label, $candidate['producer_label'] );
		$this->assertSame( $candidate['name'], $candidate['filename'] );
		$this->assertArrayHasKey( 'metadata', $candidate );
		$this->assertIsArray( $candidate['metadata'] );
		$this->assertArrayNotHasKey( 'snapshot_key', $candidate );
	}
}
