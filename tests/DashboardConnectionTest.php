<?php
/**
 * Dashboard connection tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class DashboardConnectionTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_key' )->alias(
			function ( $value ) {
				return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) );
			}
		);
		Functions\when( 'esc_url_raw' )->alias(
			function ( $value ) {
				return trim( (string) $value );
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_defaults_keep_dashboard_status_endpoint_disabled() {
		$defaults = Alynt_Drime_Backups_Uploader_Dashboard_Connection::defaults();

		$this->assertSame( Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_DISABLED, $defaults['connection_status'] );
		$this->assertFalse( $defaults['status_endpoint_enabled'] );
		$this->assertSame( '', $defaults['polling_credential_verifier'] );
	}

	public function test_prepare_records_local_intent_without_enabling_endpoint() {
		$options    = array();
		$connection = $this->connection_with_options( $options );

		$state = $connection->update_shell(
			array(
				'connection_action' => 'prepare',
				'read_only_opt_in'  => '1',
				'pairing_token'     => 'adb1.should-not-persist',
			)
		);

		$this->assertSame( Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_READY, $state['connection_status'] );
		$this->assertFalse( $state['status_endpoint_enabled'] );
		$this->assertArrayNotHasKey( 'pairing_token', $state );
		$this->assertSame( $state, $options[ Alynt_Drime_Backups_Uploader_Dashboard_Connection::OPTION_NAME ] );
	}

	public function test_prepare_without_opt_in_leaves_dashboard_disabled() {
		$options    = array();
		$connection = $this->connection_with_options( $options );

		$state = $connection->update_shell(
			array(
				'connection_action' => 'prepare',
			)
		);

		$this->assertSame( Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_DISABLED, $state['connection_status'] );
		$this->assertFalse( $state['status_endpoint_enabled'] );
	}

	public function test_disable_clears_connection_identifiers_and_verifier() {
		$options = array(
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::OPTION_NAME => array(
				'connection_status'          => Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_PAIRED,
				'dashboard_origin'           => 'https://control.sitesmanage.com',
				'dashboard_site_public_id'   => 'site_123',
				'polling_key_id'             => 'pk_123',
				'polling_credential_verifier' => hash( 'sha256', 'secret' ),
				'status_endpoint_enabled'    => true,
			),
		);
		$connection = $this->connection_with_options( $options );

		$state = $connection->update_shell(
			array(
				'connection_action' => 'disable',
			)
		);

		$this->assertSame( Alynt_Drime_Backups_Uploader_Dashboard_Connection::defaults(), $state );
		$this->assertFalse( $connection->is_status_endpoint_enabled() );
	}

	public function test_status_endpoint_requires_paired_state_and_enabled_flag() {
		$options = array(
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::OPTION_NAME => array(
				'connection_status'       => Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_READY,
				'status_endpoint_enabled' => true,
			),
		);
		$connection = $this->connection_with_options( $options );

		$this->assertFalse( $connection->is_status_endpoint_enabled() );

		$options[ Alynt_Drime_Backups_Uploader_Dashboard_Connection::OPTION_NAME ]['connection_status'] = Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_PAIRED;

		$this->assertTrue( $connection->is_status_endpoint_enabled() );
	}

	/**
	 * Creates connection storage with mocked option storage.
	 *
	 * @param array<string,mixed> $options Option storage.
	 * @return Alynt_Drime_Backups_Uploader_Dashboard_Connection
	 */
	private function connection_with_options( array &$options ) {
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

		return new Alynt_Drime_Backups_Uploader_Dashboard_Connection();
	}
}
