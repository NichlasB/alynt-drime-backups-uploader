<?php
/** Proof-only fences/audits. Not loaded by production or a web handler. */
function alynt_core_check( $ok, $label ) { if ( ! $ok ) throw new RuntimeException( $label ); }
function alynt_core_actual_digest( mysqli $db ) {
    $out = array();
    foreach ( array( 'options' => 'option_id,option_name,option_value,autoload', 'users' => 'ID,user_login,user_pass,user_nicename,user_email,user_url,user_registered,user_activation_key,user_status,display_name', 'usermeta' => 'umeta_id,user_id,meta_key,meta_value' ) as $suffix => $columns ) {
        $order = explode( ',', $columns )[0]; $rows = $db->query( "SELECT SHA2(JSON_ARRAY({$columns}),256) FROM wp_{$suffix} ORDER BY {$order}" ); $hash = hash_init( 'sha256' );
        while ( $row = $rows->fetch_row() ) hash_update( $hash, $row[0] . "\n" );
        $out[] = hash_final( $hash ); // In memory only. Never print user data, row hashes or option contents.
    }
    return $out;
}
function alynt_core_query_fence( $sql ) {
    $s =& $GLOBALS['alynt_core_proof']; $s['queries']++;
    foreach ( array( 'alynt_drime_backups_settings', 'alynt_drime_backups_schedule_safety_state' ) as $protected ) {
        if ( false !== strpos( $sql, $protected ) ) { $s['denied_io']++; throw new RuntimeException( 'Protected application option query attempted.' ); }
    }
    // Match table positions, not harmless option names such as wp_user_roles.
    preg_match_all( '/\b(?:FROM|JOIN)\s+`?([A-Za-z0-9_]+)`?/i', $sql, $tables );
    foreach ( $tables[1] as $table ) {
        if ( ! in_array( $table, array( $s['prefix'] . 'options', $s['prefix'] . 'users', $s['prefix'] . 'usermeta' ), true ) ) {
            $s['denied_io']++;
            $s['refused_callers'] = array_map( function ( $f ) { return ( isset( $f['file'] ) ? basename( $f['file'] ) . ':' . $f['line'] : '' ) . ':' . $f['function']; }, debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 8 ) );
            throw new RuntimeException( 'Non-owned table access attempted.' );
        }
    }
    if ( 1 === preg_match( '/^\s*SELECT\b/i', $sql ) ) return $sql;
    $names = preg_quote( $s['prefix'], '/' ) . '(?:options|users|usermeta)';
    if ( 1 === preg_match( '/^\s*SHOW\s+(?:FULL\s+)?COLUMNS\s+FROM\s+`?' . $names . '`?(?=\s|$)/i', $sql ) ) return $sql;
    if ( $s['guard_readonly'] || 1 !== preg_match( '/^\s*(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+`?' . $names . '`?(?=\s|\(|$)/i', $sql ) ) { $s['denied_io']++; throw new RuntimeException( 'Non-fixture or in-guard SQL write denied.' ); }
    $s['fixture_writes']++;
    return $sql;
}
function alynt_core_mutate( callable $callback ) {
    $prior = $GLOBALS['alynt_core_proof']['guard_readonly']; $GLOBALS['alynt_core_proof']['guard_readonly'] = false;
    try { return $callback(); } finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = $prior; }
}
