<?php
/** Genuine core against owned tables; captured history remains synthetic. */
if ( 'cli' !== PHP_SAPI || ! isset( $GLOBALS['alynt_core_proof'] ) ) exit( 1 );
require_once __DIR__ . '/registration-proof-fixtures.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-registration-lifecycle-request-owner.php';

function alynt_lifecycle_fixture() {
    alynt_scan_seed( alynt_registration_values() ); alynt_core_login();
    $db = alynt_scan_db(); try { $frame = alynt_registration_frame( $db ); } finally { $db->close(); }
    return array( Alynt_Drime_Backups_Uploader_Registration_Lifecycle_Request_Owner::create(), $frame, alynt_registration_form() );
}
function alynt_lifecycle_set( $o, $key, $value ) {
    $p = new ReflectionProperty( $o, $key ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); $p->setValue( $o, $value );
}
function alynt_lifecycle_hooks() {
    $out = array(); foreach ( array( 'query', 'pre_wp_load_alloptions' ) as $hook ) $out[$hook] = $GLOBALS['wp_filter'][$hook]->callbacks ?? array(); return $out;
}
function alynt_lifecycle_handler() {
    $probe = static function () { return false; }; $prior = set_error_handler( $probe ); restore_error_handler(); return $prior;
}
function alynt_lifecycle_cleared( $o ) {
    foreach ( array( 'origin', 'origin_data', 'scope', 'started', 'root_clock' ) as $key ) alynt_core_assert( null === alynt_core_property( $o, $key ), 'Private lifecycle slot survived: ' . $key );
    foreach ( array( 'used', 'fences' ) as $key ) alynt_core_assert( array() === alynt_core_property( $o, $key ), 'Private lifecycle collection survived: ' . $key );
    foreach ( array( 'attempted', 'consumed' ) as $key ) alynt_core_assert( true === alynt_core_property( $o, $key ), 'Root was not consumed: ' . $key );
    foreach ( array( 'active', 'busy' ) as $key ) alynt_core_assert( false === alynt_core_property( $o, $key ), 'Root remains active: ' . $key );
    alynt_core_assert( 'closed' === alynt_core_property( $o, 'phase' ), 'Preparation phase not closed.' );
}
function alynt_lifecycle_run( $o, $frame, $intent, $positive = false ) {
    $hooks = alynt_lifecycle_hooks(); $roles = $GLOBALS['wp_roles']; $handler = alynt_lifecycle_handler();
    $writes = $GLOBALS['alynt_core_proof']['fixture_writes']; $controls = $GLOBALS['alynt_lifecycle_control_writes'] ?? 0;
    $db = alynt_scan_db(); try { $before = alynt_registration_frame( $db ); } finally { $db->close(); }
    $GLOBALS['alynt_lifecycle_owner'] = $o; $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
    try { $r = alynt_core_private( $o, 'prepare_admission', array( $frame, $intent ) ); }
    finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = false; unset( $GLOBALS['alynt_lifecycle_owner'] ); }
    alynt_core_assert( $positive === $r, 'Genuine lifecycle preparation decision differed; groups=' . count( $GLOBALS['alynt_core_proof']['cases'] ) );
    alynt_lifecycle_cleared( $o );
    alynt_core_assert( $hooks === alynt_lifecycle_hooks() && $roles === $GLOBALS['wp_roles'] && $handler === alynt_lifecycle_handler(), 'Owned hooks/roles/error handler leaked.' );
    alynt_core_assert( $GLOBALS['alynt_core_proof']['fixture_writes'] - $writes === ( $GLOBALS['alynt_lifecycle_control_writes'] ?? 0 ) - $controls, 'Owner wrote outside deliberate owned revocation control.' );
    // Identity fault controls are restored by the caller after this returns.
    $db = new mysqli( '127.0.0.1', 'root', getenv( 'MYSQL_PWD' ), 'local', (int) $GLOBALS['argv'][2] );
    try { $prefix = $GLOBALS['alynt_core_proof']['prefix']; foreach ( $before['rows'] as $row ) {
        $stmt = $db->prepare( "SELECT BINARY option_value,BINARY autoload FROM {$prefix}options WHERE option_id=? AND BINARY option_name=?" );
        $stmt->bind_param( 'ss', $row['option_id'], $row['option_name'] ); $stmt->execute(); $now = $stmt->get_result()->fetch_row(); $stmt->close();
        alynt_core_assert( array( $row['value'], $row['autoload'] ) === $now, 'Protected owned fixture row changed.' );
    } } finally { $db->close(); }
    alynt_core_assert( false === alynt_core_private( $o, 'prepare_admission', array( $frame, $intent ) ), 'Consumed lifecycle root retried.' );
    alynt_lifecycle_cleared( $o ); return $r;
}
/** Observe real nonce creation/verification; never supply a fake verifier result. */
function alynt_lifecycle_probe( &$facts, &$created, $fault = null ) {
    return function ( $life, $action ) use ( &$facts, &$created, $fault ) {
        $stack = array_column( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 8 ), 'function' );
        if ( in_array( 'wp_create_nonce', $stack, true ) ) { $created[] = $action; return $life; }
        if ( ! in_array( 'wp_verify_nonce', $stack, true ) ) return $life;
        $o = $GLOBALS['alynt_lifecycle_owner']; $f = alynt_core_property( $o, 'fences' ); $blocked = false;
        try { $f['query']( 'SELECT option_value FROM ' . $GLOBALS['wpdb']->options . ' LIMIT 1' ); } catch ( RuntimeException $error ) { $blocked = true; }
        alynt_core_assert( $blocked && PHP_INT_MAX === has_filter( 'query', $f['query'] ) && PHP_INT_MAX === has_filter( 'pre_wp_load_alloptions', $f['pre_wp_load_alloptions'] ) && array() === apply_filters( 'pre_wp_load_alloptions', null, false ), 'Role-only query/autoload fences missing during genuine auth.' );
        $facts[] = array( 'action' => $action, 'origin' => alynt_core_property( $o, 'origin_data' ), 'used' => alynt_core_property( $o, 'used' ), 'clock' => alynt_core_property( $o, 'root_clock' ), 'started' => alynt_core_property( $o, 'started' ) );
        return null === $fault ? $life : $fault( count( $facts ), $life, $o );
    };
}
function alynt_lifecycle_change( $change ) {
    if ( 'role' === $change ) {
        // The owner's option fence must stay installed. Revoke only the owned
        // fixture through its independently marker-checked connection, not by
        // weakening that fence or mistaking its exception for actual revocation.
        $db = alynt_scan_db(); $table = $GLOBALS['alynt_core_proof']['prefix'] . 'options'; $name = $GLOBALS['wpdb']->prefix . 'user_roles';
        try {
            $stmt = $db->prepare( "SELECT option_value FROM {$table} WHERE option_name=?" ); $stmt->bind_param( 's', $name ); $stmt->execute(); $raw = $stmt->get_result()->fetch_row()[0]; $stmt->close();
            $roles = unserialize( $raw, array( 'allowed_classes' => false ) ); unset( $roles['administrator']['capabilities']['manage_options'] ); $changed = serialize( $roles );
            $stmt = $db->prepare( "UPDATE {$table} SET option_value=? WHERE option_name=?" ); $stmt->bind_param( 'ss', $changed, $name ); $stmt->execute(); alynt_core_assert( 1 === $stmt->affected_rows, 'Owned late role revocation did not change exactly one row.' ); $stmt->close();
        } finally { $db->close(); }
        return function () use ( $table, $name, $raw ) {
            $db = alynt_scan_db(); try { $stmt = $db->prepare( "UPDATE {$table} SET option_value=? WHERE option_name=?" ); $stmt->bind_param( 'ss', $raw, $name ); $stmt->execute(); alynt_core_assert( 1 === $stmt->affected_rows, 'Owned late role restoration did not change exactly one row.' ); $stmt->close(); } finally { $db->close(); }
        };
    }
    $before = $GLOBALS['alynt_core_proof']['fixture_writes']; $restore = alynt_core_change( $change );
    $GLOBALS['alynt_lifecycle_control_writes'] = ( $GLOBALS['alynt_lifecycle_control_writes'] ?? 0 ) + $GLOBALS['alynt_core_proof']['fixture_writes'] - $before; return $restore;
}
