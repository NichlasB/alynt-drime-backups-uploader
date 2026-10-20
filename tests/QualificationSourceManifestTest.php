<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/QualificationSourceManifest.php';
/** Bounded read-only source manifest controls, not native provenance or attestation. */
final class QualificationSourceManifestTest extends TestCase {
    private function call( $method, ...$args ) {
        $m = new ReflectionMethod( Alynt_Test_Qualification_Source_Manifest::class, $method ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true ); return $m->invokeArgs( null, $args );
    }
    public function test_reference_and_bitwise_ampersands_have_the_php74_token_identity() {
        $tokens = array( array( 'T_FUNCTION', 'function' ), array( 'T_STRING', 'probe' ), '(', '&', array( 'T_VARIABLE', '$value' ), ')', '{', array( 'T_RETURN', 'return' ), array( 'T_VARIABLE', '$value' ), '&', array( 'T_LNUMBER', '1' ), ';', '}' );
        $source = 'function probe( &$value ) { return $value & 1; }';
        $expected = hash( 'sha256', json_encode( $tokens ) );
        $this->assertSame( $expected, $this->call( 'token_digest', $source ), 'PHP8 named ampersands must preserve the literal PHP7.4 identity.' );
        $this->assertNotSame( $expected, $this->call( 'token_digest', 'function probe( $value ) { return $value & 1; }' ), 'By-reference syntax must not disappear.' );
        $this->assertNotSame( $expected, $this->call( 'token_digest', 'function probe( &$value ) { return $value | 1; }' ), 'Operators must still differ.' );
    }
    public function test_complete_actual_source_and_state_manifest_has_a_positive_control() {
        $this->assertTrue( Alynt_Test_Qualification_Source_Manifest::verify() );
        $this->assertFalse( Alynt_Test_Qualification_Source_Manifest::verify( 'provider' ) );
        $s = $this->call( 'snapshot', Alynt_Drime_Backups_Uploader_Registration_Native_Admission::class );
        $p = $this->call( 'snapshot', Alynt_Test_Qualification_Observation_Owner::class );
        $this->assertCount( 67, $s['methods'] ); $this->assertCount( 29, $s['properties'] ); $this->assertCount( 20, $s['traits'] );
        $this->assertTrue( $this->call( 'compare', $s, $p ) );
        foreach ( $s['methods'] as $name => $unused ) {
            $bad = $p; unset( $bad['methods'][$name] ); $this->assertFalse( $this->call( 'compare', $s, $bad ), 'Missing original method: ' . $name );
        }
    }
    /** @dataProvider changes */
    public function test_one_descriptor_fault_cannot_pass_the_actual_comparison( $case ) {
        $s = $this->call( 'snapshot', Alynt_Drime_Backups_Uploader_Registration_Native_Admission::class ); $p = $this->call( 'snapshot', Alynt_Test_Qualification_Observation_Owner::class );
        if ( 'method' === $case ) $p['methods']['guard']['body'] = str_repeat( '0', 64 );
        elseif ( 'qualifier' === $case ) $p['methods']['qualification_source']['body'] = str_repeat( '0', 64 );
        elseif ( 'factory' === $case ) $p['methods']['create']['body'] = str_repeat( '0', 64 );
        elseif ( 'extra' === $case ) $p['methods']['public_success'] = $p['methods']['create'];
        elseif ( 'parameter' === $case ) $p['methods']['guard']['parameters'][0][1] = 'object';
        elseif ( 'reference' === $case ) $p['methods']['plain']['parameters'][2][2] = false;
        elseif ( 'property' === $case ) $p['properties']['admission_qualification_spent'][3] = true;
        elseif ( 'observer' === $case ) $p['properties']['qualification_observation_spent'][0] = ReflectionProperty::IS_PUBLIC;
        elseif ( 'constant' === $case ) $p['constants']['EVENTS'][1][0] = 'invented';
        elseif ( 'trait' === $case ) array_pop( $p['direct_traits'] );
        elseif ( 'alias' === $case ) $p['aliases']['qualification_source'] = 'Other::qualify';
        elseif ( 'file' === $case ) $p['methods']['admission_root']['file'] = 'tests/Other.php';
        elseif ( 'line' === $case ) $p['methods']['finish']['start']++;
        elseif ( 'parent' === $case ) $p['parent'] = true;
        elseif ( 'source' === $case ) $s['methods']['guard']['body'] = str_repeat( '0', 64 );
        $this->assertFalse( $this->call( 'compare', $s, $p ), $case );
    }
    public function changes() { return array_map( function ( $v ) { return array( $v ); }, array( 'method', 'qualifier', 'factory', 'extra', 'parameter', 'reference', 'property', 'observer', 'constant', 'trait', 'alias', 'file', 'line', 'parent', 'source' ) ); }
}
