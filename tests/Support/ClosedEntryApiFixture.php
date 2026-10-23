<?php
/** Loaded only by the extension-free child. Core API doubles, not authentication. */
if ( defined( 'ALYNT_CLOSED_ENTRY_ISOLATED_WORKER' ) ) {
require_once __DIR__ . '/FixedCoreRequestShims.php';
require_once __DIR__ . '/ClosedEntryIo.php';
alynt_test_fixed_core_request_shims();
function add_filter( $hook, $callback, $priority = 10 ) {
    alynt_test_core_call( 'add_filter', array( $hook, $priority ) );
    $GLOBALS['alynt_entry_filters'][$hook][] = array( $callback, $priority ); return true;
}
function remove_filter( $hook, $callback, $priority = 10 ) {
    alynt_test_core_call( 'remove_filter', array( $hook, $priority ) );
    foreach ( $GLOBALS['alynt_entry_filters'][$hook] ?? array() as $key => $entry ) {
        if ( $entry === array( $callback, $priority ) ) { unset( $GLOBALS['alynt_entry_filters'][$hook][$key] ); return 'remove-false' !== ( $GLOBALS['alynt_entry_case'] ?? '' ); }
    }
    return false;
}
function is_admin() { alynt_test_core_call( 'admin' ); return $GLOBALS['alynt_entry_admin']; }
class WP_Screen { public $id = 'tools_page_alynt-drime-backups-uploader'; }
function get_current_screen() { alynt_test_core_call( 'screen' ); return $GLOBALS['alynt_entry_screen']; }
function wp_create_nonce( $action ) { alynt_test_core_call( 'create_nonce', array( $action ) ); return 'stubNonce1'; }
function wp_generate_uuid4() { alynt_test_core_call( 'protected_io', array( 'uuid' ) ); throw new RuntimeException( 'UUID forbidden.' ); }
function alynt_entry_invoke( $owner, $method ) {
    $r = new ReflectionMethod( $owner, $method ); if ( PHP_VERSION_ID < 80100 ) $r->setAccessible( true ); return $r->invoke( $owner );
}
function alynt_entry_state( $owner, $name, ...$value ) {
    $r = new ReflectionProperty( $owner, $name ); if ( PHP_VERSION_ID < 80100 ) $r->setAccessible( true );
    if ( $value ) $r->setValue( $owner, $value[0] ); return $r->getValue( $owner );
}
function alynt_entry_load( $enable_private, $mutation ) {
    $root = dirname( __DIR__, 2 ) . '/includes/'; $hits = 0;
    require_once $root . 'class-registration-request-contract.php';
    require_once $root . 'class-registration-entry-ingress.php';
    $files = array( 'trait-real-closed-writer-core-guard.php', 'trait-registration-closed-entry-transport.php', 'trait-registration-closed-entry-reader.php', 'trait-registration-closed-entry-scope.php', 'trait-registration-closed-entry-get-auth.php', 'class-registration-closed-entry-request-owner.php' );
    foreach ( $files as $file ) {
        $source = str_replace( "\r\n", "\n", file_get_contents( $root . $file ) );
        $source = preg_replace( '/^<\?php\s*|^require_once .*;\s*$/m', '', $source );
        // Only fixed external API/IO calls are doubled. First-party decisions stay intact.
        foreach ( array( 'hrtime' => 'clock', 'time' => 'utc', 'fopen' => 'open', 'fread' => 'read', 'feof' => 'eof', 'fclose' => 'close', 'stream_get_meta_data' => 'metadata', 'is_resource' => 'resource', 'file_exists' => 'exists' ) as $api => $double ) {
            $source = preg_replace( '/(?<![A-Za-z0-9_])' . $api . '\(/', 'Alynt_Test_Closed_Entry_Io::' . $double . '(', $source );
        }
        if ( 'class-registration-closed-entry-request-owner.php' === $file && $enable_private ) {
            $before = "private function entry_available() {\n\t\treturn false;\n\t}";
            alynt_entry_check( 1 === substr_count( $source, $before ), 'exact dormant gate seam' );
            $source = str_replace( $before, str_replace( 'return false;', 'return true;', $before ), $source );
        }
        $controls = array(
            'always-unavailable' => array( 'class-registration-closed-entry-request-owner.php', '$result = $form;', '$result = false;' ),
            'nonce-bypass' => array( 'class-registration-closed-entry-request-owner.php', "in_array( wp_verify_nonce( \$intent['nonce'], \$action ), array( 1, 2 ), true )", 'true' ),
            'post-first-auth' => array( 'class-registration-closed-entry-request-owner.php', '&& $this->open_fences() && $this->post_authenticated() )', '&& $this->open_fences() )' ),
            'get-first-auth' => array( 'class-registration-closed-entry-request-owner.php', '&& $this->open_fences() && $this->get_authenticated() )', '&& $this->open_fences() )' ),
            'post-second-auth' => array( 'class-registration-closed-entry-request-owner.php', '&& $this->post_scope() && $this->post_authenticated() )', '&& $this->post_scope() )' ),
            'get-second-auth' => array( 'class-registration-closed-entry-request-owner.php', '&& $this->get_scope() && $this->get_authenticated() )', '&& $this->get_scope() )' ),
            'length-bypass' => array( 'trait-registration-closed-entry-reader.php', "strlen( \$raw ) === (int) \$this->state['transport']['length']", 'true' ),
            'short-is-complete' => array( 'trait-registration-closed-entry-reader.php', 'if ( Alynt_Test_Closed_Entry_Io::eof( $stream ) )', 'if ( true )' ),
            'close-bypass' => array( 'trait-registration-closed-entry-reader.php', 'true !== Alynt_Test_Closed_Entry_Io::close( $stream )', '( Alynt_Test_Closed_Entry_Io::close( $stream ) && false )' ),
            'repeat-allowed' => array( 'class-registration-closed-entry-request-owner.php', 'if ( self::$consumed )', 'if ( false )' ),
            'reentry-not-poisoned' => array( 'class-registration-closed-entry-request-owner.php', 'self::$poisoned = true;', 'self::$poisoned = false;' ),
            'transport-bypass' => array( 'trait-registration-closed-entry-reader.php', "\$this->post_transport() === ( \$this->state['transport'] ?? null )", 'true' ),
            'get-context-bypass' => array( 'trait-registration-closed-entry-get-auth.php', '$this->tools_context()', 'true' ),
            'buffered-required' => array( 'trait-registration-closed-entry-reader.php', "true === ( \$meta['seekable'] ?? null )", 'true' ),
            'after-meta-bypass' => array( 'trait-registration-closed-entry-reader.php', '! $this->input_metadata( $stream ) || strlen( $chunk )', 'false || strlen( $chunk )' ),
            'wrapper-required' => array( 'trait-registration-closed-entry-reader.php', "'PHP' === ( \$meta['wrapper_type'] ?? null )", 'true' ),
            'unread-required' => array( 'trait-registration-closed-entry-reader.php', "0 === ( \$meta['unread_bytes'] ?? null )", 'true' ),
            'timeout-required' => array( 'trait-registration-closed-entry-reader.php', "false === ( \$meta['timed_out'] ?? null )", 'true' ),
        );
        if ( isset( $controls[$mutation] ) && $file === $controls[$mutation][0] ) {
            list( , $before, $after ) = $controls[$mutation];
            alynt_entry_check( 1 === substr_count( $source, $before ), 'single semantic-control target' );
            $source = str_replace( $before, $after, $source ); ++$hits;
        }
        eval( $source );
    }
    alynt_entry_check( '' === $mutation || 1 === $hits, 'all requested semantic controls applied' );
}
}
