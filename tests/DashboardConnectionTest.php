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
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			function ( $response ) {
				return isset( $response['response']['code'] ) ? $response['response']['code'] : 0;
			}
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			function ( $response ) {
				return isset( $response['body'] ) ? $response['body'] : '';
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

	public function test_complete_pairing_requires_confirmed_reviewed_token_metadata() {
		$options    = array();
		$connection = $this->connection_with_options( $options );

		$state = $connection->complete_pairing_from_token(
			$this->pairing_token(),
			'https://client.example.com',
			'11111111-1111-4111-8111-111111111111',
			'0.5.3',
			function () {
				$this->fail( 'Enrollment transport should not run before dashboard origin confirmation.' );
			}
		);

		$this->assertSame( 'dashboard_origin_confirmation_required', $state['last_error_code'] );
		$this->assertFalse( $state['status_endpoint_enabled'] );
	}

	public function test_complete_pairing_stores_verifier_and_enables_status_endpoint_without_secrets() {
		$options = $this->confirmed_pairing_options();
		$secret  = str_repeat( 'B', 43 );
		$called  = false;
		$connection = $this->connection_with_options( $options );

		$state = $connection->complete_pairing_from_token(
			$this->pairing_token(),
			'https://client.example.com',
			'11111111-1111-4111-8111-111111111111',
			'0.5.3',
			function ( $url, $args ) use ( &$called, $secret ) {
				$called = true;
				$body   = json_decode( $args['body'], true );

				$this->assertSame( 'https://control.sitesmanage.com/wp-json/alynt-drime-backups-dashboard/v1/enroll', $url );
				$this->assertSame( 'Bearer ' . str_repeat( 'A', 43 ), $args['headers']['Authorization'] );
				$this->assertSame( 1, $body['protocol_version'] );
				$this->assertSame( 1, $body['status_schema_version'] );
				$this->assertSame( '00000000-0000-4000-8000-000000000000', $body['enrollment_id'] );
				$this->assertSame( '11111111-1111-4111-8111-111111111111', $body['site_uuid'] );
				$this->assertSame( 'https://client.example.com', $body['home_url'] );
				$this->assertSame( 'https://client.example.com/wp-json/alynt-drime-backups-uploader/v1/status', $body['status_endpoint'] );

				return array(
					'response' => array(
						'code' => 201,
					),
					'body'     => wp_json_encode(
						array(
							'protocol_version'         => 1,
							'dashboard_site_public_id' => '00000000-0000-4000-8000-000000000000',
							'polling_key_id'           => 'pk_test',
							'polling_secret'           => $secret,
							'polling_auth_scheme'      => 'Bearer adb-poll-v1.pk_test.' . $secret,
						)
					),
				);
			}
		);

		$this->assertTrue( $called );
		$this->assertSame( Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_PAIRED, $state['connection_status'] );
		$this->assertTrue( $state['status_endpoint_enabled'] );
		$this->assertSame( 'pk_test', $state['polling_key_id'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $state['polling_credential_verifier'] );
		$this->assertArrayNotHasKey( 'pairing_token', $state );
		$this->assertArrayNotHasKey( 'secret', $state );
		$this->assertStringNotContainsString( str_repeat( 'A', 43 ), wp_json_encode( $options[ Alynt_Drime_Backups_Uploader_Dashboard_Connection::OPTION_NAME ] ) );
		$this->assertStringNotContainsString( $secret, wp_json_encode( $options[ Alynt_Drime_Backups_Uploader_Dashboard_Connection::OPTION_NAME ] ) );
		$this->assertTrue( $connection->verify_polling_authorization( 'Bearer adb-poll-v1.pk_test.' . $secret ) );
		$this->assertFalse( $connection->verify_polling_authorization( 'Bearer adb-poll-v1.pk_test.' . str_repeat( 'C', 43 ) ) );
	}

	public function test_complete_pairing_rejects_client_origin_mismatch_without_contacting_dashboard() {
		$options    = $this->confirmed_pairing_options();
		$connection = $this->connection_with_options( $options );

		$state = $connection->complete_pairing_from_token(
			$this->pairing_token(),
			'https://other.example.com',
			'11111111-1111-4111-8111-111111111111',
			'0.5.3',
			function () {
				$this->fail( 'Enrollment transport should not run for an origin mismatch.' );
			}
		);

		$this->assertSame( 'expected_client_origin_mismatch', $state['last_error_code'] );
		$this->assertFalse( $state['status_endpoint_enabled'] );
	}

	public function test_pairing_error_does_not_disable_existing_paired_endpoint() {
		$options    = $this->paired_options( 'pk_test', str_repeat( 'B', 43 ) );
		$connection = $this->connection_with_options( $options );

		$state = $connection->record_pairing_error( 'pairing_token_prefix_invalid' );

		$this->assertSame( Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_PAIRED, $state['connection_status'] );
		$this->assertTrue( $state['status_endpoint_enabled'] );
		$this->assertTrue( $connection->is_status_endpoint_enabled() );
	}

	public function test_remote_action_summary_is_absent_until_read_only_pairing_exists() {
		$options    = array();
		$connection = $this->connection_with_options( $options );

		$this->assertSame( array(), $connection->remote_action_summary() );
	}

	public function test_remote_action_summary_reports_disabled_v2_capability_without_secrets() {
		$options    = $this->paired_options( 'pk_test', str_repeat( 'B', 43 ) );
		$connection = $this->connection_with_options( $options );
		$summary    = $connection->remote_action_summary();

		$this->assertSame( 2, $summary['protocol_version'] );
		$this->assertFalse( $summary['enabled'] );
		$this->assertSame( '', $summary['key_id'] );
		$this->assertSame( array(), $summary['allowed_actions'] );
		$this->assertSame( 3600, $summary['min_interval_seconds'] );
		$this->assertTrue( $summary['one_running_action_per_site'] );
		$this->assertArrayNotHasKey( 'action_public_key', $summary );
		$this->assertArrayNotHasKey( 'action_private_key', $summary );
	}

	public function test_remote_action_summary_reports_enabled_only_with_key_and_sodium() {
		$options = $this->paired_options( 'pk_test', str_repeat( 'B', 43 ) );
		$options[ Alynt_Drime_Backups_Uploader_Dashboard_Connection::OPTION_NAME ]['remote_actions_enabled'] = true;
		$options[ Alynt_Drime_Backups_Uploader_Dashboard_Connection::OPTION_NAME ]['action_key_id']          = 'ak_test';
		$options[ Alynt_Drime_Backups_Uploader_Dashboard_Connection::OPTION_NAME ]['action_public_key']      = str_repeat( 'A', 43 );

		$connection = $this->connection_with_options( $options );
		$summary    = $connection->remote_action_summary();
		$enabled    = function_exists( 'sodium_crypto_sign_verify_detached' );

		$this->assertSame( $enabled, $summary['enabled'] );
		$this->assertSame(
			$enabled ? array( Alynt_Drime_Backups_Uploader_Dashboard_Connection::ACTION_SCAN_UPLOAD_NOW ) : array(),
			$summary['allowed_actions']
		);
		$this->assertSame( $enabled ? 'ak_test' : '', $summary['key_id'] );
	}

	public function test_parse_action_opt_in_token_returns_safe_public_metadata() {
		$connection = new Alynt_Drime_Backups_Uploader_Dashboard_Connection();
		$token      = $this->action_opt_in_token();

		$parsed = $connection->parse_action_opt_in_token( $token );

		$this->assertIsArray( $parsed );
		$this->assertSame( 'https://control.sitesmanage.com', $parsed['dashboard_origin'] );
		$this->assertSame( 'https://client.example.com', $parsed['expected_client_origin'] );
		$this->assertSame( '00000000-0000-4000-8000-000000000000', $parsed['dashboard_site_public_id'] );
		$this->assertSame( '11111111-1111-4111-8111-111111111111', $parsed['site_uuid'] );
		$this->assertSame( 'ak_test', $parsed['action_key_id'] );
		$this->assertSame( str_repeat( 'A', 43 ), $parsed['action_public_key'] );
		$this->assertSame( array( 'scan_upload_now' ), $parsed['allowed_actions'] );
		$this->assertArrayNotHasKey( 'action_private_key', $parsed );
	}

	public function test_parse_action_opt_in_rejects_expired_or_unsafe_payload() {
		$connection = new Alynt_Drime_Backups_Uploader_Dashboard_Connection();

		$expired = $connection->parse_action_opt_in_token(
			$this->action_opt_in_token(
				array(
					'expires_at' => '2020-01-01T00:00:00Z',
				)
			)
		);
		$unsafe  = $connection->parse_action_opt_in_token(
			$this->action_opt_in_token(
				array(
					'dashboard_origin' => 'http://127.0.0.1',
				)
			)
		);

		$this->assertInstanceOf( WP_Error::class, $expired );
		$this->assertSame( 'action_opt_in_expired', $expired->get_error_code() );
		$this->assertInstanceOf( WP_Error::class, $unsafe );
		$this->assertSame( 'action_dashboard_origin_invalid', $unsafe->get_error_code() );
	}

	public function test_complete_remote_action_opt_in_requires_existing_paired_read_only_connection() {
		$options    = array();
		$connection = $this->connection_with_options( $options );

		$state = $connection->complete_remote_action_opt_in_from_token(
			$this->action_opt_in_token(),
			'https://client.example.com',
			'11111111-1111-4111-8111-111111111111'
		);

		$this->assertSame( 'remote_action_opt_in_requires_pairing', $state['last_error_code'] );
		$this->assertFalse( $state['remote_actions_enabled'] );
	}

	public function test_complete_remote_action_opt_in_stores_public_key_without_private_material() {
		$options    = $this->paired_options( 'pk_test', str_repeat( 'B', 43 ) );
		$connection = $this->connection_with_options( $options );

		$state = $connection->complete_remote_action_opt_in_from_token(
			$this->action_opt_in_token(),
			'https://client.example.com',
			'11111111-1111-4111-8111-111111111111'
		);

		$enabled = function_exists( 'sodium_crypto_sign_verify_detached' );

		if ( ! $enabled ) {
			$this->assertSame( 'remote_action_signing_unavailable', $state['last_error_code'] );
			$this->assertFalse( $state['remote_actions_enabled'] );
			return;
		}

		$this->assertSame( '', $state['last_error_code'] );
		$this->assertTrue( $state['remote_actions_enabled'] );
		$this->assertSame( 'ak_test', $state['action_key_id'] );
		$this->assertSame( str_repeat( 'A', 43 ), $state['action_public_key'] );
		$this->assertGreaterThan( 0, $state['remote_actions_opted_in_at'] );
		$this->assertArrayNotHasKey( 'action_private_key', $state );
		$this->assertStringNotContainsString( 'private', strtolower( wp_json_encode( $options[ Alynt_Drime_Backups_Uploader_Dashboard_Connection::OPTION_NAME ] ) ) );
	}

	public function test_complete_remote_action_opt_in_rejects_site_identity_mismatch() {
		$options    = $this->paired_options( 'pk_test', str_repeat( 'B', 43 ) );
		$connection = $this->connection_with_options( $options );

		$state = $connection->complete_remote_action_opt_in_from_token(
			$this->action_opt_in_token(
				array(
					'dashboard_site_public_id' => '99999999-9999-4999-8999-999999999999',
				)
			),
			'https://client.example.com',
			'11111111-1111-4111-8111-111111111111'
		);

		$this->assertSame( 'remote_action_opt_in_site_mismatch', $state['last_error_code'] );
		$this->assertFalse( $state['remote_actions_enabled'] );
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
	 * Creates a confirmed pairing option fixture.
	 *
	 * @return array<string,mixed>
	 */
	private function confirmed_pairing_options() {
		return array(
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::OPTION_NAME => array(
				'connection_status'          => Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_CONFIRMED,
				'dashboard_origin'           => 'https://control.sitesmanage.com',
				'expected_client_origin'     => 'https://client.example.com',
				'pending_enrollment_id'      => '00000000-0000-4000-8000-000000000000',
				'pairing_expires_at'         => '2099-01-01T00:00:00+00:00',
				'dashboard_origin_confirmed' => true,
				'status_endpoint_enabled'    => false,
			),
		);
	}

	/**
	 * Creates paired dashboard connection options.
	 *
	 * @param string $key_id Polling key ID.
	 * @param string $secret Polling secret.
	 * @return array<string,mixed>
	 */
	private function paired_options( $key_id, $secret ) {
		return array(
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::OPTION_NAME => array(
				'connection_status'           => Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_PAIRED,
				'dashboard_origin'            => 'https://control.sitesmanage.com',
				'expected_client_origin'      => 'https://client.example.com',
				'dashboard_origin_confirmed'  => true,
				'dashboard_site_public_id'    => '00000000-0000-4000-8000-000000000000',
				'polling_key_id'              => $key_id,
				'polling_credential_verifier' => hash( 'sha256', 'adb-poll-v1|' . $key_id . '|' . $secret ),
				'status_endpoint_enabled'     => true,
			),
		);
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

	/**
	 * Creates a dashboard-style action opt-in token for tests.
	 *
	 * @param array<string,mixed> $overrides Payload overrides.
	 * @return string
	 */
	private function action_opt_in_token( array $overrides = array() ) {
		$payload = array_merge(
			array(
				'protocol_version'         => 2,
				'purpose'                  => 'remote_action_opt_in',
				'dashboard_origin'         => 'https://control.sitesmanage.com',
				'expected_client_origin'   => 'https://client.example.com',
				'dashboard_site_public_id' => '00000000-0000-4000-8000-000000000000',
				'site_uuid'                => '11111111-1111-4111-8111-111111111111',
				'action_key_id'            => 'ak_test',
				'action_public_key'        => str_repeat( 'A', 43 ),
				'allowed_actions'          => array( 'scan_upload_now' ),
				'expires_at'               => '2099-01-01T00:00:00Z',
			),
			$overrides
		);
		$json    = wp_json_encode( $payload );
		$encoded = rtrim( strtr( base64_encode( $json ), '+/', '-_' ), '=' );

		return Alynt_Drime_Backups_Uploader_Dashboard_Connection::ACTION_TOKEN_PREFIX . $encoded;
	}
}
