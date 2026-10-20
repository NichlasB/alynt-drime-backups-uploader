<?php
require_once __DIR__ . '/RegistrationAdmissionOutcomeFixture.php';
$qualification_source = dirname( __DIR__, 2 ) . '/includes/class-registration-closed-qualification.php';
if ( is_file( $qualification_source ) ) require_once $qualification_source;
/** Synthetic raw/owner/current claims only; no WordPress, native handles or clock. */
trait Alynt_Test_Registration_Closed_Qualification_Fixture {
    use Alynt_Test_Registration_Admission_Outcome_Fixture;
    private function qualificationFixture( $history = 'committed' ) {
        $f = $this->lifeFixture( 'empty' ); $facts = $this->outcomeFacts( $history );
        $facts['events']['accepted_at'] = 0;
        $facts['guards'] = array_fill_keys( array( 'auth', 'intent', 'physical', 'unreentered' ), null );
        $raw = array(); foreach ( $facts['acks'] as $key => $ack ) $raw[$key] = array( 'yes' => true, 'unknown' => 'unknown', 'not_sent' => 'not_called' )[$ack];
        return array(
            $f['data'], $f['proposal'], $f['ids'], $f['clock'], $this->admissionClock( 300, 111 ),
            array( 'valid' => true, 'facts' => $facts, 'raw_returns' => $raw ),
            array( 'started' => true, 'cleanup_done' => true, 'sealed' => true, 'tick' => max( $facts['events'] ), 'reader_remaining' => false, 'writer_remaining' => false, 'physical_remaining' => false ),
            array( 'domain' => 'closed_qualification_claims_v1', 'guards' => array_fill_keys( array( 'auth', 'intent', 'physical', 'unreentered' ), true ), 'final_pin' => true ),
        );
    }
    private function qualificationOwner() { return Alynt_Drime_Backups_Uploader_Registration_Closed_Qualification::create(); }
    private function qualification( $o, $args ) { return $this->admissionInvoke( $o, 'qualify', ...$args ); }
    private function assertQualification( $r, $history = 'committed', $released = true, $accepted = true ) {
        $this->assertIsArray( $r, 'Always-unavailable consumer must not pass.' ); $this->assertCount( 13, $r );
        $this->assertSame( $history, $r['historical_outcome'] ); $this->assertSame( $released, $r['client_release_claimed'] ); $this->assertSame( $accepted, $r['current_acceptance_claimed'] );
        $this->assertTrue( $r['request_lifecycle_closed_claimed'] ); $this->assertTrue( $r['candidate_only'] );
        foreach ( array( 'diagnostic_candidate_only', 'authenticated', 'barrier_verified', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $key ) $this->assertFalse( $r[$key], $key );
    }
    private function qualificationRefused( $args ) {
        $o = $this->qualificationOwner(); $this->assertFalse( $this->qualification( $o, $args ) );
        $this->assertTrue( $this->admissionProperty( $o, 'spent' ) ); $this->assertFalse( $this->qualification( $o, $this->qualificationFixture() ) );
    }
    private function compactQualificationEvents( &$args ) {
        $events = $args[5]['facts']['events']; $positions = array_filter( $events ); asort( $positions );
        $n = 0; foreach ( $positions as $key => $unused ) $events[$key] = ++$n; $args[6]['tick'] = $n;
        $args[5]['facts']['events'] = $events;
    }
}
