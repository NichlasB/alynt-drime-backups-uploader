<?php
require_once __DIR__ . '/RegistrationObservationHandoffFixture.php';
/** In-memory inputs only; no actual clock, authentication or storage doubles. */
trait Alynt_Test_Registration_Admission_Lifecycle_Fixture {
    use Alynt_Test_Registration_Observation_Handoff_Fixture;
    private function lifeFixture( $phase = 'registered' ) {
        $f = $this->handoffFixture( $phase ); $f['proposal'] = $this->admissionFixture()[1];
        $f['clock'] = $this->admissionClock( 200, 110 ); $f['form']['issued_at'] = '120'; $f['form']['expires_at'] = '420'; return $f;
    }
    private function lifeOwner() {
        $path = dirname( __DIR__, 2 ) . '/includes/class-registration-admission-lifecycle.php';
        $this->assertFileExists( $path, 'Approved DATA model must exist.' ); require_once $path;
        return Alynt_Drime_Backups_Uploader_Registration_Admission_Lifecycle::create();
    }
    private function lifeRetain( $o, $f ) { return $this->admissionInvoke( $o, 'retain', $f['data'], $f['proposal'], $f['ids'], $f['clock'] ); }
    private function lifeAdvance( $o, $event, $clock = null ) { return $this->admissionInvoke( $o, 'advance', $event, $clock ?? $this->admissionClock( 300, 111 ) ); }
    private function lifeRelease( $o, $f, $outcome = 'uncertain' ) {
        $this->assertTrue( $this->lifeRetain( $o, $f ) );
        $trace = array( 'begin_sent', 'transaction_owned', 'dml_sent' );
        if ( 'committed' === $outcome ) $trace = array_merge( $trace, array( 'write_acknowledged', 'commit_sent', 'committed' ) );
        if ( 'commit_unknown' === $outcome ) $trace = array_merge( $trace, array( 'write_acknowledged', 'commit_sent' ) );
        if ( 'rolled_back' === $outcome ) $trace = array_merge( $trace, array( 'rollback_sent', 'rolled_back' ) );
        foreach ( array_merge( $trace, array( 'writer_released' ) ) as $event ) $this->assertTrue( $this->lifeAdvance( $o, $event ), $event );
    }
    private function lifeRead( $o, $f, $clock = null ) { return $this->admissionInvoke( $o, 'read', $f['intent'], $f['form'], $clock ?? $this->admissionClock( 400, 120 ) ); }
    private function lifeFinish( $o, $f, $clock = null ) { return $this->admissionInvoke( $o, 'finish', $f['frame'], $f['intent'], $f['form'], $clock ?? $this->admissionClock( 500, 121 ) ); }
    private function assertLifeCleared( $o ) {
        foreach ( array( 'root_data', 'root_ids', 'root_clock', 'read_start', 'outcome', 'staged', 'inflight' ) as $key ) $this->assertNull( $this->admissionProperty( $o, $key ), $key );
        $this->assertSame( 'closed', $this->admissionProperty( $o, 'phase' ) );
    }
}
