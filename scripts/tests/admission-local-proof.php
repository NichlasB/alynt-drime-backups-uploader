<?php
/** Explicit approved Plugin Tester CLI proof only; no plugin or web entry point. */
if ( 'cli' !== PHP_SAPI ) exit( 1 );
$root = isset( $argv[1] ) ? realpath( $argv[1] ) : false; $approved = realpath( 'C:/Users/Captain/Local Sites/plugin-tester/app/public' );
if ( false === $root || false === $approved || $root !== $approved ) { fwrite( STDERR, "Only the confirmed Plugin Tester root is allowed.\n" ); exit( 1 ); }
if ( ! ( count( $argv ) === 2 || ( count( $argv ) === 6 && '--child' === $argv[2] ) ) ) { fwrite( STDERR, "Invalid explicit proof arguments.\n" ); exit( 1 ); }
foreach ( array( 'db.php', 'object-cache.php', 'advanced-cache.php' ) as $dropin ) if ( file_exists( $root . '/wp-content/' . $dropin ) ) { fwrite( STDERR, "Unsupported drop-in; proof refused.\n" ); exit( 1 ); }
define( 'SHORTINIT', true ); define( 'DISABLE_WP_CRON', true ); require $root . '/wp-load.php'; $wpdb->suppress_errors( true );
require_once __DIR__ . '/lifecycle-proof-safety.php'; require_once __DIR__ . '/consent-auth-proof-storage.php';
$sites = json_decode( file_get_contents( 'C:/Users/Captain/AppData/Roaming/Local/sites.json' ), true ); $metadata = null;
foreach ( is_array( $sites ) ? $sites : array() as $site ) if ( isset( $site['domain'], $site['path'] ) && 'plugin-tester.local' === $site['domain'] && realpath( $site['path'] . '/app/public' ) === $approved ) $metadata = $site;
$port = $metadata['services']['mariadb']['ports']['MYSQL'][0] ?? null;
alynt_lifecycle_check( is_int( $port ) && $port > 0 && $port <= 65535 && '127.0.0.1:' . $port === DB_HOST && 'local' === DB_NAME && ! is_multisite() && ! wp_using_ext_object_cache(), 'Local target/config/cache mismatch.' );
$actual = array( 'options' => $wpdb->options, 'users' => $wpdb->users, 'usermeta' => $wpdb->usermeta );
foreach ( $actual as $table ) alynt_lifecycle_check( 1 === preg_match( '/^[A-Za-z0-9_]+$/D', $table ), 'Invalid actual identifier.' );
alynt_lifecycle_check( 'http://plugin-tester.local' === $wpdb->get_var( "SELECT option_value FROM {$actual['options']} WHERE option_name='siteurl'" ), 'Wrong site origin.' );
$child_mode = count( $argv ) === 6; $base = $child_mode ? $argv[3] : 'wp_alynt_auth_probe_' . bin2hex( random_bytes( 6 ) ); $token = $child_mode ? $argv[4] : bin2hex( random_bytes( 12 ) );
alynt_lifecycle_check( 1 === preg_match( '/^wp_alynt_auth_probe_[a-f0-9]{12}$/D', $base ) && 1 === preg_match( '/^[a-f0-9]{24}$/D', $token ), 'Invalid owned admission identity.' );
$owned = array(); foreach ( $actual as $key => $table ) $owned[$key] = $base . '_' . $key; $tables = $owned + array( 'control' => $base . '_control' );
$fence = alynt_auth_write_fence( $tables ); add_filter( 'query', $fence, PHP_INT_MAX );
function alynt_admission_load( $owned ) {
	global $wpdb; foreach ( $owned as $key => $table ) $wpdb->$key = $table; wp_cache_flush();
	foreach ( array( 'consent-auth-proof-core', 'consent-auth-proof-ports', 'admission-proof-storage', 'admission-proof-fixtures', 'admission-proof-factory', 'admission-proof-transaction', 'closed-writer-proof-processes', 'admission-proof-processes' ) as $file ) require_once __DIR__ . '/' . $file . '.php';
}
if ( $child_mode ) {
	try { alynt_auth_owned( $owned, $token ); alynt_admission_load( $owned ); alynt_admission_child( $base, $token, $owned, $argv[5] ); exit( 0 ); }
	catch ( Throwable $error ) { echo json_encode( array( 'ok' => false, 'internal_failure' => true, 'line' => $error->getLine(), 'file' => basename( $error->getFile() ) ) ) . "\n"; exit( 1 ); }
}
$before = array( alynt_lifecycle_actual_digest( $actual['options'] ), alynt_provenance_users_digest() ); $created = array(); $children = array(); $factory = null;
$receipt = array( 'proof_kind' => 'genuine_core_admission_fixture_native_sql', 'fixture_scope' => $base, 'production_permission_proven' => false );
try {
	foreach ( $tables as $key => $table ) {
		alynt_lifecycle_check( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=%s', $table ) ), 'Owned table already exists.' );
		$template = $actual[ 'control' === $key ? 'options' : $key ]; alynt_lifecycle_check( false !== $wpdb->query( "CREATE TABLE {$table} LIKE {$template}" ), 'Owned table creation failed.' ); $created[] = $table;
	}
	alynt_lifecycle_check( 1 === $wpdb->insert( $owned['options'], array( 'option_name' => 'probe_owner', 'option_value' => $token, 'autoload' => 'no' ) ), 'Owned marker failed.' );
	alynt_auth_owned( $owned, $token ); alynt_admission_load( $owned ); alynt_auth_seed_users( $owned );
	$factory = new Alynt_Admission_Proof_Factory( $base, $token, $owned );
	foreach ( array( 'cases', 'edges', 'races' ) as $part ) require_once __DIR__ . '/admission-proof-' . $part . '.php';
	alynt_admission_cases( $factory, $receipt ); alynt_admission_edges( $factory, $receipt ); alynt_admission_races( $root, $factory, $owned, $children, $receipt );
} catch ( Throwable $error ) {
	$receipt['failed'] = true; $receipt['reason'] = RuntimeException::class === get_class( $error ) ? $error->getMessage() : 'Internal proof failure; no success assumed.';
	$receipt['failure_location'] = array( 'class' => get_class( $error ), 'file' => basename( $error->getFile() ), 'line' => $error->getLine() );
} finally {
	foreach ( $children as &$child ) if ( is_resource( $child['process'] ) ) { try { alynt_writer_join( $child, true ); } catch ( Throwable $error ) { $receipt['failed'] = true; $receipt['child_cleanup_failed'] = true; } } unset( $child );
	if ( $factory ) { try { $factory->db->exec( 'ROLLBACK' ); $factory->db->close(); } catch ( Throwable $error ) { $receipt['failed'] = true; } }
	$_COOKIE = array(); try { if ( function_exists( 'wp_set_current_user' ) ) wp_set_current_user( 0 ); } catch ( Throwable $error ) { $receipt['failed'] = true; unset( $GLOBALS['current_user'] ); }
	foreach ( $actual as $key => $table ) $wpdb->$key = $table; wp_cache_flush(); $receipt['owned_tables'] = array();
	foreach ( array_reverse( $created ) as $table ) {
		$preview = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); $removed = false; $absent = false;
		if ( '' === $wpdb->last_error && is_numeric( $preview ) ) {
			$removed = false !== $wpdb->query( "DROP TABLE {$table}" ); $absence = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=%s', $table ) );
			$absent = '' === $wpdb->last_error && is_numeric( $absence ) && 0 === (int) $absence;
		}
		$receipt['owned_tables'][$table] = array( 'rows_previewed' => is_numeric( $preview ) ? (int) $preview : null, 'removed' => $removed, 'absence_verified' => $absent );
	}
	$receipt['actual_options_cron_activation_unchanged'] = $before[0] === alynt_lifecycle_actual_digest( $actual['options'] ); $receipt['actual_users_usermeta_unchanged'] = $before[1] === alynt_provenance_users_digest(); $receipt['no_browser_cookies_emitted'] = array() === headers_list();
}
$clean = count( $receipt['owned_tables'] ) === 4; foreach ( $receipt['owned_tables'] as $row ) $clean = $clean && $row['removed'] && $row['absence_verified'];
echo json_encode( $receipt, JSON_PRETTY_PRINT ) . "\n";
exit( ! empty( $receipt['failed'] ) || ! $clean || ! $receipt['actual_options_cron_activation_unchanged'] || ! $receipt['actual_users_usermeta_unchanged'] || ! $receipt['no_browser_cookies_emitted'] ? 1 : 0 );
