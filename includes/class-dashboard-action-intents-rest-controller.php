<?php
/**
 * Dashboard action intents REST controller.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.12
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Receives signed V2 remote action intents from the dashboard.
 *
 * @since 0.5.12
 */
class Alynt_Drime_Backups_Uploader_Dashboard_Action_Intents_REST_Controller {
	const REST_NAMESPACE = 'alynt-drime-backups-uploader/v2';
	const REST_ROUTE     = '/action-intents';

	/**
	 * Verifier.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Remote_Action_Verifier
	 */
	private $verifier;

	/**
	 * Store.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Remote_Action_Store
	 */
	private $store;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Remote_Action_Verifier $verifier Verifier.
	 * @param Alynt_Drime_Backups_Uploader_Remote_Action_Store    $store Store.
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Remote_Action_Verifier $verifier, Alynt_Drime_Backups_Uploader_Remote_Action_Store $store ) {
		$this->verifier = $verifier;
		$this->store    = $store;
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
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_action_intent' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	/**
	 * Allows REST routing; handler performs signed fail-closed verification.
	 *
	 * @return bool
	 */
	public function permission_callback() {
		return true;
	}

	/**
	 * Handles a signed action intent.
	 *
	 * @param object $request REST request.
	 * @return array<string,mixed>|WP_REST_Response
	 */
	public function handle_action_intent( $request ) {
		$raw_body = method_exists( $request, 'get_body' ) ? (string) $request->get_body() : '';
		$intent   = $this->verifier->verify(
			method_exists( $request, 'get_method' ) ? (string) $request->get_method() : 'POST',
			Alynt_Drime_Backups_Uploader_Remote_Action_Verifier::REST_ROUTE,
			$raw_body,
			$this->header( $request, 'x-adbd-action-key-id' ),
			$this->header( $request, 'x-adbd-action-signature' ),
			$this->header( $request, 'x-adbd-action-signed-at' )
		);

		if ( is_wp_error( $intent ) ) {
			$status = is_array( $intent->get_error_data() ) && isset( $intent->get_error_data()['status'] ) ? absint( $intent->get_error_data()['status'] ) : 400;

			return $this->response( 'rejected', $intent->get_error_code(), __( 'Remote action request rejected before work was queued.', 'alynt-drime-backups-uploader' ), $status );
		}

		$existing = $this->store->find_by_idempotency_key( $intent['idempotency_key'] );
		if ( ! empty( $existing ) ) {
			if ( ! empty( $existing['request_hash'] ) && ! hash_equals( (string) $existing['request_hash'], (string) $intent['request_hash'] ) ) {
				return $this->response( 'rejected', 'action_idempotency_conflict', __( 'The remote action idempotency key was already used for a different request.', 'alynt-drime-backups-uploader' ), 409 );
			}

			return $this->response_from_record( $existing, $this->status_for_record( $existing ) );
		}

		$retry_after = $this->store->retry_after_for_action( $intent['action_type'], Alynt_Drime_Backups_Uploader_Dashboard_Connection::ACTION_MIN_INTERVAL );
		if ( $retry_after > 0 ) {
			$record = $this->store->upsert_action( $intent, 'rate_limited', 'action_rate_limited', __( 'Remote action request rate-limited by the client site.', 'alynt-drime-backups-uploader' ), array(), $retry_after );

			return $this->response_from_record( $record, 429 );
		}

		if ( $this->store->has_active_lock() ) {
			$record = $this->store->upsert_action( $intent, 'busy', 'action_already_running', __( 'Another remote action is already running on this client site.', 'alynt-drime-backups-uploader' ), array(), Alynt_Drime_Backups_Uploader_Remote_Action_Store::LOCK_TTL );

			return $this->response_from_record( $record, 409 );
		}

		$record = $this->store->upsert_action( $intent, 'accepted', 'action_accepted', __( 'Remote action accepted and queued for local scan/upload processing.', 'alynt-drime-backups-uploader' ) );
		$this->schedule_worker( $record['action_id'] );

		return $this->response_from_record( $record, 202 );
	}

	/**
	 * Returns a request header.
	 *
	 * @param object $request REST request.
	 * @param string $name Header name.
	 * @return string
	 */
	private function header( $request, $name ) {
		return method_exists( $request, 'get_header' ) ? (string) $request->get_header( $name ) : '';
	}

	/**
	 * Schedules local worker.
	 *
	 * @param string $action_id Action ID.
	 * @return void
	 */
	private function schedule_worker( $action_id ) {
		if ( function_exists( 'wp_schedule_single_event' ) ) {
			wp_schedule_single_event( time() + 5, Alynt_Drime_Backups_Uploader_Remote_Action_Worker::EVENT, array( $action_id ) );
		}
	}

	/**
	 * Builds response from a stored record.
	 *
	 * @param array<string,mixed> $record Record.
	 * @param int                 $status HTTP status.
	 * @return array<string,mixed>|WP_REST_Response
	 */
	private function response_from_record( array $record, $status ) {
		return $this->response(
			isset( $record['state'] ) ? (string) $record['state'] : 'rejected',
			isset( $record['code'] ) ? (string) $record['code'] : 'action_rejected',
			isset( $record['summary'] ) ? (string) $record['summary'] : __( 'Remote action request rejected.', 'alynt-drime-backups-uploader' ),
			$status,
			isset( $record['action_id'] ) ? (string) $record['action_id'] : '',
			isset( $record['retry_after'] ) ? absint( $record['retry_after'] ) : 0
		);
	}

	/**
	 * Returns HTTP status that matches a stored action state.
	 *
	 * @param array<string,mixed> $record Record.
	 * @return int
	 */
	private function status_for_record( array $record ) {
		$state = isset( $record['state'] ) ? sanitize_key( (string) $record['state'] ) : '';

		if ( 'rate_limited' === $state ) {
			return 429;
		}

		if ( 'busy' === $state ) {
			return 409;
		}

		if ( in_array( $state, array( 'accepted', 'running', 'succeeded' ), true ) ) {
			return 202;
		}

		return 400;
	}

	/**
	 * Builds no-store response.
	 *
	 * @param string $state State.
	 * @param string $code Code.
	 * @param string $summary Summary.
	 * @param int    $status HTTP status.
	 * @param string $action_id Action ID.
	 * @param int    $retry_after Retry-after seconds.
	 * @return array<string,mixed>|WP_REST_Response
	 */
	private function response( $state, $code, $summary, $status, $action_id = '', $retry_after = 0 ) {
		$payload = array(
			'protocol_version' => Alynt_Drime_Backups_Uploader_Dashboard_Connection::ACTION_PROTOCOL_VERSION,
			'action_id'        => $action_id,
			'state'            => sanitize_key( (string) $state ),
			'code'             => sanitize_key( (string) $code ),
			'summary'          => sanitize_text_field( (string) $summary ),
			'retry_after'      => max( 0, absint( $retry_after ) ),
		);

		if ( function_exists( 'rest_ensure_response' ) ) {
			$response = rest_ensure_response( $payload );
			if ( method_exists( $response, 'set_status' ) ) {
				$response->set_status( absint( $status ) );
			}
			if ( method_exists( $response, 'header' ) ) {
				$response->header( 'Cache-Control', 'no-store' );
			}

			return $response;
		}

		return array(
			'data'    => $payload,
			'status'  => absint( $status ),
			'headers' => array(
				'Cache-Control' => 'no-store',
			),
		);
	}
}
