<?php
/** Pure policy / CLI boundary tests. No WordPress, MySQL or fixture execution. */
final class BootstrapContainmentPolicyTest extends PHPUnit\Framework\TestCase {
    public function test_only_the_exact_owned_safe_projection_can_pass() {
        require_once dirname( __DIR__ ) . '/scripts/tests/bootstrap-containment-policy.php';
        $prefix = 'wp_alynt_bootstrap_probe_123456abcdef_';
        $safe = "SELECT option_value FROM {$prefix}options WHERE option_name = 'proof_safe' LIMIT 1";
        $this->assertSame( 'safe', alynt_bootstrap_query_kind( $safe, $prefix ) );
        foreach ( array( '', 'wp_', $prefix . 'x', str_replace( '123456', 'ABCDEF', $prefix ), null, array() ) as $bad ) $this->assertSame( 'unknown', alynt_bootstrap_query_kind( $safe, $bad ) );
        foreach ( array( str_replace( 'proof_safe', 'siteurl', $safe ), $safe . ';', $safe . ' UNION SELECT option_value FROM wp_options', str_replace( 'option_value', '*', $safe ), str_replace( $prefix, 'wp_', $safe ), strtolower( $safe ), '', str_repeat( 'x', 4097 ), null, array() ) as $bad ) $this->assertNotSame( 'safe', alynt_bootstrap_query_kind( $bad, $prefix ) );
        $this->assertSame( 'bulk_all', alynt_bootstrap_query_kind( "SELECT option_name, option_value FROM {$prefix}options", $prefix ) );
        $this->assertSame( 'bulk_autoload', alynt_bootstrap_query_kind( "SELECT option_name, option_value FROM {$prefix}options WHERE autoload IN ( 'yes', 'auto-on' )", $prefix ) );
        foreach ( array( 'alynt_drime_backups_settings', 'alynt_drime_backups_schedule_safety_state' ) as $name ) $this->assertSame( 'protected_key', alynt_bootstrap_query_kind( "SELECT option_value FROM {$prefix}options WHERE option_name = '{$name}'", $prefix ) );
    }
    public function test_worker_without_operator_context_refuses_before_core_or_database() {
        $p = proc_open( array( PHP_BINARY, '-n', dirname( __DIR__ ) . '/scripts/tests/bootstrap-containment-worker.php' ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
        $this->assertIsResource( $p ); fclose( $pipes[0] ); $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( 1, proc_close( $p ) ); $this->assertSame( '', $out ); $this->assertSame( '', $err );
    }
}
