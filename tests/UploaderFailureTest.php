<?php
/**
 * Uploader failure handling tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey\Functions;

class UploaderFailureTest extends Alynt_Drime_Backups_Uploader_Uploader_Test_Case {
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
		clearstatcache( true, $this->file );

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

	public function test_worker_that_loses_lock_does_not_clear_replacement_owner_or_mutate_queue_state() {
		$options = $this->base_options();
		$client  = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$client->upload_part_callback = function () use ( &$options ) {
			$options[ Alynt_Drime_Backups_Uploader_Uploader::UPLOAD_LOCK_OPTION ] = array(
				'owner'   => 'replacement-worker',
				'expires' => time() + 300,
			);
		};
		$uploader = $this->uploader_with_options( $options, $client );

		$result = $uploader->upload_next();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'alynt_drime_upload_lock_lost', $result->get_error_code() );
		$this->assertSame( 'replacement-worker', $options[ Alynt_Drime_Backups_Uploader_Uploader::UPLOAD_LOCK_OPTION ]['owner'] );
		$this->assertSame( 0, $options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['sig-one']['attempts'] );
		$this->assertArrayHasKey( Alynt_Drime_Backups_Uploader_Queue::ACTIVE_OPTION, $options );
		$this->assertSame( 'new-upload', $options[ Alynt_Drime_Backups_Uploader_Queue::ACTIVE_OPTION ]['upload_id'] );
	}

	public function test_upload_lock_renewal_preserves_owner_and_extends_expired_lease() {
		$options  = $this->base_options();
		$client   = new Alynt_Drime_Backups_Uploader_Test_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$uploader = $this->uploader_with_options( $options, $client );
		$acquire  = new ReflectionMethod( $uploader, 'acquire_upload_lock' );
		$renew    = new ReflectionMethod( $uploader, 'renew_upload_lock' );
		$release  = new ReflectionMethod( $uploader, 'release_upload_lock' );
		if ( PHP_VERSION_ID < 80100 ) {
			$acquire->setAccessible( true );
			$renew->setAccessible( true );
			$release->setAccessible( true );
		}

		$this->assertTrue( $acquire->invoke( $uploader ) );
		$owner = $options[ Alynt_Drime_Backups_Uploader_Uploader::UPLOAD_LOCK_OPTION ]['owner'];
		$options[ Alynt_Drime_Backups_Uploader_Uploader::UPLOAD_LOCK_OPTION ]['expires'] = time() - 1;

		$this->assertTrue( $renew->invoke( $uploader ) );
		$this->assertSame( $owner, $options[ Alynt_Drime_Backups_Uploader_Uploader::UPLOAD_LOCK_OPTION ]['owner'] );
		$this->assertGreaterThan( time(), $options[ Alynt_Drime_Backups_Uploader_Uploader::UPLOAD_LOCK_OPTION ]['expires'] );

		$release->invoke( $uploader );
		$this->assertArrayNotHasKey( Alynt_Drime_Backups_Uploader_Uploader::UPLOAD_LOCK_OPTION, $options );
	}
}
