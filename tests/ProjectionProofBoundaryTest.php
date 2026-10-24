<?php
/** Pure envelopes and rejected process entry only; never DB/native execution in CI. */
final class ProjectionProofBoundaryTest extends PHPUnit\Framework\TestCase {
    /** @dataProvider proofFiles */
    public function test_all_native_php_sources_parse_without_execution_on_each_ci_php( $file ) {
        $p = proc_open( array( PHP_BINARY, '-n', '-l', dirname( __DIR__ ) . '/scripts/tests/' . $file ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
        $this->assertIsResource( $p ); fclose( $pipes[0] ); $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( 0, proc_close( $p ) ); $this->assertSame( '', $err ); $this->assertStringContainsString( 'No syntax errors detected', $out );
    }
    public function proofFiles() { return array( array( 'projection-proof-contract.php' ), array( 'projection-proof-worker.php' ), array( 'projection-core-bootstrap.php' ) ); }
    public function test_private_envelope_cannot_supply_target_account_or_sql_authority() {
        require_once dirname( __DIR__ ) . '/scripts/tests/projection-proof-contract.php';
        $valid = array( 'seed' => str_repeat( 'a', 12 ), 'marker' => str_repeat( 'b', 24 ), 'password' => str_repeat( 'c', 64 ), 'mode' => 'capability' );
        $context = alynt_projection_context( $valid );
        $this->assertSame( 'alynt_ro_aaaaaaaaaaaa_i', $context['user'] ); $this->assertSame( 'wp_alynt_ro_aaaaaaaaaaaa_', $context['prefix'] );
        foreach ( array_keys( $valid ) as $key ) { $bad = $valid; unset( $bad[$key] ); $this->assertFalse( alynt_projection_context( $bad ) ); $bad = $valid; $bad[$key] = array(); $this->assertFalse( alynt_projection_context( $bad ) ); }
        foreach ( array( 'user' => 'root', 'sql' => 'SELECT 1', 'host' => 'localhost', 'extra' => true ) as $key => $value ) $this->assertFalse( alynt_projection_context( $valid + array( $key => $value ) ) );
        foreach ( array( '', 'ROOT', str_repeat( 'a', 13 ), "aaaaaaaaaaaa\n" ) as $seed ) $this->assertFalse( alynt_projection_context( array_merge( $valid, array( 'seed' => $seed ) ) ) );
        foreach ( array( 'unsafe', 'autoload', 'fallback' ) as $mode ) $this->assertIsArray( alynt_projection_context( array_merge( $valid, array( 'mode' => $mode ) ) ) );
        $this->assertSame( 0, alynt_projection_canaries( array( 'blogname' => 'Owned projection' ) ) );
        $this->assertSame( 1, alynt_projection_canaries( array( 'alias' => 'owned-projection-canary-settings' ) ) );
        $this->assertSame( 2, alynt_projection_canaries( array( 'alynt_drime_backups_settings' => '', 'alynt_drime_backups_schedule_safety_state' => '' ) ) );
    }
    public function test_worker_refuses_missing_flag_and_empty_private_input_before_database() {
        foreach ( array( array(), array( '--owned-projection-worker' ) ) as $args ) {
            $p = proc_open( array_merge( array( PHP_BINARY, '-n', dirname( __DIR__ ) . '/scripts/tests/projection-proof-worker.php' ), $args ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
            $this->assertIsResource( $p ); fclose( $pipes[0] ); $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
            $this->assertSame( 1, proc_close( $p ) ); $this->assertSame( '', $err );
            if ( $args ) $this->assertFalse( json_decode( $out, true )['passed'] ); else $this->assertSame( '', $out );
        }
    }
}
