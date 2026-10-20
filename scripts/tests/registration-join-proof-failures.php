<?php
/** Genuine joined failures; report mode and owned-client close are test-only fault controls. */
if ( 'cli' !== PHP_SAPI || ! isset( $GLOBALS['alynt_core_proof'] ) ) exit( 1 );

/** Independent owned NOWAIT witness: prove both actual native API error policies, not a synthetic exception. */
function alynt_join_failure_lock_witness( $db, $name, $strict ) {
    $thrown = false; $returned_false = false; $code = 0; $stmt = null;
    alynt_core_assert( true === $db->query( 'START TRANSACTION' ), 'Owned error-policy witness did not start.' );
    try {
        $stmt = $db->prepare( "SELECT option_id FROM {$GLOBALS['wpdb']->options} FORCE INDEX (option_name) WHERE option_name=? FOR UPDATE NOWAIT" ); $stmt->bind_param( 's', $name );
        try { $returned_false = false === $stmt->execute(); $code = $stmt->errno; }
        catch ( mysqli_sql_exception $error ) { $thrown = true; $code = $error->getCode(); }
    } finally { if ( $stmt instanceof mysqli_stmt ) $stmt->close(); $db->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ); }
    alynt_core_assert( 1205 === $code && $strict === $thrown && ( ! $strict ) === $returned_false, 'Native lock witness did not distinguish actual strict exception from raw false.' );
}

