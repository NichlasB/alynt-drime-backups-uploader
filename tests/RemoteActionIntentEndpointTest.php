<?php
/**
 * Remote action intent endpoint tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES' ) ) {
	define( 'SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES', 32 );
}

if ( ! defined( 'SODIUM_CRYPTO_SIGN_SECRETKEYBYTES' ) ) {
	define( 'SODIUM_CRYPTO_SIGN_SECRETKEYBYTES', 64 );
}

if ( ! defined( 'SODIUM_CRYPTO_SIGN_BYTES' ) ) {
	define( 'SODIUM_CRYPTO_SIGN_BYTES', 64 );
}

if ( ! function_exists( 'sodium_crypto_sign_keypair' ) ) {
	/**
	 * Test-only Sodium keypair shim.
	 *
	 * @return string
	 */
	function sodium_crypto_sign_keypair() {
		return str_repeat( 'k', SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES );
	}
}

if ( ! function_exists( 'sodium_crypto_sign_publickey' ) ) {
	/**
	 * Test-only Sodium public key shim.
	 *
	 * @param string $key_pair Keypair.
	 * @return string
	 */
	function sodium_crypto_sign_publickey( $key_pair ) {
		return substr( (string) $key_pair, 0, SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES );
	}
}

if ( ! function_exists( 'sodium_crypto_sign_secretkey' ) ) {
	/**
	 * Test-only Sodium secret key shim.
	 *
	 * @param string $key_pair Keypair.
	 * @return string
	 */
	function sodium_crypto_sign_secretkey( $key_pair ) {
		return str_pad( substr( (string) $key_pair, 0, SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES ), SODIUM_CRYPTO_SIGN_SECRETKEYBYTES, 's' );
	}
}

if ( ! function_exists( 'sodium_crypto_sign_detached' ) ) {
	/**
	 * Test-only Sodium signature shim.
	 *
	 * @param string $message Message.
	 * @param string $secret_key Secret key.
	 * @return string
	 */
	function sodium_crypto_sign_detached( $message, $secret_key ) {
		$seed = substr( (string) $secret_key, 0, SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES );

		return hash( 'sha512', (string) $message . $seed, true );
	}
}

if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
	/**
	 * Test-only Sodium verify shim.
	 *
	 * @param string $signature Signature.
	 * @param string $message Message.
	 * @param string $public_key Public key.
	 * @return bool
	 */
	function sodium_crypto_sign_verify_detached( $signature, $message, $public_key ) {
		return hash_equals( hash( 'sha512', (string) $message . (string) $public_key, true ), (string) $signature );
	}
}

/**
 * Fake REST request for remote action endpoint tests.
 */
class Alynt_Drime_Backups_Uploader_Test_Action_REST_Request {
	/**
	 * Headers.
	 *
	 * @var array<string,string>
	 */
	private $headers;

	/**
	 * Body.
	 *
	 * @var string
	 */
	private $body;

	/**
	 * Constructor.
	 *
	 * @param array<string,string> $headers Headers.
	 * @param string               $body Body.
	 */
	public function __construct( array $headers, $body ) {
		$this->headers = $headers;
		$this->body    = (string) $body;
	}

	/**
	 * Returns method.
	 *
	 * @return string
	 */
	public function get_method() {
		return 'POST';
	}

	/**
	 * Returns body.
	 *
	 * @return string
	 */
	public function get_body() {
		return $this->body;
	}

	/**
	 * Returns header.
	 *
	 * @param string $name Header name.
	 * @return string
	 */
	public function get_header( $name ) {
		$name = strtolower( (string) $name );

		return isset( $this->headers[ $name ] ) ? $this->headers[ $name ] : '';
	}
}

/**
 * Tests the signed V2 remote action intent endpoint.
 */
class RemoteActionIntentEndpointTest extends TestCase {
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

