<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RegistrationLifecycleOwnerFixture.php';
/** @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class RegistrationLifecycleOwnerDenialTest extends TestCase {
    use Alynt_Test_Registration_Lifecycle_Owner_Fixture;
    public function test_every_initial_auth_denial_precedes_ids_nonce_creation_and_retention() {
        $seed = $GLOBALS['alynt_core_request_test']; $frame = $this->registrationFrame();
        foreach ( array( 'session_now', 'user_now', 'role_now', 'filter_allows', 'logged_in' ) as $key ) {
            $GLOBALS['alynt_core_request_test'] = $seed; $GLOBALS['alynt_core_request_test'][$key] = false; $this->lifecycleNew();
            mt_srand( 93 ); $expected = wp_generate_uuid4(); mt_srand( 93 );
            $this->assertFalse( $this->lifecycleCall( $frame, $this->registrationForm() ), $key ); $this->assertSame( $expected, wp_generate_uuid4(), $key );
            $this->assertCount( 0, $this->registrationCalls( 'create_nonce' ) ); $this->assertCount( 0, $this->registrationCalls( 'verify_nonce' ) ); $this->assertLifecycleCleared();
        }
    }
    public function test_each_of_six_nonce_checkpoints_can_refuse_and_consume() {
        $seed = $GLOBALS['alynt_core_request_test']; $frame = $this->registrationFrame();
        for ( $gate = 1; $gate <= 6; $gate++ ) {
            $GLOBALS['alynt_core_request_test'] = $seed; $this->lifecycleNew(); $facts = array();
            $this->lifecycleCheckpoints( $facts, function ( $n ) use ( $gate ) { if ( $n === $gate ) $GLOBALS['alynt_core_request_test']['nonce_result'] = false; } );
            $this->assertFalse( $this->lifecycleCall( $frame, $this->registrationForm() ), 'gate' . $gate ); $this->assertCount( $gate, $facts );
            $this->assertLifecycleCleared(); $this->assertFalse( $this->lifecycleCall( $frame, $this->registrationForm() ) );
        }
    }
    public function test_issuance_and_full_command_nonce_domains_cannot_substitute() {
        $seed = $GLOBALS['alynt_core_request_test']; $frame = $this->registrationFrame();
        foreach ( array( 4, 5, 6 ) as $gate ) {
            $GLOBALS['alynt_core_request_test'] = $seed; $this->lifecycleNew(); $facts = array();
            $this->lifecycleCheckpoints( $facts, function ( $n ) use ( $gate ) {
                if ( $n !== $gate ) return;
                $s =& $GLOBALS['alynt_core_request_test']; $create = $this->registrationCalls( 'create_nonce' ); $full = $create[0][1][0];
                $issue = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( 1, 'register_closed' );
                if ( 5 === $gate ) $s['nonce_actions']['unit-nonce'] = $full;
                else { foreach ( $s['nonce_actions'] as $nonce => $action ) if ( $action === $full ) $s['nonce_actions'][$nonce] = $issue; }
            } );
            $this->assertFalse( $this->lifecycleCall( $frame, $this->registrationForm() ), 'domain gate' . $gate ); $this->assertLifecycleCleared();
        }
    }
    public function test_retained_stage_revocation_identity_metadata_or_clock_change_refuses() {
        $seed = $GLOBALS['alynt_core_request_test']; $frame = $this->registrationFrame();
        foreach ( array( 'session', 'capability', 'actor', 'cookie', 'blog', 'database', 'method', 'deadline', 'restart', 'clock_regression', 'origin', 'ids' ) as $fault ) {
            $GLOBALS['alynt_core_request_test'] = $seed; $_COOKIE[LOGGED_IN_COOKIE] = 'unit-cookie'; $_SERVER['REQUEST_METHOD'] = 'POST'; $GLOBALS['wpdb'] = new wpdb(); $this->lifecycleNew(); $facts = array();
            $this->lifecycleCheckpoints( $facts, function ( $n ) use ( $fault ) {
                if ( 5 !== $n ) return; $this->assertIsArray( $this->lifecycleProperty( 'origin_data' ) ); $s =& $GLOBALS['alynt_core_request_test'];
                if ( 'session' === $fault ) { $s['session_now'] = false; $s['session_cache'] = false; }
                elseif ( 'capability' === $fault ) $s['filter_allows'] = false;
                elseif ( 'actor' === $fault ) $s['actor']++;
                elseif ( 'cookie' === $fault ) $_COOKIE[LOGGED_IN_COOKIE] .= '-changed';
                elseif ( 'blog' === $fault ) $s['blog']++;
                elseif ( 'database' === $fault ) $GLOBALS['wpdb'] = new wpdb();
                elseif ( 'method' === $fault ) $_SERVER['REQUEST_METHOD'] = 'GET';
                elseif ( 'deadline' === $fault ) $this->lifecycleProperty( 'started', hrtime( true ) - 10000000000 );
                elseif ( 'restart' === $fault ) $this->lifecycleProperty( 'started', hrtime( true ) );
                elseif ( 'clock_regression' === $fault ) { $clock = $this->lifecycleProperty( 'root_clock' ); $clock['now_ns'] = hrtime( true ) + 1000000000; $this->lifecycleProperty( 'root_clock', $clock ); }
                elseif ( 'origin' === $fault ) { $d = $this->lifecycleProperty( 'origin_data' ); $d['actor_id']++; $this->lifecycleProperty( 'origin_data', $d ); }
                else { $ids = $this->lifecycleProperty( 'used' ); array_pop( $ids ); $this->lifecycleProperty( 'used', $ids ); }
            } );
            $this->assertFalse( $this->lifecycleCall( $frame, $this->registrationForm() ), $fault ); $this->assertLifecycleCleared();
        }
    }
    public function test_malformed_intent_full_read_intent_or_extra_context_cannot_issue() {
        $good = $this->registrationForm(); $frame = $this->registrationFrame();
        foreach ( array( array(), array( 'purpose' => 'observe_registration', 'confirm' => 'observe', 'nonce' => 'unit-nonce' ), array_merge( $good, array( 'nonce' => '' ) ), array_merge( $good, array( 'actor_id' => 7 ) ), str_repeat( 'x', 5000 ) ) as $form ) {
            $this->lifecycleNew(); $GLOBALS['alynt_core_request_test']['calls'] = array();
            $this->assertFalse( $this->lifecycleCall( $frame, $form ) ); $this->assertCount( 0, $this->registrationCalls( 'create_nonce' ) ); $this->assertLifecycleCleared();
        }
        $this->lifecycleNew(); $this->assertFalse( $this->lifecycleCall( $frame, $good, array( 'actor' => 7, 'phase' => 'writer_released' ) ) ); $this->assertLifecycleCleared();
    }
    public function test_malformed_or_open_captured_candidate_refuses_before_uuid_generation() {
        $good = $this->registrationFrame();
        foreach ( array_keys( $good['rows'] ) as $role ) {
            $this->lifecycleNew(); $frame = $good; $frame['rows'][$role]['octets'] = '00'; $GLOBALS['alynt_core_request_test']['calls'] = array();
            mt_srand( 103 ); $expected = wp_generate_uuid4(); mt_srand( 103 );
            $this->assertFalse( $this->lifecycleCall( $frame, $this->registrationForm() ), $role ); $this->assertSame( $expected, wp_generate_uuid4(), $role );
            $this->assertCount( 1, $this->registrationCalls( 'verify_nonce' ) ); $this->assertCount( 0, $this->registrationCalls( 'create_nonce' ) ); $this->assertLifecycleCleared();
        }
    }
}
