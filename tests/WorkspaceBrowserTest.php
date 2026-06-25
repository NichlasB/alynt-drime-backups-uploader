<?php
/**
 * Workspace browser tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/WorkspaceBrowserClient.php';

class WorkspaceBrowserTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'sanitize_text_field' )->returnArg();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_list_workspaces_returns_whitelisted_workspace_fields() {
		$browser = new Alynt_Drime_Backups_Uploader_Workspace_Browser(
			new Alynt_Drime_Backups_Uploader_Test_Workspace_Client(
				array(
					'workspaces' => array(
						array(
							'id'            => 1873,
							'name'          => 'My Workspace',
							'members_count' => 3,
							'api_token'     => 'secret',
							'currentUser'   => array(
								'role_name' => 'Workspace Owner',
								'is_owner'  => true,
							),
						),
					),
				)
			)
		);

		$result = $browser->list_workspaces();

		$this->assertSame( 1873, $result['workspaces'][0]['id'] );
		$this->assertSame( 'My Workspace', $result['workspaces'][0]['name'] );
		$this->assertSame( 3, $result['workspaces'][0]['members_count'] );
		$this->assertSame( 'Workspace Owner', $result['workspaces'][0]['role'] );
		$this->assertTrue( $result['workspaces'][0]['is_owner'] );
		$this->assertArrayNotHasKey( 'api_token', $result['workspaces'][0] );
	}

	public function test_list_workspaces_accepts_nested_data_response() {
		$browser = new Alynt_Drime_Backups_Uploader_Workspace_Browser(
			new Alynt_Drime_Backups_Uploader_Test_Workspace_Client(
				array(
					'data' => array(
						'workspaces' => array(
							array(
								'id'   => 42,
								'name' => 'Team Backups',
							),
						),
					),
				)
			)
		);

		$result = $browser->list_workspaces();

		$this->assertSame( 42, $result['workspaces'][0]['id'] );
		$this->assertSame( 'Team Backups', $result['workspaces'][0]['name'] );
	}

	public function test_list_workspaces_passes_drime_errors_through() {
		$browser = new Alynt_Drime_Backups_Uploader_Workspace_Browser(
			new Alynt_Drime_Backups_Uploader_Test_Workspace_Client(
				new WP_Error( 'drime_error', 'Drime failed.' )
			)
		);

		$result = $browser->list_workspaces();

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'drime_error', $result->get_error_code() );
	}
}
