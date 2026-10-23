<?php
/** Source-backed private branch tests. Isolated core/IO doubles, never WordPress. */
final class ClosedEntryOwnerApiIoTest extends PHPUnit\Framework\TestCase {
    private static $php_args;
    private function worker( $case, $mutation = '' ) {
        if ( null === self::$php_args ) {
            self::$php_args = array( PHP_BINARY, '-n' );
            $p = proc_open( array_merge( self::$php_args, array( '-r', 'echo function_exists("json_encode") ? "yes" : "no";' ) ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
            $this->assertIsResource( $p ); fclose( $pipes[0] ); $json = stream_get_contents( $pipes[1] ); $error = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
            $this->assertSame( 0, proc_close( $p ) ); $this->assertSame( '', $error ); $this->assertContains( $json, array( 'yes', 'no' ) );
            if ( 'no' === $json ) {
                $path = rtrim( ini_get( 'extension_dir' ), '/\\' ) . DIRECTORY_SEPARATOR . 'json.' . PHP_SHLIB_SUFFIX;
                $this->assertFileExists( $path ); self::$php_args = array_merge( self::$php_args, array( '-d', 'extension=' . $path ) );
            }
        }
        $p = proc_open( array_merge( self::$php_args, array( __DIR__ . '/Support/ClosedEntryApiWorker.php', $case, $mutation ) ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
        $this->assertIsResource( $p ); fclose( $pipes[0] ); $out = stream_get_contents( $pipes[1] ); $error = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] ); $exit = proc_close( $p );
        $this->assertSame( '', $error, $out ); $result = json_decode( $out, true ); $this->assertIsArray( $result, $out );
        $this->assertTrue( $result['loaded'], 'Bootstrap/parse failure never counts as a killed control.' );
        $this->assertSame( $case, $result['case'] ); $this->assertGreaterThan( 10, $result['checks'] );
        foreach ( array( 'authentication', 'storage', 'native_execution', 'sapi_proof' ) as $key ) $this->assertFalse( $result[$key] );
        return array( $exit, $result );
    }
    /** @dataProvider cases */
    public function test_actual_private_owner_reader_guards_and_cleanup_with_fixed_doubles( $case ) {
        list( $exit, $result ) = $this->worker( $case );
        $this->assertSame( 0, $exit, json_encode( $result ) ); $this->assertTrue( $result['ok'] ); $this->assertSame( array(), $result['failures'] );
    }
    public function cases() {
        $cases = array(
            'closed', 'post-valid', 'post-slashed', 'post-nonce-two', 'post-cache-null', 'post-fences', 'post-repeat',
            'post-get', 'post-query', 'post-get-map', 'post-files', 'post-length-zero', 'post-length-leading', 'post-length-large', 'post-length-int', 'post-length-missing', 'post-type', 'post-type-large', 'post-override', 'post-encoding', 'post-transfer', 'post-alias', 'post-array', 'post-reference', 'post-duplicate', 'post-wire-mismatch',
            'post-denied', 'post-guest', 'post-session', 'post-role-revoked', 'post-expired', 'post-wrong-domain', 'post-stale', 'post-nonce-string', 'post-nonce-throw', 'post-user-throw', 'post-capability-throw', 'post-role-throw', 'post-multisite', 'post-external-cache', 'post-cache-zero',
            'post-revoked-after', 'post-actor-after', 'post-cookie-after', 'post-db-after', 'post-blog-after', 'post-transport-after', 'post-metadata-after', 'post-method-after', 'post-reentry', 'post-deadline', 'post-auth-deadline', 'post-utc-regression', 'post-mono-regression', 'post-lastmono-regression', 'post-cleanup-reentry', 'remove-false',
            'get-closed', 'get-valid', 'get-wrong-method', 'get-query-import', 'get-post-import', 'get-screen', 'get-nonadmin', 'get-denied', 'get-guest', 'get-session', 'get-role-revoked', 'get-expired', 'get-user-throw', 'get-capability-throw', 'get-role-throw', 'get-revoked-after', 'get-actor-after', 'get-cookie-after', 'get-screen-after', 'get-transport-after', 'get-method-after', 'get-reentry', 'get-auth-deadline',
            'reader-short', 'reader-max', 'reader-overflow', 'reader-mismatch', 'reader-calls',
            'io-open-false', 'io-read-false', 'io-read-throw', 'io-read-warning', 'io-stall', 'io-never-eof', 'io-timeout', 'io-unknown-meta', 'io-wrong-uri', 'io-false-eof', 'io-close-throw', 'io-close-false',
            'post-buffer-drift', 'io-meta-null', 'io-seekable-false', 'io-seekable-int', 'io-seekable-string', 'io-seekable-missing', 'io-seekable-drift',
            'io-eof-int', 'io-unread-string', 'io-blocked-false', 'io-blocked-int', 'io-mode-append', 'io-wrong-type', 'io-wrong-wrapper', 'io-timeout-int',
        );
        return array_combine( $cases, array_map( function ( $v ) { return array( $v ); }, $cases ) );
    }
    /** @dataProvider controls */
    public function test_required_semantic_guard_removal_is_assertion_killed_after_loading( $mutation, $case ) {
        $files = array_merge( glob( dirname( __DIR__ ) . '/includes/*registration-closed-entry*.php' ), array( dirname( __DIR__ ) . '/includes/trait-real-closed-writer-core-guard.php' ) );
        $before = array_map( function ( $f ) { return hash_file( 'sha256', $f ); }, $files );
        list( $exit, $result ) = $this->worker( $case, $mutation );
        $this->assertSame( 1, $exit ); $this->assertFalse( $result['ok'] ); $this->assertNotEmpty( $result['failures'] );
        $this->assertSame( $before, array_map( function ( $f ) { return hash_file( 'sha256', $f ); }, $files ), 'All semantic controls stay in memory; disk sources unchanged.' );
    }
    public function controls() {
        return array(
            array( 'always-unavailable', 'post-valid' ), array( 'nonce-bypass', 'post-wrong-domain' ),
            array( 'post-first-auth', 'post-denied' ), array( 'get-first-auth', 'get-denied' ),
            array( 'post-second-auth', 'post-revoked-after' ), array( 'get-second-auth', 'get-revoked-after' ),
            array( 'length-bypass', 'reader-mismatch' ), array( 'short-is-complete', 'reader-short' ),
            array( 'close-bypass', 'io-close-false' ), array( 'repeat-allowed', 'post-repeat' ),
            array( 'reentry-not-poisoned', 'post-reentry' ),
            array( 'transport-bypass', 'post-transport-after' ), array( 'get-context-bypass', 'get-screen-after' ),
            array( 'buffered-required', 'io-seekable-false' ), array( 'after-meta-bypass', 'io-seekable-drift' ),
            array( 'wrapper-required', 'io-wrong-wrapper' ), array( 'unread-required', 'io-unread-string' ), array( 'timeout-required', 'io-timeout-int' ),
        );
    }
    public function test_reader_retains_fixed_forward_only_acquisition_and_production_gate() {
        $root = dirname( __DIR__ ) . '/';
        $reader = file_get_contents( $root . 'includes/trait-registration-closed-entry-reader.php' );
        $calls = array();
        foreach ( token_get_all( $reader ) as $token ) if ( is_array( $token ) && T_STRING === $token[0] ) $calls[] = strtolower( $token[1] );
        foreach ( array( 'fseek', 'rewind', 'stream_filter_append', 'stream_filter_prepend', 'file_get_contents', 'stream_get_contents', 'tmpfile', 'tempnam', 'fwrite' ) as $api ) $this->assertNotContains( $api, $calls );
        $this->assertSame( 1, substr_count( $reader, "fopen( 'php://input', 'rb' )" ) );
        $this->assertSame( 1, substr_count( $reader, 'fread( $stream,' ) );
        $this->assertSame( 1, substr_count( $reader, 'fclose( $stream )' ) );
        $owner = str_replace( "\r\n", "\n", file_get_contents( $root . 'includes/class-registration-closed-entry-request-owner.php' ) );
        $this->assertStringContainsString( "private function entry_available() {\n\t\treturn false;\n\t}", $owner );
        foreach ( array( 'alynt-drime-backups-uploader.php', 'includes/class-plugin.php' ) as $file ) $this->assertStringNotContainsString( 'registration-closed-entry', file_get_contents( $root . $file ) );
    }
}
