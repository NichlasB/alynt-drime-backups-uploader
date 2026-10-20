<?php
require_once __DIR__ . '/Support/QualificationWorkerFixture.php';
final class QualificationObservationWorkerTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Qualification_Worker_Fixture;
    /** @dataProvider outcomes */
    public function test_actual_qualifier_projection_is_extracted_once_and_never_claims_a_native_root( $case ) {
        list( $worker, $owner, $actual, $receipt ) = $this->workerFixture( $case );
        $line = $this->admissionInvoke( $worker, 'take' ); $this->assertIsString( $line ); $m = json_decode( $line, true );
        $this->assertSame( $receipt, $m['observation'] ); $this->assertSame( $actual, $m['observation']['result'] );
        $this->assertTrue( $m['isolated_only'] ); $this->assertTrue( $m['original_slots_empty'] );
        foreach ( array( 'root_invocation_proven', 'root_erasure_proven', 'authentication_proven', 'native_proven', 'ipc_proven', 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $name ) $this->assertFalse( $m[$name] );
        $this->assertSame( $this->workerContext()['root'], $m['root'] ); $this->assertSame( Alynt_Test_Qualification_Source_Manifest::WORKER, $m['worker'] );
        $this->assertNull( $this->observed( $owner ) ); $this->assertNull( $this->admissionProperty( $worker, 'owner' ) ); $this->assertNull( $this->admissionProperty( $worker, 'context' ) );
        $this->assertFalse( $this->admissionInvoke( $worker, 'take' ) ); $this->assertLessThanOrEqual( 4096, strlen( $line ) );
        foreach ( array( 'nonce', 'cookie', 'command_id', 'started_ns', 'raw_returns', 'pre_guards', 'post_guards' ) as $secret ) $this->assertStringNotContainsString( '"' . $secret . '"', $line );
    }
    public function outcomes() { return array_map( function ( $s ) { return array( $s ); }, array( 'committed', 'commit_unknown', 'commit_ack_only', 'rolled_back' ) ); }
    public function test_retained_original_state_is_refused_not_erased_to_make_evidence_pass() {
        list( $worker, $owner ) = $this->workerFixture( 'commit_unknown', false ); $raw = $this->admissionProperty( $owner, 'native_outcome' );
        $this->assertFalse( $this->admissionInvoke( $worker, 'take' ) ); $this->assertSame( $raw, $this->admissionProperty( $owner, 'native_outcome' ) );
        $this->assertNull( $this->observed( $owner ) ); $this->assertNull( $this->admissionProperty( $worker, 'owner' ) );
    }
    /** @dataProvider slots */
    public function test_one_retained_or_incomplete_slot_prevents_extraction_and_still_clears_observation( $name, $bad ) {
        list( $worker, $owner ) = $this->workerFixture(); $this->observationSet( $owner, $name, $bad );
        $this->assertFalse( $this->admissionInvoke( $worker, 'take' ) ); $this->assertNull( $this->observed( $owner ) );
        $this->assertSame( $bad, $this->admissionProperty( $owner, $name ) ); $this->assertNull( $this->admissionProperty( $worker, 'context' ) );
    }
    public function slots() { return array( array( 'before', array() ), array( 'proposal', array() ), array( 'scope', array() ), array( 'reader', new stdClass() ), array( 'read_physical', array() ), array( 'admission_ids', array( 'retained' ) ), array( 'native_outcome_tick', 1 ), array( 'active', true ), array( 'consumed', false ), array( 'qualification_observation_interrupted', true ), array( 'native_cleanup_done', false ) ); }
    public function test_execution_entry_remains_unconditionally_closed_and_cannot_rearm_observation() {
        list( $worker, $owner ) = $this->workerFixture();
        $this->assertFalse( $this->admissionInvoke( $worker, 'enter', 'POST', array( 'untrusted' => true ) ) );
        $this->assertSame( 40, $this->admissionProperty( $owner, 'protected_reads' ) ); $this->assertSame( 1, $this->admissionProperty( $owner, 'writes' ) );
        $this->assertNull( $this->observed( $owner ) ); $this->assertFalse( $this->admissionInvoke( $worker, 'take' ) );
    }
    public function test_closed_entry_does_not_even_invoke_the_original_admission_root() {
        $w = Alynt_Test_Qualification_Observation_Worker::create( $this->workerContext() ); $o = $this->admissionProperty( $w, 'owner' );
        $this->assertFalse( $this->admissionInvoke( $w, 'enter', 'POST', array() ) );
        $this->assertFalse( $this->admissionProperty( $o, 'admission_qualification_spent' ), 'Even early root finally must not have executed.' );
        $this->assertSame( 0, $this->admissionProperty( $o, 'protected_reads' ) ); $this->assertSame( 0, $this->admissionProperty( $o, 'writes' ) );
        $this->assertNull( $this->admissionProperty( $w, 'owner' ) ); $this->assertNull( $this->admissionProperty( $w, 'context' ) );
        $this->assertFalse( $this->admissionInvoke( $w, 'take' ) );
    }
    public function test_missing_or_false_observation_and_extra_take_argument_fail_closed() {
        $worker = Alynt_Test_Qualification_Observation_Worker::create( $this->workerContext() ); $this->assertFalse( $this->admissionInvoke( $worker, 'take' ) );
        foreach ( array( null, false, array( 'valid' => true, 'kind' => 'false' ) ) as $bad ) {
            list( $w, $o ) = $this->workerFixture(); $this->observationSet( $o, 'qualification_observation', $bad );
            $this->assertFalse( $this->admissionInvoke( $w, 'take' ) ); $this->assertNull( $this->observed( $o ) );
        }
        list( $w, $o ) = $this->workerFixture(); $this->assertFalse( $this->admissionInvoke( $w, 'take', 'provider' ) ); $this->assertNull( $this->observed( $o ) );
    }
    public function test_factory_has_no_owner_provider_or_public_execution_extraction_surface() {
        $c = $this->workerContext(); $bad = $c; $bad['owner'] = new stdClass();
        foreach ( array( null, false, new stdClass(), $bad, array_merge( $c, array( 'callback' => function () { throw new LogicException(); } ) ) ) as $input ) $this->assertFalse( Alynt_Test_Qualification_Observation_Worker::create( $input ) );
        $this->assertFalse( Alynt_Test_Qualification_Observation_Worker::create( $c, 'provider' ) );
        $w = Alynt_Test_Qualification_Observation_Worker::create( $c ); $r = new ReflectionClass( $w ); $names = array_map( function ( $m ) { return $m->name; }, $r->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $names );
        $this->assertSame( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ), $names );
        $this->assertTrue( $r->isFinal() ); $this->assertTrue( $r->getConstructor()->isPrivate() ); $this->assertSame( $w->disposition(), $w->__debugInfo() );
        $this->expectException( LogicException::class ); serialize( $w );
    }
    public function test_worker_and_transcript_sources_are_fixed_before_factory_or_owner_exercise() {
        $this->assertTrue( Alynt_Test_Qualification_Source_Manifest::verify() );
        foreach ( array( 'QualificationObservationWorker.php' => Alynt_Test_Qualification_Source_Manifest::WORKER, 'QualificationPrivateTranscript.php' => Alynt_Test_Qualification_Source_Manifest::TRANSCRIPT ) as $name => $hash ) {
            $m = new ReflectionMethod( Alynt_Test_Qualification_Source_Manifest::class, 'support' ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true );
            $this->assertTrue( $m->invoke( null, $name, $hash ) ); $this->assertFalse( $m->invoke( null, $name, str_repeat( '0', 64 ) ) );
            $this->assertFalse( $m->invoke( null, '../' . $name, $hash ) );
        }
    }
}
