<?php
require_once __DIR__ . '/Support/RegistrationObservationHandoffFixture.php';
require_once dirname( __DIR__ ) . '/scripts/tests/native-qualification-selected-channel.php';
/** Pure original DATA classification plus modeled native postprocessing, NOT a native proof. */
final class NativeSelectedFinalProjectionTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Registration_Observation_Handoff_Fixture;
    private function sourceResult() {
        $result = $this->candidateResult( $this->handoffFixture() );
        $this->assertCandidateOnly( $result, 'registered_closed' );
        // The actual native read owns these two updates; this unit test models them only.
        $result['barrier_verified'] = true; $result['candidate_only'] = false;
        return $result;
    }
    private function project( $result ) {
        $m = new ReflectionMethod( Alynt_Test_Native_Qualification_Selected_Channel::class, 'project_native_result' );
        if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true );
        return $m->invoke( null, $result );
    }
    public function test_original_data_result_order_is_projected_without_mutating_or_reclassifying() {
        $r = $this->sourceResult(); $before = serialize( $r );
        $this->assertSame( array( 'candidate_only','storage_supported','dispatch_supported','execution_supported','retry_supported','authenticated','classification','barrier_verified','closure_proven' ), array_keys( $r ) );
        $wire = $this->project( $r );
        $this->assertSame( array( 'classification','authenticated','barrier_verified','closure_proven','retry_supported','storage_supported','dispatch_supported','execution_supported','candidate_only' ), array_keys( $wire ) );
        foreach ( $r as $key => $value ) $this->assertSame( $value, $wire[$key] );
        $this->assertSame( $before, serialize( $r ) );
    }
    public function test_each_original_field_is_required_typed_and_unreferenced_before_projection() {
        $r = $this->sourceResult();
        foreach ( $r as $key => $value ) {
            $bad = $r; unset( $bad[$key] ); $this->assertFalse( $this->project( $bad ), $key . '/missing' );
            foreach ( array( null, 0, 1, 'false', new stdClass(), array(), is_bool( $value ) ? ! $value : 'unavailable' ) as $wrong ) {
                $bad = $r; $bad[$key] = $wrong; $this->assertFalse( $this->project( $bad ), $key . '/value' );
            }
            $bad = $r; $alias = $value; $bad[$key] =& $alias; $this->assertFalse( $this->project( $bad ), $key . '/reference' ); unset( $bad, $alias );
        }
        $this->assertFalse( $this->project( $r + array( 'write_handle' => null ) ) );
        $this->assertFalse( $this->project( array_reverse( $r, true ) ) );
        $this->assertFalse( $this->project( $this->project( $r ) ), 'wire-shaped input is not original source evidence' );
        foreach ( array( false, null, 'registered_closed', new stdClass() ) as $bad ) $this->assertFalse( $this->project( $bad ) );
    }
}
