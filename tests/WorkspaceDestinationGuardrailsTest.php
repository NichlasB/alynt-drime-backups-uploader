<?php
/**
 * Workspace destination guardrail tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class WorkspaceDestinationGuardrailsTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'wp_unslash' )->returnArg();
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

	public function test_personal_workspace_id_cannot_be_saved() {
		$options  = array();
		$settings = $this->settings_with_options( $options );

		$saved = $settings->update(
			array(
				'workspace_id' => 0,
			)
		);

		$this->assertTrue( is_wp_error( $saved ) );
		$this->assertSame( 'alynt_drime_workspace_not_allowed', $saved->get_error_code() );
	}

	public function test_blank_workspace_id_can_be_saved_during_initial_setup() {
		$options  = array();
		$settings = $this->settings_with_options( $options );

		$saved = $settings->update(
			array(
				'api_token'    => 'token',
				'workspace_id' => '',
			)
		);

		$this->assertFalse( is_wp_error( $saved ) );
		$this->assertSame( 0, $saved['workspace_id'] );
		$this->assertSame( 'token', $saved['api_token'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_workspace_allowlist_rejects_unlisted_workspace_id() {
		define( 'ALYNT_DRIME_ALLOWED_WORKSPACE_IDS', '42, 84' );

		$options  = array();
		$settings = $this->settings_with_options( $options );

		$rejected = $settings->update(
			array(
				'workspace_id' => 1873,
			)
		);

		$accepted = $settings->update(
			array(
				'workspace_id' => 42,
			)
		);

		$this->assertTrue( is_wp_error( $rejected ) );
		$this->assertSame( 'alynt_drime_workspace_not_allowed', $rejected->get_error_code() );
		$this->assertSame( 42, $accepted['workspace_id'] );
	}

	public function test_upload_path_contains_workspace_guard_before_drime_uploads() {
		$source = (string) file_get_contents( ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH . '/includes/class-uploader.php' );

		$this->assertStringContainsString( 'is_workspace_id_allowed', $source );
		$this->assertStringContainsString( 'alynt_drime_workspace_not_allowed', $source );
		$this->assertStringContainsString( 'workspace_not_allowed_message', $source );
	}

	/**
	 * Creates settings with mocked option storage.
	 *
	 * @param array<string,mixed> $options Option storage.
	 * @return Alynt_Drime_Backups_Uploader_Settings
	 */
	private function settings_with_options( array &$options ) {
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

		return new Alynt_Drime_Backups_Uploader_Settings();
	}
}
