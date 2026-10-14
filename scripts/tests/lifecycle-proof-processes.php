<?php
/** Controlled independent PHP readers and commit-acknowledgement interruption; fixture only. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
class Alynt_Lifecycle_Proof_CAS extends Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS {
	private $db;
	private $actor;
	private $case;
	private $first = true;
	public function __construct( $db, $actor, $case ) { parent::__construct( $db ); $this->db = $db; $this->actor = $actor; $this->case = $case; }
	public function read( $name ) {
		$row = parent::read( $name );
		if ( $this->first && 'lost_ack' !== $this->case ) {
			$this->first = false; $this->marker( 'ready_' . $this->actor );
			alynt_lifecycle_wait( $this->db->options, 'probe_' . $this->case . ( in_array( $this->case, array( 'ordered', 'revoke_ordered' ), true ) && '1' === $this->actor ? '_done_2' : '_go' ) );
		}
		return $row;
	}
	public function swap( $name, $expected, $replacement ) {
		$result = parent::swap( $name, $expected, $replacement );
		if ( true === $result ) {
			$this->marker( 'done_' . $this->actor );
			if ( 'lost_ack' === $this->case ) { $limit = microtime( true ) + 15; while ( microtime( true ) < $limit ) usleep( 20000 ); exit( 1 ); }
		}
		return $result;
	}
	private function marker( $suffix ) {
		alynt_lifecycle_check( 1 === $this->db->insert( $this->db->options, array( 'option_name' => 'probe_' . $this->case . '_' . $suffix, 'option_value' => 'ready', 'autoload' => 'no' ) ), 'Child marker failed.' );
	}
}
function alynt_lifecycle_wait( $table, $name ) {
	global $wpdb; $limit = microtime( true ) + 15;
	do { if ( null !== $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$table} WHERE option_name=%s", $name ) ) ) return; usleep( 20000 ); } while ( microtime( true ) < $limit );
	throw new RuntimeException( 'Child barrier timeout.' );
}
function alynt_lifecycle_child( $actor, $operation, $now, $case ) {
	global $wpdb;
	alynt_lifecycle_check( in_array( $actor, array( '1', '2' ), true ) && in_array( $operation, array( 'migrate', 'grant', 'revoke', 'rebind' ), true ) && in_array( $case, array( 'migrate', 'race', 'ordered', 'revoke_ordered', 'lost_ack' ), true ) && $now > 0, 'Invalid child scenario.' );
	$model = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Lifecycle_Store( new Alynt_Lifecycle_Proof_CAS( $wpdb, $actor, $case ), function () use ( $now ) { return $now; } );
	$binding = str_repeat( 'e', 64 );
	$result = 'migrate' === $operation ? $model->prepare_lifecycle( $binding, 'every_15_minutes', $now + 900 ) : $model->record_consent( $binding, 0, 'grant' === $operation, 'rebind' === $operation ? str_repeat( 'f', 64 ) : null );
	echo json_encode( array( 'ok' => ! is_wp_error( $result ), 'code' => is_wp_error( $result ) ? $result->get_error_code() : 'stored' ) ) . "\n";
}
function alynt_lifecycle_spawn( $root, $table, $token, &$children, $actor, $operation, $now, $case ) {
	$command = array( PHP_BINARY, '-c', get_cfg_var( 'cfg_file_path' ), __DIR__ . '/lifecycle-local-proof.php', $root, '--child', $table, $token, (string) $actor, $operation, (string) $now, $case );
	$p = proc_open( $command, array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
	alynt_lifecycle_check( is_resource( $p ), 'Child launch failed.' ); fclose( $pipes[0] );
	$children[] = array( 'process' => $p, 'pipes' => $pipes ); return count( $children ) - 1;
}
function alynt_lifecycle_join( &$child, $terminate = false ) {
	if ( $terminate ) alynt_lifecycle_check( proc_terminate( $child['process'] ), 'Owned child termination failed.' );
	$limit = microtime( true ) + 15;
	do { $status = proc_get_status( $child['process'] ); if ( ! $status['running'] ) break; usleep( 20000 ); } while ( microtime( true ) < $limit );
	alynt_lifecycle_check( ! $status['running'], 'Child failed to stop.' );
	$output = stream_get_contents( $child['pipes'][1] ); fclose( $child['pipes'][1] ); stream_get_contents( $child['pipes'][2] ); fclose( $child['pipes'][2] );
	$closed = proc_close( $child['process'] ); $child['process'] = null;
	if ( $terminate ) {
		// PHP startup diagnostics can precede the protocol. Only a structured receipt is acknowledgement.
		foreach ( explode( "\n", trim( $output ) ) as $line ) {
			$r = json_decode( $line, true ); alynt_lifecycle_check( ! is_array( $r ) || ! array_key_exists( 'ok', $r ), 'Terminated child unexpectedly acknowledged commit.' );
		}
		return;
	}
	alynt_lifecycle_check( 0 === $status['exitcode'] || 0 === $closed, 'Child execution failed.' );
	$lines = explode( "\n", trim( $output ) ); $result = json_decode( end( $lines ), true ); alynt_lifecycle_check( is_array( $result ), 'Missing child receipt.' ); return $result;
}
