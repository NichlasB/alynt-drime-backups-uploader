<?php
require_once __DIR__ . '/Support/RegistrationClosedQualificationFixture.php';
/** Exact sealed/owner consistency and original identity failures, in-memory only. */
final class RegistrationClosedQualificationDenialTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Registration_Closed_Qualification_Fixture;
    public function test_every_seal_and_empty_slot_claim_is_required_and_typed() {
        foreach ( array( 'started', 'cleanup_done', 'sealed', 'reader_remaining', 'writer_remaining', 'physical_remaining' ) as $key ) {
            foreach ( array( null, 0, 1, 'true', array(), ! $this->qualificationFixture()[6][$key] ) as $bad ) {
                $args = $this->qualificationFixture(); $args[6][$key] = $bad; $this->qualificationRefused( $args );
            }
            $args = $this->qualificationFixture(); unset( $args[6][$key] ); $this->qualificationRefused( $args );
        }
        foreach ( array( -1, 0, 1, 32, 33, 14.0, '14', true, null ) as $tick ) {
            $args = $this->qualificationFixture(); $args[6]['tick'] = $tick; $this->qualificationRefused( $args );
        }
    }
    public function test_every_raw_ack_pair_and_native_definite_no_are_rejected() {
        foreach ( array( 'begin', 'dml', 'commit', 'rollback', 'close' ) as $command ) {
            foreach ( array( 'not_called', 'unknown', false, null, 1, array(), 'true' ) as $bad ) {
                $args = $this->qualificationFixture( 'rollback' === $command ? 'rolled_back' : 'committed' );
                $args[5]['raw_returns'][$command] = $bad; $this->qualificationRefused( $args );
            }
            $args = $this->qualificationFixture( 'rollback' === $command ? 'rolled_back' : 'committed' );
            // Build a causally valid generic ack-no map. The native adapter must
            // still reject it rather than treating raw false as definite no.
            if ( 'begin' === $command || 'dml' === $command || 'commit' === $command ) {
                $cut = $args[5]['facts']['events'][$command . '_ack_at'];
                foreach ( $args[5]['facts']['events'] as $event => $at ) if ( $at > $cut && ! in_array( $event, array( 'detached', 'close_at' ), true ) ) $args[5]['facts']['events'][$event] = 0;
                foreach ( array( 'dml', 'commit', 'rollback' ) as $later ) if ( 0 === $args[5]['facts']['events'][$later . '_sent'] ) { $args[5]['facts']['acks'][$later] = 'not_sent'; $args[5]['raw_returns'][$later] = 'not_called'; }
                if ( 0 === $args[5]['facts']['events']['dml_sent'] ) $args[5]['facts']['affected_rows'] = 'not_observed';
                elseif ( 'dml' === $command ) $args[5]['facts']['affected_rows'] = 'unknown';
            } elseif ( 'rollback' === $command ) $args[5]['facts']['events']['rollback_idle'] = 0;
            $args[5]['facts']['acks'][$command] = 'no'; $args[5]['raw_returns'][$command] = false;
            $this->compactQualificationEvents( $args );
            $this->assertTrue( $this->admissionInvoke( $this->qualificationOwner(), 'facts', $args[5]['facts'] ), 'Ack-no ' . $command . ' control must reach native consistency, not merely fail generic causal validation.' );
            $this->qualificationRefused( $args );
        }
    }
    public function test_native_capture_cannot_claim_current_acceptance_or_remaining_handle() {
        foreach ( array( 'invalid', 'auth', 'intent', 'physical', 'unreentered', 'accepted', 'handle', 'null_handle' ) as $bad ) {
            $args = $this->qualificationFixture();
            if ( 'invalid' === $bad ) $args[5]['valid'] = false;
            elseif ( 'accepted' === $bad ) $args[5]['facts']['events']['accepted_at'] = ++$args[6]['tick'];
            elseif ( 'handle' === $bad || 'null_handle' === $bad ) $args[5]['facts']['handle_remaining'] = 'handle' === $bad ? true : null;
            else $args[5]['facts']['guards'][$bad] = true;
            $this->qualificationRefused( $args );
        }
        $args = $this->qualificationFixture( 'never_created' ); $args[5]['valid'] = false; $this->qualificationRefused( $args );
        $args = $this->qualificationFixture( 'never_created' ); $args[5] = null; $this->qualificationRefused( $args );
    }
    public function test_all_original_id_losses_and_changed_origin_are_consumed_without_remint() {
        foreach ( range( 0, 11 ) as $offset ) { $args = $this->qualificationFixture(); array_splice( $args[2], $offset, 1 ); $this->qualificationRefused( $args ); }
        foreach ( array( 'actor', 'context', 'manifest', 'scope', 'proposal', 'duplicate_id', 'start', 'expired_start', 'pin' ) as $loss ) {
            $args = $this->qualificationFixture();
            if ( 'actor' === $loss ) $args[0]['actor_id']++;
            elseif ( 'context' === $loss ) $args[0]['context_digest'] = str_repeat( 'f', 64 );
            elseif ( 'manifest' === $loss ) $args[0]['manifest_digest'] = str_repeat( 'f', 64 );
            elseif ( 'scope' === $loss ) $args[0]['scope']['authority_generation']++;
            elseif ( 'proposal' === $loss ) $args[1]['terminal_id'] = $this->rid( 700 );
            elseif ( 'duplicate_id' === $loss ) $args[2][] = $args[2][0];
            elseif ( 'start' === $loss ) $args[3] = null;
            elseif ( 'expired_start' === $loss ) $args[3]['utc'] = $args[0]['mutation']['expires_at'];
            else $args[7]['final_pin'] = false;
            $this->qualificationRefused( $args );
        }
    }
    public function test_sparse_duplicate_reordered_and_opposing_events_never_become_history() {
        foreach ( array( 'sparse', 'duplicate', 'reverse', 'commit_idle', 'readback', 'rollback', 'rows', 'no_close' ) as $loss ) {
            $args = $this->qualificationFixture();
            if ( 'sparse' === $loss ) { foreach ( $args[5]['facts']['events'] as $key => $at ) if ( $at ) $args[5]['facts']['events'][$key]++; $args[6]['tick']++; }
            elseif ( 'duplicate' === $loss ) $args[5]['facts']['events']['commit_idle'] = $args[5]['facts']['events']['readback'];
            elseif ( 'reverse' === $loss ) $args[5]['facts']['events'] = array_reverse( $args[5]['facts']['events'], true );
            elseif ( 'rollback' === $loss ) { $args[5]['facts']['events']['rollback_sent'] = 20; $args[5]['facts']['acks']['rollback'] = 'unknown'; $args[5]['raw_returns']['rollback'] = 'unknown'; }
            elseif ( 'rows' === $loss ) $args[5]['facts']['affected_rows'] = 'zero';
            elseif ( 'no_close' === $loss ) { $args[5]['facts']['events']['close_at'] = 0; $this->compactQualificationEvents( $args ); }
            else { $args[5]['facts']['events'][$loss] = 0; $this->compactQualificationEvents( $args ); }
            if ( 'commit_idle' === $loss ) $this->assertQualification( $this->qualification( $this->qualificationOwner(), $args ), 'uncertain' );
            else $this->qualificationRefused( $args );
        }
    }
    public function test_unknown_stale_or_malformed_qualification_claims_cannot_refresh() {
        foreach ( array( 'missing', 'extra', 'old_domain', 'guard_type', 'pin_unknown' ) as $loss ) {
            $args = $this->qualificationFixture();
            if ( 'missing' === $loss ) unset( $args[7]['guards']['auth'] );
            elseif ( 'extra' === $loss ) $args[7]['provider'] = 'external';
            elseif ( 'old_domain' === $loss ) $args[7]['domain'] = 'closed_qualification_claims_v0';
            elseif ( 'guard_type' === $loss ) $args[7]['guards']['auth'] = 'true';
            else $args[7]['final_pin'] = null;
            $this->qualificationRefused( $args );
        }
    }
}
