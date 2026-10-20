<?php
require_once __DIR__ . '/Support/RegistrationClosedQualificationFixture.php';
/** Positive and unknown/late-loss DATA; never actual native evidence. */
final class RegistrationClosedQualificationTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Registration_Closed_Qualification_Fixture;
    public function test_positive_histories_are_closed_without_authority_or_capture_mutation() {
        $this->assertTrue( class_exists( 'Alynt_Drime_Backups_Uploader_Registration_Closed_Qualification' ), 'Approved closed DATA consumer is missing.' );
        foreach ( array( 'committed' => 'committed', 'rolled_back' => 'rolled_back', 'bind_failure' => 'not_written', 'never_created' => 'not_written', 'uncertain' => 'uncertain', 'commit_unknown' => 'uncertain', 'commit_ack_only' => 'uncertain' ) as $case => $history ) {
            $args = $this->qualificationFixture( $case ); $before = $args; $o = $this->qualificationOwner();
            $this->assertQualification( $this->qualification( $o, $args ), $history, true, 'never_created' !== $case );
            $this->assertSame( $before, $args, 'Sealed history and current claims must stay separate and immutable.' );
            $this->assertTrue( $this->admissionProperty( $o, 'spent' ) ); $this->assertFalse( $this->qualification( $o, $args ) );
        }
    }
    public function test_raw_false_remains_unknown_not_definite_rejection() {
        foreach ( array( 'dml' => 'uncertain', 'commit' => 'commit_unknown', 'close' => 'committed', 'rollback' => 'rolled_back', 'begin' => 'rolled_back' ) as $command => $case ) {
            $args = $this->qualificationFixture( $case );
            if ( 'begin' === $command ) {
                foreach ( array( 'begin_ack_at', 'transaction_owned', 'bind_verified', 'dml_sent', 'rollback_sent', 'rollback_ack_at', 'rollback_idle' ) as $event ) $args[5]['facts']['events'][$event] = 0;
                $args[5]['facts']['acks']['dml'] = 'not_sent'; $args[5]['raw_returns']['dml'] = 'not_called'; $args[5]['facts']['affected_rows'] = 'not_observed';
                $args[5]['facts']['acks']['rollback'] = 'not_sent'; $args[5]['raw_returns']['rollback'] = 'not_called';
            }
            if ( in_array( $command, array( 'begin', 'rollback', 'close' ), true ) ) {
                $args[5]['facts']['events']['close' === $command ? 'close_at' : $command . '_ack_at'] = 0;
                if ( 'rollback' === $command ) $args[5]['facts']['events']['rollback_idle'] = 0;
                $args[5]['facts']['acks'][$command] = 'unknown';
            }
            $args[5]['raw_returns'][$command] = false; $this->compactQualificationEvents( $args );
            $expected = in_array( $command, array( 'begin', 'rollback' ), true ) ? 'unsupported' : ( 'close' === $command ? 'committed' : 'uncertain' );
            $this->assertQualification( $this->qualification( $this->qualificationOwner(), $args ), $expected, 'close' !== $command, 'close' !== $command );
        }
    }
    public function test_late_guard_loss_never_rewrites_committed_history_or_retries() {
        foreach ( array( 'auth', 'intent', 'physical', 'unreentered' ) as $key ) foreach ( array( false, null ) as $loss ) {
            $args = $this->qualificationFixture(); $args[7]['guards'][$key] = $loss; $o = $this->qualificationOwner();
            $this->assertQualification( $this->qualification( $o, $args ), 'committed', true, false );
            $this->assertFalse( $this->qualification( $o, $this->qualificationFixture() ) );
        }
    }
    public function test_original_half_open_clock_and_expiry_preserve_history_without_acceptance() {
        $args = $this->qualificationFixture(); $args[4] = $this->admissionClock( 10000000099, 111 );
        $this->assertQualification( $this->qualification( $this->qualificationOwner(), $args ) );
        foreach ( array( $this->admissionClock( 10000000100, 111 ), $this->admissionClock( 199, 111 ), $this->admissionClock( 300, 109 ), $this->admissionClock( 300, 111, 101 ), $this->admissionClock( 300, $args[0]['mutation']['expires_at'] ) ) as $clock ) {
            $args[4] = $clock; $this->assertQualification( $this->qualification( $this->qualificationOwner(), $args ), 'committed', true, false );
        }
    }
    public function test_synthetic_general_uncertainty_does_not_open_closed_policy() {
        $general = $this->outcome( $this->outcomeOwner(), $this->outcomeFacts() ); $this->assertTrue( $general['diagnostic_candidate_only'] );
        $this->assertQualification( $this->qualification( $this->qualificationOwner(), $this->qualificationFixture( 'uncertain' ) ), 'uncertain' );
    }
}
