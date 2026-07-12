<?php
/**
 * Uploader tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class UploaderTest extends TestCase {
	/**
	 * Temporary upload file.
	 *
	 * @var string
	 */
	private $file = '';

	/**
	 * Additional temporary files.
	 *
	 * @var array<int,string>
	 */
	private $extra_files = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'alynt-drime-upload-' . uniqid( '', true ) . '.zip';
		$this->write_large_file( $this->file );
	}

	protected function tearDown(): void {
		if ( is_file( $this->file ) ) {
			unlink( $this->file );
		}

		foreach ( $this->extra_files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}

		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_fresh_multipart_upload_uploads_all_parts() {
		$options = $this->base_options();
		$client  = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 1, $client->create_multipart_calls );
		$this->assertSame( array( array( 1, 2 ) ), $client->signed_part_number_batches );
		$this->assertSame( array( 1, 2 ), $client->uploaded_part_numbers );
		$this->assertSame( array(), $options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ] );
		$this->assertArrayNotHasKey( Alynt_Drime_Backups_Uploader_Queue::ACTIVE_OPTION, $options );
	}

	public function test_successful_upload_preserves_producer_neutral_registry_context() {
		$options = $this->base_options();
		$options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one'] = array_merge(
			$options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one'],
			array(
				'producer_key'       => 'generic_outbox',
				'producer_label'     => 'Generic Outbox',
				'package_id'         => 'site-one-20260626',
				'filename'           => basename( $this->file ),
				'backup_set_id'      => 'site-one-20260626',
				'backup_set_index'   => 1,
				'backup_set_total'   => 1,
				'manifest_path'      => $this->file . '.manifest.json',
				'checksum_path'      => $this->file . '.sha256',
				'checksum_algorithm' => 'sha256',
				'checksum_value'     => 'abc123',
				'metadata'           => array(
					'generic_outbox' => array(
						'archive_format' => 'tar.gz',
					),
				),
			)
		);

		$client   = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();
		$record = $options[ Alynt_Drime_Backups_Uploader_Backup_Registry::UPLOADED_OPTION ]['sig-one'];

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 'generic_outbox', $record['producer_key'] );
		$this->assertSame( 'Generic Outbox', $record['producer_label'] );
		$this->assertSame( 'site-one-20260626', $record['package_id'] );
		$this->assertSame( basename( $this->file ), $record['filename'] );
		$this->assertSame( 'site-one-20260626', $record['backup_set_id'] );
		$this->assertSame( 1, $record['backup_set_index'] );
		$this->assertSame( 1, $record['backup_set_total'] );
		$this->assertSame( $this->file . '.manifest.json', $record['manifest_path'] );
		$this->assertSame( $this->file . '.sha256', $record['checksum_path'] );
		$this->assertSame( 'sha256', $record['checksum_algorithm'] );
		$this->assertSame( 'abc123', $record['checksum_value'] );
		$this->assertSame( 'tar.gz', $record['metadata']['generic_outbox']['archive_format'] );
	}

	public function test_successful_generic_outbox_upload_uploads_sidecars() {
		$manifest            = $this->file . '.manifest.json';
		$checksum            = $this->file . '.sha256';
		$index               = $this->file . '.remote-index.json';
		$catalog             = $this->file . '.remote-catalog.json';
		$this->extra_files[] = $manifest;
		$this->extra_files[] = $checksum;
		$this->extra_files[] = $index;
		$this->extra_files[] = $catalog;
		file_put_contents( $manifest, '{"package_id":"test"}' );
		file_put_contents( $checksum, 'abc123  ' . basename( $this->file ) );
		file_put_contents( $index, '{"schema_version":1,"index_type":"single_package_restore_index","package_count":1}' );
		file_put_contents( $catalog, '{"schema_version":1,"catalog_type":"folder_package_catalog_snapshot","package_count":1}' );
		$options  = $this->base_options();
		$options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one'] = array_merge(
			$options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one'],
			array(
				'producer_key'       => 'generic_outbox',
				'package_id'         => 'site-one-20260626',
				'manifest_path'      => $manifest,
				'checksum_path'      => $checksum,
				'remote_index_path'  => $index,
				'remote_catalog_path' => $catalog,
			)
		);

		$client   = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();
		$record = $options[ Alynt_Drime_Backups_Uploader_Backup_Registry::UPLOADED_OPTION ]['sig-one'];

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame(
			array(
				basename( $manifest ),
				basename( $checksum ),
				basename( $index ),
				basename( $catalog ),
			),
			$client->simple_upload_names
		);
		$this->assertCount( 4, $record['sidecars'] );
		$this->assertSame( 'manifest', $record['sidecars'][0]['type'] );
		$this->assertSame( basename( $manifest ), $record['sidecars'][0]['remote_name'] );
		$this->assertSame( 'checksum', $record['sidecars'][1]['type'] );
		$this->assertSame( basename( $checksum ), $record['sidecars'][1]['remote_name'] );
		$this->assertSame( 'remote_index', $record['sidecars'][2]['type'] );
		$this->assertSame( basename( $index ), $record['sidecars'][2]['remote_name'] );
		$this->assertSame( 'remote_catalog', $record['sidecars'][3]['type'] );
		$this->assertSame( basename( $catalog ), $record['sidecars'][3]['remote_name'] );
	}

	public function test_generic_outbox_upload_creates_package_folder_and_uploads_sidecars_there() {
		$manifest            = $this->file . '.manifest.json';
		$checksum            = $this->file . '.sha256';
		$this->extra_files[] = $manifest;
		$this->extra_files[] = $checksum;
		file_put_contents( $manifest, '{"package_id":"site-one-20260626"}' );
		file_put_contents( $checksum, 'abc123  ' . basename( $this->file ) );

		$options = $this->base_options();
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['parent_folder_id']     = '321';
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['parent_folder_hash']   = 'basehash';
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['server_relative_path'] = '/site1.com/server';
		$options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one'] = array_merge(
			$options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one'],
			array(
				'producer_key'  => 'generic_outbox',
				'package_id'    => 'site-one-20260626',
				'manifest_path' => $manifest,
				'checksum_path' => $checksum,
			)
		);

		$client   = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();
		$record = $options[ Alynt_Drime_Backups_Uploader_Backup_Registry::UPLOADED_OPTION ]['sig-one'];

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame(
			array(
				'site1.com'          => 321,
				'server'             => 654,
				'site-one-20260626'  => 655,
			),
			$client->created_folders
		);
		$this->assertSame( 656, $client->validate_parent_id );
		$this->assertSame( 656, $client->create_multipart_parent_id );
		$this->assertSame( 656, $client->create_s3_parent_id );
		$this->assertSame( array( 656, 656 ), $client->simple_upload_parent_ids );
		$this->assertSame( '/site1.com/server/site-one-20260626', $record['destination_relative_path'] );
		$this->assertArrayHasKey( $this->location_key( 1, '/site1.com/server/site-one-20260626', 321 ), $options[ Alynt_Drime_Backups_Uploader_Backup_Registry::DRIME_LOCATION_OPTION ] );
	}

	public function test_generic_outbox_upload_passes_package_folder_relative_path_without_selected_base_folder() {
		$options = $this->base_options();
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['relative_path']        = '/site1.com/shared';
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['server_relative_path'] = '/site1.com/server';
		$options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one'] = array_merge(
			$options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one'],
			array(
				'producer_key' => 'generic_outbox',
				'package_id'   => 'site-one-20260626',
			)
		);

		$client   = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();
		$record = $options[ Alynt_Drime_Backups_Uploader_Backup_Registry::UPLOADED_OPTION ]['sig-one'];

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertNull( $client->create_multipart_parent_id );
		$this->assertNull( $client->create_s3_parent_id );
		$this->assertSame( '/site1.com/server/site-one-20260626', $client->create_multipart_settings['relative_path'] );
		$this->assertSame( '/site1.com/server/site-one-20260626', $client->create_s3_settings['relative_path'] );
		$this->assertSame( '/site1.com/server/site-one-20260626', $record['destination_relative_path'] );
	}

	public function test_duplicate_generic_outbox_archive_can_upload_missing_sidecars() {
		$manifest            = $this->file . '.manifest.json';
		$checksum            = $this->file . '.sha256';
		$this->extra_files[] = $manifest;
		$this->extra_files[] = $checksum;
		file_put_contents( $manifest, '{"package_id":"test"}' );
		file_put_contents( $checksum, 'abc123  ' . basename( $this->file ) );
		$options  = $this->base_options();
		$options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one'] = array_merge(
			$options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one'],
			array(
				'producer_key'  => 'generic_outbox',
				'manifest_path' => $manifest,
				'checksum_path' => $checksum,
			)
		);

		$client                    = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$client->duplicate_names[] = basename( $this->file );
		$uploader                  = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();
		$record = $options[ Alynt_Drime_Backups_Uploader_Backup_Registry::UPLOADED_OPTION ]['sig-one'];

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 0, $client->create_multipart_calls );
		$this->assertSame(
			array(
				basename( $manifest ),
				basename( $checksum ),
			),
			$client->simple_upload_names
		);
		$this->assertTrue( $record['drime']['duplicate_skipped'] );
		$this->assertCount( 2, $record['sidecars'] );
		$this->assertArrayNotHasKey( 'sig-one', $options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ] );
	}

	public function test_generic_outbox_upload_accepts_archive_stem_sidecars() {
		$stem                = substr( $this->file, 0, -4 );
		$manifest            = $stem . '.manifest.json';
		$checksum            = $stem . '.sha256';
		$this->extra_files[] = $manifest;
		$this->extra_files[] = $checksum;
		file_put_contents( $manifest, '{"package_id":"test"}' );
		file_put_contents( $checksum, 'abc123  ' . basename( $this->file ) );
		$options  = $this->base_options();
		$options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one'] = array_merge(
			$options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one'],
			array(
				'producer_key'  => 'generic_outbox',
				'manifest_path' => $manifest,
				'checksum_path' => $checksum,
			)
		);

		$client   = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame(
			array(
				basename( $manifest ),
				basename( $checksum ),
			),
			$client->simple_upload_names
		);
	}

	public function test_multipart_resume_uses_existing_active_state_and_remote_parts() {
		$options = $this->base_options();
		$options[ Alynt_Drime_Backups_Uploader_Queue::ACTIVE_OPTION ] = array(
			'local_file'  => $this->file,
			'remote_name' => basename( $this->file ),
			'key'         => 'existing-key',
			'upload_id'   => 'existing-upload',
			'signature'   => 'sig-one',
			'chunk_size'  => 32 * 1048576,
			'updated_at'  => time(),
		);

		$client          = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$client->parts   = array(
			array(
				'PartNumber' => 1,
				'ETag'       => '"etag-1"',
			),
		);
		$uploader        = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 0, $client->create_multipart_calls );
		$this->assertSame( array( 2 ), $client->uploaded_part_numbers );
		$this->assertSame( 'existing-key', $client->completed_key );
		$this->assertSame( 'existing-upload', $client->completed_upload_id );
	}

	public function test_active_upload_with_changed_chunk_size_is_aborted_before_restart() {
		$options = $this->base_options();
		$options[ Alynt_Drime_Backups_Uploader_Queue::ACTIVE_OPTION ] = array(
			'local_file'  => $this->file,
			'remote_name' => basename( $this->file ),
			'key'         => 'old-key',
			'upload_id'   => 'old-upload',
			'signature'   => 'sig-one',
			'chunk_size'  => Alynt_Drime_Backups_Uploader_Drime_Client::MIN_MULTIPART_CHUNK_SIZE,
			'updated_at'  => time(),
		);

		$client   = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 'old-key', $client->aborted_key );
		$this->assertSame( 'old-upload', $client->aborted_upload_id );
		$this->assertSame( 1, $client->create_multipart_calls );
		$this->assertArrayNotHasKey( Alynt_Drime_Backups_Uploader_Queue::ACTIVE_OPTION, $options );
	}

	public function test_duplicate_validation_uses_cached_relative_path_parent_id() {
		$options = $this->base_options();
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['relative_path'] = '/Backups/Test Site';
		$options[ Alynt_Drime_Backups_Uploader_Backup_Registry::DRIME_LOCATION_OPTION ] = array(
			$this->location_key( 1, '/Backups/Test Site' ) => array(
				'workspace_id'  => 1,
				'relative_path' => '/Backups/Test Site',
				'parent_id'     => 12345,
				'updated_at'    => time(),
			),
		);

		$client   = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 12345, $client->validate_parent_id );
		$this->assertArrayNotHasKey( 'relativePath', $client->validate_files[0] );
	}

	public function test_duplicate_validation_keeps_relative_path_for_selected_base_folder() {
		$options = $this->base_options();
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['parent_folder_id'] = '321';
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['relative_path']    = '/site1.com';

		$client   = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 321, $client->validate_parent_id );
		$this->assertSame( '/site1.com', $client->validate_files[0]['relativePath'] );
	}

	public function test_selected_base_folder_creates_missing_relative_folder_before_upload() {
		$options = $this->base_options();
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['parent_folder_id']   = '321';
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['parent_folder_hash'] = 'basehash';
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['relative_path']      = '/site1.com';

		$client   = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( array( 'site1.com' => 321 ), $client->created_folders );
		$this->assertSame( 654, $client->validate_parent_id );
		$this->assertArrayNotHasKey( 'relativePath', $client->validate_files[0] );
		$this->assertSame( 654, $client->create_multipart_parent_id );
		$this->assertSame( 654, $client->create_s3_parent_id );
	}

	public function test_generic_outbox_upload_uses_server_relative_path_when_configured() {
		$options = $this->base_options();
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['parent_folder_id']      = '321';
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['parent_folder_hash']    = 'basehash';
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['relative_path']         = '/site1.com/shared';
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['server_relative_path']  = '/site1.com/server';
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['wpvivid_relative_path'] = '/site1.com/wpvivid';
		$options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one']['producer_key'] = 'generic_outbox';
		$options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one']['package_id']   = 'site-one-20260626';

		$client   = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();
		$record = $options[ Alynt_Drime_Backups_Uploader_Backup_Registry::UPLOADED_OPTION ]['sig-one'];

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( array( 'site1.com' => 321, 'server' => 654, 'site-one-20260626' => 655 ), $client->created_folders );
		$this->assertSame( '/site1.com/server/site-one-20260626', $record['destination_relative_path'] );
		$this->assertArrayHasKey( $this->location_key( 1, '/site1.com/server/site-one-20260626', 321 ), $options[ Alynt_Drime_Backups_Uploader_Backup_Registry::DRIME_LOCATION_OPTION ] );
		$this->assertArrayNotHasKey( $this->location_key( 1, '/site1.com/shared', 321 ), $options[ Alynt_Drime_Backups_Uploader_Backup_Registry::DRIME_LOCATION_OPTION ] );
	}

	public function test_wpvivid_upload_uses_wpvivid_relative_path_when_configured() {
		$options = $this->base_options();
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['parent_folder_id']      = '321';
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['parent_folder_hash']    = 'basehash';
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['relative_path']         = '/site1.com/shared';
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['server_relative_path']  = '/site1.com/server';
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['wpvivid_relative_path'] = '/site1.com/wpvivid';
		$options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one']['producer_key'] = 'wpvivid';

		$client   = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();
		$record = $options[ Alynt_Drime_Backups_Uploader_Backup_Registry::UPLOADED_OPTION ]['sig-one'];

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( array( 'site1.com' => 321, 'wpvivid' => 654 ), $client->created_folders );
		$this->assertSame( '/site1.com/wpvivid', $record['destination_relative_path'] );
		$this->assertArrayHasKey( $this->location_key( 1, '/site1.com/wpvivid', 321 ), $options[ Alynt_Drime_Backups_Uploader_Backup_Registry::DRIME_LOCATION_OPTION ] );
		$this->assertArrayNotHasKey( $this->location_key( 1, '/site1.com/shared', 321 ), $options[ Alynt_Drime_Backups_Uploader_Backup_Registry::DRIME_LOCATION_OPTION ] );
	}

	public function test_successful_relative_path_upload_remembers_drime_parent_id() {
		$options = $this->base_options();
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['relative_path'] = '/Backups/Test Site';

		$client                  = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$client->entry_parent_id = 67890;
		$uploader                = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();
		$key    = $this->location_key( 1, '/Backups/Test Site' );

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 67890, $options[ Alynt_Drime_Backups_Uploader_Backup_Registry::DRIME_LOCATION_OPTION ][ $key ]['parent_id'] );
	}

	public function test_successful_upload_reports_uploaded_registry_persistence_failure() {
		$options  = $this->base_options();
		$client   = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$uploader = $this->uploader_with_options(
			$options,
			$client,
			array( Alynt_Drime_Backups_Uploader_Backup_Registry::UPLOADED_OPTION )
		);

		$result = $uploader->upload_next();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'alynt_drime_state_save_failed', $result->get_error_code() );
		$this->assertArrayHasKey( 'sig-one', $options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ] );
		$this->assertSame( array(), $options[ Alynt_Drime_Backups_Uploader_Backup_Registry::UPLOADED_OPTION ] );
	}

	public function test_stale_active_upload_aborts_remote_multipart_before_continuing() {
		$options = $this->base_options();
		$options[ Alynt_Drime_Backups_Uploader_Queue::ACTIVE_OPTION ] = array(
			'local_file'  => $this->file,
			'remote_name' => basename( $this->file ),
			'key'         => 'stale-key',
			'upload_id'   => 'stale-upload',
			'signature'   => 'sig-one',
			'updated_at'  => time() - Alynt_Drime_Backups_Uploader_Uploader::STALE_ACTIVE_UPLOAD_SECONDS - 1,
		);

		$client   = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 'stale-key', $client->aborted_key );
		$this->assertSame( 'stale-upload', $client->aborted_upload_id );
		$this->assertArrayNotHasKey( Alynt_Drime_Backups_Uploader_Queue::ACTIVE_OPTION, $options );
	}

	public function test_upload_stops_when_connection_preflight_fails() {
		$options = $this->base_options();
		$client  = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$client->connection_result = new WP_Error( 'alynt_drime_api_error', 'Unauthenticated.', array( 'status' => 401 ) );
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'alynt_drime_api_error', $result->get_error_code() );
		$this->assertSame( 0, $client->validate_calls );
		$this->assertSame( 0, $client->create_multipart_calls );
		$this->assertSame( 1, $options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one']['attempts'] );
	}

	public function test_changed_queued_file_is_removed_for_rescan() {
		$options = $this->base_options();
		$options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one']['size']  = filesize( $this->file );
		$options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one']['mtime'] = filemtime( $this->file );
		file_put_contents( $this->file, 'changed backup bytes' );
		touch( $this->file, time() + 5 );

		$client   = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'alynt_drime_file_changed', $result->get_error_code() );
		$this->assertArrayNotHasKey( 'sig-one', $options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ] );
		$this->assertSame( 0, $client->validate_calls );
		$this->assertArrayHasKey( 'sig-one', $options[ Alynt_Drime_Backups_Uploader_Backup_Registry::FAILED_OPTION ] );
	}

	public function test_failed_upload_stores_requeue_context() {
		$options = $this->base_options();
		$client  = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$client->connection_result = new WP_Error( 'alynt_drime_api_error', 'Gateway timeout.', array( 'status' => 504 ) );
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();
		$failed = $options[ Alynt_Drime_Backups_Uploader_Backup_Registry::FAILED_OPTION ]['sig-one'];

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'Gateway timeout.', $failed['message'] );
		$this->assertSame( basename( $this->file ), $failed['name'] );
		$this->assertSame( $this->file, $failed['path'] );
		$this->assertSame( 1, $failed['attempts'] );
	}

	public function test_malformed_signed_url_response_fails_before_uploading_parts() {
		$options = $this->base_options();
		$client  = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$client->sign_response = array(
			'urls' => array(
				array(
					'partNumber' => 1,
					'url'        => array( 'not-a-url' ),
				),
			),
		);
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'alynt_drime_missing_part_url', $result->get_error_code() );
		$this->assertSame( array(), $client->uploaded_part_numbers );
		$this->assertSame( '', $client->completed_key );
		$this->assertSame( 1, $options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one']['attempts'] );
		$this->assertArrayNotHasKey( Alynt_Drime_Backups_Uploader_Queue::ACTIVE_OPTION, $options );
	}

	public function test_rate_limit_preflight_failure_does_not_upload_bytes() {
		$options = $this->base_options();
		$client  = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$client->connection_result = new WP_Error( 'alynt_drime_api_error', 'Too many requests.', array( 'status' => 429 ) );
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'alynt_drime_api_error', $result->get_error_code() );
		$this->assertSame( array( 'status' => 429 ), $result->get_error_data() );
		$this->assertSame( 0, $client->validate_calls );
		$this->assertSame( 0, $client->create_multipart_calls );
		$this->assertSame( array(), $client->uploaded_part_numbers );
		$this->assertSame( 1, $options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one']['attempts'] );
	}

	public function test_upload_worker_lock_blocks_overlapping_uploads() {
		$options = $this->base_options();
		$options[ Alynt_Drime_Backups_Uploader_Uploader::UPLOAD_LOCK_OPTION ] = array(
			'expires' => time() + 300,
		);

		$client   = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'alynt_drime_upload_locked', $result->get_error_code() );
		$this->assertSame( 0, $client->validate_calls );
		$this->assertSame( 0, $client->create_multipart_calls );
		$this->assertArrayHasKey( Alynt_Drime_Backups_Uploader_Uploader::UPLOAD_LOCK_OPTION, $options );
	}

	public function test_successful_upload_deletes_local_file_when_enabled() {
		$options = $this->base_options();
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['delete_local_after_upload'] = true;

		Functions\when( 'wp_delete_file' )->alias(
			function ( $path ) {
				return is_file( $path ) ? unlink( $path ) : false;
			}
		);

		$client   = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertFileDoesNotExist( $this->file );
	}

	public function test_server_local_retention_prunes_uploaded_server_packages_and_keeps_newest() {
		$older        = $this->temporary_file( 'older.tar.gz' );
		$newer        = $this->temporary_file( 'newer.tar.gz' );
		$older_sidecar = $older . '.manifest.json';
		$this->extra_files[] = $older_sidecar;
		file_put_contents( $older_sidecar, '{"package_id":"older"}' );

		touch( $older, time() - 300 );
		touch( $newer, time() - 200 );
		touch( $this->file, time() - 100 );

		$options = $this->base_options();
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['server_outbox_path']              = sys_get_temp_dir();
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['server_local_retention_enabled'] = true;
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['server_local_retention_keep']    = 2;
		$options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one']['producer_key']          = 'generic_outbox';
		$options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one']['package_id']            = 'current-package';
		$options[ Alynt_Drime_Backups_Uploader_Backup_Registry::UPLOADED_OPTION ]['sig-older']           = array(
			'producer_key'  => 'generic_outbox',
			'remote_status' => 'uploaded',
			'path'          => $older,
			'manifest_path' => $older_sidecar,
			'uploaded_at'   => time() - 300,
		);
		$options[ Alynt_Drime_Backups_Uploader_Backup_Registry::UPLOADED_OPTION ]['sig-newer']           = array(
			'producer_key'  => 'generic_outbox',
			'remote_status' => 'uploaded',
			'path'          => $newer,
			'uploaded_at'   => time() - 200,
		);

		Functions\when( 'wp_delete_file' )->alias(
			function ( $path ) {
				return is_file( $path ) ? unlink( $path ) : false;
			}
		);

		$client   = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertFileDoesNotExist( $older );
		$this->assertFileDoesNotExist( $older_sidecar );
		$this->assertFileExists( $newer );
		$this->assertFileExists( $this->file );
	}

	public function test_server_local_retention_ignores_wpvivid_files() {
		$server_old  = $this->temporary_file( 'server-old.tar.gz' );
		$server_new  = $this->temporary_file( 'server-new.tar.gz' );
		$wpvivid_file = $this->temporary_file( 'wpvivid.zip' );
		touch( $server_old, time() - 300 );
		touch( $server_new, time() - 100 );
		touch( $wpvivid_file, time() - 300 );

		$options = $this->base_options();
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['server_outbox_path']              = sys_get_temp_dir();
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['server_local_retention_enabled'] = true;
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['server_local_retention_keep']    = 1;
		$options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]                                    = array();
		$options[ Alynt_Drime_Backups_Uploader_Backup_Registry::UPLOADED_OPTION ]['sig-server-old']      = array(
			'producer_key'  => 'generic_outbox',
			'remote_status' => 'uploaded',
			'path'          => $server_old,
			'uploaded_at'   => time() - 300,
		);
		$options[ Alynt_Drime_Backups_Uploader_Backup_Registry::UPLOADED_OPTION ]['sig-server-new']      = array(
			'producer_key'  => 'generic_outbox',
			'remote_status' => 'uploaded',
			'path'          => $server_new,
			'uploaded_at'   => time() - 100,
		);
		$options[ Alynt_Drime_Backups_Uploader_Backup_Registry::UPLOADED_OPTION ]['sig-wpvivid']         = array(
			'producer_key'  => 'wpvivid',
			'remote_status' => 'uploaded',
			'path'          => $wpvivid_file,
			'uploaded_at'   => time() - 300,
		);

		Functions\when( 'wp_delete_file' )->alias(
			function ( $path ) {
				return is_file( $path ) ? unlink( $path ) : false;
			}
		);

		$client   = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'alynt_drime_queue_empty', $result->get_error_code() );
		$this->assertFileDoesNotExist( $server_old );
		$this->assertFileExists( $server_new );
		$this->assertFileExists( $wpvivid_file );
	}

	public function test_successful_split_set_upload_waits_before_local_delete() {
		$options = $this->base_options();
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['delete_local_after_upload'] = true;
		$options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one']['wpvivid'] = $this->wpvivid_metadata(
			array(
				basename( $this->file ),
				'missing-part.zip',
			)
		);

		Functions\when( 'wp_delete_file' )->alias(
			function ( $path ) {
				return is_file( $path ) ? unlink( $path ) : false;
			}
		);

		$client   = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertFileExists( $this->file );
		$this->assertSame( 'set-one', $options[ Alynt_Drime_Backups_Uploader_Backup_Registry::UPLOADED_OPTION ]['sig-one']['wpvivid']['set_signature'] );
	}

	public function test_successful_final_split_set_upload_deletes_all_uploaded_parts() {
		$previous = $this->temporary_file( 'previous-part.zip' );
		$options  = $this->base_options();
		$options[ Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME ]['delete_local_after_upload'] = true;
		$options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one']['wpvivid'] = $this->wpvivid_metadata(
			array(
				basename( $previous ),
				basename( $this->file ),
			)
		);
		$options[ Alynt_Drime_Backups_Uploader_Backup_Registry::UPLOADED_OPTION ]['sig-prev'] = array(
			'path'          => $previous,
			'remote_name'   => basename( $previous ),
			'uploaded_at'   => time(),
			'remote_status' => 'uploaded',
		);

		Functions\when( 'wp_delete_file' )->alias(
			function ( $path ) {
				return is_file( $path ) ? unlink( $path ) : false;
			}
		);

		$client   = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertFileDoesNotExist( $previous );
		$this->assertFileDoesNotExist( $this->file );
	}

	public function test_clear_active_upload_reports_abort_failure() {
		$options = $this->base_options();
		$options[ Alynt_Drime_Backups_Uploader_Queue::ACTIVE_OPTION ] = array(
			'local_file'  => $this->file,
			'remote_name' => basename( $this->file ),
			'key'         => 'active-key',
			'upload_id'   => 'active-upload',
			'signature'   => 'sig-one',
			'updated_at'  => time(),
		);

		$client               = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$client->abort_result = new WP_Error( 'alynt_drime_abort_failed', 'Abort failed.' );
		$uploader             = $this->uploader_with_options( $options, $client );

		$result = $uploader->clear_active_upload();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'alynt_drime_abort_failed', $result->get_error_code() );
		$this->assertArrayHasKey( Alynt_Drime_Backups_Uploader_Queue::ACTIVE_OPTION, $options );
	}

	/**
	 * Creates base mocked options.
	 *
	 * @return array<string,mixed>
	 */
	private function base_options() {
		return array(
			Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME => array(
				'api_token'                => 'token',
				'workspace_id'             => 1,
				'multipart_chunk_size_mb'  => 32,
				'diagnostics_enabled'      => false,
			),
			Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION => array(
				'sig-one' => array(
					'signature' => 'sig-one',
					'path'      => $this->file,
					'name'      => basename( $this->file ),
					'attempts'  => 0,
				),
			),
			Alynt_Drime_Backups_Uploader_Backup_Registry::UPLOADED_OPTION => array(),
			Alynt_Drime_Backups_Uploader_Backup_Registry::FAILED_OPTION   => array(),
		);
	}

	/**
	 * Creates an uploader with mocked option storage.
	 *
	 * @param array<string,mixed>                            $options Options.
	 * @param Alynt_Drime_Backups_Uploader_Test_Drime_Client $client Client.
	 * @param array<int,string>                              $failed_update_options Options that should fail updates.
	 * @return Alynt_Drime_Backups_Uploader_Uploader
	 */
	private function uploader_with_options( array &$options, Alynt_Drime_Backups_Uploader_Test_Drime_Client $client, array $failed_update_options = array() ) {
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = array() ) use ( &$options ) {
				return array_key_exists( $name, $options ) ? $options[ $name ] : $default;
			}
		);

		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) use ( &$options, $failed_update_options ) {
				if ( in_array( $name, $failed_update_options, true ) ) {
					return false;
				}

				$options[ $name ] = $value;
				return true;
			}
		);

		Functions\when( 'add_option' )->alias(
			function ( $name, $value ) use ( &$options ) {
				if ( array_key_exists( $name, $options ) ) {
					return false;
				}

				$options[ $name ] = $value;
				return true;
			}
		);

		Functions\when( 'delete_option' )->alias(
			function ( $name ) use ( &$options ) {
				unset( $options[ $name ] );
				return true;
			}
		);

		Functions\when( 'sanitize_key' )->alias(
			function ( $key ) {
				return strtolower( preg_replace( '/[^a-zA-Z0-9_\\-]/', '', (string) $key ) );
			}
		);

		Functions\when( 'sanitize_text_field' )->alias(
			function ( $value ) {
				return is_scalar( $value ) ? trim( (string) $value ) : '';
			}
		);

		$settings = new Alynt_Drime_Backups_Uploader_Settings();

		return new Alynt_Drime_Backups_Uploader_Uploader(
			$settings,
			$client,
			new Alynt_Drime_Backups_Uploader_Queue(),
			new Alynt_Drime_Backups_Uploader_Backup_Registry(),
			new Alynt_Drime_Backups_Uploader_Logger( $settings )
		);
	}

	/**
	 * Writes a two-part upload fixture.
	 *
	 * @param string $path Path.
	 * @return void
	 */
	private function write_large_file( $path ) {
		$handle = fopen( $path, 'wb' );
		fseek( $handle, 32 * 1048576 + 100 );
		fwrite( $handle, 'x' );
		fclose( $handle );
	}

	/**
	 * Creates a temporary local backup file.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private function temporary_file( $name ) {
		$path                = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'alynt-drime-' . uniqid( '', true ) . '-' . $name;
		$this->extra_files[] = $path;
		file_put_contents( $path, 'backup' );

		return $path;
	}

	/**
	 * Builds WPvivid listed-set metadata.
	 *
	 * @param array<int,string> $set_files Set files.
	 * @return array<string,mixed>
	 */
	private function wpvivid_metadata( array $set_files ) {
		return array(
			'backup_id'     => 'backup-one',
			'set_signature' => 'set-one',
			'set_files'     => $set_files,
			'from_list'     => true,
			'file_count'    => count( $set_files ),
		);
	}

	/**
	 * Builds the registry location key.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param string $relative_path Relative path.
	 * @return string
	 */
	private function location_key( $workspace_id, $relative_path, $base_parent_id = 0 ) {
		$key = absint( $workspace_id ) . '|';
		if ( $base_parent_id > 0 ) {
			$key .= absint( $base_parent_id ) . '|';
		}

		return hash( 'sha256', $key . $relative_path );
	}
}

