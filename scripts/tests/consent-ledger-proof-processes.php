<?php
/** Owned SQL process barriers and interrupted receipt acknowledgement; no runtime authorization. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
class Alynt_Ledger_Proof_CAS extends Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS {
	private $db;
	private $actor;
	private $case;
	private $first = true;
	public function __construct( $db, $actor, $case ) { parent::__construct( $db ); $this->db = $db; $this->actor = $actor; $this->case = $case; }
	public function read( $name ) {
		$row = parent::read( $name );
		if ( $this->first && 0 !== strpos( $this->case, 'lost_ack_' ) ) {
			$this->first = false; $this->marker( 'ready_' . $this->actor );
			alynt_lifecycle_wait( $this->db->options, 'probe_ledger_' . $this->case . ( in_array( $this->case, array( 'ordered', 'revoke_ordered' ), true ) && '1' === $this->actor ? '_done_2' : '_go' ) );
		}
		return $row;
	}
	public function swap( $name, $expected, $replacement ) {
		$result = parent::swap( $name, $expected, $replacement );
		if ( true === $result ) {
			$this->marker( 'done_' . $this->actor );
			if ( 0 === strpos( $this->case, 'lost_ack_' ) ) { $limit = microtime( true ) + 15; while ( microtime( true ) < $limit ) usleep( 20000 ); exit( 1 ); }
		}
		return $result;
	}
	private function marker( $suffix ) { alynt_lifecycle_check( 1 === $this->db->insert( $this->db->options, array( 'option_name' => 'probe_ledger_' . $this->case . '_' . $suffix, 'option_value' => 'ready', 'autoload' => 'no' ) ), 'Ledger child marker failed.' ); }
}
function alynt_ledger_intent( $purpose, $number, $now, $generation = 1, $binding = null ) {
	return array( 'command_id' => sprintf( '11111111-1111-4111-8111-%012d', $number ), 'purpose' => $purpose, 'site_uuid' => '99999999-9999-4999-8999-999999999999',
		'enrollment_binding' => null === $binding ? str_repeat( 'e', 64 ) : $binding, 'expected_generation' => $generation,
		'issued_at' => 'grant' === $purpose ? $now : 0, 'expires_at' => 'grant' === $purpose ? $now + 300 : 0, 'replacement_binding' => 'rebind' === $purpose ? str_repeat( 'f', 64 ) : null );
}
function alynt_ledger_model( $backend, $now ) { return new Alynt_Drime_Backups_Uploader_Cadence_Consent_Ledger( $backend, '99999999-9999-4999-8999-999999999999', function () use ( $now ) { return $now; }, true ); }
function alynt_ledger_child( $actor, $operation, $now, $case ) {
	global $wpdb;
	alynt_lifecycle_check( in_array( $actor, array( '1', '2' ), true ) && in_array( $operation, array( 'migrate', 'grant', 'revoke', 'rebind' ), true ) && in_array( $case, array( 'migrate', 'race', 'ordered', 'revoke_ordered', 'duplicate', 'lost_ack_grant', 'lost_ack_close' ), true ) && $now > 0 && $now <= PHP_INT_MAX - 3600, 'Invalid ledger child scenario.' );
	$model = alynt_ledger_model( new Alynt_Ledger_Proof_CAS( $wpdb, $actor, $case ), $now );
	$generation = 'lost_ack_close' === $case ? 2 : 1;
	$command = alynt_ledger_intent( $operation, 'duplicate' === $case || 'lost_ack_grant' === $case ? 1 : (int) $actor, $now, $generation );
	$result = 'migrate' === $operation ? $model->prepare_ledger( str_repeat( 'e', 64 ), 0 ) : $model->command( $command );
	echo json_encode( array( 'ok' => ! is_wp_error( $result ), 'code' => is_wp_error( $result ) ? $result->get_error_code() : ( $result['outcome'] ?? 'stored' ) ) ) . "\n";
}
function alynt_ledger_spawn( $root, $table, $token, &$children, $actor, $operation, $now, $case ) {
	$command = array( PHP_BINARY, '-c', get_cfg_var( 'cfg_file_path' ), __DIR__ . '/lifecycle-local-proof.php', $root, '--ledger-child', $table, $token, (string) $actor, $operation, (string) $now, $case );
	$p = proc_open( $command, array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
	alynt_lifecycle_check( is_resource( $p ), 'Ledger child launch failed.' ); fclose( $pipes[0] ); $children[] = array( 'process' => $p, 'pipes' => $pipes ); return count( $children ) - 1;
}
