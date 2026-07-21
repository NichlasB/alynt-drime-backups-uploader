<?php
/**
 * Uploader local cleanup tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey\Functions;

class UploaderLocalCleanupTest extends Alynt_Drime_Backups_Uploader_Uploader_Test_Case {
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
}
