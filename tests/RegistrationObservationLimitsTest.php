<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RegistrationObservationFixture.php';
require_once dirname( __DIR__ ) . '/includes/trait-registration-observation-data.php';
/** Test-only pure primitive exposure: not an origin factory or native capability. */
class RegistrationObservationDataProbe {
    use Alynt_Drime_Backups_Uploader_Registration_Observation_Data { bounded as public check; }
}
class RegistrationObservationLimitsTest extends TestCase {
    public function test_encoded_byte_node_depth_and_reference_bounds_are_independently_discriminating() {
        $c = 'RegistrationObservationDataProbe';
        $this->assertTrue( $c::check( str_repeat( 'x', 4094 ) ) ); $this->assertFalse( $c::check( str_repeat( 'x', 4095 ) ) );
        $this->assertFalse( $c::check( str_repeat( '"', 2048 ) ) ); // Small raw bytes, excessive encoded escaping.
        $this->assertTrue( $c::check( array_fill( 0, 127, null ) ) ); $this->assertFalse( $c::check( array_fill( 0, 128, null ) ) );
        $v = 'leaf'; for ( $i = 0; $i < 6; ++$i ) $v = array( $v ); $this->assertTrue( $c::check( $v ) ); $this->assertFalse( $c::check( array( $v ) ) );
        $bad = array( 'same' => 'value' ); $bad['copy'] =& $bad['same']; $this->assertFalse( $c::check( $bad ) );
        $cycle = array(); $cycle['self'] =& $cycle; $this->assertFalse( $c::check( $cycle ) );
        foreach ( array( new stdClass(), NAN, INF, 1.0, "\xff" ) as $value ) $this->assertFalse( $c::check( $value ) );
    }
}
