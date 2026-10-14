<?php
/** Genuine core authorization ports, developer-only; harmless receipts are NOT consent. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
class Alynt_Auth_Proof_Ports {
	const SITE = '99999999-9999-4999-8999-999999999999';
	public $now; public $model; public $owned; public $mode = 'closed'; public $context_calls = 0; public $sink_calls = 0; public $observe_calls = 0; public $state_reads = 0; public $before_guard; public $on_context; public $retained_guard;
	public function __construct( array $owned, $now ) { $this->owned = $owned; $this->now = $now; $this->model = alynt_provenance_model( new Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS( $GLOBALS['wpdb'] ), $now ); }
	public function authorize( $site ) {
		global $wpdb;
		foreach ( $this->owned as $key => $table ) if ( $wpdb->$key !== $table ) return false;
		if ( self::SITE !== $site || is_multisite() ) return false;
		// Do not confuse a cached current user or a valid nonce with a currently valid session.
		foreach ( array( 90000001, 90000002, 90000003 ) as $id ) { clean_user_cache( $id ); wp_cache_delete( $id, 'user_meta' ); }
		wp_set_current_user( 0 ); $id = wp_validate_auth_cookie( $_COOKIE[LOGGED_IN_COOKIE] ?? '', 'logged_in' );
		if ( ! in_array( $id, array( 90000001, 90000002, 90000003 ), true ) ) return false;
		$GLOBALS['wp_roles'] = new WP_Roles(); wp_set_current_user( $id );
		return is_user_logged_in() && current_user_can( 'manage_options' );
	}
	public function context() {
		$this->context_calls++; if ( $this->on_context ) { $callback = $this->on_context; $this->on_context = null; $callback(); }
		$s = $this->model->snapshot(); return is_wp_error( $s ) ? $s : array( 'site_uuid' => self::SITE, 'binding' => $s['enrollment_binding'], 'generation' => $s['lifecycle']['permission_generation'], 'eligible' => false, 'replacement_binding' => null );
	}
	public function ports() { return array( 'authorize' => array( $this, 'authorize' ), 'verify_nonce' => 'wp_verify_nonce', 'create_nonce' => 'wp_create_nonce', 'context' => array( $this, 'context' ), 'clock' => function () { return $this->now; }, 'uuid' => 'wp_generate_uuid4', 'consume' => array( $this, 'consume' ), 'observe' => array( $this, 'observe' ) ); }
	public function adapter() { return new Alynt_Drime_Backups_Uploader_Consent_Request_Adapter( $this->ports(), self::SITE, true ); }
	public function issue( $adapter, $purpose = 'revoke' ) {
		$this->authorize( self::SITE );
		return $adapter->issue( 'POST', array( 'purpose' => $purpose, 'confirm' => 'grant' === $purpose ? 'prepare' : 'close', 'nonce' => wp_create_nonce( 'alynt_drime_backups_consent_fixture_issue_v1:' . $purpose . ':' . self::SITE ) ) );
	}
	public function consume( $command, $guard ) {
		global $wpdb; $this->sink_calls++; $this->retained_guard = $guard;
		if ( 'closed' === $this->mode ) return $this->model->command( $command, $guard );
		alynt_lifecycle_check( 'revoke' === $command['purpose'], 'Harmless sink refuses non-closure purpose.' );
		if ( $this->before_guard ) { $callback = $this->before_guard; $this->before_guard = null; $callback(); }
		if ( true !== $guard() ) return new WP_Error( 'fixture_guard_denied', 'Unavailable.' );
		if ( 'guard_only' === $this->mode ) return new WP_Error( 'fixture_guard_only', 'Unavailable.' );
		alynt_lifecycle_check( in_array( $this->mode, array( 'receipt', 'ack' ), true ), 'Unknown harmless sink mode.' );
		// This receipt acknowledges only this synthetic observation probe. Never write schema-5 permission.
		$name = alynt_auth_receipt_name( $command['command_id'] ); $digest = hash( 'sha256', json_encode( $command ) );
		$prior = alynt_auth_read_receipt( $command['command_id'] );
		if ( null !== $prior ) return $prior['digest'] === $digest ? 'observed_current' : new WP_Error( 'fixture_conflict', 'Unavailable.' );
		if ( true !== $guard() ) return new WP_Error( 'fixture_guard_denied', 'Unavailable.' );
		$r = $wpdb->insert( $wpdb->options, array( 'option_name' => $name, 'option_value' => wp_json_encode( array( 'source' => 'harmless_observation_fixture_v1', 'digest' => $digest, 'generation' => $command['expected_generation'] ) ), 'autoload' => 'no' ) );
		alynt_lifecycle_check( 1 === $r, 'Harmless receipt insert failed.' );
		if ( 'ack' === $this->mode ) {
			alynt_lifecycle_check( 1 === $wpdb->insert( $wpdb->options, array( 'option_name' => 'probe_auth_ack', 'option_value' => $command['command_id'], 'autoload' => 'no' ) ), 'Owned acknowledgement marker failed.' );
			$limit = microtime( true ) + 15; while ( microtime( true ) < $limit ) usleep( 20000 ); exit( 1 );
		}
		return 'committed';
	}
	public function observe( $site, $id ) {
		$this->observe_calls++; if ( self::SITE !== $site ) return new WP_Error( 'fixture_scope', 'Unavailable.' );
		$r = alynt_auth_read_receipt( $id );
		if ( null !== $r ) { $s = $this->model->snapshot(); return is_wp_error( $s ) || 'harmless_observation_fixture_v1' !== ( $r['source'] ?? null ) ? new WP_Error( 'fixture_unavailable', 'Unavailable.' ) : ( $r['generation'] === $s['lifecycle']['permission_generation'] ? 'observed_current' : 'observed_superseded' ); }
		$r = $this->model->receipt( 'fixture_request_v1', $id ); return is_wp_error( $r ) ? $r : $r['outcome'];
	}
	public function reconcile( $adapter, $id ) { $this->authorize( self::SITE ); return $adapter->reconcile( 'POST', array( 'command_id' => $id, 'nonce' => wp_create_nonce( 'alynt_drime_backups_consent_fixture_receipt_v1:' . self::SITE . ':' . $id ) ) ); }
}
function alynt_auth_body( $issued ) { alynt_lifecycle_check( is_array( $issued ) && isset( $issued['envelope'], $issued['nonce'] ), 'Protected fixture intent was not issued.' ); $body = array_map( 'strval', $issued['envelope'] ); $body['nonce'] = $issued['nonce']; return $body; }
function alynt_auth_unchanged( $backend, $before ) { alynt_lifecycle_check( $before === $backend->read( Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION ), 'Auth proof changed closed permission bytes.' ); }
