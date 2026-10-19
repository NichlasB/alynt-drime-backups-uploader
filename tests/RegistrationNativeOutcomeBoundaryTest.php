<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/includes/class-registration-native-admission.php';
/** Dormant private surface and erase proof only; no WordPress/native activation. */
final class RegistrationNativeOutcomeBoundaryTest extends TestCase {
    private function invoke( $owner, $name, ...$args ) { $m = new ReflectionMethod( $owner, $name ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true ); return $m->invokeArgs( $owner, $args ); }
    private function property( $owner, $name ) { $p = new ReflectionProperty( $owner, $name ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); return $p->getValue( $owner ); }
    public function test_factory_and_capture_remain_private_primitive_and_non_authorizing() {
        $o = Alynt_Drime_Backups_Uploader_Registration_Native_Admission::create(); $r = new ReflectionClass( $o );
        $names = array_map( function( $m ) { return $m->name; }, $r->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $names );
        $this->assertSame( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ), $names );
        $this->assertTrue( $r->isFinal() ); $this->assertTrue( $r->getConstructor()->isPrivate() ); $this->assertFalse( $o::create( 'provider' ) );
        $this->assertNull( $this->property( $o, 'native_outcome' ) ); $this->assertSame( $o->disposition(), $o->__debugInfo() );
        foreach ( array( 'start_native_outcome', 'native_outcome_send', 'native_outcome_ack', 'native_outcome_event', 'finish', 'clear_native_outcome' ) as $method ) $this->assertTrue( $r->getMethod( $method )->isPrivate() );
        $this->assertFalse( method_exists( $o, 'classify' ) ); $this->assertFalse( method_exists( $o, 'take_outcome' ) );
        $this->assertTrue( $this->invoke( $o, 'start_native_outcome' ) ); $capture = $this->property( $o, 'native_outcome' );
        $this->assertSame( array( 'valid', 'facts', 'raw_returns' ), array_keys( $capture ) ); $this->assertIsString( json_encode( $capture ) );
        $this->assertSame( $o->disposition(), $o->__debugInfo() );
        foreach ( array( 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $flag ) $this->assertFalse( $o->disposition()[$flag] );
        $this->invoke( $o, 'clear_native_outcome' ); $this->assertNull( $this->property( $o, 'native_outcome' ) ); $this->assertTrue( $this->property( $o, 'native_outcome_started' ) );
        $this->assertFalse( $this->invoke( $o, 'start_native_outcome' ) );
    }
    public function test_refusal_at_outer_boundary_erases_and_consumes_capture_without_io() {
        foreach ( array( null, array(), array( 'purpose' => 'bad' ) ) as $body ) {
            $o = Alynt_Drime_Backups_Uploader_Registration_Native_Admission::create(); $this->assertFalse( $this->invoke( $o, 'admit', 'POST', $body ) );
            $this->assertNull( $this->property( $o, 'native_outcome' ) ); $this->assertSame( 0, $this->property( $o, 'native_outcome_tick' ) );
            $this->assertNull( $this->property( $o, 'native_outcome_writer' ) );
            $this->assertTrue( $this->property( $o, 'native_outcome_started' ) ); $this->assertTrue( $this->property( $o, 'native_outcome_closed' ) );
            $this->assertFalse( $this->invoke( $o, 'start_native_outcome' ) );
            $this->assertSame( 0, $this->property( $o, 'protected_reads' ) ); $this->assertSame( 0, $this->property( $o, 'writes' ) );
        }
    }
    public function test_no_production_registration_or_authentication_clock_in_capture_helper() {
        $root = dirname( __DIR__ );
        foreach ( array( 'alynt-drime-backups-uploader.php', 'includes/class-plugin.php', 'includes/class-registration-lifecycle-request-owner.php' ) as $file ) {
            $source = file_get_contents( $root . '/' . $file );
            foreach ( array( 'class-registration-native-admission.php', 'Registration_Native_Outcome', 'trait-registration-native-outcome' ) as $needle ) $this->assertStringNotContainsString( $needle, $source );
        }
        $path = $root . '/includes/trait-registration-native-outcome-capture.php';
        foreach ( token_get_all( file_get_contents( $path ) ) as $token ) if ( is_array( $token ) && T_STRING === $token[0] ) $this->assertFalse( (bool) preg_match( '/^(wp_|mysqli|mysqli_init$|current_user_can$|get_option$|update_option$|add_action$|add_filter$|hrtime$|time$|microtime$|fopen$|file_put_contents$|curl_)/i', $token[1] ), $token[1] );
        $this->assertSame( array(), (new ReflectionClass( 'Alynt_Drime_Backups_Uploader_Registration_Native_Outcome_Capture' ))->getConstants(), 'PHP7.4 compatibility' );
    }
}
