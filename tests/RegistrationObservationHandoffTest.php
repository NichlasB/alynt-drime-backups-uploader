<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RegistrationObservationHandoffFixture.php';
/** Private one-shot primitive transfer, not real ownership/auth/native phases. */
class RegistrationObservationHandoffTest extends TestCase {
    use Alynt_Test_Registration_Observation_Handoff_Fixture;
    public function test_valid_one_shot_claim_removes_staged_slot_and_returns_candidate_only() {
        foreach ( array( 'registered', 'reserved', 'empty' ) as $phase ) {
            $f = $this->handoffFixture( $phase ); $o = $this->handoffOwner(); $this->assertTrue( $this->handoffStep( $o, 'stage', $f ) );
            $f['clock'] = $this->admissionClock( 300, 511 ); $this->assertTrue( $this->handoffStep( $o, 'claim', $f ) ); $this->assertNull( $this->admissionProperty( $o, 'staged' ) );
            $f['clock'] = $this->admissionClock( 400, 512 ); $this->assertCandidateOnly( $this->handoffFinish( $o, $f ), 'empty' === $phase ? 'not_observed_in_candidate' : 'registered_closed' );
            $this->assertHandoffCleared( $o ); $this->assertFalse( $this->handoffFinish( $o, $f ) ); $this->assertFalse( $this->handoffStep( $o, 'claim', $f ) ); $this->assertFalse( $this->handoffStep( $o, 'stage', $f ) );
        }
    }
    public function test_out_of_order_repeated_and_interleaved_attempts_consume_instead_of_reopening() {
        $f = $this->handoffFixture();
        foreach ( array( array( 'claim' ), array( 'finish' ), array( 'stage', 'stage' ), array( 'stage', 'finish' ), array( 'stage', 'claim', 'claim' ), array( 'stage', 'claim', 'stage' ) ) as $trace ) {
            $o = $this->handoffOwner(); foreach ( $trace as $step ) { if ( 'finish' === $step ) $this->assertFalse( $this->handoffFinish( $o, $f ) ); else $this->handoffStep( $o, $step, $f ); }
            $this->assertHandoffCleared( $o ); $this->assertFalse( $this->handoffStep( $o, 'stage', $f ) ); $this->assertFalse( $this->handoffStep( $o, 'claim', $f ) ); $this->assertFalse( $this->handoffFinish( $o, $f ) );
        }
    }
    public function test_claim_binds_original_origin_both_nonce_bytes_and_ordered_issued_set() {
        $f = $this->handoffFixture();
        foreach ( array( 'data', 'intent', 'form', 'ids' ) as $part ) {
            $o = $this->handoffOwner(); $this->assertTrue( $this->handoffStep( $o, 'stage', $f ) ); $bad = $f;
            if ( 'data' === $part ) $bad[$part]['actor_id']++; elseif ( 'ids' === $part ) $bad[$part] = array_reverse( $bad[$part] ); else $bad[$part]['nonce'] .= '-changed';
            $this->assertFalse( $this->handoffStep( $o, 'claim', $bad ), $part ); $this->assertHandoffCleared( $o ); $this->assertFalse( $this->handoffStep( $o, 'claim', $f ) );
        }
    }
    public function test_original_clock_is_preserved_at_both_transfer_and_finish() {
        $f = $this->handoffFixture();
        foreach ( array( 'claim', 'finish' ) as $phase ) {
            foreach ( array( $this->admissionClock( 300, 511, 101 ), $this->admissionClock( 199, 511 ), $this->admissionClock( 300, 509 ), $this->admissionClock( 10000000100, 511 ), $this->admissionClock( 300, 800 ), array( 'started_ns' => 100, 'now_ns' => '300', 'utc' => 511 ) ) as $clock ) {
                $o = $this->handoffOwner(); $this->assertTrue( $this->handoffStep( $o, 'stage', $f ) ); if ( 'finish' === $phase ) $this->assertTrue( $this->handoffStep( $o, 'claim', $f ) );
                $bad = $f; $bad['clock'] = $clock; $this->assertFalse( 'claim' === $phase ? $this->handoffStep( $o, 'claim', $bad ) : $this->handoffFinish( $o, $bad ) ); $this->assertHandoffCleared( $o );
            }
        }
        $o = $this->handoffOwner(); $this->assertTrue( $this->handoffStep( $o, 'stage', $f ) ); $f['clock'] = $this->admissionClock( 500, 520 ); $this->assertTrue( $this->handoffStep( $o, 'claim', $f ) );
        $bad = $f; $bad['clock'] = $this->admissionClock( 499, 521 ); $this->assertFalse( $this->handoffFinish( $o, $bad ), 'Finish compares with claim, not only stage.' );
    }
    public function test_caller_edits_do_not_mutate_private_facts_and_last_in_budget_time_is_valid() {
        $f = $this->handoffFixture(); $original = $f; $o = $this->handoffOwner(); $this->assertTrue( $this->handoffStep( $o, 'stage', $f ) );
        $f['data']['actor_id']++; $f['intent']['nonce'] .= '-edited'; $f['form']['nonce'] .= '-edited'; $f['ids'][] = $this->rid( 999 );
        $this->assertTrue( $this->handoffStep( $o, 'claim', $original ) ); $original['clock'] = $this->admissionClock( 10000000099, 799 );
        $this->assertCandidateOnly( $this->handoffFinish( $o, $original ), 'registered_closed' ); $this->assertHandoffCleared( $o );
    }
    public function test_refusal_clears_before_candidate_parsing_or_form_comparison() {
        $f = $this->handoffFixture();
        foreach ( array( 'intent', 'form', 'frame' ) as $part ) {
            $o = $this->handoffOwner(); $this->assertTrue( $this->handoffStep( $o, 'stage', $f ) ); $this->assertTrue( $this->handoffStep( $o, 'claim', $f ) ); $bad = $f;
            if ( 'frame' === $part ) $bad[$part] = new stdClass(); else $bad[$part]['nonce'] .= '-changed';
            $this->assertFalse( $this->handoffFinish( $o, $bad ), $part ); $this->assertHandoffCleared( $o ); $this->assertFalse( $this->handoffFinish( $o, $f ) );
        }
    }
    public function test_reserved_lookup_collision_or_missing_origin_identity_refuses_without_reminting() {
        $f = $this->handoffFixture();
        foreach ( $f['ids'] as $id ) { $bad = $f; $bad['form']['command_id'] = $id; $o = $this->handoffOwner(); $this->assertFalse( $this->handoffStep( $o, 'stage', $bad ) ); $this->assertHandoffCleared( $o ); $this->assertFalse( $this->handoffStep( $o, 'stage', $f ) ); }
        foreach ( array( array_slice( $f['ids'], 1 ), array_merge( $f['ids'], array( $f['ids'][0] ) ), array( 'bad' ), array_fill( 0, 129, $this->rid( 999 ) ) ) as $ids ) { $bad = $f; $bad['ids'] = $ids; $this->assertFalse( $this->handoffStep( $this->handoffOwner(), 'stage', $bad ) ); }
    }
    public function test_small_and_full_request_syntax_and_origin_binding_cannot_be_bypassed() {
        $good = $this->handoffFixture();
        foreach ( array( 'purpose' => 'observe_reservation', 'observed_command_id' => $this->rid( 999 ), 'nonce' => '', 'extra' => 'x' ) as $key => $value ) {
            foreach ( array( 'intent', 'form' ) as $part ) { $bad = $good; $bad[$part][$key] = $value; $this->assertFalse( $this->handoffStep( $this->handoffOwner(), 'stage', $bad ), "$part/$key" ); }
        }
        $bad = $good; $bad['data'] = Alynt_Drime_Backups_Uploader_Registration_Observation_Origin::prepare( $good['data'] ); $this->assertFalse( $this->handoffStep( $this->handoffOwner(), 'stage', $bad ) );
    }
}
