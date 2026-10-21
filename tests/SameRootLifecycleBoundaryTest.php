<?php
require_once __DIR__ . '/Support/SameRootLifecycleFixture.php';
/** @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class SameRootLifecycleBoundaryTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Same_Root_Lifecycle_Fixture;
    /** @dataProvider contextFaults */
    public function test_release_callback_cannot_change_original_context_or_borrowed_identity( $fault ) {
        $this->sameFixture->on = function ( $event, $owner ) use ( $fault ) {
            if ( 'release' !== $event || null === $owner->state( 'proposal' ) ) return;
            switch ( $fault ) {
                case 'actor': $GLOBALS['alynt_core_request_test']['actor'] = 8; $GLOBALS['alynt_core_request_test']['cookie_actor'] = 8; break;
                case 'cookie': $_COOKIE[LOGGED_IN_COOKIE] = 'another'; break;
                case 'blog': $GLOBALS['alynt_core_request_test']['blog'] = 2; break;
                case 'connection': $GLOBALS['wpdb'] = new wpdb(); break;
                case 'physical': $this->sameFixture->physical_bad = true; break;
                case 'intent': $_SERVER['REQUEST_METHOD'] = 'GET'; break;
                case 'role': $GLOBALS['alynt_core_request_test']['role_now'] = false; break;
                case 'session': $GLOBALS['alynt_core_request_test']['session_now'] = false; break;
                case 'origin': $d = $owner->state( 'admission_origin' ); $d['actor_id']++; $owner->state( 'admission_origin', $d ); break;
                case 'ids': $ids = $owner->state( 'admission_ids' ); array_pop( $ids ); $owner->state( 'admission_ids', $ids ); break;
                case 'proposal': $p = $owner->state( 'proposal' ); $p['actor_id']++; $owner->state( 'proposal', $p ); break;
            }
        };
        $this->assertFalse( $this->sameRun() ); $this->assertNotContains( 'read', $this->sameFixture->events );
        $this->assertSameUuidCount( 10 ); $this->assertCount( 1, $this->registrationCalls( 'create_nonce' ) ); $this->assertSameRootCleared();
    }
    public function contextFaults() { return array_map( function ( $f ) { return array( $f ); }, array( 'actor', 'cookie', 'blog', 'connection', 'physical', 'intent', 'role', 'session', 'origin', 'ids', 'proposal' ) ); }
    public function test_read_nonce_creation_callback_cannot_adopt_new_actor_or_issue_lookup_uuid() {
        $count = 0;
        $hook = function () use ( &$count, &$hook ) {
            $count++; $GLOBALS['alynt_core_request_test']['on']['create_nonce'] = $hook;
            if ( 2 === $count ) { $GLOBALS['alynt_core_request_test']['actor'] = 8; $GLOBALS['alynt_core_request_test']['cookie_actor'] = 8; }
        };
        $GLOBALS['alynt_core_request_test']['on']['create_nonce'] = $hook;
        $this->assertFalse( $this->sameRun() ); $this->assertSameUuidCount( 10 ); $this->assertNotContains( 'read', $this->sameFixture->events ); $this->assertSameRootCleared();
    }
    public function test_reentry_in_capture_attempt_release_and_read_poison_outer_and_cleanup() {
        foreach ( array( 'capture', 'attempt', 'release', 'read' ) as $where ) {
            $this->sameNew(); $fired = false;
            $this->sameFixture->on = function ( $event, $owner ) use ( $where, &$fired ) {
                if ( $where !== $event || $fired ) return; $fired = true;
                $this->assertFalse( $owner->run( 'POST', $this->registrationForm() ) );
            };
            $this->assertFalse( $this->sameRun() ); $this->assertTrue( $fired ); $this->assertSameRootCleared();
        }
    }
    public function test_expired_mutation_or_root_and_read_clocks_cannot_be_renewed() {
        foreach ( array( 'start_reset', 'root_age', 'utc_regression', 'read_age', 'expired_intent' ) as $fault ) {
            $this->sameNew(); $facts = array(); $done = false;
            $this->sameCheckpoints( $facts, function () use ( $fault, &$done ) {
                if ( $done || null === $this->sameRoot->state( 'read_started' ) ) return; $done = true;
                $o = $this->sameRoot; $clock = $o->state( 'lifecycle_clock' );
                if ( 'start_reset' === $fault ) $o->state( 'started', hrtime( true ) + 1 );
                if ( 'root_age' === $fault ) { $clock['started_ns'] = hrtime( true ) - 20000000000; $o->state( 'started', $clock['started_ns'] ); $o->state( 'lifecycle_clock', $clock ); }
                if ( 'utc_regression' === $fault ) { $clock['utc'] = time() + 5; $o->state( 'lifecycle_clock', $clock ); }
                if ( 'read_age' === $fault ) $o->state( 'read_started', hrtime( true ) - 10000000000 );
                if ( 'expired_intent' === $fault ) { $d = $o->state( 'origin_data' ); $d['mutation']['expires_at'] = time(); $o->state( 'origin_data', $d ); }
            } );
            $this->assertFalse( $this->sameRun(), $fault ); $this->assertTrue( $done ); $this->assertSameRootCleared();
        }
    }
    public function test_final_cleanup_reentry_must_invalidate_a_previously_computed_candidate() {
        $releases = 0;
        $this->sameFixture->on = function ( $event, $owner ) use ( &$releases ) {
            if ( 'release' !== $event || ++$releases !== 2 ) return;
            $this->assertFalse( $owner->run( 'POST', $this->registrationForm() ) );
        };
        $this->assertFalse( $this->sameRun() ); $this->assertSame( 2, $releases );
        $this->assertContains( 'read', $this->sameFixture->events ); $this->assertSameRootCleared();
    }
}
