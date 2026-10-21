<?php
/** Exact owned genuine-core entry. No endpoint, injected root, fault mode or response loss. */
if ( 'cli' !== PHP_SAPI || 6 !== count( $argv ) ) exit( 1 );
$root = realpath( $argv[1] );
if ( $root !== realpath( 'C:/Users/Captain/Local Sites/plugin-tester/app/public' ) || '10004' !== $argv[2] || ! ctype_digit( $argv[5] ) || (int) $argv[5] < 1 || (int) $argv[5] > 65535 || $argv[2] === $argv[5] || ! preg_match( '/^wp_alynt_core_probe_[a-f0-9]{12}_$/D', $argv[3] ) || ! preg_match( '/^[a-f0-9]{24}$/D', $argv[4] ) ) exit( 1 );
$passed = false; $audit = null; $stage = 'bootstrap'; $callback_error = null; $gates = array(); $probe = null;
try {
    $native_qualification_bootstrap_port = (int) $argv[5]; require __DIR__ . '/uncertain-barrier-no-fault-bootstrap.php';
    require __DIR__ . '/native-lifecycle-proof-source.php';
    alynt_core_check( Alynt_Test_Uncertain_Barrier_No_Fault_Manifest::composition(), 'native_qualification_composition' );
    require __DIR__ . '/native-observer-proof-fixtures.php'; require __DIR__ . '/registration-join-proof-fixtures.php';
    require __DIR__ . '/native-lifecycle-proof-checks.php'; require __DIR__ . '/uncertain-barrier-no-fault-channel.php'; require __DIR__ . '/uncertain-barrier-no-fault-denials.php';
    alynt_scan_seed( alynt_registration_values() ); alynt_core_login(); $form = alynt_registration_form();
    $audit = alynt_scan_db(); $before = alynt_registration_frame( $audit ); $auth = $wpdb->dbh;
    $auth_facts = $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row();
    $stage = 'denials';
    $denial_count = alynt_uncertain_barrier_denials( $form );
    $form = alynt_registration_form(); // Fresh genuine session after owned denial cases, before the root clock.
    alynt_core_assert( $before === alynt_registration_frame( $audit ), 'native_qualification_denials_unchanged' );
    $o = Alynt_Test_Uncertain_Barrier_No_Fault_Owner::create(); $channel = new Alynt_Test_Uncertain_Barrier_No_Fault_Channel();
    $roles = array( 'auth' => $auth->thread_id ); $start = null; $read_start = null; $proposal = null; $writer_erased = false; $barrier_closed = false;
    $probe = function ( $life, $action ) use ( $o, $channel, $auth, &$gates, &$roles, &$start, &$read_start, &$proposal, &$writer_erased, &$barrier_closed, &$callback_error ) {
        if ( ! in_array( 'wp_verify_nonce', array_column( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 14 ), 'function' ), true ) ) return $life;
        try {
            $gates[] = $action; $n = count( $gates ); $tick = hrtime( true ); $current = alynt_core_property( $o, 'started' );
            if ( null === $start ) $start = $current;
            alynt_core_assert( $n <= 24 && is_int( $start ) && $start === $current && $auth === $GLOBALS['wpdb']->dbh && $tick >= $start && $tick - $start < 20000000000, 'native_qualification_original_clock_auth' );
            if ( 1 === $n ) alynt_core_assert( null === alynt_core_property( $o, 'reader' ) && 0 === alynt_core_property( $o, 'protected_reads' ), 'native_qualification_before_protected_reads' );
            if ( 9 === $n || 10 === $n ) {
                $reader = alynt_core_property( $o, 'reader' ); $sessions = alynt_core_property( $o, 'observed_sessions' ); $outcome = alynt_core_property( $o, 'native_outcome' );
                alynt_core_assert( $reader instanceof mysqli && 'mysqli' === get_class( $reader ) && 'idle' === alynt_core_property( $o, 'phase' ) && true === alynt_core_property( $o, 'capture_receipt' ) && 20 === alynt_core_property( $o, 'protected_reads' ) && 0 === alynt_core_property( $o, 'writes' ) && 0 === $outcome['facts']['events']['begin_sent'] && 0 === $outcome['facts']['events']['dml_sent'], 'native_qualification_idle_writer' );
                if ( 9 === $n ) {
                    alynt_core_assert( array_keys( $sessions ) === array( 'capture' ) && false === alynt_core_property( $o, 'session_observation_failed' ), 'native_qualification_passive_capture' );
                    $roles['capture'] = $sessions['capture']; $roles['writer'] = $reader->thread_id; $proposal = alynt_core_property( $o, 'proposal' );
                    alynt_core_assert( 3 === count( array_unique( $roles ) ) && $tick - $start <= 4000000000, 'native_qualification_writer_roles_budget' );
                    $channel->writer( $roles, (string) ( 10000000000 - ( $tick - $start ) ) );
                    alynt_core_assert( hrtime( true ) - $tick <= 1000000000 && hrtime( true ) - $start <= 4000000000, 'native_qualification_writer_ack_budget' );
                }
                alynt_core_assert( $roles['writer'] === $reader->thread_id && $proposal === alynt_core_property( $o, 'proposal' ), 'native_qualification_writer_continuity' );
            }
            if ( $n >= 11 ) {
                foreach ( array( 'before', 'proposal', 'context', 'after', 'recorded_at', 'native_outcome', 'native_outcome_writer', 'admission_origin', 'admission_clock' ) as $key ) alynt_core_assert( null === alynt_core_property( $o, $key ), 'native_qualification_writer_erasure' );
                alynt_core_assert( true === alynt_core_property( $o, 'admission_receipt' ) && 1 === alynt_core_property( $o, 'writes' ), 'native_qualification_actual_writer_release' );
                if ( 11 === $n ) { alynt_core_assert( null === alynt_core_property( $o, 'reader' ), 'native_qualification_no_writer_at_read' ); $writer_erased = true; }
            }
            if ( 21 === $n || 22 === $n ) {
                $reader = alynt_core_property( $o, 'reader' ); $sessions = alynt_core_property( $o, 'observed_sessions' );
                alynt_core_assert( $reader instanceof mysqli && 'idle' === alynt_core_property( $o, 'phase' ) && 0 === alynt_core_property( $o, 'barrier_reads' ) && 1 === alynt_core_property( $o, 'barrier_metadata_checks' ) && array_keys( $sessions ) === array( 'capture', 'barrier' ) && $sessions['capture'] === $roles['capture'], 'native_qualification_idle_barrier' );
                if ( 21 === $n ) {
                    $roles['barrier'] = $sessions['barrier']; $read_start = alynt_core_property( $o, 'read_started' );
                    alynt_core_assert( 4 === count( array_unique( $roles ) ) && $roles['barrier'] === $reader->thread_id && is_int( $read_start ) && $tick >= $read_start && $tick - $read_start < 10000000000, 'native_qualification_barrier_roles_clock' );
                    $channel->barrier( $roles, (string) ( 20000000000 - ( $tick - $start ) ), (string) ( 10000000000 - ( $tick - $read_start ) ) );
                    alynt_core_assert( hrtime( true ) - $tick <= 1000000000, 'native_qualification_barrier_ack_budget' );
                }
                alynt_core_assert( $reader->thread_id === $roles['barrier'], 'native_qualification_same_barrier' );
            }
            if ( $n >= 23 ) {
                alynt_core_assert( null === alynt_core_property( $o, 'reader' ) && true === alynt_core_property( $o, 'barrier_receipt' ) && true === alynt_core_property( $o, 'finished' ) && 'barrier_released' === alynt_core_property( $o, 'phase' ), 'native_qualification_barrier_closed_before_finish' ); $barrier_closed = true;
            }
        } catch ( Throwable $error ) { $callback_error = $error; throw $error; }
        return $life; // Never supplies a guard result, handle, state, origin or native return.
    };
    $stage = 'native_root'; $channel->root(); $writes = $GLOBALS['alynt_core_proof']['fixture_writes']; $hooks = alynt_native_lifecycle_hooks(); $wp_roles_before = $GLOBALS['wp_roles'];
    add_filter( 'nonce_life', $probe, 10, 2 ); $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
    try { $actual = alynt_core_private( $o, 'lifecycle_root', array( 'POST', $form ) ); }
    finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = false; remove_filter( 'nonce_life', $probe, 10 ); }
    $stage = 'root_result'; if ( $callback_error ) throw $callback_error;
    alynt_core_assert( $hooks === alynt_native_lifecycle_hooks() && $wp_roles_before === $GLOBALS['wp_roles'] && $writes === $GLOBALS['alynt_core_proof']['fixture_writes'], 'native_qualification_hooks_roles_no_wp_writes' );
    alynt_native_lifecycle_erased( $o );
    alynt_core_assert( is_array( $actual ) && 'registered_closed' === $actual['classification'] && true === $actual['barrier_verified'] && false === $actual['candidate_only'], 'native_qualification_nonvacuous_original_result' );
    alynt_core_assert( 24 === count( $gates ) && 4 === count( array_unique( $gates ) ), 'native_qualification_guard_domains' );
    $m = $gates[0]; $mf = $gates[5]; $s = $gates[11]; $f = $gates[13];
    alynt_core_assert( array( $m,$m,$m,$m,$m,$mf,$m,$mf,$m,$mf,$m,$s,$s,$f,$s,$f,$s,$f,$s,$f,$s,$f,$s,$f ) === $gates, 'native_qualification_guard_order' );
    alynt_core_assert( $proposal === alynt_admission_receipt( $audit, $before ), 'native_qualification_exact_proposal_other_nine_rows' );
    alynt_core_assert( $auth_facts === $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row(), 'native_qualification_borrowed_auth_unchanged' );
    $counts = array_map( function ( $key ) use ( $o ) { return alynt_core_property( $o, $key ); }, array( 'protected_reads', 'barrier_reads', 'writes', 'barrier_metadata_checks', 'zero_dml_checks' ) );
    alynt_core_assert( array( 40,10,1,2,2 ) === $counts, 'native_qualification_counts_no_replay' );
    $taken = alynt_core_private( $o, 'take_uncertain_barrier_observation' );
    $order = array( 'M','M','M','M','M','MF','M','MF','M','MF','M','S','S','F','S','F','S','F','S','F','S','F','S','F' );
    $terminal = Alynt_Test_Uncertain_Barrier_Projection::terminal( $taken, $order );
    alynt_core_assert( is_array( $terminal ) && 'observed' === $terminal['terminal']['outcome'] && $actual === $terminal['terminal']['root_result'] && 'committed' === $terminal['qualification']['result']['historical_outcome'], 'uncertain_barrier_actual_terminal_transparency' );
    alynt_core_assert( false === alynt_core_private( $o, 'take_uncertain_barrier_observation' ) && false === alynt_core_private( $o, 'take_native_qualification_observation' ) && null === alynt_core_property( $o, 'terminal_event' ) && null === alynt_core_property( $o, 'qualification_observation' ) && array() === alynt_core_property( $o, 'observed_sessions' ), 'uncertain_barrier_once_only_erasure' );
    alynt_core_assert( false === alynt_core_private( $o, 'lifecycle_root', array( 'POST', $form ) ) && $counts === array_map( function ( $key ) use ( $o ) { return alynt_core_property( $o, $key ); }, array( 'protected_reads','barrier_reads','writes','barrier_metadata_checks','zero_dml_checks' ) ), 'uncertain_barrier_no_replay_io' );
    alynt_core_assert( 0 === $GLOBALS['alynt_core_proof']['protected_reads'] && 0 === $GLOBALS['alynt_core_proof']['denied_io'] && array() === headers_list(), 'native_qualification_no_protected_wp_api' );
    foreach ( get_included_files() as $file ) alynt_core_assert( 0 !== strpos( str_replace( '\\', '/', $file ), str_replace( '\\', '/', WP_CONTENT_DIR ) . '/' ), 'native_qualification_no_site_content' );
    alynt_core_assert( hrtime( true ) - $start < 20000000000 && hrtime( true ) - $read_start < 10000000000, 'native_qualification_final_original_budgets' );
    $audit->close(); $audit = null; $stage = 'final';
    $channel->finish( array_merge( $roles, array( 'native_result' => $actual, 'pre_guards' => 10, 'post_guards' => 14, 'guard_order' => array( 'M','M','M','M','M','MF','M','MF','M','MF','M','S','S','F','S','F','S','F','S','F','S','F','S','F' ), 'admission_reads' => $counts[0], 'barrier_reads' => $counts[1], 'writes' => $counts[2], 'metadata_checks' => $counts[3], 'zero_dml_checks' => $counts[4], 'original_slots_empty' => true, 'writer_erased_before_read' => $writer_erased, 'barrier_closed_before_finish' => $barrier_closed, 'observation' => $taken['qualification'], 'terminal' => $taken['terminal'], 'genuine_denials' => $denial_count, 'checks' => $GLOBALS['alynt_core_proof']['checks'] ) ) );
    $passed = true;
} catch ( Throwable $error ) {
    echo json_encode( array( 'passed' => false, 'failure' => 'native_qualification_worker', 'stage' => $stage, 'location' => array( basename( $error->getFile() ), $error->getLine() ), 'guards' => count( $gates ) ) ) . "\n";
} finally {
    if ( $probe && function_exists( 'remove_filter' ) ) remove_filter( 'nonce_life', $probe, 10 );
    if ( $audit instanceof mysqli ) $audit->close();
    if ( isset( $wpdb ) && $wpdb->dbh instanceof mysqli ) { try { if ( true !== $wpdb->close() ) $passed = false; } catch ( Throwable $ignored ) { $passed = false; } }
    $_COOKIE = array(); unset( $GLOBALS['current_user'] );
}
exit( $passed ? 0 : 1 );