function alynt_join_failure_cases() {
    global $wpdb;
    $values = alynt_registration_values(); $roles = array_keys( $values ); $db = alynt_scan_db(); $inspector = alynt_scan_db(); $auth = $wpdb->dbh;
    $driver = new mysqli_driver(); $report_mode = $driver->report_mode;
    try {
        alynt_core_assert( $roles === array_keys( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() ) && 10 === count( $roles ), 'Failure lock positions no longer match all ten compiled dependencies.' );
        foreach ( array( 'strict_first', 'strict_middle', 'strict_last', 'false_first', 'false_middle', 'false_last', 'closed_writer' ) as $case ) {
            mysqli_report( 0 === strpos( $case, 'false_' ) ? MYSQLI_REPORT_OFF : MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
            alynt_scan_seed( $values ); alynt_core_login(); $form = alynt_registration_form(); $before = alynt_registration_frame( $db );
            $index = false !== strpos( $case, 'first' ) ? 0 : ( false !== strpos( $case, 'middle' ) ? 5 : 9 );
            $closed_fault = 'closed_writer' === $case; $pin = null; $gates = array(); $creates = 0; $fired = false; $capture = null; $writer = null; $locked = false;
            $facts = $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row();
            $probe = function ( $life, $action, $verify, $o ) use ( &$pin, &$gates, &$creates, &$fired, &$capture, &$writer, &$locked, $db, $inspector, $auth, $before, $roles, $index, $closed_fault, $driver, $case ) {
                if ( ! $verify ) { ++$creates; return $life; }
                $gates[] = $action; $n = count( $gates );
                alynt_core_assert( $n <= 8 && false === alynt_core_property( $o, 'admission_qualification_spent' ), 'Native failure ran a fresh guard or consumed qualification before native entry.' );
                if ( 1 === $n ) alynt_core_assert( 0 === alynt_core_property( $o, 'protected_reads' ) && 0 === alynt_core_property( $o, 'writes' ), 'Failure entry accessed protected storage before authorization.' );
                if ( 2 === $n ) $capture = alynt_core_property( $o, 'reader' );
                if ( $n < 7 ) return $life;
                $p = alynt_join_pin( $o ); if ( null === $pin ) $pin = $p;
                alynt_core_assert( is_array( $pin[0] ) && $pin === $p && $before === $p[3] && $p[2]['started_ns'] === alynt_core_property( $o, 'started' ), 'Failure entry replaced original origin/clock/writer pin.' );
                $writer = alynt_core_property( $o, 'reader' );
                alynt_core_assert( $writer instanceof mysqli && $writer !== $capture && $writer !== $auth && 'idle' === alynt_core_property( $o, 'phase' ) && 20 === alynt_core_property( $o, 'protected_reads' ) && 0 === alynt_core_property( $o, 'writes' ), 'Native failure was injected outside the fixed pre-BEGIN boundary.' );
                if ( 8 !== $n ) return $life;
                $fired = true;
                if ( $closed_fault ) alynt_core_assert( true === $writer->close(), 'Owned-client close fault was not reached.' );
                else {
                    alynt_core_assert( true === $db->query( 'START TRANSACTION' ), 'Owned lock holder did not start.' ); $locked = true;
                    $name = Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows()[$roles[$index]][0];
                    $stmt = $db->prepare( "SELECT option_id FROM {$GLOBALS['wpdb']->options} WHERE option_name=? FOR UPDATE" );
                    try { $stmt->bind_param( 's', $name ); alynt_core_assert( true === $stmt->execute(), 'Owned target lock failed.' ); $r = $stmt->get_result(); alynt_core_assert( 1 === $r->num_rows, 'Owned target lock matched no row.' ); $r->free(); }
                    finally { $stmt->close(); }
                    $strict = 0 === strpos( $case, 'strict_' );
                    alynt_core_assert( ( $strict ? MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT : MYSQLI_REPORT_OFF ) === $driver->report_mode, 'Native failure API policy was silently replaced.' );
                    alynt_join_failure_lock_witness( $inspector, $name, $strict );
                }
                return $life;
            };
            try {
                $o = alynt_join_run( $form, false, $closed_fault ? 20 : 21 + $index, 0, $probe );
                alynt_core_assert( $fired && is_array( $pin ) && 8 === count( $gates ) && 1 === $creates, 'Intended genuine failure path or exact guard count was not reached.' );
                alynt_core_assert( ( $closed_fault ? 'poisoned' : 'rolled_back' ) === alynt_core_property( $o, 'phase' ), 'Native failure lost its closed cleanup phase.' );
                // Holder stays locked: previously acquired writer locks must already be gone.
                foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $role => $def ) if ( $closed_fault || $role !== $roles[$index] ) alynt_ledger_nowait( $inspector, $def[0], false );
                if ( ! $closed_fault ) alynt_ledger_nowait( $inspector, Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows()[$roles[$index]][0], true );
            } finally { if ( $locked ) alynt_core_assert( true === $db->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ), 'Owned lock-holder cleanup failed.' ); }
            $small = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( get_current_blog_id(), 'register_closed' );
            $command = array_map( 'strval', $pin[4]['command'] ); $command['nonce'] = 'pending'; $parsed = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::parse( $command );
            alynt_core_assert( is_array( $parsed ) && $small !== $parsed['action'] && array( $small, $small, $small, $small, $small, $parsed['action'], $small, $parsed['action'] ) === $gates, 'Failure path changed independent nonce domains/order or added postfailure authentication.' );
            foreach ( array( $capture, $writer ) as $client ) { $closed = false; try { $client->query( 'SELECT 1' ); } catch ( Error $error ) { $closed = true; } alynt_core_assert( $closed, 'Native failure retained an owned usable client.' ); }
            alynt_core_assert( $facts === $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row(), 'Native failure changed or closed borrowed authentication client.' );
            alynt_core_assert( $before === alynt_registration_frame( $db ), 'Native failure retained a ledger mutation or changed another owned row.' );
            foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $def ) alynt_ledger_nowait( $inspector, $def[0], false );
            foreach ( array( 'reader', 'native_outcome_writer', 'physical', 'read_physical' ) as $key ) alynt_core_assert( null === alynt_core_property( $o, $key ), 'Native failure retained an owned slot.' );
            // The root erases private qualification DATA: this is replay/IO proof, NOT a native result certificate.
            $counts = array( alynt_core_property( $o, 'protected_reads' ), alynt_core_property( $o, 'writes' ) );
            alynt_core_assert( false === alynt_core_private( $o, 'qualify_admission_outcome', array( $pin, array_fill_keys( array( 'auth', 'intent', 'physical', 'unreentered' ), true ) ) ) && $counts === array( alynt_core_property( $o, 'protected_reads' ), alynt_core_property( $o, 'writes' ) ), 'Erased failure qualification replayed or accessed storage.' );
            alynt_core_done( ( $closed_fault ? 'instrumented_owned_' : 'genuine_timeout_' ) . $case . '_eight_guards_closed_clients_no_dml_erased_no_replay' );
        }
    } finally { mysqli_report( $report_mode ); $inspector->close(); $db->close(); }
    alynt_core_assert( $report_mode === $driver->report_mode, 'Owned failure proof leaked its native API error policy.' );
}
