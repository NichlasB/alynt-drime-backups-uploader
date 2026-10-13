<?php
/** Harmless process/callback checkpoints within the owned options-clone harness. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );

class Alynt_Scan_Proof_CAS extends Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS {
	private $db;
	private $actor;
	private $checkpoint;
	private $first = true;
	public $faults = array();
	public function __construct( $db, $actor = '1', $checkpoint = '' ) { parent::__construct( $db ); $this->db = $db; $this->actor = $actor; $this->checkpoint = $checkpoint; }
	public function read( $name ) {
		$row = parent::read( $name );
		if ( $this->first && 'race' === $this->checkpoint ) {
			$this->first = false; $this->db->insert( $this->db->options, array( 'option_name' => 'probe_scan_ready_' . $this->actor, 'option_value' => 'ready', 'autoload' => 'no' ) );
			$limit = microtime( true ) + 15;
			while ( 'go' !== $this->db->get_var( "SELECT option_value FROM {$this->db->options} WHERE option_name='probe_scan_go'" ) ) { if ( microtime( true ) >= $limit ) exit( 1 ); usleep( 20000 ); }
		}
		return $row;
	}
	public function swap( $name, $expected, $replacement ) {
		$s = json_decode( $replacement, true ); $phase = $s['dispatch']['phase'] ?? '';
		if ( in_array( $phase, $this->faults, true ) ) {
			$previous = $this->db->suppress_errors( true );
			try { alynt_safety_proof_check( false !== $this->db->query( 'START TRANSACTION READ ONLY' ), 'Scan fault transaction failed.' ); return parent::swap( $name, $expected, $replacement ); }
			finally { $this->db->query( 'ROLLBACK' ); $this->db->suppress_errors( $previous ); }
		}
		$result = parent::swap( $name, $expected, $replacement );
		if ( true === $result && $phase === $this->checkpoint ) $this->pause();
		return $result;
	}
	public function effect() {
		alynt_safety_proof_check( 1 === $this->db->query( "UPDATE {$this->db->options} SET option_value=CAST(option_value AS UNSIGNED)+1 WHERE option_name='probe_scan_effects'" ), 'Harmless effect counter failed.' );
		if ( 'after_effect' === $this->checkpoint ) $this->pause();
		return array( 'errors' => array(), 'candidates' => array(), 'queued' => 0 );
	}
	private function pause() {
		$this->db->insert( $this->db->options, array( 'option_name' => 'probe_scan_checkpoint_' . $this->checkpoint, 'option_value' => 'ready', 'autoload' => 'no' ) );
		$limit = microtime( true ) + 15;
		while ( microtime( true ) < $limit ) usleep( 20000 );
		exit( 1 ); // Parent must terminate at the checkpoint, never release/resume it.
	}
}

function alynt_scan_proof_dispatcher( $model, $binding, &$now, $actor = 9 ) {
	return new Alynt_Drime_Backups_Uploader_Cadence_Authority_Scan_Dispatcher( $model, function () use ( $binding ) { return array( 'binding' => $binding, 'scan_enabled' => true ); }, true, function () use ( &$now ) { return $now; }, function () use ( $actor ) { return sprintf( '77777777-7777-4777-8777-%012d', (int) $actor ); } );
}

function alynt_scan_proof_child( $table, $actor, $now, $checkpoint, $binding ) {
	global $wpdb;
	alynt_safety_proof_check( in_array( $checkpoint, array( 'race', 'claimed', 'started', 'after_effect', 'completed' ), true ), 'Unknown scan checkpoint.' );
	$backend = new Alynt_Scan_Proof_CAS( $wpdb, $actor, $checkpoint );
	$model = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Store( $backend, function () use ( &$now ) { return $now; } );
	$result = alynt_scan_proof_dispatcher( $model, $binding, $now, $actor )->run( array( $backend, 'effect' ) );
	echo json_encode( array( 'outcome' => is_array( $result ) ? 'completed' : 'blocked' ) ) . "\n";
}

function alynt_scan_proof_spawn( $root, $table, &$children, $actor, $now, $checkpoint ) {
	$command = array( PHP_BINARY, '-c', get_cfg_var( 'cfg_file_path' ), __DIR__ . '/schedule-safety-local-proof.php', $root, '--scan-child', $table, (string) $actor, (string) $now, $checkpoint );
	$process = proc_open( $command, array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
	alynt_safety_proof_check( is_resource( $process ), 'Scan child failed to start.' ); fclose( $pipes[0] );
	$children[] = array( 'process' => $process, 'pipes' => $pipes, 'output' => '' ); return count( $children ) - 1;
}

function alynt_scan_proof_close( &$child, $terminate = false ) {
	if ( $terminate ) alynt_safety_proof_check( proc_terminate( $child['process'] ), 'Scan checkpoint termination failed.' );
	$limit = microtime( true ) + 15;
	do { $status = proc_get_status( $child['process'] ); if ( ! $status['running'] ) break; usleep( 20000 ); } while ( microtime( true ) < $limit );
	alynt_safety_proof_check( ! $status['running'], 'Scan child did not stop.' );
	$child['output'] = stream_get_contents( $child['pipes'][1] ); fclose( $child['pipes'][1] ); $errors = stream_get_contents( $child['pipes'][2] ); fclose( $child['pipes'][2] );
	$closed = proc_close( $child['process'] ); $child['process'] = null;
	if ( ! $terminate ) alynt_safety_proof_check( 0 === $status['exitcode'] || 0 === $closed, 'Scan child failed.' );
}

function alynt_scan_proof_wait( $table, $names, $count ) {
	global $wpdb; $limit = microtime( true ) + 15;
	$placeholders = implode( ',', array_fill( 0, count( $names ), '%s' ) );
	do { $ready = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE option_name IN ({$placeholders})", $names ) ); if ( $count === $ready ) return; usleep( 20000 ); } while ( microtime( true ) < $limit );
	throw new RuntimeException( 'Scan child checkpoint was not reached.' );
}

function alynt_guarded_scan_local_proof( $backend, $root, $table, &$children, &$receipt, $binding, &$now ) {
	global $wpdb;
	alynt_safety_proof_check( $wpdb->options === $table && preg_match( '/^wp_alynt_safety_probe_[a-f0-9]{12}$/D', $table ), 'Scan proof is not using its owned table.' );
	$name = Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION;
	$seed = array( 'schema_version' => 2, 'generation' => 0, 'enrollment_binding' => $binding, 'schedule_revision' => 0, 'claims' => array(), 'authority' => array( 'mode' => 'client_owned_v1', 'cadence' => 'every_30_minutes', 'next_due_at' => $now, 'enabled' => true ), 'dispatch' => null );
	$reset = function () use ( $backend, $name, $seed, $table ) {
		global $wpdb;
		// Independent owned-fixture cases only; this is NOT a recovery/reset API.
		alynt_safety_proof_check( true === $backend->swap( $name, $backend->read( $name )['option_value'], wp_json_encode( $seed ) ), 'Owned scan-fixture reset failed.' );
		$wpdb->update( $table, array( 'option_value' => '0' ), array( 'option_name' => 'probe_scan_effects' ) );
	};
	$wpdb->insert( $table, array( 'option_name' => 'probe_scan_effects', 'option_value' => '0', 'autoload' => 'no' ) ); $reset();
	$model = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Store( $backend, function () use ( &$now ) { return $now; } );
	$indexes = array(); for ( $actor = 1; $actor <= 2; $actor++ ) $indexes[] = alynt_scan_proof_spawn( $root, $table, $children, $actor, $now, 'race' );
	alynt_scan_proof_wait( $table, array( 'probe_scan_ready_1', 'probe_scan_ready_2' ), 2 );
	$wpdb->insert( $table, array( 'option_name' => 'probe_scan_go', 'option_value' => 'go', 'autoload' => 'no' ) );
	foreach ( $indexes as $index ) alynt_scan_proof_close( $children[$index] );
	alynt_safety_proof_check( '1' === $wpdb->get_var( "SELECT option_value FROM {$table} WHERE option_name='probe_scan_effects'" ) && 'completed' === $model->snapshot()['dispatch']['phase'] && $now + 1800 === $model->snapshot()['authority']['next_due_at'], 'Scan race did not produce exactly one callback and completion.' );
	$receipt['guarded_scan_two_process_single_callback'] = true;
	foreach ( array( 'claimed', 'started', 'after_effect', 'completed' ) as $checkpoint ) {
		$reset(); $index = alynt_scan_proof_spawn( $root, $table, $children, 1, $now, $checkpoint );
		alynt_scan_proof_wait( $table, array( 'probe_scan_checkpoint_' . $checkpoint ), 1 ); alynt_scan_proof_close( $children[$index], true );
		$expected_count = in_array( $checkpoint, array( 'after_effect', 'completed' ), true ) ? '1' : '0'; $s = $model->snapshot();
		alynt_safety_proof_check( $expected_count === $wpdb->get_var( "SELECT option_value FROM {$table} WHERE option_name='probe_scan_effects'" ) && ( 'after_effect' === $checkpoint ? 'started' : $checkpoint ) === $s['dispatch']['phase'], 'Checkpoint lost callback/journal evidence.' );
		$restart_now = $now + 300; $restart = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Store( $backend, function () use ( &$restart_now ) { return $restart_now; } );
		$replays = 0;
		foreach ( array( 1, 9 ) as $actor ) alynt_safety_proof_check( is_wp_error( alynt_scan_proof_dispatcher( $restart, $binding, $restart_now, $actor )->run( function () use ( &$replays ) { $replays++; return array( 'errors' => array(), 'candidates' => array(), 'queued' => 0 ); } ) ), 'Crash checkpoint was replayed.' );
		alynt_safety_proof_check( 0 === $replays && $s === $restart->snapshot() && $expected_count === $wpdb->get_var( "SELECT option_value FROM {$table} WHERE option_name='probe_scan_effects'" ), 'Restart replayed callback or changed protected evidence.' );
		$receipt['guarded_scan_terminated_' . $checkpoint . '_never_replayed'] = true;
	}
	foreach ( array( array( 'started' ), array( 'completed' ), array( 'completed', 'recovery_required' ) ) as $faults ) {
		$reset(); $fault = new Alynt_Scan_Proof_CAS( $wpdb ); $fault->faults = $faults;
		$fault_model = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Store( $fault, function () use ( &$now ) { return $now; } );
		$result = alynt_scan_proof_dispatcher( $fault_model, $binding, $now )->run( array( $fault, 'effect' ) );
		$expected_phase = 'started' === $faults[0] ? 'claimed' : ( count( $faults ) > 1 ? 'started' : 'recovery_required' );
		alynt_safety_proof_check( is_wp_error( $result ) && $expected_phase === $model->snapshot()['dispatch']['phase'] && $now === $model->snapshot()['authority']['next_due_at'], 'SQL fault acknowledged/advanced or discarded ownership.' );
		alynt_safety_proof_check( ( 'started' === $faults[0] ? '0' : '1' ) === $wpdb->get_var( "SELECT option_value FROM {$table} WHERE option_name='probe_scan_effects'" ), 'SQL fault callback count was unsafe.' );
	}
	$receipt['guarded_scan_real_start_completion_recovery_sql_faults_closed'] = true;
}