	public function test_valid_signed_intent_is_accepted_and_idempotent() {
		if ( ! $this->sodium_supported() ) {
			$this->markTestSkipped( 'Sodium signing is not available.' );
		}

		$options   = $this->options_with_remote_actions();
		$scheduled = array();
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
		Functions\when( 'wp_schedule_single_event' )->alias(
			function ( $timestamp, $hook, $args = array() ) use ( &$scheduled ) {
				$scheduled[] = array(
					'timestamp' => $timestamp,
					'hook'      => $hook,
					'args'      => $args,
				);

				return true;
			}
		);

		$connection = new Alynt_Drime_Backups_Uploader_Dashboard_Connection();
		$store      = new Alynt_Drime_Backups_Uploader_Remote_Action_Store();
		$verifier   = new Alynt_Drime_Backups_Uploader_Remote_Action_Verifier( $connection );
		$controller = new Alynt_Drime_Backups_Uploader_Dashboard_Action_Intents_REST_Controller( $verifier, $store );
		$request    = $this->signed_request( $verifier, $options['_private_key'] );

		$response = $controller->handle_action_intent( $request );
		$duplicate = $controller->handle_action_intent( $request );

		$this->assertSame( 202, $response['status'] );
		$this->assertSame( 'accepted', $response['data']['state'] );
		$this->assertSame( 'action_accepted', $response['data']['code'] );
		$this->assertSame( 202, $duplicate['status'] );
		$this->assertSame( 'accepted', $duplicate['data']['state'] );
		$this->assertCount( 1, $scheduled );
		$this->assertSame( Alynt_Drime_Backups_Uploader_Remote_Action_Worker::EVENT, $scheduled[0]['hook'] );
		$this->assertArrayHasKey( Alynt_Drime_Backups_Uploader_Remote_Action_Store::OPTION_NAME, $options );
		$this->assertSame( 'accepted', $options[ Alynt_Drime_Backups_Uploader_Remote_Action_Store::OPTION_NAME ]['latest_action']['state'] );
		$this->assertStringNotContainsString( $options['_private_key'], json_encode( $options[ Alynt_Drime_Backups_Uploader_Remote_Action_Store::OPTION_NAME ] ) );
	}

	public function test_schedule_failure_records_failed_state_without_claiming_acceptance() {
		if ( ! $this->sodium_supported() ) {
			$this->markTestSkipped( 'Sodium signing is not available.' );
		}

		$options = $this->options_with_remote_actions();
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
		Functions\when( 'wp_schedule_single_event' )->justReturn( false );

		$connection = new Alynt_Drime_Backups_Uploader_Dashboard_Connection();
		$store      = new Alynt_Drime_Backups_Uploader_Remote_Action_Store();
		$verifier   = new Alynt_Drime_Backups_Uploader_Remote_Action_Verifier( $connection );
		$controller = new Alynt_Drime_Backups_Uploader_Dashboard_Action_Intents_REST_Controller( $verifier, $store );

		$response = $controller->handle_action_intent( $this->signed_request( $verifier, $options['_private_key'] ) );

		$this->assertSame( 500, $response['status'] );
		$this->assertSame( 'failed', $response['data']['state'] );
		$this->assertSame( 'action_schedule_failed', $response['data']['code'] );
		$this->assertSame( 'failed', $options[ Alynt_Drime_Backups_Uploader_Remote_Action_Store::OPTION_NAME ]['latest_action']['state'] );
		$this->assertSame( 'action_schedule_failed', $options[ Alynt_Drime_Backups_Uploader_Remote_Action_Store::OPTION_NAME ]['latest_action']['code'] );
	}

	public function test_invalid_signature_fails_closed_without_scheduling_work() {
		if ( ! $this->sodium_supported() ) {
			$this->markTestSkipped( 'Sodium signing is not available.' );
		}

		$options   = $this->options_with_remote_actions();
		$scheduled = array();
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
		Functions\when( 'wp_schedule_single_event' )->alias(
			function () use ( &$scheduled ) {
				$scheduled[] = true;

				return true;
			}
		);

		$connection = new Alynt_Drime_Backups_Uploader_Dashboard_Connection();
		$store      = new Alynt_Drime_Backups_Uploader_Remote_Action_Store();
		$verifier   = new Alynt_Drime_Backups_Uploader_Remote_Action_Verifier( $connection );
		$controller = new Alynt_Drime_Backups_Uploader_Dashboard_Action_Intents_REST_Controller( $verifier, $store );
		$request    = $this->signed_request( $verifier, $options['_private_key'] );
		$headers    = $this->request_headers( $request );
		$headers['x-adbd-action-signature'] = str_repeat( 'A', 86 );

		$response = $controller->handle_action_intent( new Alynt_Drime_Backups_Uploader_Test_Action_REST_Request( $headers, $request->get_body() ) );

		$this->assertSame( 401, $response['status'] );
		$this->assertSame( 'rejected', $response['data']['state'] );
		$this->assertSame( 'action_signature_invalid', $response['data']['code'] );
		$this->assertCount( 0, $scheduled );
		$this->assertArrayNotHasKey( Alynt_Drime_Backups_Uploader_Remote_Action_Store::OPTION_NAME, $options );
	}

