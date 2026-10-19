<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RegistrationAdmissionOutcomeFixture.php';
/** Falsifiable historical/release/acceptance matrix, not physical evidence. */
class RegistrationAdmissionOutcomeTest extends TestCase {
    use Alynt_Test_Registration_Admission_Outcome_Fixture;
    public function test_positive_histories_are_distinct_and_never_authorize_native_work() {
        foreach ( array( 'uncertain' => 'uncertain', 'commit_unknown' => 'uncertain', 'commit_ack_only' => 'uncertain', 'committed' => 'committed', 'rolled_back' => 'rolled_back', 'bind_failure' => 'not_written', 'never_created' => 'not_written' ) as $case => $history ) {
            $o = $this->outcomeOwner(); $r = $this->outcome( $o, $this->outcomeFacts( $case ) );
            $this->assertOutcome( $r, $history, 'uncertain' === $history, true, 'never_created' !== $case );
            $this->assertFalse( $this->outcome( $o, $this->outcomeFacts() ) );
        }
    }
    public function test_reserved_attempt_is_not_a_dml_send_marker() {
        $facts = $this->outcomeFacts( 'bind_failure' ); $this->assertGreaterThan( 0, $facts['events']['attempt_reserved'] ); $this->assertSame( 0, $facts['events']['dml_sent'] );
        $this->assertOutcome( $this->outcome( $this->outcomeOwner(), $facts ), 'not_written' );
        foreach ( array( 'unknown', 'no', 'yes' ) as $ack ) {
            $facts = $this->outcomeFacts( 'rolled_back' ); $facts['acks']['dml'] = $ack;
            if ( 'unknown' !== $ack ) { $facts['events']['dml_ack_at'] = 8; foreach ( array( 'rollback_sent', 'rollback_ack_at', 'rollback_idle', 'detached', 'close_at', 'accepted_at' ) as $key ) $facts['events'][$key]++; }
            $facts['affected_rows'] = 'yes' === $ack ? 'zero' : 'unknown';
            $this->assertOutcome( $this->outcome( $this->outcomeOwner(), $facts ), 'rolled_back' );
        }
    }
    public function test_late_close_or_current_guard_failure_preserves_known_commit() {
        foreach ( array( 'close_no', 'close_unknown', 'not_detached', 'remaining', 'unknown_handle', 'auth', 'intent', 'physical', 'unreentered', 'unknown_guard' ) as $fault ) {
            $facts = $this->outcomeFacts( 'committed' ); $facts['events']['accepted_at'] = 0; $released = true;
            if ( 'close_no' === $fault ) { $facts['acks']['close'] = 'no'; $released = false; }
            elseif ( 'close_unknown' === $fault ) { $facts['acks']['close'] = 'unknown'; $facts['events']['close_at'] = 0; $released = false; }
            elseif ( 'not_detached' === $fault ) { $facts['events']['detached'] = 0; $facts['events']['close_at'] = 0; $facts['acks']['close'] = 'not_sent'; $released = false; }
            elseif ( in_array( $fault, array( 'remaining', 'unknown_handle' ), true ) ) { $facts['handle_remaining'] = 'remaining' === $fault ? true : null; $released = false; }
            elseif ( 'unknown_guard' === $fault ) $facts['guards']['auth'] = null;
            else $facts['guards'][$fault] = false;
            $o = $this->outcomeOwner(); $this->assertOutcome( $this->outcome( $o, $facts ), 'committed', false, $released, false );
            $this->assertFalse( $this->outcome( $o, $this->outcomeFacts( 'committed' ) ), 'Cannot retry a failed guard.' );
        }
    }
    public function test_uncertainty_needs_every_release_and_current_claim() {
        foreach ( array( 'close', 'detach', 'handle', 'auth', 'intent', 'physical', 'unreentered', 'not_accepted' ) as $fault ) {
            $facts = $this->outcomeFacts(); $facts['events']['accepted_at'] = 0; $released = true;
            if ( 'close' === $fault ) { $facts['acks']['close'] = 'no'; $released = false; }
            elseif ( 'detach' === $fault ) { $facts['events']['detached'] = 0; $facts['events']['close_at'] = 0; $facts['acks']['close'] = 'not_sent'; $released = false; }
            elseif ( 'handle' === $fault ) { $facts['handle_remaining'] = true; $released = false; }
            elseif ( 'not_accepted' !== $fault ) $facts['guards'][$fault] = false;
            $this->assertOutcome( $this->outcome( $this->outcomeOwner(), $facts ), 'uncertain', false, $released, false );
        }
    }
    public function test_unknown_begin_ownership_and_rollback_are_unsupported_not_uncertain_candidates() {
        foreach ( array( 'begin_ack', 'ownership', 'rollback_ack', 'rollback_idle' ) as $fault ) {
            $facts = $this->outcomeFacts( 'rolled_back' );
            if ( in_array( $fault, array( 'begin_ack', 'ownership' ), true ) ) {
                foreach ( array( 'transaction_owned', 'bind_verified', 'dml_sent', 'rollback_sent', 'rollback_ack_at', 'rollback_idle' ) as $key ) $facts['events'][$key] = 0;
                $facts['acks']['dml'] = 'not_sent'; $facts['acks']['rollback'] = 'not_sent'; $facts['affected_rows'] = 'not_observed';
                if ( 'begin_ack' === $fault ) { $facts['acks']['begin'] = 'unknown'; $facts['events']['begin_ack_at'] = 0; }
            } else { $facts['events']['rollback_idle'] = 0; if ( 'rollback_ack' === $fault ) { $facts['acks']['rollback'] = 'unknown'; $facts['events']['rollback_ack_at'] = 0; } }
            $this->assertOutcome( $this->outcome( $this->outcomeOwner(), $facts ), 'unsupported' );
        }
    }
    public function test_each_prerequisite_cannot_be_removed_or_reordered() {
        $good = $this->outcomeFacts( 'committed' );
        foreach ( array( 'writer_created', 'attempt_reserved', 'begin_sent', 'begin_ack_at', 'transaction_owned', 'bind_verified', 'dml_sent', 'dml_ack_at', 'readback', 'commit_sent', 'commit_ack_at' ) as $key ) {
            $facts = $good; $facts['events'][$key] = 0; $this->outcomeRefused( $facts );
        }
        foreach ( array( 'duplicate', 'rollback_after_commit', 'bad_readback', 'bad_commit_idle', 'after_detach', 'after_rollback', 'false_acceptance' ) as $fault ) {
            $facts = $good;
            if ( 'duplicate' === $fault ) $facts['events']['commit_idle'] = $facts['events']['readback'];
            elseif ( 'rollback_after_commit' === $fault ) { $facts['events']['rollback_sent'] = 20; $facts['acks']['rollback'] = 'unknown'; }
            elseif ( 'bad_readback' === $fault ) $facts['affected_rows'] = 'zero';
            elseif ( 'bad_commit_idle' === $fault ) $facts['acks']['commit'] = 'no';
            elseif ( 'after_detach' === $fault ) $facts['events']['commit_idle'] = 25;
            elseif ( 'after_rollback' === $fault ) { $facts = $this->outcomeFacts( 'rolled_back' ); $facts['events']['bind_verified'] = 25; }
            else $facts['guards']['auth'] = false;
            $this->outcomeRefused( $facts );
        }
    }
    public function test_acknowledgements_cannot_be_inferred_from_send_or_other_operations() {
        foreach ( array( 'begin', 'dml', 'commit', 'rollback', 'close' ) as $command ) foreach ( array( 'not_sent', 'unknown', false, null, 1, array() ) as $bad ) {
            $facts = $this->outcomeFacts( 'rollback' === $command ? 'rolled_back' : 'committed' ); $facts['acks'][$command] = $bad;
            $this->outcomeRefused( $facts );
        }
        $facts = $this->outcomeFacts( 'committed' );
        $facts['events']['begin_sent'] = 4; $facts['events']['begin_ack_at'] = 3;
        $this->outcomeRefused( $facts );
        $facts = $this->outcomeFacts(); $facts['acks']['dml'] = 'no';
        $this->outcomeRefused( $facts );
    }
}
