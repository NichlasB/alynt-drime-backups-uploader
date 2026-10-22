<?php
/** Source-backed failure-stage protection, not a physical lock/wait proof.
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class Alynt_Synthetic_Helper_Database_Exception extends RuntimeException {}
final class UncertainBarrierHelperDiagnosticTest extends PHPUnit\Framework\TestCase {
    public function test_actual_fixed_bodies_project_each_injected_stage_without_private_text() {
        foreach ( array( 'blocker' => array( '', 'lock_policy', 'lock_prepare', 'lock_execute', 'lock_rows', 'lock_counters_after', 'rollback' ), 'monitor' => array( '', 'watch_policy', 'watch_prepare', 'watch_execute', 'watch_identity', 'watch_counters' ) ) as $role => $faults ) foreach ( $faults as $fault ) {
            $p = proc_open( array( PHP_BINARY, __DIR__ . '/Support/UncertainHelperDiagnosticWorker.php', $role, $fault ), array( array( 'pipe','r' ),array( 'pipe','w' ),array( 'pipe','w' ) ), $pipes );
            $this->assertIsResource( $p ); fclose( $pipes[0] ); $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
            $this->assertSame( 0, proc_close( $p ), $out . $err ); $this->assertSame( '', $err ); $this->assertStringNotContainsString( 'RAW_PRIVATE_SQL_MARKER', $out );
            $m = json_decode( $out, true ); $this->assertIsArray( $m ); $this->assertSame( '' === $fault, $m['body_completed'] );
            if ( '' === $fault ) { $this->assertNull( $m['failure'] ); $this->assertContains( 'blocker' === $role ? 'rollback_ack' : 'finish_request', $m['events'] ); }
            else { $this->assertSame( $fault, $m['failure']['stage'] ); $this->assertSame( array( 'stage','reason','code' ), array_keys( $m['failure'] ) ); $this->assertSame( 0, $m['failure']['code'] ); }
        }
    }
    public function test_projection_never_exports_unknown_messages_or_arbitrary_codes() {
        require_once dirname( __DIR__ ) . '/scripts/tests/uncertain-barrier-helper-diagnostics.php';
        $this->assertSame( array( 'stage'=>'lock_execute','reason'=>'unknown','code'=>0 ), alynt_uncertain_helper_failure( 'blocker', 'lock_execute', new RuntimeException( 'private-password-SQL', 1205 ) ) );
        $this->assertFalse( alynt_uncertain_helper_failure( 'blocker', 'watch_execute', new RuntimeException() ) );
        $this->assertFalse( alynt_uncertain_helper_failure( 'other', 'connect', new RuntimeException() ) );
        $policy = json_decode( file_get_contents( dirname( __DIR__ ) . '/scripts/tests/uncertain-barrier-helper-diagnostics.json' ), true );
        foreach ( $policy['reasons'] as $reason ) if ( ! in_array( $reason, array( 'database','unknown' ), true ) ) $this->assertSame( $reason, alynt_uncertain_helper_failure( 'monitor', 'watch_execute', new RuntimeException( $reason ) )['reason'] );
        if ( ! class_exists( 'mysqli_sql_exception' ) ) class_alias( Alynt_Synthetic_Helper_Database_Exception::class, 'mysqli_sql_exception' ); // Synthetic exception type only, never native IO.
        foreach ( array( 1205, 1213, 987654 ) as $code ) $this->assertSame( array( 'stage'=>'watch_execute','reason'=>'database','code'=>987654 === $code ? 0 : $code ), alynt_uncertain_helper_failure( 'monitor', 'watch_execute', new mysqli_sql_exception( 'private-query', $code ) ) );
    }
    public function test_exact_catch_wiring_and_stage_assignments_remain_in_fixed_source() {
        $base = dirname( __DIR__ ) . '/scripts/tests/'; $source = file_get_contents( $base . 'uncertain-barrier-native-helper.php' );
        $this->assertStringContainsString( 'alynt_uncertain_helper_failure( ALYNT_UNCERTAIN_HELPER, $helper_stage, $error )', $source );
        $this->assertStringContainsString( "'failure' => \$failure", $source );
        foreach ( array( 'blocker','monitor' ) as $role ) {
            $body = file_get_contents( $base . 'uncertain-barrier-native-' . $role . '-body.php' );
            $this->assertStringContainsString( '$helper_stage = ' . var_export( 'blocker' === $role ? 'lock_prepare' : 'watch_prepare', true ) . '; $stmt = $db->prepare', $body );
        }
    }
}