	public function test_extra_body_keys_are_rejected_before_work_is_scheduled() {
		if ( ! $this->sodium_supported() ) {
			$this->markTestSkipped( 'Sodium signing is not available.' );
		}

		$options   = $this->options_with_remote_actions();
		$scheduled = array();
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
		Functions\when( 'wp_schedule_single_event' )->alias(
			function () use ( &$scheduled ) {
				$scheduled[] = true;

				return true;
			}
		);

		$connection = new Alynt_Drime_Backups_Uploader_Dashboard_Connection();
		$store      = new Alynt_Drime_Backups_Uploader_Remote_Action_Store();
		$verifier   = new Alynt_Drime_Backups_Uploader_Remote_Action_Verifier( $connection );
		$controller = new Alynt_Drime_Backups_Uploader_Dashboard_Action_Intents_REST_Controller( $verifier, $store );
		$body       = $this->intent_body();
		$body['path'] = '/unsafe';
		$body_json  = json_encode( $body );
		$signed_at  = gmdate( 'c' );
		$request    = new Alynt_Drime_Backups_Uploader_Test_Action_REST_Request(
			array(
				'x-adbd-action-key-id'    => 'ak_test',
				'x-adbd-action-signature' => $this->sign_body( $verifier, $body, $signed_at, $options['_private_key'] ),
				'x-adbd-action-signed-at' => $signed_at,
			),
			$body_json
		);

		$response = $controller->handle_action_intent( $request );

		$this->assertSame( 400, $response['status'] );
		$this->assertSame( 'action_body_keys_invalid', $response['data']['code'] );
		$this->assertCount( 0, $scheduled );
	}

	public function test_rate_limit_records_safe_retry_state() {
		if ( ! $this->sodium_supported() ) {
			$this->markTestSkipped( 'Sodium signing is not available.' );
		}

		$options = $this->options_with_remote_actions();
		$options[ Alynt_Drime_Backups_Uploader_Remote_Action_Store::OPTION_NAME ] = array(
			'last_accepted_at' => array(
				'scan_upload_now' => time(),
			),
		);
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
		Functions\when( 'wp_schedule_single_event' )->justReturn( true );

		$connection = new Alynt_Drime_Backups_Uploader_Dashboard_Connection();
		$store      = new Alynt_Drime_Backups_Uploader_Remote_Action_Store();
		$verifier   = new Alynt_Drime_Backups_Uploader_Remote_Action_Verifier( $connection );
		$controller = new Alynt_Drime_Backups_Uploader_Dashboard_Action_Intents_REST_Controller( $verifier, $store );

		$response = $controller->handle_action_intent( $this->signed_request( $verifier, $options['_private_key'], $this->intent_body( array( 'action_id' => '22222222-2222-4222-8222-222222222222', 'idempotency_key' => 'adb-act-test-2' ) ) ) );

		$this->assertSame( 429, $response['status'] );
		$this->assertSame( 'rate_limited', $response['data']['state'] );
		$this->assertGreaterThan( 0, $response['data']['retry_after'] );
		$this->assertSame( 'rate_limited', $options[ Alynt_Drime_Backups_Uploader_Remote_Action_Store::OPTION_NAME ]['latest_action']['state'] );
	}

	/**
	 * Creates options with paired, opted-in V2 remote actions.
	 *
	 * @return array<string,mixed>
	 */
	private function options_with_remote_actions() {
		$key_pair    = sodium_crypto_sign_keypair();
		$public_key  = $this->base64url_encode( sodium_crypto_sign_publickey( $key_pair ) );
		$private_key = $this->base64url_encode( sodium_crypto_sign_secretkey( $key_pair ) );

		return array(
			'_private_key' => $private_key,
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::OPTION_NAME => array(
				'connection_status'           => Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_PAIRED,
				'dashboard_origin'            => 'https://control.sitesmanage.com',
				'expected_client_origin'      => 'https://client.example.com',
				'dashboard_origin_confirmed'  => true,
				'dashboard_site_public_id'    => '00000000-0000-4000-8000-000000000000',
				'polling_key_id'              => 'pk_test',
				'polling_credential_verifier' => hash( 'sha256', 'adb-poll-v1|pk_test|' . str_repeat( 'B', 43 ) ),
				'status_endpoint_enabled'     => true,
				'remote_actions_enabled'      => true,
				'action_key_id'               => 'ak_test',
				'action_public_key'           => $public_key,
			),
			Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME => array(
				'site_uuid' => '11111111-1111-4111-8111-111111111111',
			),
		);
	}

