<?php
require_once __DIR__ . '/Support/UncertainBarrierOwner.php';
/** Hostile original-call double, not a source-verified owner or native result. */
final class Alynt_Test_Uncertain_Barrier_Return_Double {
    use Alynt_Test_Uncertain_Barrier_Observation;
    public $answer; public $error; public $calls = 0; public $arguments; public $nested = false;
    private function terminal_source( $method, $body ) {
        ++$this->calls; $this->arguments = func_get_args();
        if ( $this->nested && 1 === $this->calls ) $this->lifecycle_root( $method, $body );
        if ( $this->error ) throw $this->error;
        return $this->answer;
    }
}
final class UncertainBarrierTransparencyTest extends PHPUnit\Framework\TestCase {
    private function call( $o, ...$args ) { $m = new ReflectionMethod( $o, 'lifecycle_root' ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true ); return $m->invokeArgs( $o, $args ); }
    private function state( $o, $k ) { $p = new ReflectionProperty( $o, $k ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); return $p->getValue( $o ); }
    public function test_failed_projection_preserves_returns_arguments_and_original_call_count() {
        foreach ( array( false, true, null, new stdClass(), array( 'unexpected' => 'data' ) ) as $answer ) {
            $o = new Alynt_Test_Uncertain_Barrier_Return_Double(); $o->answer = $answer;
            $this->assertSame( $answer, $this->call( $o, 'POST', array(), 'extra' ) ); $this->assertSame( 1, $o->calls );
            $this->assertSame( array( 'POST', array(), 'extra' ), $o->arguments ); $this->assertFalse( $this->state( $o, 'terminal_busy' ) );
            $this->assertFalse( $this->state( $o, 'terminal_event' ) );
        }
    }
    public function test_original_throwable_identity_survives_projection_failure_and_reentry() {
        foreach ( array( new LogicException( 'Private error.' ), new Error( 'Private error.' ) ) as $error ) {
            $o = new Alynt_Test_Uncertain_Barrier_Return_Double(); $o->error = $error;
            try { $this->call( $o, 'POST', array() ); $this->fail( 'Original throw required.' ); } catch ( Throwable $actual ) { $this->assertSame( $error, $actual ); }
            $this->assertSame( 1, $o->calls ); $this->assertFalse( $this->state( $o, 'terminal_busy' ) );
        }
        $o = new Alynt_Test_Uncertain_Barrier_Return_Double(); $o->answer = false; $o->nested = true;
        $this->assertFalse( $this->call( $o, 'POST', array() ) ); $this->assertSame( 2, $o->calls );
        $this->assertTrue( $this->state( $o, 'terminal_failed' ) ); $this->assertNull( $this->state( $o, 'terminal_event' ) );
    }
}
