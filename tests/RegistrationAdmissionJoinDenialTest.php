<?php
require_once __DIR__ . '/Support/RegistrationAdmissionJoinFixture.php';
/** @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class RegistrationAdmissionJoinDenialTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Registration_Admission_Join_Fixture;
    /** @dataProvider changedPins */
    public function test_every_retained_and_writer_pin_change_refuses_before_forwarding( $slot ) {
        $facts = array(); $this->joinGuards( $facts, function ( $n ) use ( $slot ) {
            if ( 7 !== $n ) return;
            if ( 'started' === $slot ) { $this->join->state( $slot, $this->join->state( $slot ) + 1 ); return; }
            $v = $this->join->state( $slot );
            if ( 'admission_origin' === $slot ) $v['actor_id']++;
            elseif ( 'admission_ids' === $slot ) $v = array_reverse( $v );
            elseif ( 'admission_clock' === $slot ) $v['now_ns']++;
            elseif ( 'before' === $slot ) $v['rows']['queue']['value'] .= ' ';
            elseif ( 'proposal' === $slot ) $v['terminal_id'] = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
            elseif ( 'context' === $slot ) $v['actor_id']++;
            elseif ( 'recorded_at' === $slot ) ++$v;
            else $v .= ' ';
            $this->join->state( $slot, $v );
        } );
        $this->assertFalse( $this->join->run( $this->registrationForm() ) ); $this->assertNull( $this->join->sent_origin );
        $this->assertSame( 0, $this->join->state( 'writes' ) ); $this->joinCleared();
    }
    public function changedPins() { return array_map( function ( $s ) { return array( $s ); }, array( 'admission_origin', 'admission_ids', 'admission_clock', 'before', 'proposal', 'context', 'after', 'recorded_at', 'started' ) ); }
    /** @dataProvider identities */
    public function test_each_of_ten_original_id_losses_refuses_without_remint( $offset ) {
        $facts = array(); $this->joinGuards( $facts, function ( $n ) use ( $offset ) {
            if ( 7 !== $n ) return; $ids = $this->join->state( 'admission_ids' ); array_splice( $ids, count( $ids ) - 10 + $offset, 1 ); $this->join->state( 'admission_ids', $ids );
        } );
        $this->assertFalse( $this->join->run( $this->registrationForm() ) ); $this->assertNull( $this->join->sent_origin );
        $this->assertCount( 1, $this->registrationCalls( 'create_nonce' ) ); $this->joinCleared();
    }
    public function identities() { return array_map( function ( $n ) { return array( $n ); }, range( 0, 9 ) ); }
    /** @dataProvider reentries */
    public function test_cross_entry_reentry_permanently_poisons_joined_root( $gate ) {
        $facts = array(); $nested = null; $this->joinGuards( $facts, function ( $n ) use ( $gate, &$nested ) {
            if ( $gate === $n ) $nested = $this->join->run( $this->registrationForm(), false );
        } );
        $this->assertFalse( $this->join->run( $this->registrationForm() ) ); $this->assertFalse( $nested );
        $this->assertTrue( $this->join->state( 'admission_join_interrupted' ) ); $this->assertSame( $gate > 8 ? 1 : 0, $this->join->state( 'writes' ) ); $this->joinCleared();
        if ( 1 === $gate ) $this->assertSame( 0, $this->join->state( 'protected_reads' ), 'Reentry must stop before protected capture.' );
    }
    public function reentries() { return array( array( 1 ), array( 6 ), array( 7 ), array( 9 ), array( 10 ) ); }
    /** @dataProvider nativeFaults */
    public function test_api_failure_and_uncertainty_always_erase_without_read_fallback( $fault ) {
        $this->join->fault = $fault; $this->assertFalse( $this->join->run( $this->registrationForm() ) );
        $this->assertSame( in_array( $fault, array( 'uncertain', 'close' ), true ) ? 1 : 0, $this->join->state( 'writes' ) );
        if ( 'uncertain' === $fault ) $this->assertSame( 'commit_sent', $this->join->state( 'phase' ) );
        $this->assertLessThanOrEqual( 1, count( $this->registrationCalls( 'create_nonce' ) ) ); $this->joinCleared();
    }
    public function nativeFaults() { return array_map( function ( $v ) { return array( $v ); }, array( 'capture', 'writer', 'metadata', 'uncertain', 'close' ) ); }
    /** @dataProvider lateChanges */
    public function test_postrelease_changes_or_throws_refuse_without_rewriting_commit( $change ) {
        $facts = array(); $this->joinGuards( $facts, function ( $n ) use ( $change ) {
            if ( 9 !== $n ) return;
            if ( 'throw' === $change ) throw new RuntimeException( 'Isolated post-release fault.' );
            if ( 'ids' === $change ) { $ids = $this->join->state( 'admission_ids' ); array_pop( $ids ); $this->join->state( 'admission_ids', $ids ); }
            elseif ( 'origin' === $change ) $this->join->state( 'admission_origin', null );
            elseif ( 'budget' === $change ) $this->join->state( 'started', hrtime( true ) - 10000000001 );
            else $GLOBALS['alynt_core_request_test']['session_now'] = false;
        } );
        $this->assertFalse( $this->join->run( $this->registrationForm() ) ); $this->assertSame( 'committed', $this->join->state( 'phase' ) );
        $this->assertSame( 1, $this->join->state( 'writes' ) ); $this->joinCleared();
    }
    public function lateChanges() { return array_map( function ( $v ) { return array( $v ); }, array( 'throw', 'ids', 'origin', 'budget', 'session' ) ); }
    public function test_bad_initial_intents_and_original_clock_loss_never_capture_or_write() {
        $bad = $this->registrationForm(); $bad['nonce'] = 'forged';
        $this->assertFalse( $this->join->run( $bad ) ); $this->assertSame( 0, $this->join->state( 'protected_reads' ) ); $this->joinCleared();
        $this->join = new Alynt_Admission_Join_Harness( $this->registrationFrame() ); $facts = array();
        $this->joinGuards( $facts, function ( $n ) { if ( 7 === $n ) $this->join->state( 'started', hrtime( true ) - 10000000001 ); } );
        $this->assertFalse( $this->join->run( $this->registrationForm() ) ); $this->assertSame( 0, $this->join->state( 'writes' ) ); $this->joinCleared();
        $this->join = new Alynt_Admission_Join_Harness( $this->registrationFrame() ); $facts = array();
        $this->joinGuards( $facts, function ( $n ) { if ( 1 === $n ) $this->join->state( 'started', hrtime( true ) - 10000000001 ); } );
        $this->assertFalse( $this->join->run( $this->registrationForm() ) ); $this->assertSame( 0, $this->join->state( 'protected_reads' ) ); $this->joinCleared();
    }
}
