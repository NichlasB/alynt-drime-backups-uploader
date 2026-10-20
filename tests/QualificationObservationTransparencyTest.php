<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/QualificationObservationWrapper.php';
require_once dirname( __DIR__ ) . '/includes/class-registration-admission-outcome.php';
/** Wrapper-only hostile double. Never passes/uses the fixed native source manifest. */
final class Alynt_Test_Qualification_Transparency_Double {
    use Alynt_Test_Qualification_Observation_Wrapper;
    private $native_cleanup_done = true;
    private $native_outcome_closed = true;
    private $reader;
    private $native_outcome_writer;
    private $physical;
    private $read_physical;
    private $native_outcome;
    private $protected_reads = 0;
    private $writes = 0;
    public $answer;
    public $error;
    public $reenter = false;
    public $calls = 0;
    private function qualification_source( $pin, $guards ) {
        ++$this->calls;
        if ( $this->reenter && 1 === $this->calls ) $this->qualify_admission_outcome( $pin, $guards );
        if ( $this->error ) throw $this->error;
        return $this->answer;
    }
}
final class QualificationObservationTransparencyTest extends TestCase {
    private function call( $o ) { $m = new ReflectionMethod( $o, 'qualify_admission_outcome' ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true ); return $m->invoke( $o, null, null ); }
    private function property( $o, $name, ...$set ) { $p = new ReflectionProperty( $o, $name ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); if ( $set ) $p->setValue( $o, $set[0] ); return $p->getValue( $o ); }
    public function test_unrecognized_return_is_identical_and_never_sanitized_into_a_success() {
        foreach ( array( null, false, true, 'unavailable', array( 'unknown' => true ), new stdClass() ) as $answer ) {
            $o = new Alynt_Test_Qualification_Transparency_Double(); $o->answer = $answer;
            $this->assertSame( $answer, $this->call( $o ) ); $this->assertSame( 1, $o->calls );
            $r = $this->property( $o, 'qualification_observation' ); $this->assertFalse( $r['valid'] ); $this->assertNull( $r['result'] );
        }
    }
    public function test_actual_throwable_identity_is_preserved_and_never_becomes_uncertainty() {
        $o = new Alynt_Test_Qualification_Transparency_Double(); $o->error = new LogicException( 'Private test marker; must not escape through observation.' );
        try { $this->call( $o ); $this->fail( 'Expected actual alias exception.' ); }
        catch ( LogicException $e ) { $this->assertSame( $o->error, $e ); }
        $r = $this->property( $o, 'qualification_observation' ); $this->assertSame( 'exception', $r['kind'] ); $this->assertSame( 0, $r['returns'] ); $this->assertNull( $r['result'] );
        $this->assertStringNotContainsString( $o->error->getMessage(), json_encode( $r ) );
    }
    public function test_reentry_cannot_be_resurrected_by_the_outer_finally() {
        $o = new Alynt_Test_Qualification_Transparency_Double(); $o->answer = false; $o->reenter = true;
        $this->assertFalse( $this->call( $o ) ); $this->assertSame( 2, $o->calls );
        $this->assertNull( $this->property( $o, 'qualification_observation' ) );
        $this->assertTrue( $this->property( $o, 'qualification_observation_interrupted' ) );
    }
    public function test_malformed_capture_cannot_execute_json_serialization_callbacks() {
        $o = new Alynt_Test_Qualification_Transparency_Double(); $o->answer = false;
        $poison = new class implements JsonSerializable {
            #[\ReturnTypeWillChange]
            public function jsonSerialize() { throw new LogicException( 'Object must never be serialized.' ); }
        };
        $this->property( $o, 'native_outcome', $poison );
        $this->assertFalse( $this->call( $o ) ); $this->assertSame( 1, $o->calls ); $this->assertFalse( $this->property( $o, 'qualification_observation' )['valid'] );
    }
}
