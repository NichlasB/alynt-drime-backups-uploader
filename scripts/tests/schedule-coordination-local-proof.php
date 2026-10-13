<?php
/** Optional cooperative-writer proof inside the existing owned-table harness. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );

function alynt_coordination_proof_filters( $prototype, $install ) {
	global $wpdb;
	alynt_safety_proof_check( preg_match( '/^wp_alynt_safety_probe_[a-f0-9]{12}$/D', $wpdb->options ), 'Prototype filters require the owned fixture table.' );
	foreach ( $prototype->filters() as $hook => $config ) {
		if ( $install ) add_filter( $hook, array( $prototype, $config[0] ), 10, $config[1] );
		else remove_filter( $hook, array( $prototype, $config[0] ), 10 );
	}
}

function alynt_coordination_local_proof( $backend, $site_root, $table, &$children, &$receipt ) {
	global $wpdb;
	$start = count( $children ); $now = time();
	for ( $i = 1; $i <= 2; $i++ ) {
		$command = array( PHP_BINARY, '-c', get_cfg_var( 'cfg_file_path' ), __DIR__ . '/schedule-safety-local-proof.php', $site_root, '--cron-child', $table, (string) $i, (string) $now );
		$process = proc_open( $command, array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
		alynt_safety_proof_check( is_resource( $process ), 'Cron child failed to start.' ); fclose( $pipes[0] );
		$children[] = array( 'process' => $process, 'pipes' => $pipes, 'output' => '' );
	}
	$deadline = microtime( true ) + 15;
	do {
		$ready = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE option_name IN ('probe_cron_ready_1','probe_cron_ready_2')" );
		if ( 2 === $ready ) break;
		usleep( 20000 );
	} while ( microtime( true ) < $deadline );
	alynt_safety_proof_check( 2 === $ready, 'Cron readers did not reach the shared-snapshot barrier.' );
	$wpdb->insert( $table, array( 'option_name' => 'probe_cron_go', 'option_value' => 'go', 'autoload' => 'no' ) );
	for ( $i = $start; $i < count( $children ); $i++ ) {
		$child = &$children[ $i ]; $limit = microtime( true ) + 15;
		do { $status = proc_get_status( $child['process'] ); if ( ! $status['running'] ) break; usleep( 20000 ); } while ( microtime( true ) < $limit );
		alynt_safety_proof_check( ! $status['running'], 'Cron child timed out.' );
		$output = stream_get_contents( $child['pipes'][1] ); fclose( $child['pipes'][1] );
		stream_get_contents( $child['pipes'][2] ); fclose( $child['pipes'][2] );
		$closed = proc_close( $child['process'] ); $child['process'] = null;
		alynt_safety_proof_check( 0 === $status['exitcode'] || 0 === $closed, 'Cron child failed.' );
		$lines = explode( "\n", trim( $output ) ); $result = json_decode( end( $lines ), true );
		alynt_safety_proof_check( is_array( $result ) && true === $result['won'], 'Cooperative core writer did not confirm persistence.' );
		unset( $child );
	}
	$cron = unserialize( $backend->read( 'cron' )['option_value'] );
	alynt_safety_proof_check( isset( $cron[ $now + 3600 ]['alynt_coordination_fixture_child_1'], $cron[ $now + 3600 ]['alynt_coordination_fixture_child_2'], $cron[9999999999]['unrelated_fixture_event'] ), 'Concurrent core writers lost an unrelated event.' );
	$receipt['cooperative_core_two_process_writes_preserved'] = true;

	$prototype = new Alynt_Drime_Backups_Uploader_Test_Cron_Writer_Prototype( $backend );
	alynt_coordination_proof_filters( $prototype, true );
	try {
		// Real core return conventions, argument identity and cache-independent rebase.
		wp_cache_set( 'cron', array( 'version' => 2 ), 'options' );
		wp_cache_set( 'alloptions', array( 'cron' => serialize( array( 'version' => 2 ) ) ), 'options' );
		alynt_safety_proof_check( true === wp_schedule_event( $now + 7200, 'hourly', 'alynt_coordination_fixture_recurring', array(), true ), 'Recurring event was not stored.' );
		alynt_safety_proof_check( true === wp_reschedule_event( $now + 7200, 'hourly', 'alynt_coordination_fixture_recurring', array(), true ), 'Core reschedule was not intercepted.' );
		alynt_safety_proof_check( true === wp_schedule_single_event( $now + 10800, 'alynt_coordination_fixture_single', array( 'first' ), true ), 'Single event failed.' );
		alynt_safety_proof_check( true === wp_schedule_single_event( $now + 10800, 'alynt_coordination_fixture_single', array( 'second' ), true ), 'Distinct arguments collided.' );
		alynt_safety_proof_check( true === wp_unschedule_event( $now + 10800, 'alynt_coordination_fixture_single', array( 'first' ), true ), 'Exact event removal failed.' );
		alynt_safety_proof_check( 1 === wp_clear_scheduled_hook( 'alynt_coordination_fixture_single', array( 'second' ), true ), 'Clear count/args mismatch.' );
		alynt_safety_proof_check( 2 === wp_unschedule_hook( 'alynt_coordination_fixture_recurring', true ), 'Whole-hook removal count mismatch.' );
		$owned = Alynt_Drime_Backups_Uploader_Schedule_Safety_Cron_CAS::HOOK;
		$before = $backend->read( 'cron' )['option_value'];
		foreach ( array( wp_schedule_event( $now + 900, 'hourly', $owned, array(), true ), wp_reschedule_event( $now + 1800, 'hourly', $owned, array(), true ), wp_unschedule_event( $now + 1800, $owned, array(), true ), wp_clear_scheduled_hook( $owned, array(), true ), wp_unschedule_hook( $owned, true ) ) as $result ) {
			alynt_safety_proof_check( is_wp_error( $result ) && 'coordination_prototype_owned_event_protected' === $result->get_error_code(), 'Owned event escaped cooperative protection.' );
		}
		alynt_safety_proof_check( $before === $backend->read( 'cron' )['option_value'], 'Rejected mutation changed cron.' );
		alynt_safety_proof_check( false === wp_clear_scheduled_hook( $owned ), 'Core error-to-false convention changed.' );
		$receipt['core_api_results_args_cache_and_owned_protection'] = true;
		// Replay core's reschedule/unschedule/dispatch ordering with a harmless callback.
		alynt_safety_proof_check( false === has_action( $owned ), 'Unexpected real callback loaded in SHORTINIT.' );
		$calls = 0; $observer = function () use ( &$calls ) { $calls++; };
		add_action( $owned, $observer );
		try {
			alynt_safety_proof_check( is_wp_error( wp_reschedule_event( $now + 1800, 'hourly', $owned, array(), true ) ), 'Reschedule was not rejected.' );
			alynt_safety_proof_check( is_wp_error( wp_unschedule_event( $now + 1800, $owned, array(), true ) ), 'Unschedule was not rejected.' );
			do_action_ref_array( $owned, array() );
		} finally { remove_action( $owned, $observer ); }
		alynt_safety_proof_check( 1 === $calls && false === has_action( $owned ), 'Callback dispatch counterexample missing or observer not removed.' );
		$receipt['writer_rejection_does_not_block_callback_dispatch'] = true;

		$old_suppression = $wpdb->suppress_errors( true );
		try {
			alynt_safety_proof_check( false !== $wpdb->query( 'START TRANSACTION READ ONLY' ), 'Fault transaction failed.' );
			$failure = wp_schedule_event( $now + 14400, 'hourly', 'alynt_coordination_fixture_failure', array(), true );
			alynt_safety_proof_check( is_wp_error( $failure ) && 'schedule_safety_write_failed' === $failure->get_error_code(), 'Core API acknowledged a failed database write.' );
		} finally { $wpdb->query( 'ROLLBACK' ); $wpdb->suppress_errors( $old_suppression ); }
		alynt_safety_proof_check( $before === $backend->read( 'cron' )['option_value'], 'Fault transaction changed persisted cron.' );
		$receipt['cooperative_core_database_failure_closed'] = true;

		// A later short-circuiting plugin can report success without our persistence.
		$bypass = function () { return true; };
		add_filter( 'pre_schedule_event', $bypass, 20, 3 );
		try { $reported = wp_schedule_event( $now + 18000, 'hourly', $owned, array(), true ); }
		finally { remove_filter( 'pre_schedule_event', $bypass, 20 ); }
		alynt_safety_proof_check( true === $reported && $before === $backend->read( 'cron' )['option_value'], 'Filter-bypass counterexample missing.' );
		$receipt['later_filter_false_success_observed'] = true;
		// API filters do not intercept direct whole-option writers.
		$stale = unserialize( $before ); unset( $stale[ $now + 3600 ] );
		alynt_safety_proof_check( true === _set_cron_array( $stale, true ), 'Whole-option bypass counterexample failed.' );
		alynt_safety_proof_check( ! isset( get_option( 'cron' )[ $now + 3600 ] ), 'Whole-option writer did not bypass native API filters.' );
		$receipt['whole_option_writer_bypass_observed'] = true;
	} finally { alynt_coordination_proof_filters( $prototype, false ); }
	foreach ( $prototype->filters() as $hook => $config ) alynt_safety_proof_check( false === has_filter( $hook, array( $prototype, $config[0] ) ), 'Prototype filter survived cleanup.' );
	$receipt['prototype_filters_removed'] = true;
	$receipt['wordpress_version'] = $GLOBALS['wp_version'];
}
