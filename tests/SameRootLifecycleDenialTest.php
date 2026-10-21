<?php
require_once __DIR__ . '/Support/SameRootLifecycleFixture.php';
/** @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class SameRootLifecycleDenialTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Same_Root_Lifecycle_Fixture;
    /** @dataProvider closedOutcomes */
    public function test_unsupported_or_unreleased_history_cannot_issue_diagnostic( $fault ) {
        $this->sameFixture->fault = $fault; $this->assertFalse( $this->sameRun() );
        $this->assertNotContains( 'read', $this->sameFixture->events ); $this->assertSameUuidCount( 'capture' === $fault ? 0 : 10 );
        $this->assertSameRootCleared(); $this->assertFalse( $this->sameRun() );
    }
    public function closedOutcomes() { return array_map( function ( $f ) { return array( $f ); }, array( 'capture', 'unavailable', 'rolled_back', 'not_written', 'close_unknown', 'release_false', 'release_throw' ) ); }
    public function test_every_nonce_guard_individually_required_without_second_write_or_read_after_rejection() {
        $facts = array(); $this->sameCheckpoints( $facts ); $this->assertSameCandidate( $this->sameRun() );
        $total = count( $facts ); $this->assertGreaterThan( 15, $total );
        for ( $stop = 1; $stop <= $total; $stop++ ) {
            $this->sameNew(); $facts = array();
            $this->sameCheckpoints( $facts, function ( $n ) use ( $stop ) { if ( $n === $stop ) $GLOBALS['alynt_core_request_test']['nonce_result'] = false; } );
            $this->assertFalse( $this->sameRun(), 'Required nonce guard ' . $stop ); $this->assertCount( $stop, $facts );
            $this->assertLessThanOrEqual( 1, count( array_keys( $this->sameFixture->events, 'attempt', true ) ) );
            $this->assertLessThanOrEqual( 1, count( array_keys( $this->sameFixture->events, 'read', true ) ) ); $this->assertSameRootCleared();
        }
    }
    public function test_unauthorized_or_malformed_initial_intent_performs_no_capture_or_uuid() {
        foreach ( array( 'method', 'body', 'nonce', 'actor', 'role', 'cookie', 'extra' ) as $fault ) {
            $this->sameNew(); $method = 'POST'; $form = $this->registrationForm();
            if ( 'method' === $fault ) $method = 'GET';
            if ( 'body' === $fault ) $form['confirm'] = array();
            if ( 'nonce' === $fault ) $form['nonce'] = 'wrong';
            if ( 'actor' === $fault ) $GLOBALS['alynt_core_request_test']['actor'] = 0;
            if ( 'role' === $fault ) $GLOBALS['alynt_core_request_test']['role_now'] = false;
            if ( 'cookie' === $fault ) $_COOKIE[LOGGED_IN_COOKIE] = '';
            $this->assertFalse( 'extra' === $fault ? $this->sameRun( $method, $form, 'origin' ) : $this->sameRun( $method, $form ) );
            $this->assertNotContains( 'capture', $this->sameFixture->events ); $this->assertSameUuidCount( 0 ); $this->assertSameRootCleared();
            $GLOBALS['alynt_core_request_test']['actor'] = 7; $GLOBALS['alynt_core_request_test']['role_now'] = true; $_COOKIE[LOGGED_IN_COOKIE] = 'unit-cookie';
        }
    }
    public function test_read_failure_never_retries_and_prior_error_handler_survives() {
        foreach ( array( 'read_bad', 'read_throw' ) as $fault ) {
            $this->sameNew(); $this->sameFixture->fault = $fault; $handler = static function () { return false; }; set_error_handler( $handler );
            try {
                $this->assertFalse( $this->sameRun() ); $prior = set_error_handler( $handler ); $this->assertSame( $handler, $prior ); restore_error_handler();
                $this->assertSame( 1, count( array_keys( $this->sameFixture->events, 'read', true ) ) ); $this->assertSameRootCleared();
            } finally { restore_error_handler(); }
        }
    }
}
