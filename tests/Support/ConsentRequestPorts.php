<?php
/** In-memory injected request ports, NOT WordPress auth or a successor storage implementation. */
class Alynt_Test_Consent_Request_Ports {
	public $site = '11111111-1111-4111-8111-111111111111';
	public $binding;
	public $generation = 1;
	public $eligible = true;
	public $replacement;
	public $now = 1800000000;
	public $logged_in = true;
	public $capable = true;
	public $single_site = true;
	public $session = 'fixture-session-a';
	public $authorize_result;
	public $nonce_result;
	public $number = 0;
	public $calls = array();
	public $receipts = array();
	public $consent = false;
	public $writes = 0;
	public $conflicts = 0;
	public $before_write;
	public $on_context;
	public $fault;
	public $retained_guard;
	public $last_command;
	public function __construct() { $this->binding = str_repeat( 'a', 64 ); $this->replacement = str_repeat( 'b', 64 ); }
	public function hit( $name ) { $this->calls[$name] = ( $this->calls[$name] ?? 0 ) + 1; }
	public function authorize( $site ) { $this->hit( 'auth' ); return $this->authorize_result ?? ( $site === $this->site && $this->logged_in && $this->capable && $this->single_site ); }
	public function create_nonce( $action ) { $this->hit( 'mint' ); return hash( 'sha256', 'ONLY-A-TEST:' . $this->session . ':' . $action ); }
	public function verify_nonce( $nonce, $action ) { $this->hit( 'nonce' ); return $this->nonce_result ?? ( hash_equals( hash( 'sha256', 'ONLY-A-TEST:' . $this->session . ':' . $action ), $nonce ) ? 1 : false ); }
	public function context() {
		$this->hit( 'context' ); if ( $this->on_context ) { $callback = $this->on_context; $this->on_context = null; $callback( $this ); }
		return array( 'site_uuid' => $this->site, 'binding' => $this->binding, 'generation' => $this->generation, 'eligible' => $this->eligible, 'replacement_binding' => $this->replacement );
	}
	public function clock() { $this->hit( 'clock' ); return $this->now; }
	public function uuid() { $this->hit( 'uuid' ); return sprintf( '22222222-2222-4222-8222-%012d', ++$this->number ); }
	public function ports() { $ports = array(); foreach ( array( 'authorize', 'verify_nonce', 'create_nonce', 'context', 'clock', 'uuid', 'consume', 'observe' ) as $name ) $ports[$name] = array( $this, $name ); return $ports; }
	public function digest( array $command ) { ksort( $command ); return hash( 'sha256', json_encode( $command ) ); }
	public function consume( array $command, callable $guard ) {
		$this->hit( 'consume' ); $this->last_command = $command; $this->retained_guard = $guard;
		$id = $command['command_id']; $hash = $this->digest( $command );
		if ( isset( $this->receipts[$id] ) ) return $this->receipts[$id]['hash'] === $hash ? $this->observed( $id ) : new WP_Error( 'private_conflict', 'secret request detail' );
		for ( $attempt = 0; $attempt < 3; $attempt++ ) {
			$this->hit( 'attempt' ); if ( $this->before_write ) { $callback = $this->before_write; $callback( $this, $attempt ); }
			if ( true !== $guard() ) return new WP_Error( 'fixture_denied', 'secret guard detail' );
			if ( $this->conflicts > 0 ) { $this->conflicts--; continue; }
			if ( $command['expected_generation'] !== $this->generation || $command['enrollment_binding'] !== $this->binding ) return new WP_Error( 'fixture_stale', 'secret state detail' );
			$this->generation++; $this->consent = 'grant' === $command['purpose']; $this->writes++;
			if ( 'rebind' === $command['purpose'] ) $this->binding = $command['replacement_binding'];
			$this->receipts[$id] = array( 'hash' => $hash, 'generation' => $this->generation );
			if ( $this->fault ) { if ( 'throw' === $this->fault ) throw new RuntimeException( 'secret committed detail' ); return new WP_Error( 'private_ack', 'secret committed detail' ); }
			return 'committed';
		}
		return new WP_Error( 'fixture_contention', 'secret contention' );
	}
	public function observe( $site, $id ) { $this->hit( 'observe' ); return $site === $this->site ? $this->observed( $id ) : new WP_Error( 'private_scope', 'secret scope' ); }
	public function observed( $id ) { return isset( $this->receipts[$id] ) ? ( $this->receipts[$id]['generation'] === $this->generation ? 'observed_current' : 'observed_superseded' ) : 'not_found'; }
}
