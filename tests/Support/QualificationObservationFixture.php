<?php
require_once __DIR__ . '/RegistrationClosedQualificationFixture.php';
require_once __DIR__ . '/QualificationSourceManifest.php';
/** Reflection installs synthetic DATA only. No native factory, core bootstrap or SQL. */
trait Alynt_Test_Qualification_Observation_Fixture {
    use Alynt_Test_Registration_Closed_Qualification_Fixture;
    private function observationSet( $owner, $key, $value ) {
        $p = new ReflectionProperty( $owner, $key ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); $p->setValue( $owner, $value );
    }
    private function observationFixture( $case = 'committed' ) {
        $this->assertTrue( Alynt_Test_Qualification_Source_Manifest::verify(), 'Complete fixed source/state manifest must pass before owner exercise.' );
        $a = $this->qualificationFixture( $case ); $now = time(); $start = hrtime( true );
        $a[0]['mutation']['issued_at'] = $now - 1; $a[0]['mutation']['expires_at'] = $now + 299;
        $a[1]['command'] = $a[0]['mutation'];
        $digest = $this->registration_class()::registration_context( $a[0]['scope'], $a[1] );
        $a[0]['context_digest'] = $digest; $a[0]['mutation']['context_digest'] = $digest; $a[1]['command'] = $a[0]['mutation'];
        $a[3] = array( 'started_ns' => $start, 'now_ns' => $start, 'utc' => $now );
        $owner = Alynt_Test_Qualification_Observation_Owner::create();
        $slots = array( 'admission_origin' => $a[0], 'admission_ids' => $a[2], 'admission_clock' => $a[3], 'before' => array( 'synthetic' => 'original' ), 'proposal' => $a[1], 'context' => array( 'synthetic' => 'context' ), 'after' => 'synthetic-after', 'recorded_at' => $now );
        foreach ( $slots as $key => $value ) $this->observationSet( $owner, $key, $value );
        foreach ( array( 'started' => $start, 'last_clock' => $now, 'admission_join_busy' => true, 'native_outcome_started' => true, 'native_cleanup_done' => true, 'native_outcome_closed' => true, 'native_outcome_tick' => $a[6]['tick'], 'native_outcome' => $a[5], 'protected_reads' => 40, 'writes' => 1 ) as $key => $value ) $this->observationSet( $owner, $key, $value );
        return array( $owner, array_values( $slots ), array( 'auth' => 'committed' === $case ? true : null, 'intent' => 'committed' === $case ? true : null, 'physical' => 'committed' === $case ? true : null ) );
    }
    private function observed( $owner ) { return $this->admissionProperty( $owner, 'qualification_observation' ); }
    private function observe( $owner, $pin, $guards ) { return $this->admissionInvoke( $owner, 'qualify_admission_outcome', $pin, $guards ); }
}
