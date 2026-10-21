<?php
require_once __DIR__ . '/Support/SelectedQualificationFixture.php';
/**
 * Bound full-suite allocator retention from repeated complete source-manifest inspection.
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class SelectedQualificationWorkerTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Selected_Qualification_Fixture;
    public function test_actual_uncertain_projection_survives_false_root_claim_and_is_consumed_without_authority() {
        list( $w, $o, $actual, $receipt ) = $this->selectedFixture();
        $this->assertSame( 'uncertain', $actual['historical_outcome'] ); $this->assertFalse( $actual['current_acceptance_claimed'] );
        $line = $this->admissionInvoke( $w, 'take' ); $this->assertIsString( $line ); $m = json_decode( $line, true );
        $this->assertFalse( $m['root_returned'] ); $this->assertSame( $actual, $m['observation']['result'] ); $this->assertSame( $receipt, $m['observation'] );
        $this->assertSame( 8, $m['pre_guards'] ); $this->assertSame( 0, $m['post_guards'] ); $this->assertSame( 40, $m['protected_reads'] ); $this->assertSame( 1, $m['writes'] );
        foreach ( array( 'authenticated', 'closure_proven', 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $k ) $this->assertFalse( $m['observation']['result'][$k] );
        $this->assertNull( $this->observed( $o ) ); $this->assertNull( $this->admissionProperty( $w, 'owner' ) ); $this->assertNull( $this->admissionProperty( $w, 'context' ) );
        $this->assertFalse( $this->admissionInvoke( $w, 'take' ) ); $this->assertFalse( $this->admissionInvoke( $w, 'enter' ) );
        $this->assertLessThanOrEqual( 4096, strlen( $line ) );
    }
    /** @dataProvider workerFaults */
    public function test_each_missing_guard_claim_independently_denies_and_clears( $key, $value ) {
        list( $w, $o ) = $this->selectedFixture(); $this->observationSet( $w, $key, $value );
        $this->assertFalse( $this->admissionInvoke( $w, 'take' ) ); $this->assertNull( $this->observed( $o ) );
        $this->assertNull( $this->admissionProperty( $w, 'owner' ) ); $this->assertFalse( $this->admissionInvoke( $w, 'take' ) );
    }
    public function workerFaults() {
        return array( array( 'root_returned', true ), array( 'root_returned', null ), array( 'callback_clean', false ), array( 'nonce_creations', 0 ), array( 'nonce_creations', 2 ), array( 'gates', array() ), array( 'gates', array_fill( 0, 8, 'small' ) ), array( 'gates', array_fill( 0, 10, 'small' ) ) );
    }
    /** @dataProvider retainedSlots */
    public function test_retained_original_state_is_not_repaired_to_allow_extraction( $key, $value ) {
        list( $w, $o ) = $this->selectedFixture(); $this->observationSet( $o, $key, $value );
        $this->assertFalse( $this->admissionInvoke( $w, 'take' ) ); $this->assertSame( $value, $this->admissionProperty( $o, $key ) ); $this->assertNull( $this->observed( $o ) );
    }
    public function retainedSlots() {
        return array( array( 'reader', new stdClass() ), array( 'native_outcome_writer', new stdClass() ), array( 'native_outcome', array() ), array( 'admission_origin', array() ), array( 'admission_ids', array( 'secret' ) ), array( 'native_outcome_tick', 1 ), array( 'active', true ), array( 'consumed', false ), array( 'admission_qualification_spent', false ), array( 'qualification_observation_interrupted', true ), array( 'native_cleanup_done', false ), array( 'protected_reads', 39 ), array( 'writes', 0 ) );
    }
    public function test_unavailable_missing_fabricated_or_non_uncertain_results_cannot_satisfy_the_positive() {
        foreach ( array( 'committed', 'rolled_back' ) as $case ) { list( $w, $o ) = $this->selectedFixture( $case ); $this->assertFalse( $this->admissionInvoke( $w, 'take' ) ); $this->assertNull( $this->observed( $o ) ); }
        foreach ( array( null, false, array( 'valid' => true ) ) as $bad ) { list( $w, $o ) = $this->selectedFixture(); $this->observationSet( $o, 'qualification_observation', $bad ); $this->assertFalse( $this->admissionInvoke( $w, 'take' ) ); $this->assertNull( $this->observed( $o ) ); }
        list( $w, $o ) = $this->selectedFixture(); $this->assertFalse( $this->admissionInvoke( $w, 'take', 'provider' ) ); $this->assertNull( $this->observed( $o ) );
    }
    public function test_dormant_entry_never_invokes_original_root_or_observer_and_does_not_rearm() {
        $w = Alynt_Test_Selected_Qualification_Worker::create( $this->workerContext() ); $o = $this->admissionProperty( $w, 'owner' );
        $this->assertFalse( $this->admissionInvoke( $w, 'enter', 'POST', array() ) );
        $this->assertFalse( $this->admissionProperty( $o, 'attempted' ) ); $this->assertFalse( $this->admissionProperty( $o, 'admission_qualification_spent' ) );
        $this->assertSame( 0, $this->admissionProperty( $o, 'protected_reads' ) ); $this->assertSame( 0, $this->admissionProperty( $o, 'writes' ) ); $this->assertNull( $this->observed( $o ) );
        $this->assertNull( $this->admissionProperty( $w, 'owner' ) ); $this->assertFalse( $this->admissionInvoke( $w, 'take' ) );
    }
    public function test_fixed_factory_manifest_and_privacy_do_not_export_a_native_handle() {
        $this->assertTrue( Alynt_Test_Selected_Qualification_Manifest::verify() ); $this->assertTrue( Alynt_Test_Qualification_Native_Manifest::verify() );
        foreach ( array( null, new stdClass(), array_merge( $this->workerContext(), array( 'owner' => new stdClass() ) ) ) as $bad ) $this->assertFalse( Alynt_Test_Selected_Qualification_Worker::create( $bad ) );
        $this->assertFalse( Alynt_Test_Selected_Qualification_Worker::create( $this->workerContext(), 'provider' ) );
        $w = Alynt_Test_Selected_Qualification_Worker::create( $this->workerContext() ); $r = new ReflectionClass( $w );
        $names = array_map( static function ( $m ) { return $m->name; }, $r->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $names );
        $this->assertSame( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ), $names );
        $this->assertTrue( $r->isFinal() ); $this->assertTrue( $r->getConstructor()->isPrivate() ); $this->assertSame( $w->disposition(), $w->__debugInfo() );
        $this->expectException( LogicException::class ); serialize( $w );
    }
}
