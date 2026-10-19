<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RegistrationAdmissionLifecycleFixture.php';
/** Explicit clock DATA only; no sleep, hrtime or native timeout proof. */
class RegistrationAdmissionLifecycleClockTest extends TestCase {
    use Alynt_Test_Registration_Admission_Lifecycle_Fixture;
    public function test_writer_cannot_reset_start_regress_or_cross_original_ten_seconds() {
        $f = $this->lifeFixture();
        foreach ( array( $this->admissionClock( 300, 111, 101 ), $this->admissionClock( 199, 111 ), $this->admissionClock( 300, 109 ), $this->admissionClock( 10000000100, 111 ), $this->admissionClock( 300, 400 ), array(), $this->admissionClock( 300.0, 111 ) ) as $clock ) {
            $o = $this->lifeOwner(); $this->assertTrue( $this->lifeRetain( $o, $f ) ); $this->assertFalse( $this->lifeAdvance( $o, 'begin_sent', $clock ) ); $this->assertLifeCleared( $o );
        }
        $o = $this->lifeOwner(); $this->assertTrue( $this->lifeRetain( $o, $f ) ); $this->assertTrue( $this->lifeAdvance( $o, 'begin_sent', $this->admissionClock( 10000000099, 111 ) ) );
    }
    public function test_writer_reads_compare_with_latest_observation_not_initial_clock() {
        $f = $this->lifeFixture();
        foreach ( array( $this->admissionClock( 399, 112 ), $this->admissionClock( 500, 111 ) ) as $clock ) {
            $o = $this->lifeOwner(); $this->assertTrue( $this->lifeRetain( $o, $f ) ); $this->assertTrue( $this->lifeAdvance( $o, 'begin_sent', $this->admissionClock( 400, 112 ) ) );
            $this->assertFalse( $this->lifeAdvance( $o, 'transaction_owned', $clock ) ); $this->assertLifeCleared( $o );
        }
    }
    public function test_sequential_read_has_own_budget_without_replacing_root_start() {
        $f = $this->lifeFixture(); $o = $this->lifeOwner(); $this->lifeRelease( $o, $f );
        $this->assertTrue( $this->lifeRead( $o, $f, $this->admissionClock( 10500000100, 120 ) ) );
        $this->assertSame( 100, $this->admissionProperty( $o, 'root_clock' )['started_ns'] );
        $this->assertCandidateOnly( $this->lifeFinish( $o, $f, $this->admissionClock( 20000000099, 121 ) ), 'registered_closed' ); $this->assertLifeCleared( $o );
    }
    public function test_read_refuses_either_budget_exactly_and_no_regression_or_renewal() {
        $f = $this->lifeFixture();
        foreach ( array( $this->admissionClock( 10000000400, 121 ), $this->admissionClock( 500, 121, 101 ), $this->admissionClock( 399, 121 ), $this->admissionClock( 500, 119 ), $this->admissionClock( 500, 420 ) ) as $clock ) {
            $o = $this->lifeOwner(); $this->lifeRelease( $o, $f ); $this->assertTrue( $this->lifeRead( $o, $f ) ); $this->assertFalse( $this->lifeFinish( $o, $f, $clock ) ); $this->assertLifeCleared( $o );
        }
        $o = $this->lifeOwner(); $this->lifeRelease( $o, $f ); $this->assertTrue( $this->lifeRead( $o, $f, $this->admissionClock( 10500000100, 120 ) ) );
        $this->assertFalse( $this->lifeFinish( $o, $f, $this->admissionClock( 20000000100, 121 ) ) ); $this->assertLifeCleared( $o );
        $o = $this->lifeOwner(); $this->lifeRelease( $o, $f ); $this->assertTrue( $this->lifeRead( $o, $f ) );
        $this->assertCandidateOnly( $this->lifeFinish( $o, $f, $this->admissionClock( 10000000399, 121 ) ), 'registered_closed' );
    }
    public function test_stale_or_aliased_initial_clock_and_late_read_start_consume() {
        $good = $this->lifeFixture(); $ref = $this->admissionClock( 200, 110 ); $tick = 200; $ref['now_ns'] =& $tick;
        foreach ( array( $ref, $this->admissionClock( 200, 99 ), $this->admissionClock( 200, 400 ), $this->admissionClock( 99, 110 ) ) as $clock ) {
            $f = $good; $f['clock'] = $clock; $o = $this->lifeOwner(); $this->assertFalse( $this->lifeRetain( $o, $f ) ); $this->assertLifeCleared( $o );
        }
        foreach ( array( $this->admissionClock( 20000000100, 120 ), $this->admissionClock( 400, 400 ), $this->admissionClock( 299, 120 ) ) as $clock ) {
            $o = $this->lifeOwner(); $this->lifeRelease( $o, $good ); $this->assertFalse( $this->lifeRead( $o, $good, $clock ) ); $this->assertLifeCleared( $o );
        }
    }
}
