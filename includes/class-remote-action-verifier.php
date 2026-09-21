<?php
/**
 * Remote action request verifier.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.12
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verifies signed V2 dashboard action intents.
 *
 * @since 0.5.12
 */
class Alynt_Drime_Backups_Uploader_Remote_Action_Verifier {
	use Alynt_Drime_Backups_Uploader_Remote_Action_Verifier_Body_Parsing;
	use Alynt_Drime_Backups_Uploader_Remote_Action_Verifier_Signing;
	use Alynt_Drime_Backups_Uploader_Remote_Action_Verifier_Sanitizers;

	const SIGNING_PREFIX   = 'ADB-ACTION-V2';
	const REST_ROUTE       = '/wp-json/alynt-drime-backups-uploader/v2/action-intents';
	const FRESHNESS_WINDOW = 300;

	/**
	 * Dashboard connection.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Dashboard_Connection
	 */
	private $connection;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Dashboard_Connection $connection Dashboard connection.
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Dashboard_Connection $connection ) {
		$this->connection = $connection;
	}

	/**
	 * Verifies and returns a safe intent.
	 *
	 * @param string $method HTTP method.
	 * @param string $route REST route.
	 * @param string $raw_body Raw request body.
	 * @param string $key_id Header key ID.
	 * @param string $signature Header signature.
	 * @param string $signed_at Header signed-at timestamp.
	 * @return array<string,mixed>|WP_Error
	 */
	public function verify( $method, $route, $raw_body, $key_id, $signature, $signed_at ) {
		$state = $this->connection->get();

		if (
			Alynt_Drime_Backups_Uploader_Dashboard_Connection::STATUS_PAIRED !== $state['connection_status']
			|| empty( $state['status_endpoint_enabled'] )
			|| empty( $state['remote_actions_enabled'] )
		) {
			return $this->error( 'remote_actions_disabled', __( 'Remote actions are not enabled for this client site.', 'alynt-drime-backups-uploader' ), 403 );
		}

		if ( ! $this->is_sodium_available() ) {
			return $this->error( 'remote_action_signing_unavailable', __( 'Remote action signing support is unavailable on this client site.', 'alynt-drime-backups-uploader' ), 501 );
		}

		$key_id    = $this->sanitize_identifier( $key_id );
		$signature = trim( (string) $signature );
		$signed_at = sanitize_text_field( (string) $signed_at );

		if ( '' === $key_id || '' === $signature || '' === $signed_at ) {
			return $this->error( 'action_headers_missing', __( 'The remote action request is missing required signed headers.', 'alynt-drime-backups-uploader' ), 400 );
		}

		if ( empty( $state['action_key_id'] ) || ! hash_equals( (string) $state['action_key_id'], $key_id ) ) {
			return $this->error( 'action_key_mismatch', __( 'The remote action key is not recognized by this client site.', 'alynt-drime-backups-uploader' ), 403 );
		}

		if ( strtoupper( sanitize_key( (string) $method ) ) !== 'POST' || self::REST_ROUTE !== '/' . ltrim( (string) $route, '/' ) ) {
			return $this->error( 'action_route_invalid', __( 'The remote action route is not supported.', 'alynt-drime-backups-uploader' ), 404 );
		}

		$signed_ts = strtotime( $signed_at );
		if ( false === $signed_ts || abs( time() - $signed_ts ) > self::FRESHNESS_WINDOW ) {
			return $this->error( 'action_signature_expired', __( 'The remote action signature timestamp is outside the allowed freshness window.', 'alynt-drime-backups-uploader' ), 401 );
		}

		$body = $this->parse_body( $raw_body );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$expires_ts = strtotime( $body['expires_at'] );
		if ( false === $expires_ts || $expires_ts <= time() ) {
			return $this->error( 'action_intent_expired', __( 'The remote action intent has expired.', 'alynt-drime-backups-uploader' ), 409 );
		}

		if ( empty( $state['dashboard_site_public_id'] ) || ! hash_equals( (string) $state['dashboard_site_public_id'], $body['dashboard_site_public_id'] ) ) {
			return $this->error( 'action_dashboard_site_mismatch', __( 'The remote action is not for this dashboard site record.', 'alynt-drime-backups-uploader' ), 403 );
		}

		$local_site_uuid = $this->local_site_uuid();
		if ( '' === $local_site_uuid || ! hash_equals( $local_site_uuid, $body['site_uuid'] ) ) {
			return $this->error( 'action_site_uuid_mismatch', __( 'The remote action is not for this client site.', 'alynt-drime-backups-uploader' ), 403 );
		}

		$canonical_body = $this->canonical_json( $body );
		if ( is_wp_error( $canonical_body ) ) {
			return $canonical_body;
		}

		$origin = isset( $state['expected_client_origin'] ) ? $this->normalize_public_https_origin( (string) $state['expected_client_origin'] ) : '';
		if ( '' === $origin ) {
			return $this->error( 'action_origin_invalid', __( 'The paired client origin is not valid for remote action verification.', 'alynt-drime-backups-uploader' ), 403 );
		}

		$signing_input = $this->signing_input( 'POST', self::REST_ROUTE, $origin, $canonical_body, $signed_at );
		if ( ! $this->verify_signature( (string) $state['action_public_key'], $signing_input, $signature ) ) {
			return $this->error( 'action_signature_invalid', __( 'The remote action signature is not valid.', 'alynt-drime-backups-uploader' ), 401 );
		}

		$body['request_hash'] = hash( 'sha256', $canonical_body );

		return $body;
	}
}
