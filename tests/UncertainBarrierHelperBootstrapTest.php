<?php
/** Actual fixed helper prefix in a fresh PHP process, not a fake HELLO or database proof. */
final class UncertainBarrierHelperBootstrapTest extends PHPUnit\Framework\TestCase {
    private function prefix() {
        $source = file_get_contents( dirname( __DIR__ ) . '/scripts/tests/uncertain-barrier-native-helper.php' );
        $this->assertSame( 1, substr_count( $source, '$helper_bytes = 0; $helper_context = null;' ) );
        $prefix = explode( '$helper_bytes = 0; $helper_context = null;', $source )[0];
        $this->assertStringNotContainsString( 'mysqli_init', $prefix );
        // Only the fixed target literal and magic directory move to the repository for portable, socket-free execution.
        $prefix = str_replace( "'C:/Users/Captain/Local Sites/plugin-tester/app/public'", var_export( dirname( __DIR__ ), true ), substr( $prefix, 5 ) );
        return str_replace( '__DIR__', var_export( dirname( __DIR__ ) . '/scripts/tests', true ), $prefix );
    }
    private function runPrefix( $prefix, $role = 'blocker', $invalid = '', $disable_tokenizer = false ) {
        $args = array( PHP_BINARY, '-n' );
        $p = proc_open( array_merge( $args, array( '-r', 'echo (int) function_exists("json_encode"), (int) function_exists("token_get_all");' ) ), array( array( 'pipe','r' ),array( 'pipe','w' ),array( 'pipe','w' ) ), $pipes );
        $this->assertIsResource( $p ); fclose( $pipes[0] ); $capabilities = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        $this->assertSame( 0, proc_close( $p ) ); $this->assertSame( '', $err ); $this->assertContains( $capabilities, array( '00','01','10','11' ) );
        foreach ( array( 'json','tokenizer' ) as $i => $extension ) if ( '0' === $capabilities[$i] ) {
            $path = rtrim( ini_get( 'extension_dir' ), '/\\' ) . DIRECTORY_SEPARATOR . $extension . '.' . PHP_SHLIB_SUFFIX;
            $this->assertFileExists( $path ); $args = array_merge( $args, array( '-d','extension=' . $path ) );
        }
        $disabled = 'mysqli_init,mysqli_connect,fsockopen,pfsockopen,stream_socket_client,stream_socket_server,file_put_contents,exec,shell_exec,system,passthru,proc_open,popen';
        if ( $disable_tokenizer ) $disabled .= ',token_get_all';
        $args = array_merge( $args, array( '-d','disable_functions=' . $disabled,'-d','display_errors=0','-d','log_errors=0' ) );
        $root = realpath( dirname( __DIR__ ) );
        $argv = array( 'source-prefix', $root, '10004', 'wp_alynt_core_probe_' . str_repeat( 'a', 12 ) . '_', str_repeat( 'b', 24 ) );
        if ( 'root' === $invalid ) $argv[1] .= '/other';
        if ( 'port' === $invalid ) $argv[2] = '10005';
        if ( 'prefix' === $invalid ) $argv[3] = 'wp_';
        if ( 'marker' === $invalid ) $argv[4] = 'invalid';
        if ( 'arity' === $invalid ) array_pop( $argv );
        $code = '$argv=' . var_export( $argv, true ) . ';putenv("MYSQL_PWD=' . ( 'environment' === $invalid ? '' : 'synthetic-not-a-credential' ) . '");';
        if ( 'role' !== $invalid ) $code .= 'define("ALYNT_UNCERTAIN_HELPER",' . var_export( $role, true ) . ');';
        $code .= $prefix . 'echo json_encode(array("continued"=>true,"root"=>ABSPATH,' .
            '"manifest"=>Alynt_Test_Uncertain_Barrier_Native_Manifest::verify(),' .
            '"dependencies"=>class_exists("Alynt_Drime_Backups_Uploader_Registration_Native_Admission",false),' .
            '"wordpress"=>function_exists("wp_verify_nonce")||class_exists("wpdb",false),' .
            '"native"=>extension_loaded("mysqli")||extension_loaded("pdo_mysql")));';
        $p = proc_open( array_merge( $args, array( '-r', $code ) ), array( array( 'pipe','r' ),array( 'pipe','w' ),array( 'pipe','w' ) ), $pipes );
        $this->assertIsResource( $p ); fclose( $pipes[0] ); $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
        return array( proc_close( $p ), $out, $err );
    }
    public function test_actual_helper_dependency_prefix_reaches_continuation_without_native_io() {
        foreach ( array( 'blocker', 'monitor' ) as $role ) {
            list( $exit, $out, $err ) = $this->runPrefix( $this->prefix(), $role );
            $this->assertSame( 0, $exit, $out . $err ); $this->assertSame( '', $err );
            $this->assertSame( array( 'continued'=>true, 'root'=>realpath( dirname( __DIR__ ) ) . '/', 'manifest'=>true, 'dependencies'=>true, 'wordpress'=>false, 'native'=>false ), json_decode( $out, true ) );
        }
    }
    public function test_actual_entry_guards_still_deny_before_dependency_loading() {
        foreach ( array( 'root','port','prefix','marker','arity','environment','role' ) as $invalid ) {
            list( $exit, $out, $err ) = $this->runPrefix( $this->prefix(), 'blocker', $invalid );
            $this->assertSame( 1, $exit, $invalid ); $this->assertSame( '', $out ); $this->assertSame( '', $err );
        }
    }
    public function test_omitted_or_reordered_bootstrap_steps_cannot_claim_continuation() {
        $prefix = $this->prefix();
        $define = "define( 'ABSPATH', \$argv[1] . '/' );";
        $manifest = "require dirname( " . var_export( dirname( __DIR__ ) . '/scripts/tests', true ) . ", 2 ) . '/tests/Support/UncertainBarrierNativeManifest.php';";
        $this->assertSame( 1, substr_count( $prefix, $define ) ); $this->assertSame( 1, substr_count( $prefix, $manifest ) );
        foreach ( array( str_replace( $define, '', $prefix ), str_replace( $manifest, '', $prefix ), str_replace( $define, '', $prefix ) . $define, str_replace( 'require_once', 'require', $prefix ) ) as $mutant ) {
            list( $exit, $out, $err ) = $this->runPrefix( $mutant );
            $this->assertSame( '', $out ); $this->assertSame( '', $err ); $this->assertContains( $exit, array( 0, 1, 255 ) );
        }
        list( $exit, $out, $err ) = $this->runPrefix( $prefix, 'blocker', '', true );
        $this->assertSame( 1, $exit ); $this->assertSame( '', $out ); $this->assertSame( '', $err );
    }
}
