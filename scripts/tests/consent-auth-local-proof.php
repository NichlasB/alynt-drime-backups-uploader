<?php
/** Explicit Plugin Tester core-auth proof; no web handler, production bootstrap or CI SQL. */
$root = isset( $argv[1] ) ? realpath( $argv[1] ) : false; $approved = realpath( 'C:/Users/Captain/Local Sites/plugin-tester/app/public' );
if ( false === $root || false === $approved || $root !== $approved ) { fwrite( STDERR, "Use only the confirmed Plugin Tester root.\n" ); exit( 1 ); }
if ( ! ( count( $argv ) === 2 || ( count( $argv ) === 5 && '--ack-child' === $argv[2] ) ) ) { fwrite( STDERR, "Invalid explicit proof arguments.\n" ); exit( 1 ); }
foreach ( array( 'db.php', 'object-cache.php', 'advanced-cache.php' ) as $dropin ) if ( file_exists( $root . '/wp-content/' . $dropin ) ) { fwrite( STDERR, "Custom drop-in requires separate isolation proof.\n" ); exit( 1 ); }
define( 'SHORTINIT', true ); define( 'DISABLE_WP_CRON', true ); require $root . '/wp-load.php';
$wpdb->suppress_errors( true ); // Explicit checks/receipts report failures without SQL/private user data logging.
require_once __DIR__ . '/lifecycle-proof-safety.php'; require_once __DIR__ . '/consent-auth-proof-storage.php';
$sites = json_decode( file_get_contents( 'C:/Users/Captain/AppData/Roaming/Local/sites.json' ), true ); $metadata = null;
foreach ( is_array( $sites ) ? $sites : array() as $site ) if ( isset( $site['domain'], $site['path'] ) && 'plugin-tester.local' === $site['domain'] && realpath( $site['path'] . '/app/public' ) === $approved ) $metadata = $site;
$port = $metadata['services']['mariadb']['ports']['MYSQL'][0] ?? null;
alynt_lifecycle_check( is_int( $port ) && $port > 0 && $port <= 65535 && '127.0.0.1:' . $port === DB_HOST && 'local' === DB_NAME && ! is_multisite() && ! wp_using_ext_object_cache(), 'Local target/config/cache mismatch.' );
$actual = array( 'options' => $wpdb->options, 'users' => $wpdb->users, 'usermeta' => $wpdb->usermeta );
foreach ( $actual as $table ) alynt_lifecycle_check( 1 === preg_match( '/^[A-Za-z0-9_]+$/D', $table ), 'Invalid actual table identifier.' );
alynt_lifecycle_check( 'http://plugin-tester.local' === $wpdb->get_var( "SELECT option_value FROM {$actual['options']} WHERE option_name='siteurl'" ), 'Wrong WordPress target.' );
$child_mode = count( $argv ) === 5;
$base = $child_mode ? $argv[3] : 'wp_alynt_auth_probe_' . bin2hex( random_bytes( 6 ) ); $token = $child_mode ? $argv[4] : bin2hex( random_bytes( 12 ) );
alynt_lifecycle_check( 1 === preg_match( '/^wp_alynt_auth_probe_[a-f0-9]{12}$/D', $base ) && 1 === preg_match( '/^[a-f0-9]{24}$/D', $token ), 'Invalid auth fixture identity.' );
$owned = array(); foreach ( $actual as $key => $table ) $owned[$key] = $base . '_' . $key;
$fence = alynt_auth_write_fence( $owned ); add_filter( 'query', $fence, PHP_INT_MAX );
if ( $child_mode ) {
	alynt_auth_owned( $owned, $token ); foreach ( $owned as $key => $table ) $wpdb->$key = $table; wp_cache_flush();
	require_once __DIR__ . '/consent-auth-proof-core.php'; require_once __DIR__ . '/consent-auth-proof-ports.php'; require_once __DIR__ . '/consent-auth-proof-cases.php';
	alynt_auth_ack_child( $owned ); exit( 1 ); // Successful fixture commit is deliberately never acknowledged.
}
$before = array( alynt_lifecycle_actual_digest( $actual['options'] ), alynt_provenance_users_digest() ); $created = array(); $children = array();
$receipt = array( 'proof_kind' => 'wordpress_authenticated_request_boundary', 'fixture_scope' => $base );
try {
	foreach ( $owned as $key => $table ) {
		alynt_lifecycle_check( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=%s', $table ) ), 'Auth fixture table already exists.' );
		alynt_lifecycle_check( false !== $wpdb->query( "CREATE TABLE {$table} LIKE {$actual[$key]}" ), 'Auth fixture creation failed.' ); $created[] = $table;
	}
	alynt_lifecycle_check( 1 === $wpdb->insert( $owned['options'], array( 'option_name' => 'probe_owner', 'option_value' => $token, 'autoload' => 'no' ) ), 'Auth owner marker failed.' ); alynt_auth_owned( $owned, $token );
	foreach ( $owned as $key => $table ) $wpdb->$key = $table; wp_cache_flush();
	require_once __DIR__ . '/consent-auth-proof-core.php'; require_once __DIR__ . '/consent-auth-proof-ports.php'; require_once __DIR__ . '/consent-auth-proof-cases.php';
	alynt_auth_seed_users( $owned ); alynt_auth_cases( $root, $owned, $token, $children, $receipt );
} catch ( Throwable $error ) {
	$receipt['failed'] = true; $receipt['reason'] = $error instanceof RuntimeException ? $error->getMessage() : 'Unexpected internal auth proof failure; no acknowledgement assumed.';
	$receipt['failure_location'] = array( 'class' => get_class( $error ), 'file' => basename( $error->getFile() ), 'line' => $error->getLine() ); // No exception payload, stack arguments or private values.
	if ( $error instanceof TypeError && preg_match( '/function "([A-Za-z_][A-Za-z0-9_]*)" not found/', $error->getMessage(), $missing ) ) $receipt['missing_core_callback'] = $missing[1];
} finally {
	foreach ( $children as &$child ) if ( is_resource( $child['process'] ) ) { proc_terminate( $child['process'] ); proc_close( $child['process'] ); $child['process'] = null; } unset( $child );
	$_COOKIE = array();
	try { if ( function_exists( 'wp_set_current_user' ) ) wp_set_current_user( 0 ); }
	catch ( Throwable $error ) { $receipt['failed'] = true; $receipt['context_reset_failed'] = true; unset( $GLOBALS['current_user'] ); } // Never let partial core loading prevent owned-table cleanup.
	foreach ( $actual as $key => $table ) $wpdb->$key = $table; wp_cache_flush(); $receipt['owned_tables'] = array();
	foreach ( array_reverse( $created ) as $table ) {
		// Only this parent's exact tables, proven absent before creation, are ever dropped.
		$preview = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( '' !== $wpdb->last_error || ! is_numeric( $preview ) ) { $receipt['owned_tables'][$table] = array( 'preview_failed' => true, 'removed' => false, 'absence_verified' => false ); continue; }
		$removed = false !== $wpdb->query( "DROP TABLE {$table}" ); $absence = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=%s', $table ) );
		$receipt['owned_tables'][$table] = array( 'rows_previewed' => (int) $preview, 'removed' => $removed, 'absence_verified' => '' === $wpdb->last_error && is_numeric( $absence ) && 0 === (int) $absence );
	}
	// Keep the process-local fence through audit/shutdown; it is never installed on the web site.
	$receipt['actual_options_cron_activation_unchanged'] = $before[0] === alynt_lifecycle_actual_digest( $actual['options'] ); $receipt['actual_users_usermeta_unchanged'] = $before[1] === alynt_provenance_users_digest();
	$receipt['no_browser_cookies_emitted'] = array() === headers_list();
}
$clean = count( $receipt['owned_tables'] ) === 3; foreach ( $receipt['owned_tables'] as $row ) $clean = $clean && $row['removed'] && $row['absence_verified'];
echo json_encode( $receipt, JSON_PRETTY_PRINT ) . "\n";
exit( ! empty( $receipt['failed'] ) || ! $clean || ! $receipt['actual_options_cron_activation_unchanged'] || ! $receipt['actual_users_usermeta_unchanged'] || ! $receipt['no_browser_cookies_emitted'] ? 1 : 0 );
