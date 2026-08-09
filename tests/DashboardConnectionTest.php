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
		Functions\when( 'sanitize_text_field' )->alias(
			function ( $value ) {
				return trim( (string) $value );
			}
		);
		Functions\when( 'esc_url_raw' )->alias(
			function ( $value ) {
				return trim( (string) $value );
			}
		);
		Functions\when( 'wp_json_encode' )->alias(
			function ( $value, $flags = 0, $depth = 512 ) {
				return json_encode( $value, $flags, $depth );
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

	public function test_parse_pairing_token_returns_safe_metadata_without_secret() {
		$connection = new Alynt_Drime_Backups_Uploader_Dashboard_Connection();
		$token      = $this->pairing_token();

		$parsed = $connection->parse_pairing_token( $token );

		$this->assertIsArray( $parsed );
		$this->assertSame( '00000000-0000-4000-8000-000000000000', $parsed['enrollment_id'] );
		$this->assertSame( 'https://control.sitesmanage.com', $parsed['dashboard_origin'] );
		$this->assertSame( 'https://client.example.com', $parsed['expected_client_origin'] );
		$this->assertArrayNotHasKey( 'secret', $parsed );
		$this->assertArrayNotHasKey( 'pairing_token', $parsed );
	}

	public function test_parse_rejects_unsafe_dashboard_origin() {
		$connection = new Alynt_Drime_Backups_Uploader_Dashboard_Connection();
		$token      = $this->pairing_token(
			array(
				'dashboard_origin' => 'http://127.0.0.1',
			)
		);

		$result = $connection->parse_pairing_token( $token );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'dashboard_origin_invalid', $result->get_error_code() );
	}

	public function test_parse_rejects_expired_token() {
		$connection = new Alynt_Drime_Backups_Uploader_Dashboard_Connection();
		$token      = $this->pairing_token(
			array(
				'expires_at' => '2020-01-01T00:00:00Z',
			)
		);

		$result = $connection->parse_pairing_token( $token );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'pairing_expired', $result->get_error_code() );
	}

	public function test_parse_action_stores_only_pairing_metadata() {
		$options    = array();
		$connection = $this->connection_with_options( $options );

		$state = $connection->update_shell(
			array(
				'connection_action' => 'parse_token',
				'read_only_opt_in'  => '1',
				'pairing_token'     => $this->pairing_token(),
			)
		);

		$this->assertSame( Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_TOKEN_READY, $state['connection_status'] );
		$this->assertSame( 'https://control.sitesmanage.com', $state['dashboard_origin'] );
		$this->assertSame( 'https://client.example.com', $state['expected_client_origin'] );
		$this->assertSame( '00000000-0000-4000-8000-000000000000', $state['pending_enrollment_id'] );
		$this->assertFalse( $state['dashboard_origin_confirmed'] );
		$this->assertFalse( $state['status_endpoint_enabled'] );
		$this->assertArrayNotHasKey( 'pairing_token', $state );
		$this->assertArrayNotHasKey( 'secret', $state );
		$this->assertStringNotContainsString( 'secret', wp_json_encode( $options[ Alynt_Drime_Backups_Uploader_Dashboard_Connection::OPTION_NAME ] ) );
	}

	public function test_confirm_origin_requires_reviewed_token_and_keeps_endpoint_disabled() {
		$options = array(
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::OPTION_NAME => array(
				'connection_status'        => Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_TOKEN_READY,
				'dashboard_origin'         => 'https://control.sitesmanage.com',
				'expected_client_origin'   => 'https://client.example.com',
				'pending_enrollment_id'    => '00000000-0000-4000-8000-000000000000',
				'pairing_expires_at'       => '2099-01-01T00:00:00+00:00',
			),
		);
		$connection = $this->connection_with_options( $options );

		$state = $connection->update_shell(
			array(
				'connection_action'         => 'confirm_origin',
				'confirm_dashboard_origin' => '1',
			)
		);

		$this->assertSame( Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_CONFIRMED, $state['connection_status'] );
		$this->assertTrue( $state['dashboard_origin_confirmed'] );
		$this->assertFalse( $state['status_endpoint_enabled'] );
		$this->assertFalse( $connection->is_status_endpoint_enabled() );
	}

	public function test_revoke_clears_pairing_metadata_and_keeps_endpoint_disabled() {
		$options = array(
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::OPTION_NAME => array(
				'connection_status'        => Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_CONFIRMED,
				'dashboard_origin'         => 'https://control.sitesmanage.com',
				'expected_client_origin'   => 'https://client.example.com',
				'pending_enrollment_id'    => '00000000-0000-4000-8000-000000000000',
				'pairing_expires_at'       => '2099-01-01T00:00:00+00:00',
				'dashboard_origin_confirmed' => true,
				'status_endpoint_enabled'  => true,
			),
		);
		$connection = $this->connection_with_options( $options );

		$state = $connection->update_shell(
			array(
				'connection_action' => 'revoke',
			)
		);

		$this->assertSame( Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_REVOKED, $state['connection_status'] );
		$this->assertSame( '', $state['dashboard_origin'] );
		$this->assertSame( '', $state['pending_enrollment_id'] );
		$this->assertFalse( $state['dashboard_origin_confirmed'] );
		$this->assertFalse( $state['status_endpoint_enabled'] );
		$this->assertGreaterThan( 0, $state['revoked_at'] );
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

	/**
	 * Creates a dashboard-style pairing token for tests.
	 *
	 * @param array<string,mixed> $overrides Payload overrides.
	 * @return string
	 */
	private function pairing_token( array $overrides = array() ) {
		$payload = array_merge(
			array(
				'protocol_version'        => 1,
				'enrollment_id'          => '00000000-0000-4000-8000-000000000000',
				'dashboard_origin'       => 'https://control.sitesmanage.com',
				'expected_client_origin' => 'https://client.example.com',
				'secret'                 => str_repeat( 'A', 43 ),
				'expires_at'             => '2099-01-01T00:00:00Z',
			),
			$overrides
		);
		$json    = wp_json_encode( $payload );
		$encoded = rtrim( strtr( base64_encode( $json ), '+/', '-_' ), '=' );

		return Alynt_Drime_Backups_Uploader_Dashboard_Connection::TOKEN_PREFIX . $encoded;
	}
}
