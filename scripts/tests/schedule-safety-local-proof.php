<?php
/**
 * Plugin-tester-only proof. Creates/removes its own options-table clone.
 * SHORTINIT loads no plugins, workers, backup tasks or scheduled actions.
 */
$site_root = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
$approved_root = realpath( 'C:/Users/Captain/Local Sites/plugin-tester/app/public' );
if ( false === $site_root || false === $approved_root || $site_root !== $approved_root ) {
	fwrite( STDERR, "Use only the approved plugin-tester WordPress root.\n" ); exit( 1 );
}
define( 'SHORTINIT', true );
define( 'DISABLE_WP_CRON', true );
require $site_root . '/wp-load.php';
require_once ABSPATH . WPINC . '/cron.php';
$repository = dirname( __DIR__, 2 );
foreach ( array( 'class-schedule-safety-option-cas', 'trait-schedule-safety-state-validation', 'trait-schedule-safety-state-transitions', 'class-schedule-safety-store', 'class-schedule-safety-cron-cas' ) as $part ) require_once $repository . '/includes/' . $part . '.php';
require_once $repository . '/tests/Support/ScheduleSafetyFixture.php';

function alynt_safety_proof_check( $condition, $label ) { if ( ! $condition ) throw new RuntimeException( $label ); }
$actual_options = $wpdb->options;
alynt_safety_proof_check( preg_match( '/^[A-Za-z0-9_]+$/D', $actual_options ), 'Unexpected options table.' );
alynt_safety_proof_check( 'http://plugin-tester.local' === $wpdb->get_var( "SELECT option_value FROM {$actual_options} WHERE option_name='siteurl'" ), 'Wrong local WordPress target.' );
$name = Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION;
$binding = str_repeat( 'e', 64 );
$source = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE;

/** Synchronize actual database readers before their competing updates. */
class Alynt_Safety_Proof_Barrier_CAS extends Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS {
	private $first = true;
	private $proof_database;
	private $actor;
	public function __construct( $database, $actor ) { parent::__construct( $database ); $this->proof_database = $database; $this->actor = $actor; }
	public function read( $name ) {
		$row = parent::read( $name );
		if ( $this->first ) {
			$this->first = false;
			$db = $this->proof_database;
			$db->insert( $db->options, array( 'option_name' => 'probe_ready_' . $this->actor, 'option_value' => 'ready', 'autoload' => 'no' ) );
			$limit = microtime( true ) + 15;
			while ( 'go' !== $db->get_var( "SELECT option_value FROM {$db->options} WHERE option_name='probe_go'" ) ) {
				if ( microtime( true ) >= $limit ) exit( 1 );
				usleep( 20000 );
			}
		}
		return $row;
	}
}
if ( isset( $argv[2] ) && '--claim-child' === $argv[2] ) {
	$table = isset( $argv[3] ) ? $argv[3] : '';
	alynt_safety_proof_check( preg_match( '/^wp_alynt_safety_probe_[a-f0-9]{12}$/D', $table ), 'Unexpected child table.' );
	$wpdb->options = $table;
	$now = (int) $argv[5];
	alynt_safety_proof_check( in_array( $argv[4], array( '1', '2' ), true ), 'Unexpected child actor.' );
	$store = new Alynt_Drime_Backups_Uploader_Schedule_Safety_Store( new Alynt_Safety_Proof_Barrier_CAS( $wpdb, $argv[4] ), function () use ( $now ) { return $now; } );
	$result = $store->claim( $binding, $source, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $now, (int) $argv[4] ) );
	echo json_encode( array( 'won' => is_array( $result ), 'code' => is_wp_error( $result ) ? $result->get_error_code() : 'claimed' ) ) . "\n";
	exit;
}

