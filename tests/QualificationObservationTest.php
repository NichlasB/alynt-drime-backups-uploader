<?php
require_once __DIR__ . '/Support/QualificationObservationFixture.php';
/** Actual private alias over synthetic state: not native, auth, storage or request proof. */
final class QualificationObservationTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Qualification_Observation_Fixture;
    /** @dataProvider histories */
    public function test_actual_source_result_is_observed_without_reclassification_or_io( $case, $history, $current ) {
        list( $o, $pin, $guards ) = $this->observationFixture( $case );
        $before = $this->admissionProperty( $o, 'native_outcome' );
        $result = $this->observe( $o, $pin, $guards ); $receipt = $this->observed( $o );
        $this->assertQualification( $result, $history, true, $current );
        $this->assertTrue( $receipt['valid'] ); $this->assertSame( 1, $receipt['entries'] ); $this->assertSame( 1, $receipt['returns'] ); $this->assertSame( 'closed_array', $receipt['kind'] ); $this->assertSame( $result, $receipt['result'] );
        foreach ( array( 'sealed', 'empty_slots', 'capture_unchanged', 'io_unchanged' ) as $k ) $this->assertTrue( $receipt[$k], $k );
        $this->assertSame( $before, $this->admissionProperty( $o, 'native_outcome' ) );
        $this->assertSame( 40, $this->admissionProperty( $o, 'protected_reads' ) ); $this->assertSame( 1, $this->admissionProperty( $o, 'writes' ) );
        $this->assertStringNotContainsString( $pin[4]['command']['command_id'], json_encode( $receipt ) ); $this->assertLessThan( 2048, strlen( json_encode( $receipt ) ) );
        $this->assertFalse( $this->observe( $o, $pin, $guards ) ); $this->assertNull( $this->observed( $o ), 'Replay must invalidate the proof projection.' );
    }
    public function histories() { return array( array( 'committed', 'committed', true ), array( 'commit_unknown', 'uncertain', false ), array( 'commit_ack_only', 'uncertain', false ), array( 'rolled_back', 'rolled_back', false ) ); }
    /** @dataProvider faults */
    public function test_bad_original_pin_clock_ids_and_seal_cannot_be_laundered( $fault ) {
        list( $o, $pin, $guards ) = $this->observationFixture( 'commit_unknown' );
        if ( 'pin' === $fault ) $pin[3]['changed'] = true;
        elseif ( 'ids' === $fault ) { $ids = $pin[1]; array_pop( $ids ); $this->observationSet( $o, 'admission_ids', $ids ); }
        elseif ( 'clock' === $fault ) $this->observationSet( $o, 'started', $pin[2]['started_ns'] - 10000000001 );
        elseif ( 'seal' === $fault ) $this->observationSet( $o, 'native_outcome_closed', false );
        elseif ( 'slot' === $fault ) $this->observationSet( $o, 'physical', array( 'remaining' => true ) );
        elseif ( 'reentry' === $fault ) $this->observationSet( $o, 'admission_join_interrupted', true );
        elseif ( 'raw' === $fault ) { $c = $this->admissionProperty( $o, 'native_outcome' ); $c['raw_returns']['commit'] = true; $this->observationSet( $o, 'native_outcome', $c ); }
        $this->assertFalse( $this->observe( $o, $pin, $guards ) ); $r = $this->observed( $o );
        $this->assertSame( 'false', $r['kind'] ); $this->assertNull( $r['result'] ); $this->assertSame( 1, $r['entries'] );
    }
    public function faults() { return array_map( function ( $s ) { return array( $s ); }, array( 'pin', 'ids', 'clock', 'seal', 'slot', 'reentry', 'raw' ) ); }
    public function test_unchanged_cleanup_and_erasure_leave_only_a_consumable_test_projection() {
        list( $o, $pin, $guards ) = $this->observationFixture( 'commit_unknown' ); $this->assertIsArray( $this->observe( $o, $pin, $guards ) );
        $r = $this->observed( $o );
        $this->admissionInvoke( $o, 'finish' ); $this->admissionInvoke( $o, 'clear_native_outcome' ); $this->admissionInvoke( $o, 'clear_admission_origin' );
        foreach ( array( 'reader', 'native_outcome_writer', 'native_outcome', 'physical', 'read_physical', 'admission_origin', 'admission_clock' ) as $k ) $this->assertNull( $this->admissionProperty( $o, $k ) );
        $this->assertSame( array(), $this->admissionProperty( $o, 'admission_ids' ) ); $this->assertSame( $r, $this->observed( $o ) );
        $this->observationSet( $o, 'qualification_observation', null ); $this->assertNull( $this->observed( $o ) );
        $this->assertFalse( $this->observe( $o, $pin, $guards ) ); $this->assertNull( $this->observed( $o ) );
    }
    public function test_public_surface_and_privacy_never_expose_the_private_projection() {
        list( $o, $pin, $guards ) = $this->observationFixture(); $this->observe( $o, $pin, $guards );
        $r = new ReflectionClass( $o ); $names = array_map( function ( $m ) { return $m->name; }, $r->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $names );
        $this->assertSame( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ), $names );
        $this->assertSame( $o->disposition(), $o->__debugInfo() ); $this->assertTrue( $r->isFinal() ); $this->assertTrue( $r->getConstructor()->isPrivate() ); $this->assertFalse( $o::create( 'provider' ) );
        $this->expectException( LogicException::class ); serialize( $o );
    }
    public function test_actual_early_root_refusal_has_zero_qualifier_entries_and_protected_io() {
        $this->assertTrue( Alynt_Test_Qualification_Source_Manifest::verify() ); $o = Alynt_Test_Qualification_Observation_Owner::create();
        $this->assertFalse( $this->admissionInvoke( $o, 'admit_retained', 'POST', array() ) );
        $this->assertNull( $this->observed( $o ) ); $this->assertFalse( $this->admissionProperty( $o, 'qualification_observation_spent' ) );
        $this->assertSame( 0, $this->admissionProperty( $o, 'protected_reads' ) ); $this->assertSame( 0, $this->admissionProperty( $o, 'writes' ) );
        $this->assertTrue( $this->admissionProperty( $o, 'admission_qualification_spent' ), 'Finally-spent is not proof that qualifier ran.' );
        foreach ( array( 'native_outcome', 'admission_origin', 'admission_clock', 'before', 'proposal', 'context', 'after', 'recorded_at' ) as $k ) $this->assertNull( $this->admissionProperty( $o, $k ) );
    }
}
