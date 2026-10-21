<?php
require_once __DIR__ . '/Support/NativeQualificationObservation.php';
/** Hostile wrapper double only; never a source-verified/native proof owner. */
final class Alynt_Test_Native_Qualification_Factory_Double {
    use Alynt_Test_Native_Qualification_Observation;
    public $answer; public $error; public $reenter = false; public $calls = 0; public $arguments = array();
    private function session_source( $writer ) {
        ++$this->calls; $this->arguments[] = $writer;
        if ( $this->reenter && 1 === $this->calls ) $this->open_lifecycle_session( $writer );
        if ( $this->error ) throw $this->error; return $this->answer;
    }
}
final class NativeQualificationFactoryTransparencyTest extends PHPUnit\Framework\TestCase {
    private function call( $o, $arg ) { $m = new ReflectionMethod( $o, 'open_lifecycle_session' ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true ); return $m->invoke( $o, $arg ); }
    private function slot( $o, $name, ...$set ) { $p = new ReflectionProperty( $o, $name ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); if ( $set ) $p->setValue( $o, $set[0] ); return $p->getValue( $o ); }
    public function test_observation_failure_never_replaces_original_returns_or_skips_original_factory() {
        foreach ( array( null, false, true, 'unavailable', array( 'not' => 'a handle' ), new stdClass() ) as $answer ) {
            $o = new Alynt_Test_Native_Qualification_Factory_Double(); $o->answer = $answer;
            $this->assertSame( $answer, $this->call( $o, false ) ); $this->assertSame( 1, $o->calls );
            $this->assertTrue( $this->slot( $o, 'session_observation_failed' ) ); $this->assertSame( array(), $this->slot( $o, 'observed_sessions' ) );
            $this->assertSame( $answer, $this->call( $o, true ) ); $this->assertSame( 2, $o->calls );
            $this->assertSame( array( false, true ), $o->arguments );
        }
    }
    public function test_original_throwable_identity_survives_observation_finally() {
        foreach ( array( new LogicException( 'Synthetic private marker.' ), new Error( 'Synthetic private error.' ) ) as $error ) {
            $o = new Alynt_Test_Native_Qualification_Factory_Double(); $o->error = $error;
            try { $this->call( $o, false ); $this->fail( 'Actual factory exception required.' ); }
            catch ( Throwable $actual ) { $this->assertSame( $error, $actual ); }
            $this->assertSame( 1, $o->calls ); $this->assertFalse( $this->slot( $o, 'session_observation_busy' ) ); $this->assertSame( array(), $this->slot( $o, 'observed_sessions' ) );
        }
    }
    public function test_reentry_cannot_resurrect_an_observation_or_suppress_the_original_call() {
        $o = new Alynt_Test_Native_Qualification_Factory_Double(); $o->answer = 'exact-result'; $o->reenter = true;
        $this->assertSame( 'exact-result', $this->call( $o, false ) ); $this->assertSame( 2, $o->calls );
        $this->assertTrue( $this->slot( $o, 'session_observation_failed' ) ); $this->assertSame( array(), $this->slot( $o, 'observed_sessions' ) );
    }
    public function test_non_mysqli_accessor_objects_are_never_inspected_or_retained() {
        $poison = new class { public $calls = 0; public function __get( $name ) { ++$this->calls; throw new LogicException( 'Accessor forbidden.' ); } };
        $o = new Alynt_Test_Native_Qualification_Factory_Double(); $o->answer = true;
        $this->slot( $o, 'native_resource', 'capture' ); $this->slot( $o, 'reader', $poison );
        $this->assertTrue( $this->call( $o, false ) ); $this->assertSame( 1, $o->calls ); $this->assertSame( 0, $poison->calls );
        $this->assertSame( array(), $this->slot( $o, 'observed_sessions' ) ); $this->assertTrue( $this->slot( $o, 'session_observation_failed' ) );
    }
}