$table = 'wp_alynt_safety_probe_' . bin2hex( random_bytes( 6 ) );
$created = false; $children = array(); $receipt = array();
$before_cron = $wpdb->get_var( "SELECT SHA2(option_value,256) FROM {$actual_options} WHERE option_name='cron'" );
$before_state = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$actual_options} WHERE option_name=%s", $name ) );
try {
	alynt_safety_proof_check( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=%s', $table ) ), 'Probe table already exists.' );
	alynt_safety_proof_check( false !== $wpdb->query( "CREATE TABLE {$table} LIKE {$actual_options}" ), 'Probe table creation failed.' );
	$created = true; $wpdb->options = $table;
	foreach ( array( 'alloptions', 'notoptions', 'cron', $name ) as $key ) wp_cache_delete( $key, 'options' );
	$now = time();
	$backend = new Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS( $wpdb );
	$store = new Alynt_Drime_Backups_Uploader_Schedule_Safety_Store( $backend, function () use ( &$now ) { return $now; } );
	alynt_safety_proof_check( is_array( $store->initialize( $binding ) ), 'Initialization failed.' );
	$receipt['non_autoloaded'] = in_array( $backend->read( $name )['autoload'], array( 'no', 'off', 'auto-off' ), true );
	alynt_safety_proof_check( $receipt['non_autoloaded'], 'Safety state autoloaded.' );
	for ( $i = 1; $i <= 2; $i++ ) {
		$command = array( PHP_BINARY, '-c', get_cfg_var( 'cfg_file_path' ), __FILE__, $site_root, '--claim-child', $table, (string) $i, (string) $now );
		$process = proc_open( $command, array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
		alynt_safety_proof_check( is_resource( $process ), 'Child process failed.' );
		fclose( $pipes[0] );
		$children[] = array( 'process' => $process, 'pipes' => $pipes, 'output' => '' );
	}
	$deadline = microtime( true ) + 15;
	do {
		$ready = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE option_name IN ('probe_ready_1','probe_ready_2')" );
		if ( 2 === $ready ) break;
		usleep( 20000 );
	} while ( microtime( true ) < $deadline );
	alynt_safety_proof_check( 2 === $ready, 'Child barrier failed.' );
	$wpdb->insert( $table, array( 'option_name' => 'probe_go', 'option_value' => 'go', 'autoload' => 'no' ) );
	$wins = 0;
	foreach ( $children as &$child ) {
		$limit = microtime( true ) + 15;
		do {
			$status = proc_get_status( $child['process'] );
			if ( ! $status['running'] ) break;
			usleep( 20000 );
		} while ( microtime( true ) < $limit );
		alynt_safety_proof_check( ! $status['running'], 'Child claim timed out.' );
		$child['output'] .= stream_get_contents( $child['pipes'][1] ); fclose( $child['pipes'][1] );
		stream_get_contents( $child['pipes'][2] ); fclose( $child['pipes'][2] );
		$closed = proc_close( $child['process'] );
		alynt_safety_proof_check( 0 === $status['exitcode'] || 0 === $closed, 'Child claim failed.' ); $child['process'] = null;
		$lines = explode( "\n", trim( $child['output'] ) ); $result = json_decode( end( $lines ), true );
		alynt_safety_proof_check( is_array( $result ), 'Child result missing.' ); $wins += $result['won'] ? 1 : 0;
	}
	unset( $child );
	alynt_safety_proof_check( 1 === $wins, 'Concurrent claim was not exclusive.' ); $receipt['concurrent_single_winner'] = true;

	$claim = $store->snapshot()['claims'][ $source ];
	wp_cache_set( $name, 'stale fixture cache', 'options' );
	wp_cache_set( 'alloptions', array( $name => 'stale fixture cache' ), 'options' );
	wp_cache_set( 'notoptions', array( $name => true ), 'options' );
	alynt_safety_proof_check( is_array( $store->snapshot() ), 'Read depended on stale cache.' );
	alynt_safety_proof_check( is_array( $store->transition( $binding, $source, $claim['action_id'], $claim['request_hash'], 'claimed', 'write_started' ) ), 'Journal transition failed.' );
	alynt_safety_proof_check( $backend->read( $name )['option_value'] === get_option( $name ), 'Cache invalidation failed.' ); $receipt['cache_invalidation'] = true;
	$restart = new Alynt_Drime_Backups_Uploader_Schedule_Safety_Store( $backend, function () use ( &$now ) { return $now; } );
	alynt_safety_proof_check( 'write_started' === $restart->snapshot()['claims'][ $source ]['phase'], 'Journal lost across restart.' );
	$now += 400;
	alynt_safety_proof_check( is_wp_error( $restart->transition( $binding, $source, $claim['action_id'], $claim['request_hash'], 'write_started', 'succeeded' ) ), 'Expired write claimed success.' );
	alynt_safety_proof_check( is_array( $restart->transition( $binding, $source, $claim['action_id'], $claim['request_hash'], 'write_started', 'recovery_required' ) ), 'Unresolved journal lost.' ); $receipt['expired_restart_protected'] = true;

	$new_source = '44444444-4444-4444-8444-444444444444';
	$new_claim = $restart->claim( $binding, $new_source, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $now, 9 ) );
	alynt_safety_proof_check( is_array( $new_claim ), 'Fault fixture claim failed.' );
	$old_suppression = $wpdb->suppress_errors( true );
	try {
		alynt_safety_proof_check( false !== $wpdb->query( 'START TRANSACTION READ ONLY' ), 'Read-only transaction failed.' );
		$failure = $restart->invalidate( $binding );
		alynt_safety_proof_check( is_wp_error( $failure ) && 'schedule_safety_write_failed' === $failure->get_error_code(), 'Failed DB write claimed success.' );
	} finally { $wpdb->query( 'ROLLBACK' ); $wpdb->suppress_errors( $old_suppression ); }
	alynt_safety_proof_check( 'claimed' === $restart->snapshot()['claims'][ $new_source ]['phase'], 'Failed write changed journal.' ); $receipt['database_failure_closed'] = true;

	$before = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::cron( time() + 1800 );
	$after = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::cron( time() + 900, 'fifteen_minutes', 900 );
	add_option( 'cron', $before, '', true );
	$guard = new Alynt_Drime_Backups_Uploader_Schedule_Safety_Cron_CAS( $backend );
	alynt_safety_proof_check( true === $guard->replace( maybe_serialize( $before ), $before, $after ), 'Scoped cron CAS failed.' );
	alynt_safety_proof_check( is_wp_error( $guard->replace( maybe_serialize( $before ), $before, $after ) ), 'Stale cron write accepted.' );
	alynt_safety_proof_check( $after === get_option( 'cron' ), 'Unrelated events changed.' ); $receipt['scoped_cron_cas'] = true;
	// Core's unconditional whole-option write proves the remaining coordination gap.
	alynt_safety_proof_check( true === _set_cron_array( $before, true ), 'Core overwrite fixture failed.' );
	alynt_safety_proof_check( $before === get_option( 'cron' ), 'Core writer counterexample missing.' ); $receipt['unguarded_core_overwrite_observed'] = true;
	$receipt['persistent_object_cache_active'] = (bool) wp_using_ext_object_cache();
} catch ( Throwable $error ) { $receipt['failed'] = true; $receipt['reason'] = $error->getMessage(); }
finally {
	foreach ( $children as $child ) if ( is_resource( $child['process'] ) ) { proc_terminate( $child['process'] ); proc_close( $child['process'] ); }
	$wpdb->options = $actual_options;
	if ( $created && preg_match( '/^wp_alynt_safety_probe_[a-f0-9]{12}$/D', $table ) ) {
		$receipt['synthetic_rows_removed'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$receipt['probe_table_removed'] = false !== $wpdb->query( "DROP TABLE {$table}" );
	}
	foreach ( array( 'alloptions', 'notoptions', 'cron', $name ) as $key ) wp_cache_delete( $key, 'options' );
	$receipt['site_cron_unchanged'] = $before_cron === $wpdb->get_var( "SELECT SHA2(option_value,256) FROM {$actual_options} WHERE option_name='cron'" );
	$receipt['site_safety_option_unchanged'] = $before_state === $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$actual_options} WHERE option_name=%s", $name ) );
}
echo json_encode( $receipt, JSON_PRETTY_PRINT ) . "\n";
exit( ! empty( $receipt['failed'] ) || empty( $receipt['probe_table_removed'] ) || empty( $receipt['site_cron_unchanged'] ) || empty( $receipt['site_safety_option_unchanged'] ) ? 1 : 0 );
