<?php
require_once __DIR__ . '/RegistrationObservationConstructionFixture.php';
require_once __DIR__ . '/RegistrationAdmissionJoinHarness.php';
/** API-only orchestration; no sockets, native handles, WordPress, users or database. */
trait Alynt_Test_Registration_Admission_Join_Fixture {
    use Alynt_Test_Registration_Observation_Construction_Fixture { setUp as protected setupJoinApis; }
    private $join;
    protected function setUp(): void { $this->setupJoinApis(); $this->join = new Alynt_Admission_Join_Harness( $this->registrationFrame() ); }
    private function joinGuards( &$facts, $fault = null ) {
        $GLOBALS['alynt_core_request_test']['on']['verify_nonce'] = function () use ( &$facts, $fault ) {
            $calls = $this->registrationCalls( 'verify_nonce' ); $call = end( $calls );
            $facts[] = array( 'action' => $call[1][1], 'origin' => $this->join->state( 'admission_origin' ), 'ids' => $this->join->state( 'admission_ids' ), 'clock' => $this->join->state( 'admission_clock' ), 'started' => $this->join->state( 'started' ), 'phase' => $this->join->state( 'phase' ), 'writes' => $this->join->state( 'writes' ) );
            $f = $GLOBALS['alynt_test_owned_filter_inventory'];
            $this->assertCount( 2, $f ); $this->assertSame( array(), $f['pre_wp_load_alloptions'][0][0]() );
            $blocked = false; try { $f['query'][0][0]( 'SELECT option_value FROM wp_options LIMIT 1' ); } catch ( RuntimeException $e ) { $blocked = true; }
            $this->assertTrue( $blocked );
            $this->joinGuards( $facts, $fault ); if ( null !== $fault ) $fault( count( $facts ) );
        };
    }
    private function joinCleared() {
        foreach ( array( 'admission_origin', 'admission_clock', 'scope', 'reader', 'physical', 'read_physical', 'before', 'proposal', 'context', 'after', 'recorded_at', 'native_outcome' ) as $key ) $this->assertNull( $this->join->state( $key ), $key );
        $this->assertSame( array(), $this->join->state( 'admission_ids' ) ); $this->assertFalse( $this->join->state( 'admission_join_busy' ) );
        $this->assertFalse( $this->join->state( 'active' ) ); $this->assertTrue( $this->join->state( 'attempted' ) ); $this->assertTrue( $this->join->state( 'consumed' ) );
        $this->assertSame( array(), $GLOBALS['alynt_test_owned_filter_inventory'] ); $this->assertSame( array(), $this->registrationCalls( 'protected_io' ) );
        $this->assertSame( array(), $this->registrationCalls( 'query' ) ); $this->assertFalse( $this->join->core['db']->dbh->closed );
        $this->assertLessThanOrEqual( 1, $this->join->state( 'writes' ) ); $this->assertLessThanOrEqual( 1, $this->join->closes );
        $before = array( $this->join->state( 'writes' ), count( $this->registrationCalls( 'create_nonce' ) ) );
        $this->assertFalse( $this->join->run( $this->registrationForm() ) );
        $this->assertSame( $before, array( $this->join->state( 'writes' ), count( $this->registrationCalls( 'create_nonce' ) ) ) );
    }
}
