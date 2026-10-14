<?php
/** Owned closed-migration barriers; no real authorization or production construction. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
class Alynt_Provenance_Proof_CAS extends Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS {
	private $db; private $actor; private $case; private $first = true;
	public function __construct( $db, $actor, $case ) { parent::__construct( $db ); $this->db = $db; $this->actor = $actor; $this->case = $case; }
	public function read( $name ) {
		$row = parent::read( $name );
		if ( $this->first && 0 !== strpos( $this->case, 'lost_ack_' ) ) {
			$this->first = false; $this->marker( 'ready_' . $this->actor );
			alynt_lifecycle_wait( $this->db->options, 'probe_provenance_' . $this->case . ( 'ordered' === $this->case && '1' === $this->actor ? '_done_2' : '_go' ) );
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
	private function marker( $suffix ) { alynt_lifecycle_check( 1 === $this->db->insert( $this->db->options, array( 'option_name' => 'probe_provenance_' . $this->case . '_' . $suffix, 'option_value' => 'ready', 'autoload' => 'no' ) ), 'Provenance child marker failed.' ); }
}
function alynt_provenance_model( $backend, $now, $enabled = true ) { return new Alynt_Drime_Backups_Uploader_Closed_Consent_Provenance_Store( $backend, '99999999-9999-4999-8999-999999999999', function () use ( $now ) { return $now; }, $enabled ); }
function alynt_provenance_child( $actor, $operation, $now, $case ) {
	global $wpdb;
	alynt_lifecycle_check( in_array( $actor, array( '1', '2' ), true ) && in_array( $operation, array( 'migrate', 'revoke' ), true ) && in_array( $case, array( 'migrate_three', 'migrate_four', 'ordered', 'lost_ack_three', 'lost_ack_four' ), true ) && $now > 0 && $now <= PHP_INT_MAX - 3600, 'Invalid provenance child scenario.' );
	alynt_lifecycle_check( 'revoke' !== $operation || ( 'ordered' === $case && '2' === $actor ), 'Invalid fixture closure actor.' );
	$backend = new Alynt_Provenance_Proof_CAS( $wpdb, $actor, $case ); $generation = false !== strpos( $case, 'three' ) ? 0 : 2;
	$result = 'migrate' === $operation ? alynt_provenance_model( $backend, $now )->prepare_closed( str_repeat( 'e', 64 ), $generation, function () { return true; } ) : alynt_ledger_model( $backend, $now )->command( alynt_ledger_intent( 'revoke', 2, $now, 2 ) );
	echo json_encode( array( 'ok' => ! is_wp_error( $result ), 'code' => is_wp_error( $result ) ? $result->get_error_code() : 'stored' ) ) . "\n";
}
function alynt_provenance_spawn( $root, $table, $token, &$children, $actor, $operation, $now, $case ) {
	$command = array( PHP_BINARY, '-c', get_cfg_var( 'cfg_file_path' ), __DIR__ . '/lifecycle-local-proof.php', $root, '--provenance-child', $table, $token, (string) $actor, $operation, (string) $now, $case );
	$p = proc_open( $command, array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
	alynt_lifecycle_check( is_resource( $p ), 'Provenance child launch failed.' ); fclose( $pipes[0] ); $children[] = array( 'process' => $p, 'pipes' => $pipes ); return count( $children ) - 1;
}
