<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/includes/class-registration-entry-policy.php';

/** Isolated first-party policy API, NOT genuine authentication/native evidence. */
class RegistrationEntryPolicyTest extends TestCase {
    private function events() {
        // Independent expected contract, not read from the implementation.
        return array( 'authenticate', 'manage_options', 'small_mutation_nonce', 'authenticate_again', 'protected_capture', 'full_mutation_nonce', 'context_recheck', 'pre_begin', 'writer_released', 'writer_erased', 'read_authenticate', 'read_manage_options', 'small_read_nonce', 'read_authenticate_again', 'read_capture', 'full_read_nonce', 'read_claim', 'read_pre_begin', 'barrier_closed', 'post_close_authenticate', 'erased' );
    }
    private function start() { return Alynt_Drime_Backups_Uploader_Registration_Entry_Policy::start( 'closed_post', 'POST', 100 ); }
    private function finish( $policy ) { foreach ( $this->events() as $event ) { $this->assertTrue( $policy->step( $event, 100 ), $event ); } }
    public function test_positive_separate_method_policies_do_not_issue_nonces_or_authority() {
        $c = 'Alynt_Drime_Backups_Uploader_Registration_Entry_Policy';
        $get = $c::start( 'issuer_get', 'GET', 100 );
        foreach ( array( 'authenticate', 'manage_options', 'installation', 'authenticate_again' ) as $event ) { $this->assertTrue( $get->step( $event, 100 ) ); }
        $this->assertSame( array( 'policy_complete' => true, 'nonce_issued' => false ), $get->finish( null, true, 100 ) );
        $this->assertFalse( $get->finish( null, true, 100 ) );
        foreach ( array( array( 'issuer_get', 'POST', 100 ), array( 'closed_post', 'GET', 100 ), array( 'closed_post', 'post', 100 ), array( 'other', 'POST', 100 ), array( 'closed_post', 'POST', '100' ), array( 'closed_post', 'POST', -1 ), array( 'closed_post', 'POST', PHP_INT_MAX ) ) as $args ) { $this->assertFalse( $c::start( ...$args ) ); }
        foreach ( array( 'closed_receipt_observed', 'not_observed_at_barrier' ) as $outcome ) {
            $post = $this->start(); $this->finish( $post ); $out = $post->finish( $outcome, true, 100 );
            $this->assertSame( array( 'status' => 200, 'cache_control' => 'no-store', 'body' => array( 'outcome' => $outcome, 'execution_supported' => false, 'dispatch_supported' => false, 'retry_supported' => false ) ), $out );
            $this->assertLessThanOrEqual( 1024, strlen( json_encode( $out ) ) ); $this->assertFalse( $post->finish( $outcome, true, 100 ) );
        }
    }
    public function test_each_missing_or_reordered_guard_consumes_before_later_steps() {
        $events = $this->events();
        foreach ( $events as $index => $missing ) {
            $policy = $this->start(); foreach ( array_slice( $events, 0, $index ) as $event ) { $this->assertTrue( $policy->step( $event, 100 ) ); }
            $wrong = $events[$index + 1] ?? 'finish'; $this->assertFalse( $policy->step( $wrong, 100 ), $missing );
            $this->assertFalse( $policy->step( $missing, 100 ) ); $this->assertFalse( $policy->finish( 'closed_receipt_observed', true, 100 ) );
        }
    }
    public function test_context_change_expiry_backward_clock_and_reentry_erase() {
        foreach ( array( array( 'context_changed', 100 ), array( 'authenticate', 99 ), array( 'authenticate', 110 ), array( 'authenticate', '100' ), array( new stdClass(), 100 ) ) as $bad ) {
            $policy = $this->start(); $this->assertFalse( $policy->step( ...$bad ) ); $this->assertFalse( $policy->step( 'authenticate', 100 ) ); $this->assertFalse( $policy->finish( 'closed_receipt_observed', true, 100 ) );
        }
        $policy = $this->start(); $this->assertTrue( $policy->step( 'authenticate', 100 ) ); $this->assertFalse( $policy->step( 'authenticate', 100 ) );
        foreach ( array( 109, 119, 120 ) as $final ) {
            $policy = $this->start(); foreach ( $this->events() as $i => $event ) { $this->assertTrue( $policy->step( $event, $i < 9 ? 100 : 109 ) ); }
            if ( 109 === $final ) { $this->assertIsArray( $policy->finish( 'closed_receipt_observed', true, $final ) ); }
            else { $this->assertFalse( $policy->finish( 'closed_receipt_observed', true, $final ) ); }
        }
        // Refusal erases the whole bounded model, not just its public response.
        $policy = $this->start(); $this->assertFalse( $policy->finish( null, true, 100 ) );
        $r = new ReflectionObject( $policy );
        foreach ( array( 'events' => array(), 'kind' => '', 'start' => 0, 'last' => 0, 'read_start' => null, 'used' => true ) as $name => $expected ) { $p = $r->getProperty( $name ); if ( PHP_VERSION_ID < 80100 ) { $p->setAccessible( true ); } $this->assertSame( $expected, $p->getValue( $policy ) ); }
    }
    public function test_untrusted_downstream_or_cleanup_never_coerces_to_success() {
        foreach ( array( 'committed', 'uncertain', 'refused', 'held', 'registered', true, false, null, array( 'outcome' => 'closed_receipt_observed', 'secret' => 'nonce' ), new stdClass() ) as $outcome ) { $p = $this->start(); $this->finish( $p ); $this->assertFalse( $p->finish( $outcome, true, 100 ) ); }
        foreach ( array( false, null, 1, 'true', array() ) as $clean ) { $p = $this->start(); $this->finish( $p ); $this->assertFalse( $p->finish( 'closed_receipt_observed', $clean, 100 ) ); }
        $p = $this->start(); $this->finish( $p ); $this->assertFalse( $p->step( 'extra', 100 ) ); $this->assertFalse( $p->finish( 'closed_receipt_observed', true, 100 ) );
        $c = 'Alynt_Drime_Backups_Uploader_Registration_Entry_Policy';
        foreach ( array( 400 => 'malformed', 403 => 'forbidden', 405 => 'method_not_allowed', 503 => 'unavailable' ) as $status => $outcome ) { $out = $c::refusal( $status ); $this->assertSame( $status, $out['status'] ); $this->assertSame( $outcome, $out['body']['outcome'] ); $this->assertSame( 'no-store', $out['cache_control'] ); $this->assertSame( array( 'outcome', 'execution_supported', 'dispatch_supported', 'retry_supported' ), array_keys( $out['body'] ) ); }
        $this->assertSame( 503, $c::refusal( '200' )['status'] ); $this->assertSame( 503, $c::refusal( 200 )['status'] );
    }
}