class Alynt_Drime_Backups_Uploader_Test_Drime_Client extends Alynt_Drime_Backups_Uploader_Drime_Client {
	public $create_multipart_calls = 0;
	public $uploaded_part_numbers  = array();
	public $parts                  = array();
	public $completed_key          = '';
	public $completed_upload_id    = '';
	public $validate_files         = array();
	public $validate_parent_id     = null;
	public $create_multipart_parent_id = null;
	public $create_s3_parent_id    = null;
	public $create_multipart_settings = array();
	public $create_s3_settings     = array();
	public $entry_parent_id        = 0;
	public $aborted_key            = '';
	public $aborted_upload_id      = '';
	public $connection_result      = true;
	public $validate_calls         = 0;
	public $sign_response          = array();
	public $signed_part_number_batches = array();
	public $abort_result           = array( 'status' => 'success' );
	public $children               = array();
	public $created_folders        = array();
	public $simple_upload_names    = array();
	public $simple_upload_parent_ids = array();
	public $simple_upload_settings = array();
	public $duplicate_names        = array();
	private $next_folder_id        = 654;

	public function test_connection() {
		return $this->connection_result;
	}

	public function validate_upload( array $files, $parent_id = null ) {
		++$this->validate_calls;
		$this->validate_files     = $files;
		$this->validate_parent_id = $parent_id;

		$duplicates = array();
		foreach ( $files as $file ) {
			if ( is_array( $file ) && isset( $file['name'] ) && in_array( (string) $file['name'], $this->duplicate_names, true ) ) {
				$duplicates[] = $file;
			}
		}

		return array( 'duplicates' => $duplicates );
	}

