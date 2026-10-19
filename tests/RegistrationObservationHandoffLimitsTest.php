<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RegistrationObservationHandoffFixture.php';
/** Primitive bounds/privacy only; never native timer, authentication or IO. */
class RegistrationObservationHandoffLimitsTest extends TestCase {
    use Alynt_Test_Registration_Observation_Handoff_Fixture;
    public function test_packet_walk_bounds_before_encoding_and_encoded_bound_are_independent() {
        $o = $this->handoffOwner();
        $this->assertTrue( $this->admissionInvoke( $o, 'packet', array( str_repeat( '\\', 8190 ) ) ) );
        $this->assertFalse( $this->admissionInvoke( $o, 'packet', array( str_repeat( '\\', 8191 ) ) ) );
        $this->assertFalse( $this->admissionInvoke( $o, 'packet', array( str_repeat( 'x', 16385 ) ) ) );
        $this->assertTrue( $this->admissionInvoke( $o, 'packet', array_fill( 0, 255, null ) ) );
        $this->assertFalse( $this->admissionInvoke( $o, 'packet', array_fill( 0, 256, null ) ) );
        $deep = null; for ( $i = 0; $i < 9; $i++ ) $deep = array( $deep );
        $this->assertFalse( $this->admissionInvoke( $o, 'packet', $deep ) );
        $this->assertFalse( $this->admissionInvoke( $o, 'packet', array( "\xff" ) ) );
    }
    public function test_aliases_at_every_retained_component_or_identity_are_rejected_without_copying() {
        $good = $this->handoffFixture();
        foreach ( array( 'data', 'intent', 'form', 'clock', 'ids' ) as $part ) {
            foreach ( array_keys( $good[$part] ) as $key ) {
                $bad = $good; $value = $bad[$part][$key]; $bad[$part][$key] =& $value; $o = $this->handoffOwner();
                $this->assertFalse( $this->handoffStep( $o, 'stage', $bad ), "$part/$key" ); $this->assertHandoffCleared( $o ); $this->assertFalse( $this->handoffStep( $o, 'stage', $good ) ); unset( $bad, $value );
            }
        }
        $bad = $good; $bad['ids'][0] =& $bad['ids']; $this->assertFalse( $this->handoffStep( $this->handoffOwner(), 'stage', $bad ) ); unset( $bad );
    }
    public function test_objects_resources_floats_and_callbacks_are_refused_without_invocation() {
        $good = $this->handoffFixture(); $calls = 0;
        $callback = function () use ( &$calls ) { $calls++; return true; };
        $object = new class implements JsonSerializable {
            #[\ReturnTypeWillChange]
            public function jsonSerialize() { throw new RuntimeException( 'Must never encode an object.' ); }
        };
        $resource = fopen( 'php://memory', 'r' );
        try {
            foreach ( array( $callback, $object, $resource, NAN, INF, 2.5 ) as $value ) {
                foreach ( array( 'data', 'intent', 'form', 'clock', 'ids' ) as $part ) { $bad = $good; $bad[$part] = $value; $o = $this->handoffOwner(); $this->assertFalse( $this->handoffStep( $o, 'stage', $bad ), $part ); $this->assertHandoffCleared( $o ); }
            }
        } finally { fclose( $resource ); }
        $this->assertSame( 0, $calls );
    }
    public function test_maximum_identity_list_is_bounded_unique_and_not_reordered_by_model() {
        $f = $this->handoffFixture(); while ( count( $f['ids'] ) < 128 ) $f['ids'][] = $this->rid( 1000 + count( $f['ids'] ) );
        $o = $this->handoffOwner(); $this->assertTrue( $this->handoffStep( $o, 'stage', $f ) ); $this->assertTrue( $this->handoffStep( $o, 'claim', $f ) ); $this->assertCandidateOnly( $this->handoffFinish( $o, $f ), 'registered_closed' );
        $f['ids'][] = $this->rid( 2000 ); $this->assertFalse( $this->handoffStep( $this->handoffOwner(), 'stage', $f ) );
        $f = $this->handoffFixture(); $f['ids'] = array_combine( $f['ids'], $f['ids'] ); $this->assertFalse( $this->handoffStep( $this->handoffOwner(), 'stage', $f ) );
    }
    public function test_individual_form_bounds_are_not_relaxed_by_larger_aggregate_allowance() {
        $good = $this->handoffFixture();
        foreach ( array( 'data', 'intent', 'form', 'clock' ) as $part ) {
            $bad = $good;
            if ( 'data' === $part ) $bad[$part]['installation']['database'] = str_repeat( 'x', 4097 );
            elseif ( 'clock' === $part ) $bad[$part]['now_ns'] = str_repeat( 'x', 4097 );
            else $bad[$part]['nonce'] = str_repeat( 'x', 2049 );
            $o = $this->handoffOwner(); $this->assertFalse( $this->handoffStep( $o, 'stage', $bad ), $part ); $this->assertHandoffCleared( $o );
        }
        foreach ( array( $this->admissionClock( 99, 510 ), $this->admissionClock( 200, 0 ), $this->admissionClock( 200, PHP_INT_MAX ), array( 'started_ns' => 100, 'now_ns' => 200, 'utc' => 510, 'extra' => true ) ) as $clock ) { $bad = $good; $bad['clock'] = $clock; $this->assertFalse( $this->handoffStep( $this->handoffOwner(), 'stage', $bad ) ); }
    }
    public function test_privacy_dormancy_and_public_surface_cannot_transfer_or_qualify_a_barrier() {
        $o = $this->handoffOwner(); $f = $this->handoffFixture(); $this->assertTrue( $this->handoffStep( $o, 'stage', $f ) );
        $this->assertSame( $o->disposition(), $o->__debugInfo() ); $this->assertStringNotContainsString( $f['intent']['nonce'], print_r( $o, true ) );
        foreach ( array( 'stage', 'claim', 'finish' ) as $method ) $this->assertTrue( ( new ReflectionMethod( $o, $method ) )->isPrivate() );
        $this->assertFalse( Alynt_Drime_Backups_Uploader_Registration_Observation_Handoff::create( 'provider' ) );
        foreach ( array( 'read', 'execute', 'write', 'admit', 'export', 'get', 'barrier', 'reader' ) as $method ) $this->assertFalse( method_exists( $o, $method ) );
        foreach ( array( 'clone', '__serialize', '__unserialize', '__wakeup' ) as $operation ) {
            $error = null; try { if ( 'clone' === $operation ) { $copy = clone $o; } elseif ( '__unserialize' === $operation ) { $o->__unserialize( array( 'barrier_verified' => true ) ); } else { $o->$operation(); } } catch ( Throwable $caught ) { $error = $caught; }
            $this->assertInstanceOf( 'clone' === $operation ? Error::class : LogicException::class, $error );
        }
        // The separately approved private API coordinator may now compose these
        // DATA helpers; production loaders and native admission must still not.
        foreach ( array_merge( array( dirname( __DIR__ ) . '/alynt-drime-backups-uploader.php' ), glob( dirname( __DIR__ ) . '/includes/class-plugin*.php' ), array( dirname( __DIR__ ) . '/includes/class-registration-native-admission.php' ) ) as $path ) {
            foreach ( array( 'registration-observation-handoff', 'registration-observation-candidate' ) as $part ) $this->assertStringNotContainsString( $part, file_get_contents( $path ) );
        }
        $bad = $f; $bad['form']['barrier_verified'] = true; $this->assertFalse( $this->handoffStep( $this->handoffOwner(), 'stage', $bad ) );
    }
}
