<?php
/**
 * Uploader package tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey\Functions;

class UploaderPackageTest extends Alynt_Drime_Backups_Uploader_Uploader_Test_Case {
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
}
