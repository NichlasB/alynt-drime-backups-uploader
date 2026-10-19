<?php
require_once __DIR__ . '/Support/RegistrationObservationIntegrationFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class RegistrationObservationIntegrationBoundaryTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Registration_Observation_Integration_Fixture;
    public function test_malformed_mismatched_or_missing_origin_requests_consume_before_authentication(): void {
        foreach ( array( 'purpose' => 'observe_reservation', 'observed_command_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'nonce' => '', 'confirm' => 'register', 'provider' => 'caller' ) as $key => $value ) {
            $this->observationOwner = Alynt_Drime_Backups_Uploader_Registration_Observation_Construction::create(); list( $d, $frame, $i ) = $this->integrationFixture(); $i[$key] = $value;
            $this->assertFalse( $this->integrationCall( $frame, $i ), $key ); $this->assertSame( array(), $this->registrationCalls( 'validate_cookie' ) ); $this->assertSame( array(), $this->registrationCalls( 'create_nonce' ) );
            $this->assertIntegrationCleared(); $this->assertFalse( $this->integrationCall( $frame, $this->observationIntent( $d['mutation']['command_id'] ) ) );
        }
        $this->observationOwner = Alynt_Drime_Backups_Uploader_Registration_Observation_Construction::create(); $GLOBALS['alynt_core_request_test']['calls'] = array();
        $this->assertFalse( $this->integrationCall( array(), array() ) ); $this->assertSame( array(), $this->registrationCalls( 'validate_cookie' ) ); $this->assertIntegrationCleared();
    }
    public function test_initial_profile_method_session_and_capability_are_fresh(): void {
        foreach ( array( 'filter_allows' => false, 'session_now' => false, 'role_now' => false, 'blog' => 2, 'multisite' => true, 'external_cache' => true, 'method' => 'GET' ) as $key => $value ) {
            $this->observationOwner = Alynt_Drime_Backups_Uploader_Registration_Observation_Construction::create(); list( $d, $frame, $i ) = $this->integrationFixture(); $s = $GLOBALS['alynt_core_request_test'];
            if ( 'method' === $key ) $_SERVER['REQUEST_METHOD'] = $value; else $GLOBALS['alynt_core_request_test'][$key] = $value;
            $this->assertFalse( $this->integrationCall( $frame, $i ), $key ); $this->assertIntegrationCleared();
            $GLOBALS['alynt_core_request_test'] = $s; $_SERVER['REQUEST_METHOD'] = 'POST';
        }
    }
    public function test_pre_claim_and_post_finish_callbacks_cannot_revoke_or_swap_pinned_facts(): void {
        foreach ( array( 4, 6 ) as $phase ) {
            foreach ( array( 'session', 'capability', 'cookie', 'actor', 'method', 'db', 'prefix', 'budget', 'renew_start', 'utc', 'throw' ) as $fault ) {
                $this->observationOwner = Alynt_Drime_Backups_Uploader_Registration_Observation_Construction::create(); list( $d, $frame, $i ) = $this->integrationFixture(); $facts = array();
                $s = $GLOBALS['alynt_core_request_test']; $db = $GLOBALS['wpdb']; $roles = $GLOBALS['wp_roles'];
                $this->integrationCheckpoints( $facts, function ( $n ) use ( $phase, $fault ) {
                    if ( $n !== $phase ) return;
                    if ( 'session' === $fault ) $GLOBALS['alynt_core_request_test']['session_now'] = false;
                    if ( 'capability' === $fault ) $GLOBALS['alynt_core_request_test']['filter_allows'] = false;
                    if ( 'cookie' === $fault ) $_COOKIE[LOGGED_IN_COOKIE] = 'changed-cookie';
                    if ( 'actor' === $fault ) { $GLOBALS['alynt_core_request_test']['actor'] = 8; $GLOBALS['alynt_core_request_test']['cookie_actor'] = 8; }
                    if ( 'method' === $fault ) $_SERVER['REQUEST_METHOD'] = 'GET';
                    if ( 'db' === $fault ) $GLOBALS['wpdb'] = new wpdb();
                    if ( 'prefix' === $fault ) $GLOBALS['wpdb']->options = 'other_options';
                    if ( 'budget' === $fault ) $this->observationProperty( 'started', hrtime( true ) - 10000000000 );
                    if ( 'renew_start' === $fault ) $this->observationProperty( 'started', hrtime( true ) );
                    if ( 'utc' === $fault ) $this->observationProperty( 'last_clock', time() + 1 );
                    if ( 'throw' === $fault ) throw new RuntimeException( 'Synthetic private fault.' );
                } );
                $this->assertFalse( $this->integrationCall( $frame, $i ), "$phase/$fault" ); $this->assertNotEmpty( $facts ); $this->assertIntegrationCleared(); $this->assertSame( $roles, $GLOBALS['wp_roles'] );
                $GLOBALS['alynt_core_request_test'] = $s; $db->options = 'wp_options'; $GLOBALS['wpdb'] = $db; $_COOKIE[LOGGED_IN_COOKIE] = 'unit-cookie'; $_SERVER['REQUEST_METHOD'] = 'POST';
            }
        }
    }
    public function test_reentry_through_any_existing_entry_point_poisoned_outer_without_native_work(): void {
        foreach ( array( 4, 6 ) as $phase ) {
            foreach ( array( 'observe_candidate', 'construct_observation', 'construct_native_observation', 'retain_registration' ) as $method ) {
                $this->observationOwner = Alynt_Drime_Backups_Uploader_Registration_Observation_Construction::create(); list( $d, $frame, $i ) = $this->integrationFixture(); $facts = array(); $nested = null;
                $this->integrationCheckpoints( $facts, function ( $n ) use ( $phase, $method, &$nested, $frame, $i ) { if ( $n === $phase ) $nested = in_array( $method, array( 'observe_candidate', 'retain_registration' ), true ) ? $this->observationCall( $method, $frame, $i ) : $this->observationCall( $method, $i ); } );
                $this->assertFalse( $this->integrationCall( $frame, $i ), "$phase/$method" ); $this->assertFalse( $nested ); $this->assertTrue( $this->observationProperty( 'interrupted' ) ); $this->assertIntegrationCleared();
                $GLOBALS['alynt_core_request_test']['on'] = array();
            }
        }
    }
    public function test_role_only_autoload_and_query_fences_cover_all_seven_nonce_phases(): void {
        list( $d, $frame, $i ) = $this->integrationFixture(); $facts = array(); $fences = array();
        $this->integrationCheckpoints( $facts, function ( $n ) use ( &$fences ) {
            $filters = $this->ownedFilters; $query = $filters['query'][0][0]; $refused = array();
            foreach ( array( 'SELECT * FROM wp_options', 'SELECT * FROM/*comment*/wp_options', 'UPDATE wp_options SET option_value = 1' ) as $sql ) { $denied = false; try { $query( $sql ); } catch ( RuntimeException $error ) { $denied = true; } $refused[] = $denied; }
            $role = "SELECT option_value FROM wp_options WHERE option_name = 'wp_user_roles' LIMIT 1";
            $fences[] = array( 'n' => $n, 'count' => count( $filters['query'] ), 'priority' => $filters['query'][0][1], 'autoload' => ( $filters['pre_wp_load_alloptions'][0][0] )(), 'role' => $query( $role ), 'refused' => $refused );
        } );
        $sentinel = static function () { return false; }; set_error_handler( $sentinel );
        try {
            $this->assertIntegrationResult( $this->integrationCall( $frame, $i ), 'registered_closed' ); $this->assertCount( 7, $fences );
            foreach ( $fences as $f ) { $this->assertSame( 1, $f['count'] ); $this->assertSame( PHP_INT_MAX, $f['priority'] ); $this->assertSame( array(), $f['autoload'] ); $this->assertSame( "SELECT option_value FROM wp_options WHERE option_name = 'wp_user_roles' LIMIT 1", $f['role'] ); $this->assertSame( array( true, true, true ), $f['refused'] ); }
            $previous = set_error_handler( $sentinel ); $this->assertSame( $sentinel, $previous ); restore_error_handler(); $this->assertIntegrationCleared();
        } finally { restore_error_handler(); }
    }
    public function test_lookup_collision_is_once_only_and_caller_cannot_supply_other_private_facts(): void {
        list( $d, $frame, $i ) = $this->integrationFixture(); mt_srand( 1212 ); $next = wp_generate_uuid4(); mt_srand( 1212 );
        $used = $this->observationProperty( 'used' ); $used[$next] = true; $this->observationProperty( 'used', $used );
        $this->assertFalse( $this->integrationCall( $frame, $i ) ); $after = wp_generate_uuid4(); mt_srand( 1212 ); wp_generate_uuid4(); $this->assertSame( wp_generate_uuid4(), $after ); $this->assertIntegrationCleared();
        $this->observationOwner = Alynt_Drime_Backups_Uploader_Registration_Observation_Construction::create(); list( $d, $frame, $i ) = $this->integrationFixture();
        $this->assertFalse( $this->observationCall( 'observe_candidate', $frame, $i, $d ) ); $this->assertSame( array(), $this->registrationCalls( 'validate_cookie' ) ); $this->assertIntegrationCleared();
        $public = array(); foreach ( ( new ReflectionClass( $this->observationOwner ) )->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) $public[] = $method->getName(); sort( $public );
        $this->assertSame( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ), $public );
        foreach ( array_merge( array( dirname( __DIR__ ) . '/alynt-drime-backups-uploader.php' ), glob( dirname( __DIR__ ) . '/includes/class-plugin*.php' ), array( dirname( __DIR__ ) . '/includes/class-registration-native-admission.php', dirname( __DIR__ ) . '/includes/trait-registration-observation-native-capture.php' ) ) as $path ) $this->assertStringNotContainsString( 'observe_candidate', file_get_contents( $path ) );
    }
}
