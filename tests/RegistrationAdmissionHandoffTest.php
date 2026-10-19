<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RegistrationAdmissionFixture.php';
/** Private data lifetime only; no actual caller authentication or native handle. */
class RegistrationAdmissionHandoffTest extends TestCase {
    use Alynt_Test_Registration_Admission_Fixture;
    private function newHandoff() { return Alynt_Drime_Backups_Uploader_Registration_Admission_Handoff::create(); }
    public function test_positive_stage_and_exact_revalidation_are_once_only_and_release_private_data() {
        list( $f, $p, $c ) = $this->admissionFixture(); $owner = $this->newHandoff();
        $this->assertTrue( $this->admissionInvoke( $owner, 'stage', $f, $p, $c, $this->admissionClock() ) );
        $this->assertIsObject( $this->admissionProperty( $owner, 'plan' ) );
        $this->assertTrue( $this->admissionInvoke( $owner, 'revalidate', $f, $p, $c, $this->admissionClock( 300, 111 ) ) );
        $this->assertNull( $this->admissionProperty( $owner, 'plan' ) ); $this->assertNull( $this->admissionProperty( $owner, 'clock' ) );
        $this->assertFalse( $this->admissionInvoke( $owner, 'revalidate', $f, $p, $c, $this->admissionClock( 301, 112 ) ) );
        $this->assertFalse( $this->admissionInvoke( $owner, 'stage', $f, $p, $c, $this->admissionClock() ) );
        foreach ( array( 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $flag ) $this->assertFalse( $owner->disposition()[$flag] );
    }
    public function test_stale_failed_premature_or_duplicate_attempts_cannot_retry_or_adopt_a_plan() {
        list( $f, $p, $c ) = $this->admissionFixture();
        foreach ( array( 'stale', 'initial', 'premature', 'duplicate', 'import', 'extra' ) as $case ) {
            $owner = $this->newHandoff();
            if ( 'premature' === $case ) $this->assertFalse( $this->admissionInvoke( $owner, 'revalidate', $f, $p, $c, $this->admissionClock() ) );
            elseif ( 'initial' === $case ) $this->assertFalse( $this->admissionInvoke( $owner, 'stage', null, $p, $c, $this->admissionClock() ) );
            elseif ( 'import' === $case ) $this->assertFalse( $this->admissionInvoke( $owner, 'stage', $this->prepareAdmission( $f, $p, $c ), $p, $c, $this->admissionClock() ) );
            elseif ( 'extra' === $case ) $this->assertFalse( $this->admissionInvoke( $owner, 'stage', $f, $p, $c, $this->admissionClock(), 'extra' ) );
            else {
                $this->assertTrue( $this->admissionInvoke( $owner, 'stage', $f, $p, $c, $this->admissionClock() ) );
                if ( 'duplicate' === $case ) $this->assertFalse( $this->admissionInvoke( $owner, 'stage', $f, $p, $c, $this->admissionClock() ) );
                else { $bad = $f; $bad['rows']['failed']['autoload'] = 'off'; $this->assertFalse( $this->admissionInvoke( $owner, 'revalidate', $bad, $p, $c, $this->admissionClock( 300 ) ) ); }
            }
            $this->assertFalse( $this->admissionInvoke( $owner, 'revalidate', $f, $p, $c, $this->admissionClock( 301 ) ), $case );
            $this->assertFalse( $this->admissionInvoke( $owner, 'stage', $f, $p, $c, $this->admissionClock() ), $case );
            $this->assertNull( $this->admissionProperty( $owner, 'plan' ) );
        }
    }
    public function test_original_monotonic_and_utc_budget_cannot_be_reset_extended_or_moved_backwards() {
        list( $f, $p, $c ) = $this->admissionFixture();
        $cases = array( $this->admissionClock( 10000000100 ), $this->admissionClock( 199 ), $this->admissionClock( 300, 109 ), $this->admissionClock( 300, 111, 101 ), $this->admissionClock( 300, 400 ), array( 'started_ns' => 100, 'now_ns' => '300', 'utc' => 111 ), array() );
        foreach ( $cases as $clock ) { $owner = $this->newHandoff(); $this->assertTrue( $this->admissionInvoke( $owner, 'stage', $f, $p, $c, $this->admissionClock() ) ); $this->assertFalse( $this->admissionInvoke( $owner, 'revalidate', $f, $p, $c, $clock ) ); $this->assertFalse( $this->admissionInvoke( $owner, 'revalidate', $f, $p, $c, $this->admissionClock( 301 ) ) ); }
        $owner = $this->newHandoff(); $this->assertTrue( $this->admissionInvoke( $owner, 'stage', $f, $p, $c, $this->admissionClock() ) );
        $this->assertTrue( $this->admissionInvoke( $owner, 'revalidate', $f, $p, $c, $this->admissionClock( 10000000099, 399 ) ), 'Strictly less than ten seconds and original deadline.' );
        foreach ( array( $this->admissionClock( 99 ), $this->admissionClock( 10000000100 ), $this->admissionClock( 200, 0 ) ) as $clock ) { $owner = $this->newHandoff(); $this->assertFalse( $this->admissionInvoke( $owner, 'stage', $f, $p, $c, $clock ) ); }
        $clock = $this->admissionClock(); $utc = 110; $clock['utc'] =& $utc; $this->assertFalse( $this->admissionInvoke( $this->newHandoff(), 'stage', $f, $p, $c, $clock ) );
    }
    public function test_public_surface_is_zero_io_redacted_untransferable_and_not_production_loaded() {
        $owner = $this->newHandoff(); $this->assertSame( $owner->disposition(), $owner->__debugInfo() );
        $this->assertFalse( Alynt_Drime_Backups_Uploader_Registration_Admission_Handoff::create( 'provider' ) );
        foreach ( array( 'stage', 'revalidate' ) as $name ) $this->assertTrue( ( new ReflectionMethod( $owner, $name ) )->isPrivate() );
        foreach ( array( 'execute', 'write', 'consume', 'read_registration', 'get_plan' ) as $name ) $this->assertFalse( method_exists( $owner, $name ) );
        foreach ( array( 'registration-admission-plan', 'registration-admission-handoff' ) as $part ) {
            $source = file_get_contents( dirname( __DIR__ ) . '/includes/class-' . $part . '.php' );
            foreach ( array( 'mysqli', 'wp_verify_nonce(', 'wp_create_nonce(', 'wp_generate_uuid4(', 'add_action(', 'register_rest_route(', 'update_option(', 'hrtime(', 'time(' ) as $token ) $this->assertStringNotContainsString( $token, $source );
            foreach ( array_merge( array( dirname( __DIR__ ) . '/alynt-drime-backups-uploader.php' ), glob( dirname( __DIR__ ) . '/includes/class-plugin*.php' ) ) as $path ) $this->assertStringNotContainsString( $part . '.php', file_get_contents( $path ) );
        }
        foreach ( array( 'clone', 'serialize', 'restore', 'legacy' ) as $operation ) {
            try { if ( 'clone' === $operation ) $copy = clone $owner; elseif ( 'serialize' === $operation ) serialize( $owner ); elseif ( 'restore' === $operation ) $owner->__unserialize( array() ); else $owner->__wakeup(); $this->fail( $operation . ' must refuse.' ); }
            catch ( LogicException $error ) { $this->assertNotEmpty( $error->getMessage() ); }
            catch ( Error $error ) { $this->assertSame( 'clone', $operation ); }
        }
    }
}
