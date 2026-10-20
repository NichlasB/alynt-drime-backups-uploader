<?php
require_once __DIR__ . '/Support/QualificationObservationFixture.php';
/** Counterfactual RECEIPTS only; no source patch/eval, native fault or generated class. */
final class QualificationObservationControlsTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Qualification_Observation_Fixture;
    private function proofOracle( $r, $actual ) {
        $this->assertIsArray( $r ); $this->assertTrue( $r['valid'] ); $this->assertSame( 'closed_array', $r['kind'] );
        $this->assertSame( 1, $r['entries'] ); $this->assertSame( 1, $r['returns'] ); $this->assertSame( $actual, $r['result'] );
        foreach ( array( 'sealed', 'empty_slots', 'capture_unchanged', 'io_unchanged' ) as $k ) $this->assertTrue( $r[$k] );
    }
    public function test_positive_oracle_assertion_kills_six_counterfactual_observation_receipts() {
        list( $o, $pin, $guards ) = $this->observationFixture( 'commit_unknown' );
        $actual = $this->observe( $o, $pin, $guards ); $r = $this->observed( $o ); $this->assertIsArray( $actual ); $this->proofOracle( $r, $actual );
        foreach ( array( 'missing_call', 'always_false', 'fabricated_result', 'unsealed', 'changed_capture', 'io' ) as $case ) {
            $bad = $r;
            if ( 'missing_call' === $case ) $bad['entries'] = 0;
            elseif ( 'always_false' === $case ) { $bad['kind'] = 'false'; $bad['result'] = null; }
            elseif ( 'fabricated_result' === $case ) $bad['result']['historical_outcome'] = 'committed';
            elseif ( 'unsealed' === $case ) $bad['sealed'] = false;
            elseif ( 'changed_capture' === $case ) $bad['capture_unchanged'] = false;
            else $bad['io_unchanged'] = false;
            $killed = false;
            try { $this->proofOracle( $bad, $actual ); }
            catch ( PHPUnit\Framework\ExpectationFailedException $e ) { $killed = true; }
            $this->assertTrue( $killed, $case . ' must fail a meaningful assertion, not a bootstrap/type error.' );
        }
    }
}
