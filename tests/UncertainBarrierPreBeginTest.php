<?php
/** Actual barrier_capture control flow, API doubles only; no WordPress/native IO.
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class UncertainBarrierPreBeginTest extends PHPUnit\Framework\TestCase {
    public function test_original_pre_begin_and_first_safety_read_fail_closed_without_resend() {
        require dirname( __DIR__ ) . '/scripts/tests/native-lifecycle-proof-source.php';
        define( 'DB_NAME', 'synthetic_only' );
        require_once __DIR__ . '/Support/UncertainBarrierCaptureHarness.php';
        foreach ( array( '', 'idle', 'session_1', 'budget_1', 'zero_1', 'begin', 'session_2', 'budget_2', 'session_3', 'row', 'row_throw', 'metadata' ) as $fault ) {
            $o = new Alynt_Test_Barrier_Capture_Harness( $fault ); $r = $o->run();
            $this->assertSame( '' === $fault, is_array( $r ), $fault );
            $this->assertSame( '' === $fault ? 10 : ( in_array( $fault, array( 'row','row_throw','metadata' ), true ) ? 1 : 0 ), $o->reads(), $fault );
            $begins = array_keys( $o->events, 'START TRANSACTION READ WRITE', true );
            $this->assertCount( in_array( $fault, array( 'idle','session_1','budget_1','zero_1' ), true ) ? 0 : 1, $begins, $fault );
            $this->assertCount( '' === $fault ? 1 : 0, array_keys( $o->events, 'COMMIT AND NO CHAIN NO RELEASE', true ), $fault );
            if ( in_array( $fault, array( 'budget_2','session_3' ), true ) ) $this->assertNotContains( 'row:safety', $o->events, $fault );
            if ( '' === $fault ) {
                $this->assertSame( 'barrier_released', $o->phase() );
                $this->assertSame( array( 'session_1','budget_1','zero_1','START TRANSACTION READ WRITE','begin','session_2','budget_2','session_3','row:safety','metadata' ), array_slice( $o->events, 0, 10 ) );
                $this->assertCount( 10, $r['rows'] );
            }
        }
    }
    public function test_worker_diagnostics_are_post_root_scalars_not_an_authority_seam() {
        $s = file_get_contents( dirname( __DIR__ ) . '/scripts/tests/uncertain-barrier-native-worker.php' );
        $this->assertLessThan( strpos( $s, '$barrier_progress = array(' ), strpos( $s, "finally { \$GLOBALS['alynt_core_proof']['guard_readonly'] = false; remove_filter" ) );
        $this->assertStringContainsString( "'barrier_progress' => \$barrier_progress", $s );
        $this->assertStringNotContainsString( 'barrier_progress', file_get_contents( dirname( __DIR__ ) . '/scripts/tests/uncertain-barrier-native-owner.php' ) );
    }
}
