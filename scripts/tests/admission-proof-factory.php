<?php
/** Fixed genuine-core/native-SQL proof owner; only fixture action/receipt domains. */
use Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Boundary as AdmissionBoundary;
use Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract as AdmissionContract;
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
final class Alynt_Admission_Proof_Factory {
	public $db; public $base; public $token; public $auth; public $fault = ''; public $phase = 'none'; public $auth_checks = 0; public $reads = 0; public $writes = 0; public $transactions = 0; public $retained; public $revoke_on_read = false;
	public function __construct( $base, $token, array $owned ) {
		alynt_auth_owned( $owned, $token ); $this->db = new Alynt_Admission_Proof_DB( $base ); $this->db->owned( $token );
		$this->base = $base; $this->token = $token; $this->auth = new Alynt_Auth_Proof_Ports( $owned, time() );
	}
	public function boundary( $enabled = true ) {
		return new AdmissionBoundary( array( 'authorize' => function () { $this->auth_checks++; return $this->auth->authorize( Alynt_Auth_Proof_Ports::SITE ); }, 'verify' => 'wp_verify_nonce', 'nonce' => 'wp_create_nonce', 'clock' => 'time', 'uuid' => 'wp_generate_uuid4', 'read' => array( $this, 'read' ), 'compare' => array( $this, 'compare' ), 'transaction' => array( $this, 'transaction' ) ), Alynt_Auth_Proof_Ports::SITE, $this->db, $enabled );
	}
	public function issue( $boundary, $purpose, $id = null ) {
		$this->auth->authorize( Alynt_Auth_Proof_Ports::SITE ); $confirm = array( 'upgrade_closed' => 'close', 'begin_disable' => 'disable', 'abandon_closed' => 'abandon', 'receipt_read' => 'observe' );
		$form = array( 'purpose' => $purpose, 'confirm' => $confirm[$purpose], 'nonce' => wp_create_nonce( $boundary->issue_action( $purpose ) ) );
		if ( null !== $id ) $form[ 'receipt_read' === $purpose ? 'observed_command_id' : 'operation_id' ] = $id;
		$r = $boundary->issue( 'POST', $form ); alynt_lifecycle_check( is_array( $r ) && isset( $r['nonce'], $r['command_id'] ), 'Genuine-core fixture issuance failed.' ); return $r;
	}
	public function read( $lock = false ) {
		$this->reads++; if ( $lock ) $this->phase = 'safety_lock'; $row = $this->db->one( 'SELECT option_value,autoload FROM ' . $this->db->table . ' WHERE option_name=?' . ( $lock ? ' FOR UPDATE' : '' ), array( alynt_writer_state_name() ) );
		alynt_lifecycle_check( is_array( $row ) && strlen( $row['option_value'] ) <= 131072, 'Admission safety row unavailable.' );
		if ( $lock ) $this->phase = 'target_lock'; $target = $this->db->value( 'probe_settings', $lock ); alynt_lifecycle_check( is_string( $target ) && strlen( $target ) <= 1048576, 'Admission target unavailable.' );
		$s = json_decode( $row['option_value'], true, 16 ); alynt_lifecycle_check( is_array( $s ), 'Admission safety bytes invalid.' );
		if ( ! $lock && $this->revoke_on_read ) { $this->revoke_on_read = false; WP_Session_Tokens::get_instance( 90000001 )->destroy_all(); }
		return array( 'state' => $s, 'target' => $target, 'autoload' => $row['autoload'] );
	}
	public function compare( $expected, $next ) {
		$this->writes++; $this->start();
		try {
			$row = $this->read( true ); if ( $row !== $expected ) { $this->db->exec( 'ROLLBACK' ); return false; }
			$purpose = $next['writer']['completion']['purpose'] ?? $next['writer']['closure']['purpose'] ?? 'upgrade_closed';
			$slot = 'abandon_closed' === $purpose ? 'completion' : ( 'begin_disable' === $purpose ? 'closure' : 'upgrade' ); $intent = $next['writer'][$slot]['command'];
			alynt_lifecycle_check( time() === $next['lifecycle']['changed_at'] && time() < $intent['expires_at'], 'Closed comparison original clock expired.' );
			$raw = $this->db->value( alynt_writer_state_name() );
			alynt_lifecycle_check( 1 === $this->db->exec( 'UPDATE ' . $this->db->table . ' SET option_value=? WHERE option_name=? AND BINARY option_value=BINARY ?', array( wp_json_encode( $next ), alynt_writer_state_name(), $raw ) ), 'Admission closed comparison failed.' );
			alynt_lifecycle_check( time() === $next['lifecycle']['changed_at'] && time() < $intent['expires_at'], 'Closed comparison pre-commit deadline expired.' );
			$this->db->exec( 'COMMIT' );
		} catch ( Throwable $error ) { $this->db->exec( 'ROLLBACK' ); throw $error; }
		if ( 'begin_disable' === ( $next['writer']['closure']['purpose'] ?? null ) ) {
			if ( 'begin_false_ack' === $this->fault ) return false;
			if ( 'begin_throw_ack' === $this->fault ) throw new RuntimeException( 'Injected owned begin acknowledgement loss.' );
			if ( 'revoke_after_begin' === $this->fault ) WP_Session_Tokens::get_instance( 90000001 )->destroy_all();
		}
		return true;
	}
	public function start() { alynt_lifecycle_check( '0' === (string) $this->db->one( 'SELECT @@in_transaction AS active' )['active'], 'Nested fixture transaction denied.' ); $this->db->exec( 'START TRANSACTION' ); }
	public function transaction( $continuation ) { return alynt_admission_transaction( $this, $continuation ); }
	public function pause( $phase ) {
		$ipc = new Alynt_Admission_Proof_DB( $this->base );
		try { $ipc->owned( $this->token ); $ipc->marker( $phase ); $ipc->wait( 'release_' . $phase ); } finally { $ipc->close(); }
	}
}