	public function simple_upload( $path, $remote_name, $parent_id = null, ?array $settings_override = null ) {
		unset( $path );
		$this->simple_upload_names[] = $remote_name;
		$this->simple_upload_parent_ids[] = $parent_id;
		$this->simple_upload_settings[] = $settings_override;

		return array(
			'fileEntry' => array(
				'id'   => 456,
				'name' => $remote_name,
				'parent_id' => null !== $parent_id ? $parent_id : 0,
			),
		);
	}

	public function create_multipart_upload( $filename, $size, $extension, $parent_id = null, ?array $settings_override = null ) {
		unset( $filename, $size, $extension );
		++$this->create_multipart_calls;
		$this->create_multipart_parent_id = $parent_id;
		$this->create_multipart_settings  = null === $settings_override ? array() : $settings_override;
		return array(
			'key'      => 'new-key',
			'uploadId' => 'new-upload',
		);
	}

	public function get_uploaded_parts( $key, $upload_id ) {
		unset( $key, $upload_id );
		return array( 'parts' => $this->parts );
	}

	public function abort_multipart_upload( $key, $upload_id ) {
		$this->aborted_key       = $key;
		$this->aborted_upload_id = $upload_id;

		return $this->abort_result;
	}

	public function sign_part_urls( $key, $upload_id, array $part_numbers ) {
		unset( $key, $upload_id );
		$this->signed_part_number_batches[] = $part_numbers;
		if ( ! empty( $this->sign_response ) ) {
			return $this->sign_response;
		}

		$urls = array();
		foreach ( $part_numbers as $part_number ) {
			$urls[] = array(
				'partNumber' => $part_number,
				'url'        => 'https://example.test/upload/' . $part_number,
			);
		}

		return array(
			'urls' => $urls,
		);
	}

