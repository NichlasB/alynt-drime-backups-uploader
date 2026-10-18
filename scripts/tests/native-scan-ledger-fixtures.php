<?php
/** Owned fixture instrumentation only; reflection is not a production port. */
if ( 'cli' !== PHP_SAPI || ! isset( $GLOBALS['alynt_core_proof'] ) ) exit( 1 );
require_once dirname( __DIR__, 2 ) . '/includes/class-scan-ledger-checkpoint.php';
function alynt_ledger_set( $o, $name, $value ) { $p = new ReflectionProperty( $o, $name ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); $p->setValue( $o, $value ); }
function alynt_ledger_frame( $db ) {
    global $wpdb; $rows = array();
    foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $role => $def ) {
        $stmt = $db->prepare( "SELECT CAST(option_id AS CHAR) AS option_id,BINARY option_name AS option_name,BINARY autoload AS autoload,CAST(OCTET_LENGTH(option_value) AS CHAR) AS octets,BINARY option_value AS value FROM {$wpdb->options} WHERE option_name=?" );
        $stmt->bind_param( 's', $def[0] ); $stmt->execute(); $r = $stmt->get_result();
        alynt_core_assert( 1 === $r->num_rows, 'Owned complete fixture frame missing a role.' ); $rows[$role] = $r->fetch_assoc(); $r->free(); $stmt->close();
    }
    return array( 'blog_id' => get_current_blog_id(), 'prefix' => $wpdb->prefix, 'database' => DB_NAME, 'rows' => $rows );
}
function alynt_ledger_form( $db, $offset = 0 ) {
    $frame = alynt_ledger_frame( $db ); $now = time() + $offset;
    $form = array( 'contract' => 'scan_ledger_checkpoint_v1', 'purpose' => 'checkpoint_closed_ledger', 'command_id' => '88888888-8888-4888-8888-000000000066', 'site_uuid' => '11111111-1111-4111-8111-111111111111', 'issued_at' => (string) $now, 'expires_at' => (string) ( $now + 300 ), 'confirm' => 'checkpoint', 'nonce' => 'pending', 'context_digest' => Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint_Plan::context( $frame ) );
    $p = Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint_Request::parse( $form ); alynt_core_assert( is_array( $p ), 'Owned ledger intent must parse.' );
    $form['nonce'] = wp_create_nonce( $p['action'] ); return $form;
}
function alynt_ledger_run( $form, $positive, $reads ) {
    $o = Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint::create(); $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
    try { $actual = alynt_core_private( $o, 'checkpoint', array( 'POST', $form ) ); }
    finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = false; }
    alynt_core_assert( $actual === $positive, 'Actual checkpoint decision differed: expected=' . (int) $positive . ', actual=' . (int) $actual . ', reads=' . alynt_core_property( $o, 'protected_reads' ) . ', groups=' . count( $GLOBALS['alynt_core_proof']['cases'] ) );
    alynt_core_assert( $reads === alynt_core_property( $o, 'protected_reads' ), 'Checkpoint protected-read ordering differed.' );
    alynt_core_assert( null === alynt_core_property( $o, 'reader' ) && null === alynt_core_property( $o, 'physical' ), 'Owned checkpoint handle escaped.' );
    $writes = alynt_core_property( $o, 'writes' );
    alynt_core_assert( false === alynt_core_private( $o, 'checkpoint', array( 'POST', $form ) ) && $writes === alynt_core_property( $o, 'writes' ), 'One-shot checkpoint replayed.' );
    alynt_core_assert( array( 'candidate_only' => true, 'storage_supported' => false, 'dispatch_supported' => false, 'execution_supported' => false ) === $o->disposition(), 'Checkpoint advertised authority.' );
    return $o;
}
function alynt_ledger_primitive() {
    global $wpdb; $o = Alynt_Drime_Backups_Uploader_Scan_Ledger_Checkpoint::create();
    alynt_ledger_set( $o, 'used', true ); alynt_ledger_set( $o, 'started', hrtime( true ) );
    $facts = alynt_core_private( $o, 'native_idle', array( $wpdb->dbh ) );
    alynt_core_assert( is_array( $facts ) && alynt_core_private( $o, 'open_writer', array( $wpdb->dbh, $facts ) ), 'Owned checkpoint primitive setup failed.' );
    return $o;
}
function alynt_ledger_locked( $o ) {
    $writer = alynt_core_property( $o, 'reader' );
    alynt_core_assert( true === $writer->query( 'START TRANSACTION READ WRITE' ), 'Owned primitive BEGIN failed.' );
    alynt_ledger_set( $o, 'phase', 'transaction_owned' ); $rows = array();
    foreach ( array_keys( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() ) as $role ) {
        $rows[$role] = alynt_core_private( $o, 'checkpoint_row', array( $GLOBALS['wpdb']->options, $role, true ) );
        alynt_core_assert( is_array( $rows[$role] ), 'Owned primitive row lock failed.' );
    }
    return $rows;
}
function alynt_ledger_nowait( $db, $name, $blocked ) {
    $table = $GLOBALS['wpdb']->options; $db->query( 'START TRANSACTION' ); $code = 0; $ok = false;
    try {
        $stmt = $db->prepare( "SELECT option_id FROM {$table} FORCE INDEX (option_name) WHERE option_name=? FOR UPDATE NOWAIT" ); $stmt->bind_param( 's', $name );
        if ( false === $stmt->execute() ) $code = $stmt->errno;
        else { $r = $stmt->get_result(); $ok = $r instanceof mysqli_result && 1 === $r->num_rows; if ( $r instanceof mysqli_result ) $r->free(); }
        $stmt->close();
    } catch ( mysqli_sql_exception $e ) { $code = $e->getCode(); }
    finally { $db->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ); }
    alynt_core_assert( $blocked ? 1205 === $code : $ok && 0 === $code, 'Independent owned NOWAIT outcome differed; code=' . $code );
}
