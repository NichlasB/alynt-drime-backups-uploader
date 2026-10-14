<?php
/** Explicit Plugin Tester owned-table proof; never a plugin bootstrap or CI database task. */
$root = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
$approved = realpath( 'C:/Users/Captain/Local Sites/plugin-tester/app/public' );
if ( false === $root || false === $approved || $root !== $approved ) {
	fwrite( STDERR, "Use only the confirmed Plugin Tester root.\n" ); exit( 1 );
}
define( 'SHORTINIT', true ); define( 'DISABLE_WP_CRON', true );
require $root . '/wp-load.php';
require_once __DIR__ . '/lifecycle-proof-safety.php';
$sites = json_decode( file_get_contents( 'C:/Users/Captain/AppData/Roaming/Local/sites.json' ), true ); $metadata = null;
foreach ( is_array( $sites ) ? $sites : array() as $site ) if ( isset( $site['domain'], $site['path'] ) && 'plugin-tester.local' === $site['domain'] && realpath( $site['path'] . '/app/public' ) === $approved ) $metadata = $site;
$port = $metadata['services']['mariadb']['ports']['MYSQL'][0] ?? null;
if ( ! is_int( $port ) || $port <= 0 || $port > 65535 || '127.0.0.1:' . $port !== DB_HOST || 'local' !== DB_NAME ) {
	fwrite( STDERR, "LocalWP database metadata/configuration mismatch; no fixture writes permitted.\n" ); exit( 1 );
}
$repo = dirname( __DIR__, 2 );
foreach ( array( 'class-schedule-safety-option-cas', 'trait-schedule-safety-state-validation', 'trait-schedule-safety-state-transitions', 'class-schedule-safety-store', 'trait-cadence-authority-validation', 'trait-cadence-authority-mutations', 'trait-cadence-authority-dispatch', 'class-cadence-authority-store', 'trait-cadence-authority-lifecycle-preparation', 'class-cadence-authority-lifecycle-store' ) as $part ) require_once $repo . '/includes/' . $part . '.php';
require_once $repo . '/tests/Support/ScheduleSafetyFixture.php';
require_once __DIR__ . '/lifecycle-proof-processes.php';
require_once __DIR__ . '/lifecycle-proof-cases.php';
$ledger_mode = isset( $argv[2] ) && in_array( $argv[2], array( '--ledger', '--ledger-child' ), true );
$provenance_mode = isset( $argv[2] ) && in_array( $argv[2], array( '--provenance', '--provenance-child' ), true );
if ( $ledger_mode || $provenance_mode ) {
	foreach ( array( 'trait-consent-ledger-validation', 'trait-consent-ledger-commands', 'trait-consent-ledger-migration', 'trait-consent-ledger-api-boundary', 'class-cadence-consent-ledger' ) as $part ) require_once $repo . '/includes/' . $part . '.php';
	require_once __DIR__ . '/consent-ledger-proof-processes.php';
	require_once __DIR__ . '/consent-ledger-proof-cases.php';
}
if ( $provenance_mode ) {
	foreach ( array( 'trait-closed-consent-provenance-validation', 'class-closed-consent-provenance-store', 'trait-consent-request-validation', 'class-consent-request-adapter' ) as $part ) require_once $repo . '/includes/' . $part . '.php';
	require_once $repo . '/tests/Support/ConsentRequestPorts.php';
	require_once __DIR__ . '/closed-provenance-proof-processes.php';
	require_once __DIR__ . '/closed-provenance-proof-cases.php';
}

