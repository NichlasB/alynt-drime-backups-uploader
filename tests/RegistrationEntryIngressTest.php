<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/includes/class-registration-request-contract.php';
require_once dirname( __DIR__ ) . '/includes/class-registration-entry-ingress.php';

/** Synthetic bytes only: no HTTP request, superglobal use, core auth or IO. */
class RegistrationEntryIngressTest extends TestCase {
    private function form( $nonce = 'synthetic-nonce' ) {
        return array( 'action' => 'alynt_drime_backups_register_closed', 'purpose' => 'register_closed', 'confirm' => 'register', 'nonce' => $nonce );
    }
    private function parse( $raw = null, $post = null, $changes = array() ) {
        $form = $this->form();
        $args = array( 'POST', 'application/x-www-form-urlencoded', '', array(), array(), $raw ?? http_build_query( $form, '', '&' ), $post ?? array_map( 'addslashes', $form ), true, array() );
        foreach ( $changes as $key => $value ) { $args[$key] = $value; }
        return Alynt_Drime_Backups_Uploader_Registration_Entry_Ingress::parse( ...$args );
    }
    public function test_valid_unordered_wire_preserves_original_slashes_for_domain_parser() {
        foreach ( array( '0', 'a+b c', 'é中😀', "a\\b\\'\"%25&=;" ) as $nonce ) {
            $form = array_reverse( $this->form( $nonce ), true ); $post = array_map( 'addslashes', $form );
            $out = $this->parse( http_build_query( $form, '', '&' ), $post ); unset( $post['action'] );
            $this->assertSame( $post, $out );
            $this->assertSame( $nonce, Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issuance( $out )['nonce'] );
        }
        foreach ( array( 'application/x-www-form-urlencoded;charset=UTF-8', 'Application/X-WWW-Form-Urlencoded; charset="utf-8"' ) as $type ) {
            $this->assertIsArray( $this->parse( null, null, array( 1 => $type ) ) );
        }
        $raw = str_replace( 'action=', '%61ction=', http_build_query( $this->form(), '', '&' ) );
        $this->assertIsArray( $this->parse( $raw ) ); // Decode names once, then compare exact decoded names.
    }
    public function test_wire_alias_duplicates_malformed_and_control_bytes_refuse() {
        $raw = http_build_query( $this->form(), '', '&' );
        $bad = array( '', $raw . '&nonce=other', str_replace( 'confirm=register', '%6eonce=other', $raw ), str_replace( 'nonce=', 'nonce[]=', $raw ), str_replace( 'nonce=', 'non.ce=', $raw ), str_replace( 'nonce=', 'non+ce=', $raw ), str_replace( 'nonce=', '0=', $raw ), str_replace( 'nonce=', '=', $raw ), str_replace( 'nonce=', 'Nonce=', $raw ), str_replace( 'nonce=', '%256eonce=', $raw ), str_replace( '&nonce=', ';nonce=', $raw ), $raw . '&', str_replace( 'nonce=', 'nonce', $raw ) );
        foreach ( array( '%', '%a', '%gg', '%00', '%1f', '%7f', '%C2%80', '%ff', '%c0%af', '%ed%a0%80' ) as $value ) { $bad[] = preg_replace( '/nonce=.*/', 'nonce=' . $value, $raw ); }
        foreach ( $bad as $wire ) { $this->assertFalse( $this->parse( $wire ), bin2hex( $wire ) ); }
    }
    public function test_matching_post_cannot_hide_controls_or_decode_twice() {
        foreach ( array( "\0", "\x1f", "\x7f", "\xc2\x80", "\xc2\x9f", "\xff", "\xc0\xaf", "\xed\xa0\x80" ) as $nonce ) {
            $form = $this->form( $nonce );
            $this->assertFalse( $this->parse( http_build_query( $form, '', '&' ), array_map( 'addslashes', $form ) ), bin2hex( $nonce ) );
        }
    }
    public function test_wrong_transport_mixed_sources_and_incomplete_reads_refuse() {
        foreach ( array( 'GET', 'post', 'PUT', '', null, array() ) as $method ) { $this->assertFalse( $this->parse( null, null, array( 0 => $method ) ) ); }
        foreach ( array( '', null, array(), 'application/json', 'multipart/form-data', 'application/x-www-form-urlencoded; charset=latin1', 'application/x-www-form-urlencoded; charset=UTF-8; charset=UTF-8', "application/x-www-form-urlencoded\r\nX: y", 'application/x-www-form-urlencoded,application/json' ) as $type ) { $this->assertFalse( $this->parse( null, null, array( 1 => $type ) ) ); }
        foreach ( array( array( 2, 'action=x' ), array( 2, null ), array( 3, array( 'nonce' => 'x' ) ), array( 3, null ), array( 4, array( 'file' => array() ) ), array( 4, false ), array( 7, false ), array( 7, 1 ), array( 5, false ), array( 5, str_repeat( 'x', 16384 ) ), array( 5, str_repeat( 'x', 16385 ) ) ) as $case ) { $this->assertFalse( $this->parse( null, null, array( $case[0] => $case[1] ) ) ); }
        $this->assertFalse( $this->parse( null, null, array( 6 => null ) ) );
        foreach ( array( array( 'HTTP_X_HTTP_METHOD_OVERRIDE' => 'POST' ), array( '_method' => 'POST' ), null, false ) as $override ) { $this->assertFalse( $this->parse( null, null, array( 8 => $override ) ) ); }
    }
    public function test_raw_post_disagreement_wrong_purpose_types_references_and_imports_refuse() {
        $form = $this->form();
        foreach ( array( 'action' => 'alynt_drime_backups_other', 'purpose' => 'reserve_wpvivid_intent', 'confirm' => 'REGISTER', 'nonce' => '' ) as $key => $value ) {
            $other = $form; $other[$key] = $value;
            $this->assertFalse( $this->parse( http_build_query( $other, '', '&' ), array_map( 'addslashes', $other ) ) );
        }
        foreach ( array( null, false, 1, array(), new stdClass(), 'different' ) as $value ) { $post = $form; $post['nonce'] = $value; $this->assertFalse( $this->parse( null, $post ) ); }
        $post = $form; $nonce = $post['nonce']; $post['nonce'] =& $nonce; $this->assertFalse( $this->parse( null, $post ) );
        foreach ( array( '_method', 'actor_id', 'blog_id', 'command_id', 'callback', 'enable', '_wp_http_referer', 'submit', 'redirect_to' ) as $key ) { $post = $form; $post[$key] = 'x'; $this->assertFalse( $this->parse( http_build_query( $post, '', '&' ), $post ) ); }
        // Once decoded percent data is not decoded again into a control character.
        $form = $this->form( '%00' ); $this->assertIsArray( $this->parse( http_build_query( $form, '', '&' ), $form ) );
    }
    public function test_key_value_and_json_limits_are_transport_inclusive() {
        $form = $this->form( str_repeat( 'x', 2048 ) ); $this->assertIsArray( $this->parse( http_build_query( $form, '', '&' ), $form ) );
        $form['nonce'] .= 'x'; $this->assertFalse( $this->parse( http_build_query( $form, '', '&' ), $form ) );
        $form = $this->form( str_repeat( 'é', 500 ) ); $form['nonce'] .= str_repeat( 'x', 4096 - strlen( json_encode( $form ) ) );
        $this->assertSame( 4096, strlen( json_encode( $form ) ) ); $this->assertIsArray( $this->parse( http_build_query( $form, '', '&' ), $form ) );
        $form['nonce'] .= 'x'; $this->assertFalse( $this->parse( http_build_query( $form, '', '&' ), $form ) );
        $form = $this->form( str_repeat( '\\', 1000 ) ); $this->assertFalse( $this->parse( http_build_query( $form, '', '&' ), array_map( 'addslashes', $form ) ) ); // Slashed JSON cap must not be lost.
    }
    public function test_rejection_happens_before_any_simulated_downstream_work() {
        $calls = array( 'protected' => 0, 'uuid' => 0, 'dispatch' => 0 );
        if ( false !== $this->parse( 'action=x&purpose=register_closed&confirm=register&nonce=x' ) ) { foreach ( $calls as &$count ) { ++$count; } unset( $count ); }
        $this->assertSame( array( 'protected' => 0, 'uuid' => 0, 'dispatch' => 0 ), $calls );
        $this->assertIsArray( $this->parse() ); // This assertion prevents an always-unavailable parser.
    }
}
