<?php
/**
 * Dashboard status REST controller tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Fake health summary for status REST tests.
 */
class Alynt_Drime_Backups_Uploader_Test_Dashboard_Health_Summary extends Alynt_Drime_Backups_Uploader_Health_Summary {
	/**
	 * Last include-paths flag.
	 *
	 * @var bool|null
	 */
	public $last_include_paths = null;

	/**
	 * Test constructor intentionally avoids parent dependencies.
	 */
	public function __construct() {}

	/**
	 * Returns a deterministic health payload.
	 *
	 * @param int|false $next_scan Next scheduled scan.
	 * @param bool      $include_paths Include paths flag.
	 * @return array<string,mixed>
	 */
	public function status( $next_scan = false, $include_paths = false ) {
		$this->last_include_paths = $include_paths;

		return array(
			'schema_version' => 1,
			'site_uuid'      => '11111111-1111-4111-8111-111111111111',
			'next_scan'      => $next_scan,
		);
	}
}

/**
 * Fake REST request for status REST tests.
 */
class Alynt_Drime_Backups_Uploader_Test_Dashboard_REST_Request {
	/**
	 * Headers.
	 *
	 * @var array<string,string>
	 */
	private $headers;

	/**
	 * Constructor.
	 *
	 * @param array<string,string> $headers Headers.
	 */
	public function __construct( array $headers ) {
		$this->headers = $headers;
	}

	/**
	 * Gets a header.
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
 * Tests dashboard status REST controller behavior.
 */
class DashboardStatusRestControllerTest extends TestCase {
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
		Functions\when( 'wp_next_scheduled' )->justReturn( 1234567890 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_permission_requires_valid_polling_authorization() {
		$options    = $this->paired_options( 'pk_test', str_repeat( 'B', 43 ) );
		$connection = $this->connection_with_options( $options );
		$controller = new Alynt_Drime_Backups_Uploader_Dashboard_Status_REST_Controller( $connection, new Alynt_Drime_Backups_Uploader_Test_Dashboard_Health_Summary() );

		$this->assertTrue( $controller->permission_callback( new Alynt_Drime_Backups_Uploader_Test_Dashboard_REST_Request( array( 'authorization' => 'Bearer adb-poll-v1.pk_test.' . str_repeat( 'B', 43 ) ) ) ) );

		$result = $controller->permission_callback( new Alynt_Drime_Backups_Uploader_Test_Dashboard_REST_Request( array( 'authorization' => 'Bearer adb-poll-v1.pk_test.' . str_repeat( 'C', 43 ) ) ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'dashboard_status_unauthorized', $result->get_error_code() );
	}

	public function test_status_response_is_redacted_and_records_authenticated_read() {
		$options    = $this->paired_options( 'pk_test', str_repeat( 'B', 43 ) );
		$connection = $this->connection_with_options( $options );
		$summary    = new Alynt_Drime_Backups_Uploader_Test_Dashboard_Health_Summary();
		$controller = new Alynt_Drime_Backups_Uploader_Dashboard_Status_REST_Controller( $connection, $summary );

		$response = $controller->handle_status_request( new Alynt_Drime_Backups_Uploader_Test_Dashboard_REST_Request( array() ) );

		$this->assertIsArray( $response );
		$this->assertSame( 200, $response['status'] );
		$this->assertSame( 'no-store', $response['headers']['Cache-Control'] );
		$this->assertFalse( $summary->last_include_paths );
		$this->assertArrayNotHasKey( 'server_outbox_path', $response['data'] );
		$this->assertArrayNotHasKey( 'backup_path_override', $response['data'] );
		$this->assertGreaterThan( 0, $options[ Alynt_Drime_Backups_Uploader_Dashboard_Connection::OPTION_NAME ]['last_authenticated_read_at'] );
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
}