$actual = $wpdb->options;
alynt_lifecycle_check( preg_match( '/^[A-Za-z0-9_]+$/D', $actual ), 'Invalid actual options identifier.' );
alynt_lifecycle_check( 'http://plugin-tester.local' === $wpdb->get_var( "SELECT option_value FROM {$actual} WHERE option_name='siteurl'" ), 'Wrong WordPress target.' );
alynt_lifecycle_check( ! wp_using_ext_object_cache(), 'Persistent cache isolation needs separate approval/proof.' );
if ( isset( $argv[2] ) && in_array( $argv[2], array( '--child', '--ledger-child', '--provenance-child' ), true ) ) {
	alynt_lifecycle_check( count( $argv ) === 9, 'Invalid child arguments.' );
	$table = $argv[3]; $token = $argv[4]; alynt_lifecycle_owned( $table, $token ); $wpdb->options = $table;
	if ( $provenance_mode ) alynt_provenance_child( $argv[5], $argv[6], (int) $argv[7], $argv[8] );
	elseif ( $ledger_mode ) alynt_ledger_child( $argv[5], $argv[6], (int) $argv[7], $argv[8] );
	else alynt_lifecycle_child( $argv[5], $argv[6], (int) $argv[7], $argv[8] );
	exit;
}
alynt_lifecycle_check( $ledger_mode || $provenance_mode ? count( $argv ) === 3 && ( $provenance_mode ? '--provenance' : '--ledger' ) === $argv[2] : count( $argv ) === 2, 'Proof accepts only the approved root and an explicit proof selector.' );
$table = 'wp_alynt_lifecycle_probe_' . bin2hex( random_bytes( 6 ) ); $token = bin2hex( random_bytes( 12 ) );
$before = alynt_lifecycle_actual_digest( $actual ); $created = false; $children = array(); $receipt = $ledger_mode ? array( 'proof_kind' => 'consent_ledger' ) : array();
if ( $ledger_mode || $provenance_mode ) $receipt['fixture_table'] = $table;
if ( $provenance_mode ) { $receipt['proof_kind'] = 'closed_provenance'; $users_before = alynt_provenance_users_digest(); }
try {
	alynt_lifecycle_check( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=%s', $table ) ), 'Fixture table already exists.' );
	alynt_lifecycle_check( false !== $wpdb->query( "CREATE TABLE {$table} LIKE {$actual}" ), 'Fixture creation failed.' ); $created = true;
	alynt_lifecycle_check( 1 === $wpdb->insert( $table, array( 'option_name' => 'probe_owner', 'option_value' => $token, 'autoload' => 'no' ) ), 'Owner marker creation failed.' );
	alynt_lifecycle_owned( $table, $token ); $wpdb->options = $table;
	foreach ( array( 'alloptions', 'notoptions', Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION ) as $key ) wp_cache_delete( $key, 'options' );
	if ( $provenance_mode ) alynt_provenance_cases( $root, $table, $token, $children, $receipt );
	elseif ( $ledger_mode ) alynt_ledger_cases( $root, $table, $token, $children, $receipt );
	else alynt_lifecycle_cases( $root, $table, $token, $children, $receipt );
} catch ( Throwable $error ) {
	$receipt['failed'] = true;
	$receipt['reason'] = $error instanceof RuntimeException ? $error->getMessage() : 'Unexpected internal proof failure; no acknowledgement assumed.';
} finally {
	foreach ( $children as &$child ) if ( is_resource( $child['process'] ) ) { proc_terminate( $child['process'] ); proc_close( $child['process'] ); $child['process'] = null; }
	unset( $child ); $wpdb->options = $actual;
	if ( $created && 1 === preg_match( '/^wp_alynt_lifecycle_probe_[a-f0-9]{12}$/D', $table ) && $table !== $actual ) {
		// Exact table was proven absent then created by this parent. Never delete any pre-existing table.
		$receipt['fixture_rows_previewed'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$receipt['fixture_table_removed'] = false !== $wpdb->query( "DROP TABLE {$table}" );
		$receipt['fixture_absence_verified'] = 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=%s', $table ) );
	}
	foreach ( array( 'alloptions', 'notoptions', Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION ) as $key ) wp_cache_delete( $key, 'options' );
	$receipt['actual_options_cron_activation_unchanged'] = $before === alynt_lifecycle_actual_digest( $actual );
	if ( $provenance_mode ) $receipt['actual_users_usermeta_unchanged'] = $users_before === alynt_provenance_users_digest();
}
echo json_encode( $receipt, JSON_PRETTY_PRINT ) . "\n";
exit( ! empty( $receipt['failed'] ) || empty( $receipt['fixture_table_removed'] ) || empty( $receipt['fixture_absence_verified'] ) || empty( $receipt['actual_options_cron_activation_unchanged'] ) || ( $provenance_mode && empty( $receipt['actual_users_usermeta_unchanged'] ) ) ? 1 : 0 );
