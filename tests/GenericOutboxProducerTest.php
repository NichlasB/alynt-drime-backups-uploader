<?php
/**
 * Generic outbox producer tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class GenericOutboxProducerTest extends TestCase {
	/**
	 * Temporary outbox directory.
	 *
	 * @var string
	 */
	private $outbox_dir = '';

	/**
	 * Mocked option storage.
	 *
	 * @var array<string,mixed>
	 */
	private $options = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->outbox_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'alynt-drime-outbox-' . uniqid( '', true );
		mkdir( $this->outbox_dir );

		$this->options = array(
			Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME => array(
				'server_outbox_path'   => $this->outbox_dir,
				'min_file_age_seconds' => 60,
			),
			Alynt_Drime_Backups_Uploader_Generic_Outbox_Producer::SNAPSHOT_OPTION => array(),
		);

		Functions\when( 'get_option' )->alias(
			function ( $name, $default = array() ) {
				return array_key_exists( $name, $this->options ) ? $this->options[ $name ] : $default;
			}
		);

		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) {
				$this->options[ $name ] = $value;
				return true;
			}
		);
	}

	protected function tearDown(): void {
		$this->remove_directory( $this->outbox_dir );
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_stable_archive_with_manifest_and_checksum_is_returned() {
		$archive  = $this->write_stable_file( 'site-full-20260625.tar.gz', 'archive bytes' );
		$manifest = $archive . '.manifest.json';
		$checksum = $archive . '.sha256';

		file_put_contents(
			$manifest,
			json_encode(
				array(
					'package_id'    => 'pkg-20260625-001',
					'backup_set_id' => 'set-20260625-001',
					'site_url'      => 'https://example.test',
					'created_at'    => 1782403200,
				)
			)
		);
		file_put_contents( $checksum, str_repeat( 'a', 64 ) . '  ' . basename( $archive ) );

		$producer = new Alynt_Drime_Backups_Uploader_Generic_Outbox_Producer( new Alynt_Drime_Backups_Uploader_Settings() );
		$first    = $producer->scan();
		$second   = $producer->scan();

		$this->assertSame( array(), $first['candidates'] );
		$this->assertCount( 1, $second['candidates'] );

		$candidate = $second['candidates'][0];
		$this->assertSame( 'generic_outbox', $candidate['producer_key'] );
		$this->assertSame( 'Generic Outbox', $candidate['producer_label'] );
		$this->assertSame( 'pkg-20260625-001', $candidate['package_id'] );
		$this->assertSame( 'set-20260625-001', $candidate['backup_set_id'] );
		$this->assertSame( basename( $archive ), $candidate['filename'] );
		$this->assertSame( wp_normalize_path( $manifest ), wp_normalize_path( $candidate['manifest_path'] ) );
		$this->assertSame( wp_normalize_path( $checksum ), wp_normalize_path( $candidate['checksum_path'] ) );
		$this->assertSame( 'sha256', $candidate['checksum_algorithm'] );
		$this->assertSame( str_repeat( 'a', 64 ), $candidate['checksum_value'] );
		$this->assertSame( 'https://example.test', $candidate['site_url'] );
		$this->assertSame( 1782403200, $candidate['created_at'] );
		$this->assertArrayNotHasKey( 'snapshot_key', $candidate );
		$this->assertSame( 'pkg-20260625-001', $candidate['metadata']['generic_outbox']['manifest']['package_id'] );
	}

	public function test_temporary_and_unsupported_files_are_not_returned() {
		$this->write_stable_file( 'site-full.zip', 'archive bytes' );
		$this->write_stable_file( 'site-full.zip.partial', 'partial bytes' );
		$this->write_stable_file( 'notes.txt', 'not a backup' );
		$this->write_stable_file( 'working.tar.gz.tmp', 'tmp bytes' );

		$producer = new Alynt_Drime_Backups_Uploader_Generic_Outbox_Producer( new Alynt_Drime_Backups_Uploader_Settings() );
		$producer->scan();
		$result = $producer->scan();

		$this->assertCount( 1, $result['candidates'] );
		$this->assertSame( 'site-full.zip', $result['candidates'][0]['filename'] );
	}

	/**
	 * Writes an old file fixture.
	 *
	 * @param string $name File basename.
	 * @param string $contents File contents.
	 * @return string
	 */
	private function write_stable_file( $name, $contents ) {
		$path = $this->outbox_dir . DIRECTORY_SEPARATOR . $name;
		file_put_contents( $path, $contents );
		touch( $path, time() - 120 );

		return $path;
	}

	/**
	 * Removes a directory recursively.
	 *
	 * @param string $directory Directory.
	 * @return void
	 */
	private function remove_directory( $directory ) {
		if ( '' === $directory || ! is_dir( $directory ) ) {
			return;
		}

		foreach ( glob( $directory . DIRECTORY_SEPARATOR . '*' ) as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}

		rmdir( $directory );
	}
}
