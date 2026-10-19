<?php
require_once __DIR__ . '/Support/RegistrationObservationConstructionFixture.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class RegistrationObservationConstructionBoundaryTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Registration_Observation_Construction_Fixture;

    public function test_malformed_or_stale_intents_are_consumed_before_authentication(): void {
        foreach ( array( 'purpose' => 'observe_reservation', 'confirm' => 'register', 'observed_command_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'nonce' => '', 'actor_id' => '7' ) as $key => $value ) {
            $this->observationOwner = Alynt_Drime_Backups_Uploader_Registration_Observation_Construction::create();
            $d = $this->retainObservation(); $i = $this->observationIntent( $d['mutation']['command_id'] ); $i[$key] = $value;
            $GLOBALS['alynt_core_request_test']['calls'] = array();
            $this->assertFalse( $this->observationCall( 'construct_observation', $i ), $key );
            $this->assertSame( array(), $this->registrationCalls( 'validate_cookie' ), $key );
            $this->assertSame( array(), $this->registrationCalls( 'create_nonce' ), $key ); $this->assertObservationCleared();
        }
    }

    public function test_read_profile_capability_session_and_method_are_fresh(): void {
        foreach ( array( 'filter_allows' => false, 'session_now' => false, 'role_now' => false, 'blog' => 2, 'external_cache' => true, 'multisite' => true, 'method' => 'GET' ) as $key => $value ) {
            $this->observationOwner = Alynt_Drime_Backups_Uploader_Registration_Observation_Construction::create();
            $d = $this->retainObservation(); $i = $this->observationIntent( $d['mutation']['command_id'] );
            $s = $GLOBALS['alynt_core_request_test']; $GLOBALS['alynt_core_request_test'][$key] = $value;
            if ( 'method' === $key ) $_SERVER['REQUEST_METHOD'] = $value;
            $this->assertFalse( $this->observationCall( 'construct_observation', $i ), $key ); $this->assertObservationCleared();
            $GLOBALS['alynt_core_request_test'] = $s; $_SERVER['REQUEST_METHOD'] = 'POST';
        }
    }

    public function test_callback_revocations_and_installation_changes_fail_after_nonce_creation(): void {
        foreach ( array( 'session', 'cookie', 'method', 'db', 'prefix', 'budget', 'throw', 'reentry' ) as $fault ) {
            $this->observationOwner = Alynt_Drime_Backups_Uploader_Registration_Observation_Construction::create();
            $d = $this->retainObservation(); $i = $this->observationIntent( $d['mutation']['command_id'] );
            $s = $GLOBALS['alynt_core_request_test']; $db = $GLOBALS['wpdb'];
            $GLOBALS['alynt_core_request_test']['on']['create_nonce'] = function () use ( $fault ) {
                if ( 'session' === $fault ) $GLOBALS['alynt_core_request_test']['session_now'] = false;
                if ( 'cookie' === $fault ) $_COOKIE[LOGGED_IN_COOKIE] = 'changed-cookie';
                if ( 'method' === $fault ) $_SERVER['REQUEST_METHOD'] = 'GET';
                if ( 'db' === $fault ) $GLOBALS['wpdb'] = new wpdb();
                if ( 'prefix' === $fault ) $GLOBALS['wpdb']->options = 'other_options';
                if ( 'budget' === $fault ) $this->observationProperty( 'started', hrtime( true ) - 10000000000 );
                if ( 'throw' === $fault ) throw new RuntimeException( 'Synthetic secret.' );
                if ( 'reentry' === $fault ) $this->observationCall( 'construct_observation', array() );
            };
            $roles = $GLOBALS['wp_roles'];
            $this->assertFalse( $this->observationCall( 'construct_observation', $i ), $fault ); $this->assertObservationCleared();
            $this->assertSame( $roles, $GLOBALS['wp_roles'] );
            $GLOBALS['alynt_core_request_test'] = $s; $db->options = 'wp_options'; $GLOBALS['wpdb'] = $db;
            $_COOKIE[LOGGED_IN_COOKIE] = 'unit-cookie'; $_SERVER['REQUEST_METHOD'] = 'POST';
        }
    }

    public function test_lookup_identity_collisions_fail_once_without_reminting(): void {
            $this->observationOwner = Alynt_Drime_Backups_Uploader_Registration_Observation_Construction::create();
            $d = $this->retainObservation(); $i = $this->observationIntent( $d['mutation']['command_id'] );
            mt_srand( 31337 ); $next = wp_generate_uuid4(); mt_srand( 31337 );
            // Reflection instrumentation adds a known collision to the private identity set;
            // it does not grant origin or represent genuine UUID/storage proof.
            $used = $this->observationProperty( 'used' ); $used[$next] = true; $this->observationProperty( 'used', $used );
            $this->assertFalse( $this->observationCall( 'construct_observation', $i ) );
            $after = wp_generate_uuid4(); mt_srand( 31337 ); wp_generate_uuid4(); $this->assertSame( wp_generate_uuid4(), $after, 'Exactly one UUID attempt, no retry.' );
            $this->assertObservationCleared();
    }

    public function test_query_fence_policy_and_owned_cleanup_cover_read_callbacks(): void {
        $d = $this->retainObservation(); $i = $this->observationIntent( $d['mutation']['command_id'] ); $facts = array();
        $sentinel = static function () { return false; }; set_error_handler( $sentinel );
        try {
            $GLOBALS['alynt_core_request_test']['on']['create_nonce'] = function () use ( &$facts ) {
                $f = $this->ownedFilters['query'][0][0];
                foreach ( array( 'SELECT * FROM wp_options', 'SELECT * FROM `wp_options`', 'UPDATE wp_options SET option_value = 1', 'DELETE FROM wp_options', "SELECT option_value FROM wp_options WHERE option_name = 'wp_user_roles' LIMIT 1 ", 'SELECT * FROM wp_options JOIN wp_users ON 1', 'SELECT * FROM/*comment*/wp_options', "SELECT * FROM `wp_options`\nWHERE 1" ) as $sql ) {
                    try { $f( $sql ); $facts[] = false; } catch ( RuntimeException $e ) { $facts[] = true; }
                }
            };
            $this->assertIsArray( $this->observationCall( 'construct_observation', $i ) );
            $this->assertSame( array_fill( 0, 8, true ), $facts );
            $previous = set_error_handler( $sentinel ); $this->assertSame( $sentinel, $previous ); restore_error_handler();
            $this->assertObservationCleared();
        } finally { restore_error_handler(); }
    }

    public function test_public_surface_is_redacted_and_cannot_import_a_context_or_export_capabilities(): void {
        $this->retainObservation();
        $class = new ReflectionClass( $this->observationOwner ); $public = array();
        foreach ( $class->getMethods( ReflectionMethod::IS_PUBLIC ) as $m ) $public[] = $m->getName();
        sort( $public ); $this->assertSame( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ), $public );
        $this->assertFalse( Alynt_Drime_Backups_Uploader_Registration_Observation_Construction::create( new stdClass() ) );
        $this->assertSame( $this->observationOwner->disposition(), $this->observationOwner->__debugInfo() );
        try { serialize( $this->observationOwner ); $this->fail( 'Transfer must be refused.' ); } catch ( LogicException $e ) { $this->assertSame( 'Closed contract data cannot be transferred.', $e->getMessage() ); }
        $this->observationCall( 'construct_observation', array() ); $this->assertObservationCleared();
    }
}
