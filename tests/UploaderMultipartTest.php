<?php
/**
 * Uploader multipart lifecycle tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey\Functions;

class UploaderMultipartTest extends Alynt_Drime_Backups_Uploader_Uploader_Test_Case {
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
}
