<?php
require_once __DIR__ . '/Support/QualificationWorkerFixture.php';
require_once __DIR__ . '/Support/QualificationNativeWorker.php';
final class QualificationNativeWorkerTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Qualification_Worker_Fixture;
    private function nativeFixture() {
        // Privileged synthetic post-root fixture ONLY; not genuine entry or native provenance.
        list( $reference, $from, $actual, $receipt ) = $this->workerFixture( 'committed' );
        $w = Alynt_Test_Qualification_Native_Worker::create(); $this->assertInstanceOf( Alynt_Test_Qualification_Native_Worker::class, $w );
        $to = $this->admissionProperty( $w, 'owner' );
        foreach ( ( new ReflectionClass( $from ) )->getProperties() as $p ) $this->observationSet( $to, $p->getName(), $this->admissionProperty( $from, $p->getName() ) );
        return array( $w, $to, $actual, $receipt );
    }
    private function frame() {
        list( $w, $o, $actual, $receipt ) = $this->nativeFixture();
        $m = array( 'domain' => Alynt_Test_Qualification_Native_Protocol::DOMAIN, 'v' => 1, 'kind' => 'final', 'seq' => 3, 'attempt' => str_repeat( 'a', 64 ), 'root' => str_repeat( 'b', 64 ), 'pair' => str_repeat( 'c', 64 ), 'profile' => Alynt_Test_Qualification_Native_Protocol::PROFILE, 'source' => Alynt_Test_Qualification_Native_Manifest::identities(), 'root_returned' => true, 'pre_guards' => 8, 'post_guards' => 2, 'protected_reads' => 40, 'writes' => 1, 'original_slots_empty' => true, 'observation' => $this->admissionInvoke( $w, 'take' ) );
        $this->assertSame( $receipt, $m['observation'] ); $this->assertSame( $actual, $m['observation']['result'] ); return $m;
    }
    public function test_fixed_factory_and_actual_malformed_root_are_one_shot_with_zero_protected_io() {
        $this->assertTrue( Alynt_Test_Qualification_Native_Manifest::verify() );
        $this->assertFalse( Alynt_Test_Qualification_Native_Worker::create( new stdClass() ) );
        $w = Alynt_Test_Qualification_Native_Worker::create(); $o = $this->admissionProperty( $w, 'owner' );
        $this->assertFalse( $this->admissionProperty( $o, 'attempted' ) );
        $this->assertFalse( $this->admissionInvoke( $w, 'enter', 'POST', array() ) );
        $this->assertTrue( $this->admissionProperty( $o, 'attempted' ), 'Required original root must actually run, not an always-unavailable worker.' );
        $this->assertTrue( $this->admissionProperty( $o, 'admission_qualification_spent' ) );
        $this->assertFalse( $this->admissionProperty( $o, 'qualification_observation_spent' ) );
        $this->assertSame( 0, $this->admissionProperty( $o, 'protected_reads' ) ); $this->assertSame( 0, $this->admissionProperty( $o, 'writes' ) );
        $this->assertFalse( $this->admissionInvoke( $w, 'enter', 'POST', array() ) );
        $this->assertFalse( $this->admissionInvoke( $w, 'take' ) );
        $r = new ReflectionClass( $w ); $this->assertTrue( $r->isFinal() ); $this->assertTrue( $r->getConstructor()->isPrivate() );
        $names = array_map( function ( $m ) { return $m->name; }, $r->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $names );
        $this->assertSame( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ), $names );
        $this->expectException( LogicException::class ); serialize( $w );
    }
    public function test_positive_actual_projection_is_consumed_and_retained_original_slots_are_not_repaired() {
        list( $w, $o, $actual, $receipt ) = $this->nativeFixture();
        $this->assertSame( $receipt, $this->admissionInvoke( $w, 'take' ) ); $this->assertNull( $this->observed( $o ) ); $this->assertFalse( $this->admissionInvoke( $w, 'take' ) );
        foreach ( array( array( 'reader', new stdClass() ), array( 'scope', array() ), array( 'native_outcome', array() ), array( 'consumed', false ), array( 'native_cleanup_done', false ), array( 'qualification_observation_interrupted', true ) ) as $bad ) {
            list( $w, $o ) = $this->nativeFixture(); $this->observationSet( $o, $bad[0], $bad[1] );
            $this->assertFalse( $this->admissionInvoke( $w, 'take' ) ); $this->assertSame( $bad[1], $this->admissionProperty( $o, $bad[0] ) ); $this->assertNull( $this->observed( $o ) );
        }
    }
    public function test_native_grammar_copies_positive_projection_and_refuses_each_null_or_missing_field() {
        $m = $this->frame(); $line = Alynt_Test_Qualification_Native_Protocol::line( $m ); $this->assertIsString( $line );
        $this->assertSame( $m, Alynt_Test_Qualification_Native_Protocol::parse( substr( $line, 0, -1 ) ) );
        foreach ( array_keys( $m ) as $name ) {
            $bad = $m; $bad[$name] = null; $this->assertFalse( Alynt_Test_Qualification_Native_Protocol::check( $bad ), 'Null ' . $name );
            unset( $bad[$name] ); $this->assertFalse( Alynt_Test_Qualification_Native_Protocol::check( $bad ), 'Missing ' . $name );
        }
        foreach ( array( 'entries', 'returns', 'valid', 'sealed', 'empty_slots', 'capture_unchanged', 'io_unchanged' ) as $name ) { $bad = $m; $bad['observation'][$name] = false; $this->assertFalse( Alynt_Test_Qualification_Native_Protocol::check( $bad ) ); }
        foreach ( array( 'authenticated', 'barrier_verified', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $name ) { $bad = $m; $bad['observation']['result'][$name] = true; $this->assertFalse( Alynt_Test_Qualification_Native_Protocol::check( $bad ) ); }
        foreach ( array( 'root_returned', 'original_slots_empty', 'pre_guards', 'post_guards', 'protected_reads', 'writes', 'source' ) as $name ) { $bad = $m; $bad[$name] = false; $this->assertFalse( Alynt_Test_Qualification_Native_Protocol::check( $bad ) ); }
        $this->assertFalse( Alynt_Test_Qualification_Native_Protocol::parse( str_repeat( 'x', 4096 ) ) );
        foreach ( array( $line, ' ' . rtrim( $line ), str_replace( '"v":1', '"v":1,"v":1', rtrim( $line ) ), str_replace( '"seq":3', '"seq":3.0', rtrim( $line ) ), '{"x":' . str_repeat( '[', 10 ) . '0' . str_repeat( ']', 10 ) . '}' ) as $bad ) $this->assertFalse( Alynt_Test_Qualification_Native_Protocol::parse( $bad ) );
    }
    public function test_boot_bind_and_bound_domains_roles_and_budget_are_exact() {
        $common = array( 'domain' => Alynt_Test_Qualification_Native_Protocol::DOMAIN, 'v' => 1, 'kind' => 'boot', 'seq' => 1, 'attempt' => str_repeat( 'a', 64 ) );
        $this->assertTrue( Alynt_Test_Qualification_Native_Protocol::check( $common + array( 'profile' => 'qualification_no_fault' ) ) );
        foreach ( array( null, 'observe_only', 'selected_commit_response_loss' ) as $profile ) $this->assertFalse( Alynt_Test_Qualification_Native_Protocol::check( $common + array( 'profile' => $profile ) ) );
        $common['kind'] = 'bind'; $common['seq'] = 2; $m = $common + array( 'root' => str_repeat( 'b', 64 ), 'auth' => 1, 'capture' => 2, 'writer' => 3, 'remaining_ns' => '6000000000' );
        $this->assertTrue( Alynt_Test_Qualification_Native_Protocol::check( $m ) );
        foreach ( array( '5999999999', '10000000001', '06000000000', '6e9', null, 6000000000 ) as $bad ) { $v = $m; $v['remaining_ns'] = $bad; $this->assertFalse( Alynt_Test_Qualification_Native_Protocol::check( $v ) ); }
        foreach ( array( 0, -1, 4294967296, '3', 2, null ) as $bad ) { $v = $m; $v['writer'] = $bad; $this->assertFalse( Alynt_Test_Qualification_Native_Protocol::check( $v ) ); }
        $this->assertFalse( Alynt_Test_Qualification_Native_Manifest::verify( 'supplied' ) );
        $this->assertFalse( Alynt_Test_Qualification_Native_Manifest::identities( 'supplied' ) );
    }
    /** @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_actual_probe_error_survives_a_swallowing_root_and_is_cleared_after_rethrow() {
        // Isolated named API shim, not actual WordPress auth or a native session.
        require_once __DIR__ . '/Support/FixedCoreRequestShims.php'; alynt_test_fixed_core_request_shims();
        require_once dirname( __DIR__ ) . '/scripts/tests/fixed-core-proof-storage.php';
        require_once dirname( __DIR__ ) . '/scripts/tests/fixed-core-proof-fixtures.php';
        $GLOBALS['alynt_core_proof'] = array( 'checks' => 0 ); $GLOBALS['wpdb'] = (object) array( 'dbh' => new stdClass() );
        $w = Alynt_Test_Qualification_Native_Worker::create(); $o = $this->admissionProperty( $w, 'owner' );
        $this->observationSet( $o, 'scope', array( 'db' => $GLOBALS['wpdb'] ) ); $this->observationSet( $o, 'protected_reads', 1 );
        $GLOBALS['alynt_core_request_test'] = array( 'calls' => array(), 'nonce_result' => false, 'on' => array( 'verify_nonce' => function () use ( $w ) { $this->admissionInvoke( $w, 'probe', 86400, 'synthetic-action' ); } ) );
        $caught = null; try { wp_verify_nonce( 'bad', 'synthetic-action' ); } catch ( RuntimeException $e ) { $caught = $e; } // Model the unchanged root's refusal catch.
        $this->assertInstanceOf( RuntimeException::class, $caught ); $this->assertSame( 'qualification_early_io', $caught->getMessage() );
        $this->assertTrue( $this->admissionProperty( $w, 'failed' ) );
        try { $this->admissionInvoke( $w, 'check_callback' ); $this->fail( 'Callback error must remain visible outside the root catch.' ); }
        catch ( RuntimeException $e ) { $this->assertSame( $caught, $e ); }
        $this->assertNull( $this->admissionProperty( $w, 'callback_failure' ) ); $this->assertNull( $this->admissionInvoke( $w, 'check_callback' ) );
        $r = Alynt_Test_Qualification_Native_Diagnostic::failure( $caught ); $this->assertSame( 'qualification_early_io', $r['code'] ); $this->assertSame( 'QualificationNativeWorker.php', $r['location'][0] );
    }
    public function test_diagnostic_projection_never_exports_unrecognized_messages_or_trace_payloads() {
        Alynt_Test_Qualification_Native_Diagnostic::remember( null );
        $secret = 'SELECT cookie=private-test-payload'; $r = Alynt_Test_Qualification_Native_Diagnostic::failure( new RuntimeException( $secret ) );
        $this->assertSame( array( 'passed', 'redacted_failure', 'code', 'location', 'progress' ), array_keys( $r ) );
        $this->assertSame( 'qualification_unclassified', $r['code'] ); $this->assertSame( array( 'qualification-native-worker.php', 1 ), $r['location'] );
        $this->assertStringNotContainsString( $secret, json_encode( $r ) );
        foreach ( Alynt_Test_Qualification_Native_Diagnostic::CODES as $code ) $this->assertSame( $code, Alynt_Test_Qualification_Native_Diagnostic::failure( new RuntimeException( $code ) )['code'] );
    }
    public function test_root_acceptance_isolates_each_predicate_without_weakening_the_original_conjunction() {
        // Synthetic private counters only: no original root/native execution is proved here.
        foreach ( array( array( true, false, 10, 1, null ), array( true, true, 10, 1, 'qualification_callback_state' ), array( false, false, 10, 1, 'qualification_root_return' ), array( true, false, 9, 1, 'qualification_guard_count' ), array( true, false, 10, 0, 'qualification_nonce_creation_count' ), array( true, false, 10, 2, 'qualification_nonce_creation_count' ) ) as $case ) {
            list( $actual, $failed, $guards, $creates, $code ) = $case; $w = Alynt_Test_Qualification_Native_Worker::create();
            $this->observationSet( $w, 'failed', $failed ); $this->observationSet( $w, 'gates', array_fill( 0, $guards, 'synthetic' ) ); $this->observationSet( $w, 'creates', $creates );
            $o = $this->admissionProperty( $w, 'owner' ); $this->observationSet( $o, 'protected_reads', 20 );
            try { $this->assertTrue( $this->admissionInvoke( $w, 'require_root', $actual ) ); $this->assertNull( $code, 'A failed predicate must refuse.' ); }
            catch ( RuntimeException $e ) { $this->assertNotNull( $code ); $this->assertSame( $code, $e->getMessage() ); }
            $r = Alynt_Test_Qualification_Native_Diagnostic::failure( new RuntimeException( $code ?? 'qualification_unclassified' ) );
            $this->assertFalse( $r['passed'] ); $this->assertSame( array( 'callback_clean' => ! $failed, 'root_returned_true' => $actual, 'guards_observed' => $guards, 'nonce_creations_observed' => $creates, 'protected_reads_observed' => 20, 'writes_observed' => 0 ), $r['progress'] );
            $this->assertSame( 20, $this->admissionProperty( $o, 'protected_reads' ) ); $this->assertSame( 0, $this->admissionProperty( $o, 'writes' ) );
        }
    }
    public function test_failure_progress_is_bounded_unaliased_and_consumed_not_an_acceptance_receipt() {
        $p = array( 'callback_clean' => true, 'root_returned_true' => false, 'guards_observed' => 6, 'nonce_creations_observed' => 1, 'protected_reads_observed' => 20, 'writes_observed' => 0 );
        $this->assertTrue( Alynt_Test_Qualification_Native_Diagnostic::remember( $p ) );
        $r = Alynt_Test_Qualification_Native_Diagnostic::failure( new RuntimeException( 'qualification_root_return' ) ); $this->assertSame( $p, $r['progress'] ); $this->assertFalse( $r['passed'] );
        $this->assertNull( Alynt_Test_Qualification_Native_Diagnostic::failure( new RuntimeException( 'qualification_root_return' ) )['progress'] );
        foreach ( array_keys( $p ) as $key ) {
            $bad = $p; $bad[$key] = null; $this->assertFalse( Alynt_Test_Qualification_Native_Diagnostic::remember( $bad ) );
            unset( $bad[$key] ); $this->assertFalse( Alynt_Test_Qualification_Native_Diagnostic::remember( $bad ) );
        }
        foreach ( array( -1, 12, '6', 1.5, new stdClass() ) as $v ) { $bad = $p; $bad['guards_observed'] = $v; $this->assertFalse( Alynt_Test_Qualification_Native_Diagnostic::remember( $bad ) ); }
        $aliased = true; $bad = $p; $bad['callback_clean'] =& $aliased; $this->assertFalse( Alynt_Test_Qualification_Native_Diagnostic::remember( $bad ) );
        $aliased = 'must-not-export'; $this->assertNull( Alynt_Test_Qualification_Native_Diagnostic::failure( new RuntimeException( 'qualification_root_return' ) )['progress'] );
    }
}
