<?php
require_once __DIR__ . '/Support/RegistrationClosedQualificationFixture.php';
/** Packet/one-shot/privacy/loading boundaries; no authentication or native execution. */
final class RegistrationClosedQualificationBoundaryTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Registration_Closed_Qualification_Fixture;
    public function test_under_and_over_arity_consume_even_invalid_first_attempts() {
        foreach ( range( 0, 7 ) as $arity ) $this->qualificationRefused( array_slice( $this->qualificationFixture(), 0, $arity ) );
        $args = $this->qualificationFixture(); $args[] = 'extra'; $this->qualificationRefused( $args );
    }
    public function test_unknown_keys_references_objects_and_recursion_are_not_converted() {
        foreach ( array( 0, 1, 3, 4, 5, 6, 7 ) as $slot ) {
            $args = $this->qualificationFixture(); $args[$slot]['provider'] = 'external';
            // Current malformed clock is simply unavailable acceptance; other
            // exact-map inputs refuse the entire qualification.
            if ( 4 === $slot ) $this->assertQualification( $this->qualification( $this->qualificationOwner(), $args ), 'committed', true, false );
            else $this->qualificationRefused( $args );
        }
        foreach ( array( 'reference', 'recursive', 'object', 'utf8', 'float', 'oversize', 'depth', 'nodes' ) as $fault ) {
            $args = $this->qualificationFixture();
            if ( 'reference' === $fault ) { $v = true; $args[6]['sealed'] =& $v; }
            elseif ( 'recursive' === $fault ) $args[6]['loop'] =& $args[6];
            elseif ( 'object' === $fault ) $args[5] = new class implements JsonSerializable { public function jsonSerialize(): array { throw new LogicException( 'No conversion.' ); } };
            elseif ( 'utf8' === $fault ) $args[5]['raw_returns']['begin'] = "\xff";
            elseif ( 'float' === $fault ) $args[6]['tick'] = 14.0;
            elseif ( 'oversize' === $fault ) $args[7]['domain'] = str_repeat( 'x', 16385 );
            elseif ( 'nodes' === $fault ) $args[6] = array_fill( 0, 256, 1 );
            else { $v = 1; for ( $n = 0; $n < 9; $n++ ) $v = array( $v ); $args[6] = $v; }
            $this->qualificationRefused( $args ); unset( $args );
        }
    }
    public function test_exact_existing_byte_depth_node_and_identity_caps_are_reused() {
        $o = $this->qualificationOwner();
        foreach ( array( 'packet' => 16384, 'bounded' => 4096 ) as $method => $cap ) {
            $this->assertTrue( $this->admissionInvoke( $o, $method, str_repeat( 'x', $cap - 2 ) ) );
            $this->assertFalse( $this->admissionInvoke( $o, $method, str_repeat( 'x', $cap - 1 ) ) );
        }
        foreach ( array( 8 => true, 9 => false ) as $depth => $expected ) { $v = 1; for ( $n = 0; $n < $depth; $n++ ) $v = array( $v ); $this->assertSame( $expected, $this->admissionInvoke( $o, 'packet', $v ) ); }
        $v = array_fill( 0, 255, 1 ); $this->assertTrue( $this->admissionInvoke( $o, 'packet', $v ) ); $v[] = 1; $this->assertFalse( $this->admissionInvoke( $o, 'packet', $v ) );
        $args = $this->qualificationFixture(); while ( count( $args[2] ) < 128 ) $args[2][] = $this->rid( 1000 + count( $args[2] ) );
        $this->assertTrue( $this->admissionInvoke( $o, 'original', $args[0], $args[1], $args[2] ) );
        $this->assertQualification( $this->qualification( $this->qualificationOwner(), $args ) );
        $args[2][] = $this->rid( 2000 ); $this->qualificationRefused( $args );
    }
    public function test_public_surface_retains_only_spent_and_exports_no_input_or_continuation() {
        $o = $this->qualificationOwner(); $r = new ReflectionClass( $o );
        $this->assertTrue( $r->isFinal() ); $this->assertTrue( $r->getConstructor()->isPrivate() ); $this->assertTrue( $r->getMethod( 'qualify' )->isPrivate() );
        $names = array_map( function ( $m ) { return $m->name; }, $r->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $names );
        $this->assertSame( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ), $names );
        $this->assertSame( array( 'spent' ), array_map( function ( $p ) { return $p->name; }, $r->getProperties() ) );
        $this->assertSame( array(), ( new ReflectionClass( 'Alynt_Drime_Backups_Uploader_Registration_Sealed_Outcome_Data' ) )->getConstants() );
        $this->assertSame( Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome::EVENTS, $o::EVENTS );
        $this->assertFalse( $o::create( 'provider' ) ); $this->assertSame( $o->disposition(), $o->__debugInfo() );
        $this->qualification( $o, $this->qualificationFixture() ); $this->assertTrue( $this->admissionProperty( $o, 'spent' ) );
        foreach ( array( 'serialize', '__wakeup', '__unserialize' ) as $action ) {
            try { if ( 'serialize' === $action ) serialize( $o ); elseif ( '__wakeup' === $action ) $o->__wakeup(); else $o->__unserialize( array() ); $this->fail( 'No transferable closed result.' ); }
            catch ( LogicException $error ) { $this->assertSame( 'Closed contract data cannot be transferred.', $error->getMessage() ); }
        }
    }
    public function test_no_native_auth_storage_clock_callbacks_or_production_loading() {
        $root = dirname( __DIR__ );
        foreach ( array( 'class-registration-closed-qualification.php', 'trait-registration-sealed-outcome-data.php' ) as $file ) {
            $source = file_get_contents( $root . '/includes/' . $file );
            foreach ( token_get_all( $source ) as $token ) if ( is_array( $token ) && T_STRING === $token[0] ) $this->assertFalse( (bool) preg_match( '/^(wp_|mysqli|current_user_can$|get_option$|update_option$|add_action$|add_filter$|hrtime$|time$|microtime$|sleep$|fopen$|file_put_contents$|curl_)/i', $token[1] ), $token[1] );
        }
        foreach ( array( 'alynt-drime-backups-uploader.php', 'includes/class-plugin.php', 'includes/class-registration-native-admission.php', 'includes/trait-registration-admission-root.php', 'includes/class-registration-admission-outcome.php' ) as $file ) {
            $source = file_get_contents( $root . '/' . $file );
            foreach ( array( 'Registration_Closed_Qualification', 'class-registration-closed-qualification.php', 'Registration_Sealed_Outcome_Data', 'trait-registration-sealed-outcome-data.php' ) as $needle ) $this->assertStringNotContainsString( $needle, $source );
        }
    }
}
