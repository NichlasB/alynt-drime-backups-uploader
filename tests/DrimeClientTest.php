<?php
/**
 * Drime client tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class DrimeClientTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'sanitize_text_field' )->returnArg();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_upload_part_rejects_unsafe_signed_url() {
		Functions\expect( 'wp_http_validate_url' )
			->once()
			->with( 'http://127.0.0.1/internal' )
			->andReturn( false );

		Functions\expect( 'wp_safe_remote_request' )->never();

		$client = new Alynt_Drime_Backups_Uploader_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$result = $client->upload_part( 'http://127.0.0.1/internal', 'backup-bytes' );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'alynt_drime_unsafe_signed_url', $result->get_error_code() );
	}

	public function test_get_available_name_accepts_live_available_response_key() {
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = array() ) {
				if ( Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME !== $name ) {
					return $default;
				}

				return array(
					'api_token'     => 'test-token',
					'workspace_id'  => 0,
					'relative_path' => '/Alynt WPvivid Test Backups/plugin-tester.local',
				);
			}
		);

		Functions\expect( 'wp_json_encode' )->once()->with(
			array(
				'name'         => 'backup.zip',
				'workspaceId'  => 0,
				'relativePath' => '/Alynt WPvivid Test Backups/plugin-tester.local',
			)
		)->andReturn( '{"name":"backup.zip"}' );

		Functions\expect( 'wp_remote_request' )->once()->andReturn( array( 'body' => '{"available":"backup (1).zip","status":"success"}' ) );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"available":"backup (1).zip","status":"success"}' );

		$client = new Alynt_Drime_Backups_Uploader_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );

		$this->assertSame( 'backup (1).zip', $client->get_available_name( 'backup.zip' ) );
	}

	public function test_get_available_name_with_cached_parent_id_omits_relative_path() {
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = array() ) {
				if ( Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME !== $name ) {
					return $default;
				}

				return array(
					'api_token'     => 'test-token',
					'workspace_id'  => 0,
					'relative_path' => '/Alynt WPvivid Test Backups/plugin-tester.local',
				);
			}
		);

		Functions\expect( 'wp_json_encode' )->once()->with(
			array(
				'name'        => 'backup.zip',
				'workspaceId' => 0,
				'parentId'    => 12345,
			)
		)->andReturn( '{"name":"backup.zip"}' );

		Functions\expect( 'wp_remote_request' )->once()->andReturn( array( 'body' => '{"available":"backup (1).zip","status":"success"}' ) );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"available":"backup (1).zip","status":"success"}' );

		$client = new Alynt_Drime_Backups_Uploader_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );

		$this->assertSame( 'backup (1).zip', $client->get_available_name( 'backup.zip', 12345 ) );
	}

	public function test_create_multipart_upload_rejects_missing_upload_id() {
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = array() ) {
				if ( Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME !== $name ) {
					return $default;
				}

				return array(
					'api_token'        => 'test-token',
					'workspace_id'     => 0,
					'relative_path'    => '',
					'parent_folder_id' => '',
				);
			}
		);

		Functions\expect( 'wp_json_encode' )->once()->andReturn( '{"filename":"backup.zip"}' );
		Functions\expect( 'wp_remote_request' )->once()->andReturn( array( 'body' => '{"key":"multipart-key","status":"success"}' ) );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"key":"multipart-key","status":"success"}' );

		$client = new Alynt_Drime_Backups_Uploader_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$result = $client->create_multipart_upload( 'backup.zip', 1234, 'zip' );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'alynt_drime_malformed_response', $result->get_error_code() );
	}

	public function test_rate_limit_response_returns_api_error_with_status() {
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = array() ) {
				if ( Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME !== $name ) {
					return $default;
				}

				return array(
					'api_token'    => 'test-token',
					'workspace_id' => 0,
				);
			}
		);

		Functions\expect( 'wp_remote_request' )->once()->andReturn( array( 'body' => '{"message":"Too many requests."}' ) );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 429 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"message":"Too many requests."}' );

		$client = new Alynt_Drime_Backups_Uploader_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$result = $client->test_connection();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'alynt_drime_api_error', $result->get_error_code() );
		$this->assertSame( 'Too many requests.', $result->get_error_message() );
		$this->assertSame( array( 'status' => 429 ), $result->get_error_data() );
	}

	public function test_api_requests_use_extended_timeout() {
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = array() ) {
				if ( Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME !== $name ) {
					return $default;
				}

				return array(
					'api_token'    => 'test-token',
					'workspace_id' => 0,
				);
			}
		);

		Functions\expect( 'wp_remote_request' )->once()->with(
			Alynt_Drime_Backups_Uploader_Drime_Client::BASE_URL . '/drive/file-entries?workspaceId=0&perPage=1',
			\Mockery::on(
				function ( $args ) {
					return Alynt_Drime_Backups_Uploader_Drime_Client::API_REQUEST_TIMEOUT === $args['timeout'];
				}
			)
		)->andReturn( array( 'body' => '{"status":"success"}' ) );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"status":"success"}' );

		$client = new Alynt_Drime_Backups_Uploader_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );

		$this->assertSame( array( 'status' => 'success' ), $client->test_connection() );
	}

	public function test_get_logged_user_accepts_nested_user_response() {
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = array() ) {
				if ( Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME !== $name ) {
					return $default;
				}

				return array(
					'api_token' => 'test-token',
				);
			}
		);

		Functions\expect( 'wp_remote_request' )->once()->with(
			Alynt_Drime_Backups_Uploader_Drime_Client::BASE_URL . '/cli/loggedUser',
			\Mockery::type( 'array' )
		)->andReturn( array( 'body' => '{"user":{"id":42}}' ) );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"user":{"id":42}}' );

		$client = new Alynt_Drime_Backups_Uploader_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$result = $client->get_logged_user();

		$this->assertSame( 42, $result['id'] );
	}

	public function test_list_workspaces_uses_me_workspaces_endpoint() {
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = array() ) {
				if ( Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME !== $name ) {
					return $default;
				}

				return array(
					'api_token' => 'test-token',
				);
			}
		);

		Functions\expect( 'wp_remote_request' )->once()->with(
			Alynt_Drime_Backups_Uploader_Drime_Client::BASE_URL . '/me/workspaces',
			\Mockery::type( 'array' )
		)->andReturn( array( 'body' => '{"workspaces":[],"status":"success"}' ) );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"workspaces":[],"status":"success"}' );

		$client = new Alynt_Drime_Backups_Uploader_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );

		$this->assertSame( array( 'workspaces' => array(), 'status' => 'success' ), $client->list_workspaces() );
	}

	public function test_list_folder_entries_uses_hash_pagination_and_search() {
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = array() ) {
				if ( Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME !== $name ) {
					return $default;
				}

				return array(
					'api_token' => 'test-token',
				);
			}
		);

		Functions\expect( 'wp_remote_request' )->once()->with(
			Alynt_Drime_Backups_Uploader_Drime_Client::BASE_URL . '/drive/file-entries?workspaceId=0&type=folder&folderId=abc123&page=2&perPage=100&search=Backups',
			\Mockery::type( 'array' )
		)->andReturn( array( 'body' => '{"data":[]}' ) );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"data":[]}' );

		$client = new Alynt_Drime_Backups_Uploader_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );

		$this->assertSame( array( 'data' => array() ), $client->list_folder_entries( 0, 'abc123', 2, 'Backups' ) );
	}

	public function test_get_folder_path_uses_folder_path_endpoint() {
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = array() ) {
				if ( Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME !== $name ) {
					return $default;
				}

				return array(
					'api_token' => 'test-token',
				);
			}
		);

		Functions\expect( 'wp_remote_request' )->once()->with(
			Alynt_Drime_Backups_Uploader_Drime_Client::BASE_URL . '/folders/basehash/path',
			\Mockery::type( 'array' )
		)->andReturn( array( 'body' => '{"path":"General/Files/Backups"}' ) );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"path":"General/Files/Backups"}' );

		$client = new Alynt_Drime_Backups_Uploader_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );

		$this->assertSame( array( 'path' => 'General/Files/Backups' ), $client->get_folder_path( 'basehash' ) );
	}

	public function test_multipart_create_sends_parent_id_with_relative_path() {
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = array() ) {
				if ( Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME !== $name ) {
					return $default;
				}

				return array(
					'api_token'        => 'test-token',
					'workspace_id'     => 0,
					'parent_folder_id' => '321',
					'relative_path'    => '/site1.com',
				);
			}
		);

		Functions\expect( 'wp_json_encode' )->once()->with(
			array(
				'filename'     => 'backup.zip',
				'mime'         => 'application/zip',
				'size'         => 1234,
				'extension'    => 'zip',
				'workspaceId'  => 0,
				'relativePath' => '/site1.com',
				'parentId'     => 321,
			)
		)->andReturn( '{"filename":"backup.zip"}' );
		Functions\expect( 'wp_remote_request' )->once()->andReturn( array( 'body' => '{"key":"multipart-key","uploadId":"upload-id"}' ) );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"key":"multipart-key","uploadId":"upload-id"}' );

		$client = new Alynt_Drime_Backups_Uploader_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$result = $client->create_multipart_upload( 'backup.zip', 1234, 'zip' );

		$this->assertSame( 'multipart-key', $result['key'] );
	}

	public function test_trash_file_entry_uses_non_permanent_delete_request() {
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = array() ) {
				if ( Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME !== $name ) {
					return $default;
				}

				return array(
					'api_token' => 'test-token',
				);
			}
		);

		Functions\expect( 'wp_json_encode' )->once()->with(
			array(
				'entryIds'      => array( 123 ),
				'deleteForever' => false,
			)
		)->andReturn( '{"entryIds":[123],"deleteForever":false}' );

		Functions\expect( 'wp_remote_request' )->once()->with(
			Alynt_Drime_Backups_Uploader_Drime_Client::BASE_URL . '/file-entries/delete',
			\Mockery::on(
				function ( $args ) {
					return 'POST' === $args['method']
						&& '{"entryIds":[123],"deleteForever":false}' === $args['body'];
				}
			)
		)->andReturn( array( 'body' => '{"status":"success"}' ) );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"status":"success"}' );

		$client = new Alynt_Drime_Backups_Uploader_Drime_Client( new Alynt_Drime_Backups_Uploader_Settings() );

		$this->assertSame( array( 'status' => 'success' ), $client->trash_file_entry( 123 ) );
	}
}
