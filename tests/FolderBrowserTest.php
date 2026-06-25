<?php
/**
 * Folder browser tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/FolderBrowserClient.php';

class FolderBrowserTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_key' )->alias(
			function ( $value ) {
				return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) );
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_list_folders_returns_whitelisted_folder_fields() {
		$browser = $this->browser(
			array(
				'folders' => array(
					array(
						'id'        => 100,
						'hash'      => 'basehash',
						'name'      => 'Backups',
						'path'      => 'General/Files/Backups',
						'type'      => 'folder',
						'api_token' => 'secret',
					),
				),
			)
		);

		$result = $browser->list_folders();

		$this->assertSame( 100, $result['folders'][0]['id'] );
		$this->assertSame( 'basehash', $result['folders'][0]['hash'] );
		$this->assertSame( 'General/Files/Backups', $result['folders'][0]['path'] );
		$this->assertArrayNotHasKey( 'api_token', $result['folders'][0] );
	}

	public function test_list_folders_filters_by_query() {
		$browser = $this->browser(
			array(
				'folders' => array(
					array(
						'id'   => 100,
						'hash' => 'basehash',
						'name' => 'Backups',
						'path' => 'General/Files/Backups',
						'type' => 'folder',
					),
					array(
						'id'   => 101,
						'hash' => 'mediahash',
						'name' => 'Media',
						'path' => 'General/Files/Media',
						'type' => 'folder',
					),
				),
			)
		);

		$result = $browser->list_folders( '', 1, 'backup' );

		$this->assertCount( 1, $result['folders'] );
		$this->assertSame( 'Backups', $result['folders'][0]['name'] );
	}

	public function test_list_folders_converts_numeric_drime_paths_to_names() {
		$browser = $this->browser(
			array(
				'folders' => array(
					array(
						'id'        => 480542162,
						'hash'      => 'generalhash',
						'name'      => 'General',
						'parent_id' => null,
						'path'      => '480542162',
						'type'      => 'folder',
					),
					array(
						'id'        => 480542186,
						'hash'      => 'fileshash',
						'name'      => 'Files',
						'parent_id' => 480542162,
						'path'      => '480542162/480542186',
						'type'      => 'folder',
					),
					array(
						'id'        => 100,
						'hash'      => 'backuphash',
						'name'      => 'Backups',
						'parent_id' => 480542186,
						'path'      => '480542162/480542186/100',
						'type'      => 'folder',
					),
				),
			)
		);

		$result = $browser->list_folders( '', 1, 'general/files/backups' );

		$this->assertCount( 1, $result['folders'] );
		$this->assertSame( 'General/Files/Backups', $result['folders'][0]['path'] );
	}

	public function test_preview_destination_reports_existing_relative_path() {
		$client = new Alynt_Drime_Backups_Uploader_Test_Folder_Client(
			array(),
			array(
				'basehash' => array(
					array(
						'id'   => 200,
						'hash' => 'sitehash',
						'name' => 'site1.com',
						'type' => 'folder',
					),
				),
			),
			array(
				'basehash' => array( 'path' => 'General/Files/Backups' ),
			)
		);
		$browser = $this->browser_with_client( $client );

		$result = $browser->preview_destination( '100', 'basehash', 'site1.com' );

		$this->assertTrue( $result['exists'] );
		$this->assertSame( 'General/Files/Backups/site1.com', $result['destination_path'] );
		$this->assertSame( array( 'site1.com' ), $result['existing_segments'] );
		$this->assertSame( array(), $result['missing_segments'] );
	}

	public function test_preview_destination_reports_missing_relative_path_segments() {
		$client = new Alynt_Drime_Backups_Uploader_Test_Folder_Client(
			array(),
			array(
				'basehash' => array(),
			),
			array(
				'basehash' => array( 'path' => 'General/Files/Backups' ),
			)
		);
		$browser = $this->browser_with_client( $client );

		$result = $browser->preview_destination( '100', 'basehash', 'site1.com/daily' );

		$this->assertFalse( $result['exists'] );
		$this->assertSame( array( 'site1.com', 'daily' ), $result['missing_segments'] );
		$this->assertTrue( $result['read_only'] );
	}

	public function test_preview_destination_resolves_parent_id_when_hash_is_missing() {
		$client = new Alynt_Drime_Backups_Uploader_Test_Folder_Client(
			array(
				'folders' => array(
					array(
						'id'   => 100,
						'hash' => 'basehash',
						'name' => 'Backups',
						'path' => 'General/Files/Backups',
						'type' => 'folder',
					),
				),
			),
			array(
				'basehash' => array(),
			),
			array(
				'basehash' => array( 'path' => 'General/Files/Backups' ),
			)
		);
		$browser = $this->browser_with_client( $client );

		$result = $browser->preview_destination( '100', '', '' );

		$this->assertTrue( $result['exists'] );
		$this->assertSame( 'basehash', $result['parent_folder_hash'] );
	}

	public function test_preview_destination_converts_breadcrumb_array_to_display_path() {
		$client = new Alynt_Drime_Backups_Uploader_Test_Folder_Client(
			array(),
			array(
				'basehash' => array(),
			),
			array(
				'basehash' => array(
					'path' => array(
						array( 'name' => 'General' ),
						array( 'name' => 'Files' ),
						array( 'name' => 'Backups' ),
					),
				),
			)
		);
		$browser = $this->browser_with_client( $client );

		$result = $browser->preview_destination( '100', 'basehash', 'site1.com' );

		$this->assertSame( 'General/Files/Backups/site1.com', $result['destination_path'] );
	}

	public function test_preview_destination_returns_error_for_unknown_parent_id() {
		$browser = $this->browser(
			array(
				'folders' => array(),
			)
		);

		$result = $browser->preview_destination( '999', '', 'site1.com' );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'alynt_drime_parent_folder_not_found', $result->get_error_code() );
	}

	public function test_preview_destination_returns_drime_api_error() {
		$client = new Alynt_Drime_Backups_Uploader_Test_Folder_Client(
			array(),
			array(),
			array(
				'basehash' => new WP_Error( 'drime_error', 'Drime failed.' ),
			)
		);
		$browser = $this->browser_with_client( $client );

		$result = $browser->preview_destination( '100', 'basehash', 'site1.com' );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'drime_error', $result->get_error_code() );
	}

	/**
	 * Builds a browser with a fake client.
	 *
	 * @param array<string,mixed> $user_folders User folder response.
	 * @return Alynt_Drime_Backups_Uploader_Folder_Browser
	 */
	private function browser( array $user_folders ) {
		return $this->browser_with_client( new Alynt_Drime_Backups_Uploader_Test_Folder_Client( $user_folders ) );
	}

	/**
	 * Builds a browser with a provided fake client.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Test_Folder_Client $client Client.
	 * @return Alynt_Drime_Backups_Uploader_Folder_Browser
	 */
	private function browser_with_client( Alynt_Drime_Backups_Uploader_Test_Folder_Client $client ) {
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = array() ) {
				if ( Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME !== $name ) {
					return $default;
				}

				return array(
					'workspace_id' => 0,
				);
			}
		);

		$settings = new Alynt_Drime_Backups_Uploader_Settings();

		return new Alynt_Drime_Backups_Uploader_Folder_Browser( $settings, $client, new Alynt_Drime_Backups_Uploader_Logger( $settings ) );
	}
}
