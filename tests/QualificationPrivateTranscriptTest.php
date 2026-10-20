<?php
require_once __DIR__ . '/Support/QualificationWorkerFixture.php';
final class QualificationPrivateTranscriptTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Qualification_Worker_Fixture;
    private function parser() { return Alynt_Test_Qualification_Private_Transcript::create( $this->workerContext() ); }
    /** @dataProvider widths */
    public function test_exact_private_frame_is_fragmentable_non_authorizing_and_consumed_only_at_eof( $width ) {
        $line = $this->workerFrame(); $expected = json_decode( $line, true ); $p = $this->parser();
        foreach ( str_split( $line, $width ) as $chunk ) $this->assertTrue( $p->feed( $chunk ) );
        $this->assertSame( $expected, $p->finish() ); $this->assertFalse( $p->finish() ); $this->assertFalse( $p->feed( $line ) );
        foreach ( array( 'context', 'frame' ) as $name ) $this->assertNull( $this->admissionProperty( $p, $name ) ); $this->assertSame( '', $this->admissionProperty( $p, 'line' ) );
        $this->assertSame( 'alynt.qualification-observation.isolated.v1', $expected['domain'] );
        $this->assertSame( 1, $expected['seq'] );
        foreach ( array( 'root_invocation_proven', 'root_erasure_proven', 'authentication_proven', 'native_proven', 'ipc_proven', 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $name ) $this->assertFalse( $expected[$name] );
    }
    public function widths() { return array( array( 1 ), array( 2 ), array( 7 ), array( 4096 ) ); }
    public function test_every_truncation_is_inconclusive_and_cannot_be_rearmed() {
        $line = $this->workerFrame();
        for ( $n = 0, $max = strlen( $line ); $n < $max; ++$n ) {
            $p = $this->parser(); if ( $n ) $this->assertTrue( $p->feed( substr( $line, 0, $n ) ) );
            $this->assertFalse( $p->finish(), 'Truncated at ' . $n ); $this->assertFalse( $p->feed( $line ) );
        }
    }
    public function test_each_binding_pin_authority_or_observation_fault_is_independently_refused() {
        $line = $this->workerFrame(); $m = json_decode( $line, true ); $bad = array();
        foreach ( array( 'domain', 'kind', 'attempt', 'root', 'pair', 'production', 'composition', 'projection', 'worker', 'transcript' ) as $name ) { $v = $m; $v[$name] = str_repeat( 'd', 64 ); $bad[$name] = $v; }
        foreach ( array( 'root_invocation_proven', 'root_erasure_proven', 'authentication_proven', 'native_proven', 'ipc_proven', 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $name ) { $v = $m; $v[$name] = true; $bad[$name] = $v; }
        foreach ( array( 'isolated_only', 'original_slots_empty' ) as $name ) { $v = $m; $v[$name] = false; $bad[$name] = $v; }
        foreach ( array( 'valid', 'sealed', 'empty_slots', 'capture_unchanged', 'io_unchanged' ) as $name ) { $v = $m; $v['observation'][$name] = false; $bad[$name] = $v; }
        foreach ( array( 'entries', 'returns' ) as $name ) { $v = $m; $v['observation'][$name] = 0; $bad[$name] = $v; }
        $v = $m; $v['observation']['kind'] = 'false'; $v['observation']['result'] = null; $bad['always_unavailable'] = $v;
        $v = $m; $v['observation']['result']['execution_supported'] = true; $bad['result_authority'] = $v;
        $v = $m; $v['observation']['result']['current_acceptance_claimed'] = true; $bad['uncertain_promoted'] = $v;
        $v = $m; $v['observation']['result']['candidate_only'] = false; $bad['not_candidate'] = $v;
        $v = $m; $v['observation']['result']['request_lifecycle_closed_claimed'] = false; $bad['not_closed'] = $v;
        $v = $m; $v['observation']['result']['client_release_claimed'] = 1; $bad['wrong_type'] = $v;
        $v = $m; $v['v'] = 2; $bad['old_version'] = $v;
        $v = $m; $v['seq'] = 2; $bad['other_sequence'] = $v;
        $v = $m; $v['nonce'] = 'must-not-be-exported'; $bad['extra'] = $v;
        $v = $m; unset( $v['native_proven'] ); $bad['missing'] = $v;
        foreach ( $bad as $name => $frame ) { $p = $this->parser(); $this->assertFalse( $p->feed( json_encode( $frame, JSON_UNESCAPED_SLASHES ) . "\n" ), $name ); $this->assertFalse( $p->finish(), $name ); }
    }
    public function test_noncanonical_duplicate_escaped_control_deep_and_trailing_bytes_refuse() {
        $line = $this->workerFrame();
        $bad = array( "\n", "[]\n", "null\n", "true\n", "{}\n", ' ' . $line, str_replace( '"v":1', '"v":1,"v":1', $line ), str_replace( '"v":1', '"v":1.0', $line ), str_replace( '"v":1', '"v":1e0', $line ), str_replace( 'isolated.v1', 'isolated.\u00761', $line ), $line . 'x', $line . $line, str_replace( "\n", "\r\n", $line ), "é\n", '{"nested":' . str_repeat( '[', 10 ) . '0' . str_repeat( ']', 10 ) . "}\n" );
        foreach ( $bad as $bytes ) { $p = $this->parser(); $this->assertFalse( $p->feed( $bytes ) ); $this->assertFalse( $p->finish() ); }
        $p = $this->parser(); $this->assertTrue( $p->feed( $line ) ); $this->assertFalse( $p->feed( 'x' ) ); $this->assertFalse( $p->finish(), 'A false EOF claim cannot hide trailing bytes.' );
    }
    public function test_fixed_context_and_size_limits_fail_before_serializing_or_retaining_objects() {
        $c = $this->workerContext(); $same = $c; $same['pair'] = $same['root']; $order = array_reverse( $c, true ); $upper = $c; $upper['root'] = strtoupper( $upper['root'] );
        foreach ( array( null, false, new stdClass(), $same, $order, $upper, array_merge( $c, array( 'provider' => 'unknown' ) ) ) as $bad ) $this->assertFalse( Alynt_Test_Qualification_Private_Transcript::create( $bad ) );
        $poison = new class implements JsonSerializable {
            #[\ReturnTypeWillChange]
            public function jsonSerialize() { throw new LogicException( 'Object callback must not run.' ); }
        };
        $this->assertFalse( Alynt_Test_Qualification_Private_Transcript::encode( $c, $poison ) );
        foreach ( array( null, false, $poison, '', str_repeat( 'x', 4097 ) ) as $bytes ) { $p = $this->parser(); $this->assertFalse( $p->feed( $bytes ) ); $this->assertSame( 0, $this->admissionProperty( $p, 'bytes' ) ); $this->assertFalse( $p->finish() ); }
        $p = $this->parser(); $this->assertTrue( $p->feed( str_repeat( 'x', 4095 ) ) ); $this->assertSame( 4095, strlen( $this->admissionProperty( $p, 'line' ) ) );
        $this->assertFalse( $p->feed( 'x' ) ); $this->assertSame( '', $this->admissionProperty( $p, 'line' ) ); $this->assertFalse( $p->finish() );
    }
    public function test_extra_api_arguments_wrong_identity_and_private_debug_surface_cannot_complete() {
        $line = $this->workerFrame(); $c = $this->workerContext();
        $this->assertFalse( Alynt_Test_Qualification_Private_Transcript::create( $c, 'provider' ) );
        $p = $this->parser(); $this->assertFalse( $p->feed( $line, 'callback' ) ); $this->assertFalse( $p->finish() );
        $p = $this->parser(); $this->assertTrue( $p->feed( $line ) ); $this->assertFalse( $p->finish( 'claimed-eof' ) );
        $other = $c; $other['root'] = str_repeat( 'd', 64 ); $p = Alynt_Test_Qualification_Private_Transcript::create( $other ); $this->assertFalse( $p->feed( $line ) );
        $p = $this->parser(); $this->assertTrue( $p->feed( $line ) ); $this->assertSame( $p->disposition(), $p->__debugInfo() );
        $this->expectException( LogicException::class ); serialize( $p );
    }
    public function test_actual_result_projection_is_not_promoted_and_parser_is_not_a_source_verifier() {
        list( $w, $o, $actual, $receipt ) = $this->workerFixture();
        $this->assertSame( 'uncertain', $actual['historical_outcome'] ); $this->assertFalse( $actual['current_acceptance_claimed'] );
        $line = $this->admissionInvoke( $w, 'take' ); $p = $this->parser(); $this->assertTrue( $p->feed( $line ) ); $m = $p->finish();
        $this->assertSame( $receipt, $m['observation'] ); $this->assertSame( $actual, $m['observation']['result'] );
        $this->assertFalse( $m['root_erasure_proven'] ); $this->assertFalse( $m['native_proven'] );
        // Structurally valid caller-constructed bytes are still synthetic claims, not private worker/authentication provenance.
        $claimed = $receipt; $claimed['result']['client_release_claimed'] = false; $claimed['result']['current_acceptance_claimed'] = false;
        $this->assertFalse( json_decode( Alynt_Test_Qualification_Private_Transcript::encode( $this->workerContext(), $claimed ), true )['observation']['result']['client_release_claimed'] );
    }
}