	public function upload_part( $url, $data ) {
		unset( $url, $data );
		$this->uploaded_part_numbers[] = count( $this->uploaded_part_numbers ) + ( empty( $this->parts ) ? 1 : 2 );
		return '"etag-' . end( $this->uploaded_part_numbers ) . '"';
	}

	public function complete_multipart_upload( $key, $upload_id, array $parts ) {
		$this->completed_key       = $key;
		$this->completed_upload_id = $upload_id;
		return array(
			'location' => 'https://example.test/object.zip',
			'parts'    => $parts,
		);
	}

	public function create_s3_entry( $key, $client_name, $size, $extension, $parent_id = null, ?array $settings_override = null ) {
		unset( $key, $client_name, $size, $extension );
		$this->create_s3_parent_id = $parent_id;
		$this->create_s3_settings  = null === $settings_override ? array() : $settings_override;
		$file_entry = array( 'id' => 123 );

		if ( $this->entry_parent_id > 0 ) {
			$file_entry['parent_id'] = $this->entry_parent_id;
		} elseif ( null !== $parent_id ) {
			$file_entry['parent_id'] = $parent_id;
		}

		return array( 'fileEntry' => $file_entry );
	}

	public function list_folder_entries( $workspace_id, $folder_hash, $page = 1, $query = '' ) {
		unset( $workspace_id, $page, $query );

		return array(
			'data' => isset( $this->children[ $folder_hash ] ) ? $this->children[ $folder_hash ] : array(),
		);
	}

	public function create_folder( $workspace_id, $name, $parent_id = 0 ) {
		unset( $workspace_id );
		$this->created_folders[ $name ] = $parent_id;
		$id = $this->next_folder_id++;

		return array(
			'folder' => array(
				'id'   => $id,
				'hash' => 'createdhash' . $id,
				'name' => $name,
			),
		);
	}
}
