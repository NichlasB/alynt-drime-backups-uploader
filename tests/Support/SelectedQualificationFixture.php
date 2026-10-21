<?php
require_once __DIR__ . '/QualificationWorkerFixture.php';
require_once __DIR__ . '/SelectedQualificationWorker.php';
/** Privileged synthetic state, not original root/guard/finally/native execution. */
trait Alynt_Test_Selected_Qualification_Fixture {
    use Alynt_Test_Qualification_Worker_Fixture;
    private function selectedFixture( $case = 'commit_unknown' ) {
        $worker = Alynt_Test_Selected_Qualification_Worker::create( $this->workerContext() );
        $this->assertInstanceOf( Alynt_Test_Selected_Qualification_Worker::class, $worker );
        $owner = $this->admissionProperty( $worker, 'owner' );
        list( $old, $synthetic, $actual, $receipt ) = $this->workerFixture( $case );
        // The actual original qualifier ran over synthetic state, followed by mirrored finally.
        foreach ( ( new ReflectionClass( $synthetic ) )->getProperties() as $p ) $this->observationSet( $owner, $p->getName(), $this->admissionProperty( $synthetic, $p->getName() ) );
        foreach ( array( 'root_returned' => false, 'callback_clean' => true, 'nonce_creations' => 1, 'gates' => array( 'small', 'small', 'small', 'small', 'small', 'command', 'small', 'command' ) ) as $k => $v ) $this->observationSet( $worker, $k, $v );
        return array( $worker, $owner, $actual, $receipt );
    }
    private function selectedFrames() {
        list( $w ) = $this->selectedFixture(); $line = $this->admissionInvoke( $w, 'take' ); $this->assertIsString( $line ); $final = json_decode( $line, true );
        $base = array( 'domain' => 'alynt.qualification-observation.selected.v1', 'v' => 1, 'kind' => '', 'seq' => 1, 'attempt' => $final['attempt'] );
        $make = static function ( $kind, $seq, $more ) use ( $base ) { return array_merge( $base, array( 'kind' => $kind, 'seq' => $seq ), $more ); };
        return array( $make( 'boot', 1, array( 'profile' => 'qualification_selected_commit_response_loss' ) ), $make( 'root_request', 1, array( 'source' => $final['source'] ) ), $make( 'root', 2, array( 'root' => $final['root'] ) ), $make( 'bind', 2, array( 'root' => $final['root'], 'auth' => 11, 'capture' => 22, 'writer' => 33, 'remaining_ns' => '6000000000' ) ), $make( 'bound', 3, array( 'root' => $final['root'], 'writer' => 33, 'pair' => $final['pair'] ) ), $final );
    }
}
