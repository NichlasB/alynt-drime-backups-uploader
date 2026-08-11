<?php
/**
 * Dashboard status REST endpoint.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves the fixed read-only dashboard polling status endpoint.
 *
 * @since 0.5.3
 */
class Alynt_Drime_Backups_Uploader_Dashboard_Status_REST_Controller {
	const REST_NAMESPACE = 'alynt-drime-backups-uploader/v1';
	const REST_ROUTE     = '/status';

	/**
	 * Dashboard connection state.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Dashboard_Connection
	 */
	private $connection;

	/**
	 * Health summary service.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Health_Summary
	 */
	private $health_summary;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Dashboard_Connection $connection Dashboard connection.
	 * @param Alynt_Drime_Backups_Uploader_Health_Summary       $health_summary Health summary.
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Dashboard_Connection $connection, Alynt_Drime_Backups_Uploader_Health_Summary $health_summary ) {
		$this->connection     = $connection;
		$this->health_summary = $health_summary;
	}

	/**
	 * Registers REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_status_request' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	/**
	 * Checks the dashboard polling credential.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission_callback( $request ) {
		$authorization = method_exists( $request, 'get_header' ) ? (string) $request->get_header( 'authorization' ) : '';

		if ( ! $this->connection->verify_polling_authorization( $authorization ) ) {
			return new WP_Error( 'dashboard_status_unauthorized', __( 'The dashboard status credential is not valid.', 'alynt-drime-backups-uploader' ), array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Handles authenticated dashboard status reads.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|array<string,mixed>
	 */
	public function handle_status_request( $request ) {
		unset( $request );

		$this->connection->record_authenticated_read();

		$payload = $this->health_summary->status( wp_next_scheduled( Alynt_Drime_Backups_Uploader_Cron::SCAN_EVENT ), false );

		if ( function_exists( 'rest_ensure_response' ) ) {
			$response = rest_ensure_response( $payload );
			if ( is_object( $response ) && method_exists( $response, 'header' ) ) {
				$response->header( 'Cache-Control', 'no-store' );
			}

			return $response;
		}

		return array(
			'data'    => $payload,
			'status'  => 200,
			'headers' => array(
				'Cache-Control' => 'no-store',
			),
		);
	}
}
