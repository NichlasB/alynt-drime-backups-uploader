<?php
require_once __DIR__ . '/Support/SelectedQualificationFixture.php';
require_once __DIR__ . '/Support/SelectedQualificationNativeWorker.php';
/**
 * Synthetic owner state only; not genuine guards, finally or native uncertainty.
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class SelectedQualificationNativeWorkerTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Selected_Qualification_Fixture;
    private function nativeFixture() {
        list( $unused, $owner, $actual, $receipt ) = $this->selectedFixture();
        $w = Alynt_Test_Selected_Qualification_Native_Worker::create();
        $this->assertInstanceOf( Alynt_Test_Selected_Qualification_Native_Worker::class, $w );
        $this->observationSet( $w, 'owner', $owner );
        $this->observationSet( $owner, 'phase', 'poisoned' );
        $this->observationSet( $w, 'gates', array_fill( 0, 8, 'synthetic' ) );
        $this->observationSet( $w, 'creates', 1 );
        return array( $w, $owner, $receipt );
    }
    public function test_false_root_is_required_without_injecting_or_reclassifying_the_actual_receipt() {
        list( $w, $o, $receipt ) = $this->nativeFixture();
        $this->assertTrue( $this->admissionInvoke( $w, 'require_root', false ) );
        $this->assertSame( $receipt, $this->admissionInvoke( $w, 'take' ) );
        $this->assertNull( $this->observed( $o ) ); $this->assertFalse( $this->admissionInvoke( $w, 'take' ) );
        $this->assertFalse( $receipt['result']['current_acceptance_claimed'] );
        $this->assertSame( 'uncertain', $receipt['result']['historical_outcome'] );
    }
    /** @dataProvider refusalFaults */
    public function test_expected_refusal_does_not_hide_callback_or_guard_failure( $field, $value, $root ) {
        list( $w, $o ) = $this->nativeFixture();
        if ( 'callback_failure' === $field ) $value = new RuntimeException( 'owned_callback' );
        if ( 'phase' === $field ) $this->observationSet( $o, $field, $value );
        else $this->observationSet( $w, $field, $value );
        $this->expectException( RuntimeException::class ); $this->admissionInvoke( $w, 'require_root', $root );
    }
    public function refusalFaults() {
        return array( array( 'creates', 1, true ), array( 'creates', 1, null ), array( 'creates', 0, false ), array( 'creates', 2, false ), array( 'gates', array_fill( 0, 7, 'x' ), false ), array( 'gates', array_fill( 0, 10, 'x' ), false ), array( 'failed', true, false ), array( 'callback_failure', 'synthetic', false ), array( 'phase', 'idle', false ) );
    }
    public function test_take_consumes_before_validation_and_does_not_repair_original_slots() {
        list( $w, $o ) = $this->nativeFixture(); $handle = new stdClass(); $this->observationSet( $o, 'reader', $handle );
        $this->assertFalse( $this->admissionInvoke( $w, 'take' ) ); $this->assertNull( $this->observed( $o ) );
        $this->assertSame( $handle, $this->admissionProperty( $o, 'reader' ) );
        $this->assertFalse( $this->admissionInvoke( $w, 'take' ) );
    }
    public function test_fixed_factory_exports_no_operation_or_handle_and_old_entry_remains_closed() {
        $this->assertFalse( Alynt_Test_Selected_Qualification_Native_Worker::create( new stdClass() ) );
        $w = Alynt_Test_Selected_Qualification_Native_Worker::create(); $r = new ReflectionClass( $w );
        $this->assertTrue( $r->isFinal() ); $this->assertTrue( $r->getConstructor()->isPrivate() );
        foreach ( array( 'enter', 'run', 'take', 'probe', 'slot' ) as $name ) $this->assertTrue( $r->getMethod( $name )->isPrivate() );
        $this->assertTrue( Alynt_Test_Qualification_Native_Manifest::verify() );
        $old = Alynt_Test_Selected_Qualification_Worker::create( $this->workerContext() );
        $this->assertFalse( $this->admissionInvoke( $old, 'enter', 'POST', array() ) );
        $this->expectException( LogicException::class ); serialize( $w );
    }
}
