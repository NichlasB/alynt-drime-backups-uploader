<?php
require_once __DIR__ . '/RegistrationAdmissionLifecycleFixture.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-registration-admission-outcome.php';
/** Pure claims only. No API doubles, actual WordPress, clocks or storage. */
trait Alynt_Test_Registration_Admission_Outcome_Fixture {
    use Alynt_Test_Registration_Admission_Lifecycle_Fixture;
    private function outcomeOwner() { return Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome::create(); }
    private function outcomeFacts( $history = 'uncertain' ) {
        $e = array_fill_keys( Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome::EVENTS, 0 );
        $trace = array( 'writer_created', 'attempt_reserved', 'begin_sent', 'begin_ack_at', 'transaction_owned', 'bind_verified', 'dml_sent' );
        $acks = array( 'begin' => 'yes', 'dml' => 'unknown', 'commit' => 'not_sent', 'rollback' => 'not_sent', 'close' => 'yes' );
        $rows = 'unknown';
        if ( in_array( $history, array( 'committed', 'commit_unknown', 'commit_ack_only' ), true ) ) {
            $trace = array_merge( $trace, array( 'dml_ack_at', 'readback', 'commit_sent' ) ); $acks['dml'] = 'yes'; $rows = 'one'; $acks['commit'] = 'unknown';
            if ( 'commit_unknown' !== $history ) { $trace[] = 'commit_ack_at'; $acks['commit'] = 'yes'; }
            if ( 'committed' === $history ) $trace[] = 'commit_idle';
        } elseif ( 'rolled_back' === $history ) {
            $trace = array_merge( $trace, array( 'rollback_sent', 'rollback_ack_at', 'rollback_idle' ) ); $acks['rollback'] = 'yes';
        } elseif ( 'bind_failure' === $history ) {
            $trace = array_slice( $trace, 0, 5 ); $trace = array_merge( $trace, array( 'rollback_sent', 'rollback_ack_at', 'rollback_idle' ) ); $acks['dml'] = 'not_sent'; $acks['rollback'] = 'yes'; $rows = 'not_observed';
        } elseif ( 'never_created' === $history ) {
            $trace = array(); $acks = array_fill_keys( array_keys( $acks ), 'not_sent' ); $rows = 'not_observed';
        }
        if ( 'never_created' !== $history ) $trace = array_merge( $trace, array( 'detached', 'close_at', 'accepted_at' ) );
        foreach ( $trace as $i => $name ) $e[$name] = $i + 1;
        return array( 'events' => $e, 'acks' => $acks, 'affected_rows' => $rows, 'handle_remaining' => false, 'guards' => array( 'auth' => true, 'intent' => true, 'physical' => true, 'unreentered' => true ) );
    }
    private function outcome( $o, $facts, $f = null, $clock = null ) {
        $f = $f ?? $this->lifeFixture( 'empty' );
        return $this->admissionInvoke( $o, 'classify', $f['data'], $f['proposal'], $f['ids'], $f['clock'], $clock ?? $this->admissionClock( 300, 111 ), $facts );
    }
    private function assertOutcome( $result, $history, $candidate = false, $released = true, $accepted = true ) {
        $this->assertIsArray( $result ); $this->assertCount( 12, $result );
        $this->assertSame( $history, $result['historical_outcome'] ); $this->assertSame( $candidate, $result['diagnostic_candidate_only'] );
        $this->assertSame( $released, $result['client_release_claimed'] ); $this->assertSame( $accepted, $result['current_acceptance_claimed'] );
        $this->assertTrue( $result['candidate_only'] );
        foreach ( array( 'authenticated', 'barrier_verified', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $flag ) $this->assertFalse( $result[$flag], $flag );
    }
    private function outcomeRefused( $facts, $f = null ) {
        $o = $this->outcomeOwner(); $this->assertFalse( $this->outcome( $o, $facts, $f ) );
        $this->assertTrue( $this->admissionProperty( $o, 'spent' ) ); $this->assertFalse( $this->outcome( $o, $this->outcomeFacts() ) );
    }
}
