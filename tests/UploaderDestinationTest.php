<?php
/**
 * Uploader destination tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey\Functions;

class UploaderDestinationTest extends Alynt_Drime_Backups_Uploader_Uploader_Test_Case {
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
}
