<?php
/**
 * Backup registry tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class BackupRegistryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'sanitize_text_field' )->alias(
			function ( $value ) {
				return is_scalar( $value ) ? trim( (string) $value ) : '';
			}
		);
		Functions\when( 'sanitize_key' )->alias(
			function ( $key ) {
				return strtolower( preg_replace( '/[^a-zA-Z0-9_\\-]/', '', (string) $key ) );
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_mark_failed_preserves_safe_requeue_context() {
		$options  = array(
			Alynt_Drime_Backups_Uploader_Backup_Registry::FAILED_OPTION => array(),
		);
		$registry = $this->registry_with_options( $options );

		$this->assertTrue(
			$registry->mark_failed(
				'sig-one',
				'Gateway timeout.',
				array(
					'signature' => 'sig-one',
					'path'      => '/var/www/site/wp-content/wpvividbackups/backup.zip',
					'name'      => 'backup.zip',
					'attempts'  => 3,
					'producer_key'       => 'generic_outbox',
					'producer_label'     => 'Generic Outbox',
					'package_id'         => 'site-one-20260626',
					'filename'           => 'site-one.tar.gz',
					'backup_set_id'      => 'set-one',
					'backup_set_index'   => 1,
					'backup_set_total'   => 1,
					'manifest_path'      => '/var/backups/site-one.tar.gz.manifest.json',
					'checksum_path'      => '/var/backups/site-one.tar.gz.sha256',
					'checksum_algorithm' => 'sha256',
					'checksum_value'     => 'abc123',
					'wpvivid'            => array(
						'backup_id'     => 'abc123',
						'set_signature' => 'set-one',
						'set_files'     => array( 'one.zip', 'two.zip' ),
						'from_list'     => true,
					),
				)
			)
		);

		$record = $options[ Alynt_Drime_Backups_Uploader_Backup_Registry::FAILED_OPTION ]['sig-one'];

		$this->assertSame( 'Gateway timeout.', $record['message'] );
		$this->assertSame( 'backup.zip', $record['name'] );
		$this->assertSame( 3, $record['attempts'] );
		$this->assertSame( 'generic_outbox', $record['producer_key'] );
		$this->assertSame( 'Generic Outbox', $record['producer_label'] );
		$this->assertSame( 'site-one-20260626', $record['package_id'] );
		$this->assertSame( 'site-one.tar.gz', $record['filename'] );
		$this->assertSame( 'set-one', $record['backup_set_id'] );
		$this->assertSame( 1, $record['backup_set_index'] );
		$this->assertSame( 1, $record['backup_set_total'] );
		$this->assertSame( '/var/backups/site-one.tar.gz.manifest.json', $record['manifest_path'] );
		$this->assertSame( '/var/backups/site-one.tar.gz.sha256', $record['checksum_path'] );
		$this->assertSame( 'sha256', $record['checksum_algorithm'] );
		$this->assertSame( 'abc123', $record['checksum_value'] );
		$this->assertSame( 'abc123', $record['wpvivid']['backup_id'] );
		$this->assertSame( array( 'one.zip', 'two.zip' ), $record['wpvivid']['set_files'] );
	}

	public function test_remembered_drime_location_is_scoped_to_selected_base_parent() {
		$options  = array();
		$registry = $this->registry_with_options( $options );

		$this->assertTrue( $registry->remember_drime_location( 1, '/site1.com', 654, 321 ) );
		$this->assertTrue( $registry->remember_drime_location( 1, '/site1.com', 777, 999 ) );

		$this->assertSame( 654, $registry->get_drime_parent_id( 1, '/site1.com', 321 ) );
		$this->assertSame( 777, $registry->get_drime_parent_id( 1, '/site1.com', 999 ) );
		$this->assertSame( 0, $registry->get_drime_parent_id( 1, '/site1.com', 555 ) );
	}

	public function test_selected_base_parent_can_use_legacy_drime_location_cache() {
		$options  = array(
			Alynt_Drime_Backups_Uploader_Backup_Registry::DRIME_LOCATION_OPTION => array(
				$this->location_key( 1, '/site1.com' ) => array(
					'workspace_id'  => 1,
					'relative_path' => '/site1.com',
					'parent_id'     => 777,
					'updated_at'    => time(),
				),
			),
		);
		$registry = $this->registry_with_options( $options );

		$this->assertSame( 777, $registry->get_drime_parent_id( 1, '/site1.com', 321 ) );
	}

	public function test_selected_base_parent_ignores_legacy_record_for_different_base_parent() {
		$options  = array(
			Alynt_Drime_Backups_Uploader_Backup_Registry::DRIME_LOCATION_OPTION => array(
				$this->location_key( 1, '/site1.com' ) => array(
					'workspace_id'    => 1,
					'relative_path'   => '/site1.com',
					'base_parent_id'  => 999,
					'parent_id'       => 777,
					'updated_at'      => time(),
				),
			),
		);
		$registry = $this->registry_with_options( $options );

		$this->assertSame( 0, $registry->get_drime_parent_id( 1, '/site1.com', 321 ) );
	}

	/**
	 * Creates a registry with mocked option storage.
	 *
	 * @param array<string,mixed> $options Options.
	 * @return Alynt_Drime_Backups_Uploader_Backup_Registry
	 */
	private function registry_with_options( array &$options ) {
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = array() ) use ( &$options ) {
				return array_key_exists( $name, $options ) ? $options[ $name ] : $default;
			}
		);

		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) use ( &$options ) {
				$options[ $name ] = $value;
				return true;
			}
		);

		return new Alynt_Drime_Backups_Uploader_Backup_Registry();
	}

	/**
	 * Builds the registry location key.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param string $relative_path Relative path.
	 * @return string
	 */
	private function location_key( $workspace_id, $relative_path ) {
		return hash( 'sha256', absint( $workspace_id ) . '|' . $relative_path );
	}
}
