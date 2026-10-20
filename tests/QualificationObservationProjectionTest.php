<?php
require_once __DIR__ . '/Support/QualificationObservationFixture.php';
/** Pure projection controls: invented state is never native provenance. */
final class QualificationObservationProjectionTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Qualification_Observation_Fixture;
    public function test_exact_source_result_is_required_and_authority_is_never_filled_in() {
        list( $o, $pin, $guards ) = $this->observationFixture( 'commit_unknown' ); $result = $this->observe( $o, $pin, $guards );
        $c = $this->admissionProperty( $o, 'native_outcome' ); $s = Alynt_Test_Qualification_Observation_Projection::state( true, true, $c, 40, 1 );
        $positive = Alynt_Test_Qualification_Observation_Projection::receipt( true, $result, $s, $s ); $this->assertTrue( $positive['valid'] ); $this->assertSame( $result, $positive['result'] );
        foreach ( array( 'extra', 'missing', 'history', 'type', 'authority', 'candidate', 'lifecycle' ) as $case ) {
            $bad = $result;
            if ( 'extra' === $case ) $bad['token'] = 'secret';
            elseif ( 'missing' === $case ) unset( $bad['historical_outcome'] );
            elseif ( 'history' === $case ) $bad['historical_outcome'] = 'server_confirmed';
            elseif ( 'type' === $case ) $bad['current_acceptance_claimed'] = 1;
            elseif ( 'authority' === $case ) $bad['execution_supported'] = true;
            elseif ( 'candidate' === $case ) $bad['candidate_only'] = false;
            else $bad['request_lifecycle_closed_claimed'] = false;
            $r = Alynt_Test_Qualification_Observation_Projection::receipt( true, $bad, $s, $s ); $this->assertFalse( $r['valid'], $case ); $this->assertNull( $r['result'], $case );
        }
        foreach ( array( 'sealed', 'empty', 'digest', 'reads', 'writes' ) as $key ) {
            $bad = $s; $bad[$key] = in_array( $key, array( 'sealed', 'empty' ), true ) ? false : ( 'digest' === $key ? str_repeat( '0', 64 ) : 0 );
            $this->assertFalse( Alynt_Test_Qualification_Observation_Projection::receipt( true, $result, $s, $bad )['valid'], $key );
        }
    }
    public function test_real_release_claim_is_not_repaired_from_closed_slots() {
        list( $o, $pin, $guards ) = $this->observationFixture(); $c = $this->admissionProperty( $o, 'native_outcome' );
        $c['facts']['events']['close_at'] = 0; $c['facts']['acks']['close'] = 'unknown'; $c['raw_returns']['close'] = false;
        $this->observationSet( $o, 'native_outcome_tick', max( $c['facts']['events'] ) ); $this->observationSet( $o, 'native_outcome', $c );
        $r = $this->observe( $o, $pin, $guards ); $this->assertIsArray( $r ); $this->assertFalse( $r['client_release_claimed'] );
        $this->assertSame( $r, $this->observed( $o )['result'] ); $this->assertTrue( $this->observed( $o )['valid'] );
    }
    public function test_projection_resource_bounds_reject_outside_and_wrong_type_values() {
        list( $o ) = $this->observationFixture(); $capture = $this->admissionProperty( $o, 'native_outcome' );
        $event = array_keys( $capture['facts']['events'] )[0];
        foreach ( array( 0, 32 ) as $n ) {
            $c = $capture; $c['facts']['events'][$event] = $n;
            $this->assertIsString( Alynt_Test_Qualification_Observation_Projection::state( true, true, $c, 0, 0 )['digest'] );
        }
        foreach ( array( -1, 33, 1.0, '1', null, new stdClass() ) as $n ) {
            $c = $capture; $c['facts']['events'][$event] = $n;
            $this->assertNull( Alynt_Test_Qualification_Observation_Projection::state( true, true, $c, 40, 1 )['digest'] );
        }
        foreach ( array( array( 0, 0 ), array( 40, 1 ) ) as $limits ) {
            $s = Alynt_Test_Qualification_Observation_Projection::state( true, true, $capture, $limits[0], $limits[1] );
            $this->assertSame( $limits[0], $s['reads'] ); $this->assertSame( $limits[1], $s['writes'] );
        }
        foreach ( array( -1, 41, 40.0, '40', null ) as $n ) $this->assertNull( Alynt_Test_Qualification_Observation_Projection::state( true, true, $capture, $n, 1 )['reads'] );
        foreach ( array( -1, 2, 1.0, '1', null ) as $n ) $this->assertNull( Alynt_Test_Qualification_Observation_Projection::state( true, true, $capture, 40, $n )['writes'] );
    }
}
