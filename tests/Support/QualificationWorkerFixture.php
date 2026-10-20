<?php
require_once __DIR__ . '/QualificationObservationFixture.php';
require_once __DIR__ . '/QualificationObservationWorker.php';
/** Privileged isolated fixtures only. Never a real request, native root or physical cleanup proof. */
trait Alynt_Test_Qualification_Worker_Fixture {
    use Alynt_Test_Qualification_Observation_Fixture;
    private function workerContext() { return array( 'attempt' => str_repeat( 'a', 64 ), 'root' => str_repeat( 'b', 64 ), 'pair' => str_repeat( 'c', 64 ) ); }
    private function workerFixture( $case = 'commit_unknown', $clear = true ) {
        $worker = Alynt_Test_Qualification_Observation_Worker::create( $this->workerContext() );
        $this->assertInstanceOf( Alynt_Test_Qualification_Observation_Worker::class, $worker );
        $owner = $this->admissionProperty( $worker, 'owner' );
        list( $synthetic, $pin, $guards ) = $this->observationFixture( $case );
        // Seed the worker's fixed owned instance, never replace it or pass an owner to its factory.
        foreach ( ( new ReflectionClass( $synthetic ) )->getProperties() as $p ) $this->observationSet( $owner, $p->getName(), $this->admissionProperty( $synthetic, $p->getName() ) );
        $actual = $this->observe( $owner, $pin, $guards );
        $this->assertIsArray( $actual ); $receipt = $this->observed( $owner );
        if ( $clear ) {
            $this->admissionInvoke( $owner, 'finish' ); $this->admissionInvoke( $owner, 'clear_native_outcome' ); $this->admissionInvoke( $owner, 'clear_admission_origin' );
            // Synthetic post-root state only: this does not execute or prove root finally.
            foreach ( array( 'scope', 'before', 'proposal', 'context', 'after', 'recorded_at' ) as $name ) $this->observationSet( $owner, $name, null );
            foreach ( array( 'active', 'admission_join_busy' ) as $name ) $this->observationSet( $owner, $name, false );
            $this->observationSet( $owner, 'consumed', true );
        }
        return array( $worker, $owner, $actual, $receipt );
    }
    private function workerFrame() {
        list( $worker ) = $this->workerFixture();
        $line = $this->admissionInvoke( $worker, 'take' ); $this->assertIsString( $line );
        return $line;
    }
}
