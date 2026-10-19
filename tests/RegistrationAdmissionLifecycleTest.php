<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RegistrationAdmissionLifecycleFixture.php';
/** Non-vacuous synthetic lifecycle transitions, never native provenance. */
class RegistrationAdmissionLifecycleTest extends TestCase {
    use Alynt_Test_Registration_Admission_Lifecycle_Fixture;
    public function test_positive_unknown_and_committed_paths_remain_candidate_only() {
        foreach ( array( 'uncertain', 'commit_unknown', 'committed' ) as $outcome ) foreach ( array( 'empty', 'registered', 'reserved' ) as $history ) {
            $f = $this->lifeFixture( $history ); $o = $this->lifeOwner(); $this->lifeRelease( $o, $f, $outcome );
            $this->assertTrue( $this->lifeRead( $o, $f ) ); $this->assertNull( $this->admissionProperty( $o, 'root_data' ) );
            $this->assertCandidateOnly( $this->lifeFinish( $o, $f ), 'empty' === $history ? 'not_observed_in_candidate' : 'registered_closed' );
            $this->assertLifeCleared( $o ); $this->assertFalse( $this->lifeFinish( $o, $f ) ); $this->assertFalse( $this->lifeRetain( $o, $f ) );
        }
    }
    public function test_rolled_back_and_no_write_paths_close_without_diagnostic() {
        $f = $this->lifeFixture(); $o = $this->lifeOwner(); $this->lifeRelease( $o, $f, 'rolled_back' ); $this->assertLifeCleared( $o ); $this->assertFalse( $this->lifeRead( $o, $f ) );
        $o = $this->lifeOwner(); $this->assertTrue( $this->lifeRetain( $o, $f ) );
        foreach ( array( 'begin_sent', 'transaction_owned', 'rollback_sent', 'rolled_back', 'writer_released' ) as $event ) $this->assertTrue( $this->lifeAdvance( $o, $event ) );
        $this->assertLifeCleared( $o ); $this->assertFalse( $this->lifeRead( $o, $f ) );
    }
    public function test_every_out_of_order_attempt_consumes_and_erases() {
        $f = $this->lifeFixture();
        foreach ( array( 'dml_sent', 'commit_sent', 'committed', 'writer_released', false, array(), 'admission_false' ) as $event ) {
            $o = $this->lifeOwner(); $this->assertTrue( $this->lifeRetain( $o, $f ) ); $this->assertFalse( $this->lifeAdvance( $o, $event ) ); $this->assertLifeCleared( $o ); $this->assertFalse( $this->lifeRetain( $o, $f ) );
        }
        foreach ( array( 'read', 'finish', 'advance', 'retain_twice' ) as $early ) {
            $o = $this->lifeOwner(); if ( 'retain_twice' === $early ) $this->assertTrue( $this->lifeRetain( $o, $f ) );
            $r = 'read' === $early ? $this->lifeRead( $o, $f ) : ( 'finish' === $early ? $this->lifeFinish( $o, $f ) : ( 'advance' === $early ? $this->lifeAdvance( $o, 'begin_sent' ) : $this->lifeRetain( $o, $f ) ) );
            $this->assertFalse( $r ); $this->assertLifeCleared( $o ); $this->assertFalse( $this->lifeRetain( $o, $f ) );
        }
    }
    public function test_second_write_read_or_returned_false_label_cannot_reopen() {
        $f = $this->lifeFixture();
        foreach ( array( 'write', 'read', 'false_label' ) as $fault ) {
            $o = $this->lifeOwner(); $this->lifeRelease( $o, $f );
            if ( 'write' === $fault ) $this->assertFalse( $this->lifeAdvance( $o, 'dml_sent' ) );
            elseif ( 'false_label' === $fault ) $this->assertFalse( $this->lifeAdvance( $o, false ) );
            else { $this->assertTrue( $this->lifeRead( $o, $f ) ); $this->assertFalse( $this->lifeRead( $o, $f ) ); }
            $this->assertLifeCleared( $o ); $this->assertFalse( $this->lifeFinish( $o, $f ) );
        }
    }
    public function test_origin_must_precede_dml_and_read_must_follow_writer_release() {
        $f = $this->lifeFixture(); $o = $this->lifeOwner(); $this->assertFalse( $this->lifeAdvance( $o, 'dml_sent' ) ); $this->assertLifeCleared( $o );
        foreach ( array( 'retained', 'transaction', 'dml', 'commit' ) as $phase ) {
            $o = $this->lifeOwner(); $this->assertTrue( $this->lifeRetain( $o, $f ) );
            if ( 'retained' !== $phase ) foreach ( array( 'begin_sent', 'transaction_owned' ) as $event ) $this->assertTrue( $this->lifeAdvance( $o, $event ) );
            if ( in_array( $phase, array( 'dml', 'commit' ), true ) ) $this->assertTrue( $this->lifeAdvance( $o, 'dml_sent' ) );
            if ( 'commit' === $phase ) foreach ( array( 'write_acknowledged', 'commit_sent' ) as $event ) $this->assertTrue( $this->lifeAdvance( $o, $event ) );
            $this->assertFalse( $this->lifeRead( $o, $f ), $phase ); $this->assertLifeCleared( $o );
        }
    }
    public function test_abort_loses_origin_at_every_stage_without_reconstruction() {
        $f = $this->lifeFixture();
        foreach ( array( 'new', 'retained', 'released', 'observing' ) as $phase ) {
            $o = $this->lifeOwner(); if ( 'retained' === $phase ) $this->assertTrue( $this->lifeRetain( $o, $f ) );
            if ( in_array( $phase, array( 'released', 'observing' ), true ) ) $this->lifeRelease( $o, $f );
            if ( 'observing' === $phase ) $this->assertTrue( $this->lifeRead( $o, $f ) );
            $this->assertTrue( $this->admissionInvoke( $o, 'abort' ) ); $this->assertLifeCleared( $o ); $this->assertFalse( $this->lifeRetain( $o, $f ) ); $this->assertFalse( $this->lifeRead( $o, $f ) );
        }
    }
}
