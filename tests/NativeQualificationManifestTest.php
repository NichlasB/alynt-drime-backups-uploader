<?php
/** No WordPress, native session, listener or private control channel. */
final class NativeQualificationManifestTest extends PHPUnit\Framework\TestCase {
    public function test_fixed_source_and_two_passive_aliases_are_closed_and_socket_free() {
        $args = array( PHP_BINARY, '-n' );
        $p = proc_open( array_merge( $args, array( '-r', 'echo function_exists("json_encode") ? "yes" : "no";' ) ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
        $this->assertIsResource( $p ); fclose( $pipes[0] ); $json = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( 0, proc_close( $p ) ); $this->assertSame( '', $err ); $this->assertContains( $json, array( 'yes', 'no' ) );
        if ( 'no' === $json ) { $path = rtrim( ini_get( 'extension_dir' ), '/\\' ) . DIRECTORY_SEPARATOR . 'json.' . PHP_SHLIB_SUFFIX; $this->assertFileExists( $path ); $args = array_merge( $args, array( '-d', 'extension=' . $path ) ); }
        $p = proc_open( array_merge( $args, array( dirname( __DIR__ ) . '/scripts/tests/native-qualification-preflight.php' ) ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
        $this->assertIsResource( $p ); fclose( $pipes[0] ); $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( 0, proc_close( $p ), $out ); $this->assertSame( '', $err );
        $this->assertSame( array( 'passed' => true, 'two_passive_aliases_only' => true, 'final_entry_unavailable' => true, 'wordpress_or_storage_executed' => false ), json_decode( $out, true ) );
    }
    /** @dataProvider compositionCases */
    public function test_named_alias_lookup_preserves_every_composition_check( $mode, $expected ) {
        // Fixed local verifier variants only: no owner/source edits, WordPress or native execution.
        $code = <<<'PHP'
define('ABSPATH', getcwd() . '/');
require 'scripts/tests/native-lifecycle-proof-source.php';
$code = file_get_contents('tests/Support/NativeQualificationManifest.php');
$code = str_replace('__DIR__', var_export(getcwd() . '/tests/Support', true), $code);
$mode = $argv[1];
$changes = array(
    'missing_alias' => array("'session_source' => 'Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_Methods::open_lifecycle_session'", "'missing_source' => 'Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_Methods::open_lifecycle_session'"),
    'wrong_target' => array("'session_source' => 'Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_Methods::open_lifecycle_session'", "'session_source' => 'Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_Methods::qualify_admission_outcome'"),
    'source_row' => array("foreach ( array( 'qualify_admission_outcome' =>", "\$b['session_source'][1]++; foreach ( array( 'qualify_admission_outcome' =>"),
    'signature' => array("foreach ( array( '__construct', 'create', 'native_entry_available' )", "\$b['create'][6][] = 'unexpected'; foreach ( array( '__construct', 'create', 'native_entry_available' )"),
    'default' => array('$defaults = $proof->getDefaultProperties();', '$defaults = $proof->getDefaultProperties(); $defaults["session_observation_busy"] = true;'),
    'extra_method' => array('ksort( $a ); ksort( $b );', '$b["unexpected_write"] = $b["create"]; ksort( $a ); ksort( $b );'),
    'entry' => array('array( $proof->getName(), false )', 'array( $proof->getName(), true )'),
);
if ('baseline' !== $mode) {
    if (!isset($changes[$mode]) || 1 !== substr_count($code, $changes[$mode][0])) exit(2);
    $code = str_replace($changes[$mode][0], $changes[$mode][1], $code);
}
eval(substr($code, 5));
echo Alynt_Test_Native_Qualification_Manifest::composition() ? 'accepted' : 'refused';
PHP;
        $p = proc_open( array( PHP_BINARY, '-n', '-r', $code, $mode ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes, dirname( __DIR__ ) );
        $this->assertIsResource( $p ); fclose( $pipes[0] ); $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        // PHP 7.4 Linux may build JSON as a shared extension; reuse the existing isolated worker's probe.
        $status = proc_close( $p );
        if ( 0 !== $status && false !== strpos( $out . $err, 'undefined function json_decode' ) ) {
            $json = rtrim( ini_get( 'extension_dir' ), '/\\' ) . DIRECTORY_SEPARATOR . 'json.' . PHP_SHLIB_SUFFIX;
            $this->assertFileExists( $json );
            $p = proc_open( array( PHP_BINARY, '-n', '-d', 'extension=' . $json, '-r', $code, $mode ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes, dirname( __DIR__ ) );
            $this->assertIsResource( $p ); fclose( $pipes[0] ); $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] ); $status = proc_close( $p );
        }
        $this->assertSame( 0, $status, $out . $err ); $this->assertSame( '', $err );
        $this->assertSame( $expected ? 'accepted' : 'refused', $out );
    }
    public function compositionCases() {
        $cases = array( 'baseline' => array( 'baseline', true ) );
        foreach ( array( 'missing_alias', 'wrong_target', 'source_row', 'signature', 'default', 'extra_method', 'entry' ) as $mode ) $cases[$mode] = array( $mode, false );
        return $cases;
    }
}