	/**
	 * Builds intent body.
	 *
	 * @param array<string,mixed> $overrides Overrides.
	 * @return array<string,mixed>
	 */
	private function intent_body( array $overrides = array() ) {
		return array_merge(
			array(
				'protocol_version'         => 2,
				'action_id'                => '11111111-2222-4333-8444-555555555555',
				'dashboard_site_public_id' => '00000000-0000-4000-8000-000000000000',
				'site_uuid'                => '11111111-1111-4111-8111-111111111111',
				'action_type'              => 'scan_upload_now',
				'requested_at'             => gmdate( 'c' ),
				'expires_at'               => gmdate( 'c', time() + 120 ),
				'idempotency_key'          => 'adb-act-test-1',
			),
			$overrides
		);
	}

	/**
	 * Builds a signed fake request.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Remote_Action_Verifier $verifier Verifier.
	 * @param string                                              $private_key Private key.
	 * @param array<string,mixed>|null                            $body Body.
	 * @return Alynt_Drime_Backups_Uploader_Test_Action_REST_Request
	 */
	private function signed_request( Alynt_Drime_Backups_Uploader_Remote_Action_Verifier $verifier, $private_key, $body = null ) {
		$body      = is_array( $body ) ? $body : $this->intent_body();
		$signed_at = gmdate( 'c' );
		$body_json = $verifier->canonical_json( $body );
		$input     = $verifier->signing_input( 'POST', Alynt_Drime_Backups_Uploader_Remote_Action_Verifier::REST_ROUTE, 'https://client.example.com', $body_json, $signed_at );

		return new Alynt_Drime_Backups_Uploader_Test_Action_REST_Request(
			array(
				'x-adbd-action-key-id'    => 'ak_test',
				'x-adbd-action-signature' => $this->base64url_encode( sodium_crypto_sign_detached( $input, $this->base64url_decode( $private_key ) ) ),
				'x-adbd-action-signed-at' => $signed_at,
			),
			$body_json
		);
	}

	/**
	 * Returns headers from fake request.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Test_Action_REST_Request $request Request.
	 * @return array<string,string>
	 */
	private function request_headers( Alynt_Drime_Backups_Uploader_Test_Action_REST_Request $request ) {
		return array(
			'x-adbd-action-key-id'    => $request->get_header( 'x-adbd-action-key-id' ),
			'x-adbd-action-signature' => $request->get_header( 'x-adbd-action-signature' ),
			'x-adbd-action-signed-at' => $request->get_header( 'x-adbd-action-signed-at' ),
		);
	}

	/**
	 * Signs body.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Remote_Action_Verifier $verifier Verifier.
	 * @param array<string,mixed>                                 $body Body.
	 * @param string                                              $signed_at Signed at.
	 * @param string                                              $private_key Private key.
	 * @return string
	 */
	private function sign_body( Alynt_Drime_Backups_Uploader_Remote_Action_Verifier $verifier, array $body, $signed_at, $private_key ) {
		$body_json = $verifier->canonical_json( $body );
		$input     = $verifier->signing_input( 'POST', Alynt_Drime_Backups_Uploader_Remote_Action_Verifier::REST_ROUTE, 'https://client.example.com', $body_json, $signed_at );

		return $this->base64url_encode( sodium_crypto_sign_detached( $input, $this->base64url_decode( $private_key ) ) );
	}

	/**
	 * Returns whether Sodium is available.
	 *
	 * @return bool
	 */
	private function sodium_supported() {
		return function_exists( 'sodium_crypto_sign_keypair' )
			&& function_exists( 'sodium_crypto_sign_detached' )
			&& function_exists( 'sodium_crypto_sign_verify_detached' );
	}

	/**
	 * Encodes base64url.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function base64url_encode( $value ) {
		return rtrim( strtr( base64_encode( (string) $value ), '+/', '-_' ), '=' );
	}

	/**
	 * Decodes base64url.
	 *
	 * @param string $value Value.
	 * @return string|false
	 */
	private function base64url_decode( $value ) {
		$pad = strlen( (string) $value ) % 4;
		if ( $pad ) {
			$value .= str_repeat( '=', 4 - $pad );
		}

		return base64_decode( strtr( (string) $value, '-_', '+/' ), true );
	}
}
