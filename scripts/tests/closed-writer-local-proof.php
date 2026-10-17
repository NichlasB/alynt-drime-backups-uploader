<?php
/** Explicit Plugin Tester owned SQL/process proof; never loaded by the plugin or CI runtime. */
$approved = realpath( 'C:/Users/Captain/Local Sites/plugin-tester/app/public' ); $root = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( 'cli' !== PHP_SAPI || false === $root || false === $approved || $root !== $approved || ! ( count( $argv ) === 2 || ( count( $argv ) === 6 && '--child' === $argv[2] ) ) ) { fwrite( STDERR, "Use only the confirmed Plugin Tester root and explicit proof arguments.\n" ); exit( 1 ); }
foreach ( array( 'db.php', 'object-cache.php', 'advanced-cache.php' ) as $name ) if ( file_exists( $root . '/wp-content/' . $name ) ) { fwrite( STDERR, "Unsupported LocalWP drop-in; no fixture writes.\n" ); exit( 1 ); }
$sites = json_decode( file_get_contents( 'C:/Users/Captain/AppData/Roaming/Local/sites.json' ), true ); $metadata = null;
foreach ( is_array( $sites ) ? $sites : array() as $site ) if ( isset( $site['domain'], $site['path'] ) && 'plugin-tester.local' === $site['domain'] && realpath( $site['path'] . '/app/public' ) === $approved ) $metadata = $site;
$port = $metadata['services']['mariadb']['ports']['MYSQL'][0] ?? null;
if ( ! is_int( $port ) || $port < 1 || $port > 65535 ) { fwrite( STDERR, "LocalWP metadata mismatch.\n" ); exit( 1 ); }
define( 'SHORTINIT', true ); define( 'DISABLE_WP_CRON', true ); require $root . '/wp-load.php';
$wpdb->suppress_errors( true ); mysqli_report( MYSQLI_REPORT_OFF );
require_once __DIR__ . '/lifecycle-proof-safety.php';
alynt_lifecycle_check( '127.0.0.1:' . $port === DB_HOST && 'local' === DB_NAME && ! is_multisite() && ! wp_using_ext_object_cache(), 'Local target/config/cache mismatch.' );
$actual = $wpdb->options;
alynt_lifecycle_check( 1 === preg_match( '/^[A-Za-z0-9_]+$/D', $actual ) && 'http://plugin-tester.local' === $wpdb->get_var( "SELECT option_value FROM {$actual} WHERE option_name='siteurl'" ), 'Wrong WordPress target.' );
require_once __DIR__ . '/closed-writer-proof-fixtures.php'; require_once __DIR__ . '/closed-writer-proof-storage.php'; require_once __DIR__ . '/closed-writer-proof-transaction.php'; require_once __DIR__ . '/closed-writer-proof-processes.php';
$child = count( $argv ) === 6; $table = $child ? $argv[3] : 'wp_alynt_writer_probe_' . bin2hex( random_bytes( 6 ) ); $token = $child ? $argv[4] : bin2hex( random_bytes( 12 ) );
$db = new Alynt_Closed_Writer_Proof_DB( $table );
if ( $child ) {
	try { $db->owned( $token ); alynt_writer_child( $db, $token, $argv[5] ); }
	catch ( Throwable $error ) { echo json_encode( array( 'ok' => false, 'result' => 'child_failure', 'execution_supported' => false ) ) . "\n"; exit( 1 ); }
	finally { $db->close(); } exit;
}
$before = array( alynt_lifecycle_actual_digest( $actual ), alynt_provenance_users_digest() ); $created = array(); $children = array();
$receipt = array( 'proof_kind' => 'closed_writer_serialized_sql', 'fixture_scope' => $table, 'owned_tables' => array() );
try {
	$engine = $db->one( 'SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?', array( $actual ) );
	alynt_lifecycle_check( is_array( $engine ) && 'InnoDB' === $engine['ENGINE'], 'Actual options structure must use InnoDB.' );
	foreach ( array( $table, $db->control ) as $owned ) {
		$r = $db->one( 'SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?', array( $owned ) );
		alynt_lifecycle_check( is_array( $r ) && '0' === (string) $r['n'], 'Writer fixture table already exists.' );
		$db->exec( 'CREATE TABLE ' . $owned . ' LIKE ' . $actual ); $created[] = $owned;
	}
	$db->exec( 'INSERT INTO ' . $table . ' (option_name,option_value,autoload) VALUES (?,?,?)', array( 'probe_owner', $token, 'no' ) ); $db->owned( $token );
	require_once __DIR__ . '/closed-writer-proof-cases.php'; require_once __DIR__ . '/closed-writer-proof-races.php';
	alynt_writer_cases( $root, $db, $token, $children, $receipt );
} catch ( Throwable $error ) {
	$receipt['failed'] = true; $receipt['reason'] = $error instanceof RuntimeException ? $error->getMessage() : 'Unexpected internal writer proof failure.';
	$receipt['location'] = array( 'file' => basename( $error->getFile() ), 'line' => $error->getLine() );
} finally {
	foreach ( $children as &$p ) if ( is_resource( $p['process'] ) ) {
		try { alynt_writer_join( $p, true ); } catch ( Throwable $error ) { $receipt['failed'] = true; $receipt['child_cleanup_unresolved'] = true; }
	} unset( $p );
	try {
		$db->exec( 'ROLLBACK' );
		alynt_lifecycle_check( empty( $receipt['child_cleanup_unresolved'] ), 'Owned children must stop before table cleanup.' );
		foreach ( array_reverse( $created ) as $owned ) {
			// Exact parent-created tables only, proven absent before CREATE; never pre-existing storage.
			$r = $db->one( 'SELECT COUNT(*) AS n FROM ' . $owned ); alynt_lifecycle_check( is_array( $r ) && ctype_digit( (string) $r['n'] ), 'Owned cleanup preview failed.' );
			$db->exec( 'DROP TABLE ' . $owned ); $absent = $db->one( 'SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?', array( $owned ) );
			$receipt['owned_tables'][$owned] = array( 'rows_previewed' => (int) $r['n'], 'absence_verified' => is_array( $absent ) && '0' === (string) $absent['n'] );
		}
	} catch ( Throwable $error ) { $receipt['failed'] = true; $receipt['fixture_cleanup_unresolved'] = true; }
	$db->close();
	$receipt['actual_options_cron_activation_unchanged'] = $before[0] === alynt_lifecycle_actual_digest( $actual );
	$receipt['actual_users_usermeta_unchanged'] = $before[1] === alynt_provenance_users_digest();
	foreach ( array( 'probe_settings', alynt_writer_state_name() ) as $name ) wp_cache_delete( $name, 'options' );
}
$clean = count( $receipt['owned_tables'] ) === 2; foreach ( $receipt['owned_tables'] as $r ) $clean = $clean && $r['absence_verified'];
echo json_encode( $receipt, JSON_PRETTY_PRINT ) . "\n";
exit( ! empty( $receipt['failed'] ) || ! $clean || ! $receipt['actual_options_cron_activation_unchanged'] || ! $receipt['actual_users_usermeta_unchanged'] ? 1 : 0 );
