<?php
require_once __DIR__ . '/Support/RegistrationAdmissionJoinFixture.php';
/** @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class RegistrationNativeQualificationTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Registration_Admission_Join_Fixture;
    private function result( $history = 'committed', $current = true ) {
        $this->assertCount( 1, $this->join->qualifications, 'Root must consume exactly one qualification.' );
        $sample = $this->join->qualifications[0]; $r = $sample['result'];
        $this->assertIsArray( $r, 'Always-unavailable qualifier must fail.' ); $this->assertCount( 13, $r );
        $this->assertSame( $history, $r['historical_outcome'] ); $this->assertSame( $current, $r['current_acceptance_claimed'] );
        $this->assertTrue( $r['request_lifecycle_closed_claimed'] ); $this->assertTrue( $r['candidate_only'] );
        foreach ( array( 'diagnostic_candidate_only', 'authenticated', 'barrier_verified', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $key ) $this->assertFalse( $r[$key], $key );
        $this->assertSame( $sample['before'], $sample['after'], 'Postrelease qualification never reopens the native seal.' );
        $this->assertSame( 0, $sample['after']['facts']['events']['accepted_at'] );
        $this->assertSame( array_fill_keys( array( 'auth', 'intent', 'physical', 'unreentered' ), null ), $sample['after']['facts']['guards'] );
        return $sample;
    }
    public function test_positive_root_qualifies_its_own_sealed_map_and_preserves_nonce_order() {
        $this->assertFileExists( dirname( __DIR__ ) . '/includes/trait-registration-admission-qualification.php', 'Approved fixed source integration is required.' );
        $facts = array(); $this->joinGuards( $facts, function ( $n ) {
            if ( $n < 9 ) return;
            foreach ( array( 'reader', 'native_outcome_writer', 'physical', 'read_physical' ) as $key ) $this->assertNull( $this->join->state( $key ), 'Before final WordPress API doubles: ' . $key );
            $this->assertTrue( $this->join->state( 'native_cleanup_done' ) ); $this->assertTrue( $this->join->state( 'native_outcome_closed' ) );
        } );
        $this->assertTrue( $this->join->run( $this->registrationForm() ) ); $sample = $this->result();
        $this->assertTrue( $sample['result']['client_release_claimed'] ); $this->assertTrue( $sample['spent'] );
        $this->assertFalse( $this->join->qualification_replay, 'The original slots cannot be qualified twice.' );
        $this->assertCount( 10, $facts ); $this->assertCount( 1, $this->registrationCalls( 'create_nonce' ) );
        $small = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( 1, 'register_closed' );
        $full = $this->registrationCalls( 'create_nonce' )[0][1][0]; $this->assertNotSame( $small, $full );
        $this->assertSame( array( $small, $small, $small, $small, $small, $full, $small, $full, $small, $full ), array_column( $facts, 'action' ) );
        foreach ( array_slice( $facts, 8 ) as $fact ) { $this->assertSame( 'committed', $fact['phase'] ); $this->assertSame( 1, $fact['writes'] ); }
        $this->assertSame( 1, $this->join->closes ); $this->joinCleared(); $this->assertCount( 1, $this->join->qualifications );
    }
    /** @dataProvider lateGuards */
    public function test_late_failed_guard_preserves_history_but_not_acceptance( $gate ) {
        $facts = array(); $this->joinGuards( $facts, function ( $n ) use ( $gate ) { if ( $gate === $n ) $GLOBALS['alynt_core_request_test']['nonce_result'] = false; } );
        $this->assertFalse( $this->join->run( $this->registrationForm() ) ); $sample = $this->result( 'committed', false );
        $this->assertFalse( $sample['guards']['intent'] ); $this->assertCount( $gate, $facts ); $this->joinCleared();
    }
    public function lateGuards() { return array( array( 9 ), array( 10 ) ); }
    /** @dataProvider failedHistories */
    public function test_native_false_qualifies_history_without_new_current_guards_or_observer( $fault, $history ) {
        $facts = array(); $this->joinGuards( $facts ); $this->join->fault = $fault;
        $this->assertFalse( $this->join->run( $this->registrationForm() ) ); $sample = $this->result( $history, false );
        $this->assertCount( 8, $facts ); $this->assertNull( $sample['guards']['auth'] ); $this->assertNull( $sample['guards']['intent'] ); $this->assertNull( $sample['guards']['physical'] );
        $this->joinCleared();
    }
    public function failedHistories() { return array( array( 'uncertain', 'uncertain' ), array( 'rolled_back', 'rolled_back' ), array( 'not_written', 'not_written' ) ); }
    public function test_false_close_cannot_rewrite_commit_or_claim_release_and_skips_final_guards() {
        $facts = array(); $this->joinGuards( $facts ); $this->join->fault = 'close';
        $this->assertFalse( $this->join->run( $this->registrationForm() ) ); $sample = $this->result( 'committed', false );
        $this->assertFalse( $sample['result']['client_release_claimed'] ); $this->assertCount( 8, $facts );
        $this->assertSame( 'unknown', $sample['after']['facts']['acks']['close'] ); $this->assertFalse( $sample['after']['raw_returns']['close'] ); $this->joinCleared();
    }
    /** @dataProvider inconsistentQualifications */
    public function test_successful_phase_still_requires_both_qualified_commit_history_and_current_acceptance( $fault, $history, $current ) {
        // Deliberately mismatched synthetic capture/phase, not native provenance.
        $this->join->qualification_fault = $fault;
        $this->assertFalse( $this->join->run( $this->registrationForm() ) ); $this->result( $history, $current );
        $this->assertSame( 'committed', $this->join->state( 'phase' ) ); $this->joinCleared();
    }
    public function inconsistentQualifications() { return array( array( 'history', 'rolled_back', true ), array( 'missing_release', 'committed', false ) ); }
    /** @dataProvider ownerFaults */
    public function test_owner_seal_and_slot_faults_cannot_certify_a_successful_phase( $fault ) {
        $this->join->qualification_fault = $fault;
        $this->assertFalse( $this->join->run( $this->registrationForm() ) );
        $this->assertCount( 1, $this->join->qualifications ); $this->assertFalse( $this->join->qualifications[0]['result'] );
        $this->assertSame( 'committed', $this->join->state( 'phase' ) ); $this->joinCleared();
    }
    public function ownerFaults() { return array_map( function ( $s ) { return array( $s ); }, array( 'no_capture', 'invalid', 'unsealed', 'unclean', 'reader', 'writer', 'physical', 'read_physical', 'clock_regression', 'raw', 'tick' ) ); }
    /** @dataProvider earlyGuards */
    public function test_early_refusal_never_qualifies_in_finally( $gate ) {
        $facts = array(); $this->joinGuards( $facts, function ( $n ) use ( $gate ) { if ( $gate === $n ) $GLOBALS['alynt_core_request_test']['nonce_result'] = false; } );
        $this->assertFalse( $this->join->run( $this->registrationForm() ) ); $this->assertSame( array(), $this->join->qualifications );
        $this->assertTrue( $this->join->state( 'admission_qualification_spent' ) ); $this->assertSame( 0, $this->join->state( 'writes' ) ); $this->joinCleared();
        if ( 1 === $gate ) $this->assertSame( 0, $this->join->state( 'protected_reads' ) );
    }
    public function earlyGuards() { return array_map( function ( $n ) { return array( $n ); }, range( 1, 8 ) ); }
    /** @dataProvider lateFaults */
    public function test_callback_origin_budget_reentry_or_exception_loss_never_exports_success( $fault ) {
        $facts = array(); $this->joinGuards( $facts, function ( $n ) use ( $fault ) {
            if ( 10 !== $n ) return;
            if ( 'throw' === $fault ) throw new RuntimeException( 'Isolated callback fault.' );
            if ( 'reentry' === $fault ) { $this->assertFalse( $this->join->run( $this->registrationForm(), false ) ); return; }
            if ( 'clock' === $fault ) { $this->join->state( 'started', hrtime( true ) - 10000000001 ); return; }
            $key = 'ids' === $fault ? 'admission_ids' : ( 'origin' === $fault ? 'admission_origin' : ( 'bytes' === $fault ? 'before' : 'proposal' ) );
            $v = $this->join->state( $key ); if ( 'ids' === $fault ) array_pop( $v ); elseif ( 'origin' === $fault ) $v['actor_id']++; elseif ( 'bytes' === $fault ) $v['rows']['queue']['value'] .= ' '; else $v['terminal_id'] = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
            $this->join->state( $key, $v );
        } );
        $this->assertFalse( $this->join->run( $this->registrationForm() ) ); $this->assertSame( 'committed', $this->join->state( 'phase' ) );
        if ( 'throw' === $fault ) $this->assertSame( array(), $this->join->qualifications );
        else { $this->assertCount( 1, $this->join->qualifications ); $this->assertFalse( $this->join->qualifications[0]['result'] ); }
        $this->assertTrue( $this->join->state( 'admission_qualification_spent' ) ); $this->joinCleared();
    }
    public function lateFaults() { return array_map( function ( $s ) { return array( $s ); }, array( 'throw', 'reentry', 'clock', 'ids', 'origin', 'proposal', 'bytes' ) ); }
    public function test_standalone_keeps_nine_guards_and_never_enters_qualification() {
        $facts = array(); $this->joinGuards( $facts ); $this->assertTrue( $this->join->run( $this->registrationForm(), false ) );
        $this->assertCount( 9, $facts ); $this->assertSame( array(), $this->join->qualifications ); $this->joinCleared();
    }
}
