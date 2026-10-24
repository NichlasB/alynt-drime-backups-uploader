<?php
/** Explicit owned CLI proof only; limited credential through bounded private stdin. */
if ( 'cli' !== PHP_SAPI || 2 !== count( $argv ) || '--owned-projection-worker' !== $argv[1] ) exit( 1 );
require __DIR__ . '/projection-proof-contract.php';
$receipt = array( 'passed' => false, 'checks' => 0, 'admission' => false, 'wiring' => false ); $GLOBALS['alynt_projection_receipt'] =& $receipt;
try {
    alynt_projection_check( false === getenv( 'MYSQL_PWD' ), 'supervisor_secret_environment' );
    $bytes = stream_get_contents( STDIN, 4097 );
    alynt_projection_check( is_string( $bytes ) && strlen( $bytes ) <= 4096 && feof( STDIN ), 'private_input_bound' );
    $c = alynt_projection_context( json_decode( $bytes, true, 8 ) ); unset( $bytes );
    alynt_projection_check( false !== $c, 'private_context' ); $receipt['mode'] = $c['mode'];
    mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
    $db = mysqli_init(); $db->options( MYSQLI_OPT_CONNECT_TIMEOUT, 3 ); $db->options( MYSQLI_OPT_READ_TIMEOUT, 3 );
    $db->real_connect( '127.0.0.1', $c['user'], $c['password'], 'local', 10004 );
    $db->query( 'SET SESSION max_statement_time=2' );
    alynt_projection_check( $c['user'] . '@127.0.0.1' === $db->query( 'SELECT CURRENT_USER()' )->fetch_row()[0], 'limited_identity' );
    $grants = $db->query( 'SHOW GRANTS' ); $selects = 0; $usage = 0;
    while ( $row = $grants->fetch_row() ) {
        if ( 0 === strpos( $row[0], 'GRANT USAGE ON *.* TO ' ) && false === strpos( $row[0], 'WITH GRANT OPTION' ) ) { $usage++; continue; }
        $matched = false;
        foreach ( array_merge( array( 'options', 'users', 'usermeta' ), 'unsafe' === $c['mode'] ? array( 'unsafe' ) : array() ) as $suffix ) {
            if ( 0 === strpos( $row[0], 'GRANT SELECT ON `local`.`' . $c['prefix'] . $suffix . '` TO ' ) && false === strpos( $row[0], 'WITH GRANT OPTION' ) ) $matched = true;
        }
        alynt_projection_check( $matched, 'unexpected_effective_grant' ); $selects++;
    }
    alynt_projection_check( 1 === $usage && ( 'unsafe' === $c['mode'] ? 4 : 3 ) === $selects, 'effective_privileges' );
    $receipt['limited_identity_and_grants'] = true; unset( $row, $grants );
    if ( 'capability' === $c['mode'] || 'unsafe' === $c['mode'] ) {
        $rows = $db->query( 'SELECT option_name,option_value FROM ' . $c['prefix'] . ( 'unsafe' === $c['mode'] ? 'unsafe' : 'options' ) )->fetch_all( MYSQLI_ASSOC );
        $options = array_column( $rows, 'option_value', 'option_name' );
        alynt_projection_check( 'Owned projection ' . $c['marker'] === $options['blogname'], 'nonempty_safe_read' );
        if ( 'unsafe' === $c['mode'] ) {
            $killed = false;
            try { alynt_projection_check( 0 === alynt_projection_canaries( $options ), 'zero_protected_values' ); } catch ( RuntimeException $error ) { $killed = 'zero_protected_values' === $error->getMessage(); }
            alynt_projection_check( $killed && 2 === alynt_projection_canaries( $options ), 'unsafe_view_must_kill_assertion' );
            $receipt['control_only_canary_values'] = 2; $receipt['semantic_control_killed'] = true;
        } else {
            alynt_projection_check( 8 === count( $options ) && 0 === alynt_projection_canaries( $options ), 'projection_value_boundary' );
            $receipt['safe_options'] = count( $options ); $receipt['php_observer_present'] = false;
            alynt_projection_check( 2 === (int) $db->query( 'SELECT COUNT(*) FROM ' . $c['prefix'] . 'users' )->fetch_row()[0], 'populated_users' );
            alynt_projection_check( 4 === (int) $db->query( 'SELECT COUNT(*) FROM ' . $c['prefix'] . 'usermeta' )->fetch_row()[0], 'populated_usermeta' );
            $p = $c['prefix']; $base = $c['base'];
            $denials = array(
                'owned_base' => array( "SELECT option_value FROM {$base}options", array( 1142, 1143 ) ),
                'qualified_base' => array( "SELECT option_value FROM local.{$base}options", array( 1142, 1143 ) ),
                'union_base' => array( "SELECT option_value FROM {$p}options UNION SELECT option_value FROM {$base}options", array( 1142, 1143 ) ),
                'application_empty_projection' => array( 'SELECT option_value FROM local.wp_options WHERE 1=0', array( 1142, 1143 ) ),
                'unsafe_ungranted_view' => array( "SELECT option_value FROM {$p}unsafe", array( 1142, 1143 ) ),
                'insert_view' => array( "INSERT INTO {$p}options(option_name,option_value,autoload) VALUES('owned_denied','owned','no')", array( 1142, 1143 ) ),
                'update_view' => array( "UPDATE {$p}options SET option_value='owned_denied' WHERE option_name='blogname'", array( 1142, 1143 ) ),
                'delete_view' => array( "DELETE FROM {$p}options WHERE option_name='blogname'", array( 1142, 1143 ) ),
                'alter_owned' => array( "ALTER TABLE {$base}options ADD COLUMN owned_denied INT", array( 1142, 1143 ) ),
                'grant_owned' => array( "GRANT SELECT ON local.{$base}options TO '{$c['user']}'@'127.0.0.1'", array( 1044, 1142, 1227 ) ),
                'create_existing_owned_account' => array( "CREATE USER 'alynt_ro_{$c['seed']}_d'@'127.0.0.1'", array( 1227 ) ),
                'execute_absent_owned_routine' => array( "CALL local.{$p}absent_procedure()", array( 1370 ) ),
                'file_privilege' => array( "SELECT 'owned' INTO OUTFILE 'C:/Users/Captain/Documents/AI Workflows/owned-projection-{$c['seed']}-absent/denied.txt'", array( 1045, 1227 ) ),
            );
            foreach ( $denials as $name => $test ) {
                $code = null; try { $db->query( $test[0] ); } catch ( mysqli_sql_exception $error ) { $code = $error->getCode(); }
                alynt_projection_check( in_array( $code, $test[1], true ), 'denial_' . $name ); $receipt['denials'][$name] = $code;
            }
        }
        $db->close();
    } else {
        $db->close(); require __DIR__ . '/projection-core-bootstrap.php';
    }
    $receipt['passed'] = true;
} catch ( Throwable $error ) {
    $receipt['failure'] = 1 === preg_match( '/\A[a-z_]+\z/D', $error->getMessage() ) ? $error->getMessage() : 'private_worker_failure';
    if ( $error instanceof mysqli_sql_exception ) $receipt['database_error_code'] = $error->getCode();
}
unset( $c );
echo json_encode( $receipt, JSON_PRETTY_PRINT ) . "\n";
exit( $receipt['passed'] ? 0 : 1 );
