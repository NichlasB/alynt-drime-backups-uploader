<?php
/** Developer prototype on owned fixtures only; no runtime factory, hooks or authorization claim. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
final class Alynt_Closed_Writer_Proof_Transaction {
	private $db; private $token;
	public function __construct( $db, $token ) { $db->owned( $token ); $this->db = $db; $this->token = $token; }
	public function run( array $original, $id, $purpose = 'finish', $fault = 'none', $marker = '' ) {
		alynt_lifecycle_check( isset( $original['kind'], $original['target'], $original['fields'], $original['site_uuid'], $original['enrollment_binding'] ) && 'settings_patch' === $original['kind'] && 'settings' === $original['target'] && array( 'auto_scan_enabled' ) === $original['fields'] && '99999999-9999-4999-8999-999999999999' === $original['site_uuid'] && str_repeat( 'e', 64 ) === $original['enrollment_binding'], 'Prototype accepts only its fixed synthetic settings descriptor.' );
		$db = $this->db; $db->owned( $this->token );
		alynt_lifecycle_check( in_array( $purpose, array( 'finish', 'abandon', 'timestamp' ), true ) && in_array( $fault, array( 'none', 'target_sql', 'terminal_sql', 'pause_before_commit', 'pause_after_commit' ), true ), 'Invalid prototype operation.' );
		$active = $db->one( 'SELECT @@in_transaction AS active' );
		alynt_lifecycle_check( is_array( $active ) && '0' === (string) $active['active'], 'Nested transaction denied; never implicitly commit caller writes.' );
		// Fixed fixture admission only. Real capability/session checks are a separate gate.
		$db->exec( 'START TRANSACTION' ); $committed = false; $phase = 'safety_lock';
		try {
			$db->value( alynt_writer_state_name(), true ); // Mandatory first lock, never target first.
			$phase = 'target_lock';
			$raw = $db->value( 'probe_settings', true ); $target = alynt_writer_target( $raw );
			$phase = 'pending';
			$s = alynt_writer_closed( $db ); $b = $s['barrier'];
			alynt_lifecycle_check( $b['pending'] === $original['command_id'] && null !== $b['closure'] && $original === $b['closure']['command'] && $s['lifecycle']['permission_generation'] === $original['expected_generation'] + 1, 'Original pending operation no longer current.' );
			$now = time();
			$phase = 'deadline';
			alynt_lifecycle_check( $now >= $s['lifecycle']['changed_at'] && ( 'abandon' === $purpose || ( $now >= $original['issued_at'] && $now < $original['expires_at'] ) ), 'Original writer deadline/clock invalid after locks.' );
			$hash = hash( 'sha256', $raw );
			$phase = 'precondition';
			if ( 'timestamp' === $purpose ) {
				$target['polled_at']++; $next = wp_json_encode( $target );
			} elseif ( 'finish' === $purpose ) {
				alynt_lifecycle_check( $hash === $original['before_hash'], 'Locked target precondition changed.' );
				$target['auto_scan_enabled'] = ! $target['auto_scan_enabled']; $next = wp_json_encode( $target );
				alynt_lifecycle_check( hash( 'sha256', $next ) === $original['after_hash'], 'Exact synthetic delta differs.' );
			} else {
				alynt_lifecycle_check( in_array( $hash, array( $original['before_hash'], $original['after_hash'] ), true ), 'Abandon target facts unresolved.' ); $next = $raw;
			}
			$phase = 'target_write';
			if ( 'target_sql' === $fault ) $db->exec( 'UPDATE ' . $db->table . ' SET missing_fixture_column=? WHERE option_name=?', array( 'fail', 'probe_settings' ) );
			if ( $next !== $raw ) alynt_lifecycle_check( 1 === $db->exec( 'UPDATE ' . $db->table . ' SET option_value=? WHERE option_name=? AND BINARY option_value=BINARY ?', array( $next, 'probe_settings', $raw ) ), 'Conditional fixture target write failed.' );
			if ( 'timestamp' !== $purpose ) {
				$phase = 'terminal_write';
				$c = array( 'command_id' => $id, 'operation_id' => $original['command_id'], 'site_uuid' => $original['site_uuid'], 'enrollment_binding' => $original['enrollment_binding'], 'expected_generation' => $s['lifecycle']['permission_generation'], 'closure_generation' => $b['closure']['result_generation'], 'observed_hash' => hash( 'sha256', $next ), 'issued_at' => 'abandon' === $purpose ? $now : $original['issued_at'], 'expires_at' => 'abandon' === $purpose ? $now + 300 : $original['expires_at'] );
				if ( 'terminal_sql' === $fault ) $db->exec( 'UPDATE ' . $db->table . ' SET missing_fixture_column=? WHERE option_name=?', array( 'fail', alynt_writer_state_name() ) );
				$r = alynt_writer_model( $db )->$purpose( $c, function () { return true; } );
				alynt_lifecycle_check( alynt_writer_ok( $r ), 'Terminal fixture CAS failed.' );
				$after = alynt_writer_closed( $db ); alynt_lifecycle_check( null === $after['barrier']['pending'] && $after['barrier']['closure'] === $b['closure'] && $id === $after['barrier']['completion']['command']['command_id'] && $purpose === $after['barrier']['completion']['purpose'], 'Atomic terminal result invalid.' );
			}
			$phase = 'before_commit';
			if ( 'pause_before_commit' === $fault ) $this->pause( $marker );
			$commit_now = time(); $deadline = 'abandon' === $purpose ? $now + 300 : $original['expires_at'];
			alynt_lifecycle_check( $commit_now >= $now && $commit_now < $deadline, 'Writer deadline/clock invalid before commit.' );
			$phase = 'commit'; $db->exec( 'COMMIT' ); $committed = true;
			// No speculative caches exist: the native connection/model never populate or invalidate WP options.
			if ( 'pause_after_commit' === $fault ) $this->pause( $marker );
			return array( 'ok' => true, 'result' => 'committed', 'execution_supported' => false );
		} catch ( Throwable $error ) {
			if ( ! $committed ) $db->exec( 'ROLLBACK' );
			return array( 'ok' => false, 'result' => $committed ? 'committed_ack_unresolved' : 'unresolved', 'phase' => $phase, 'execution_supported' => false );
		}
	}
	private function pause( $marker ) {
		alynt_lifecycle_check( 1 === preg_match( '/^case_[a-z0-9_]+$/D', $marker ), 'Invalid interrupt marker.' );
		// Separate owned control connection is explicit test instrumentation, never a production callback.
		$signal = new Alynt_Closed_Writer_Proof_DB( $this->db->table );
		try { $signal->marker( $marker . '_staged' ); $signal->wait( $marker . '_release' ); } finally { $signal->close(); }
	}
}
