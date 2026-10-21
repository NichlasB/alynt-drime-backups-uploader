<?php
require_once __DIR__ . '/Support/SelectedQualificationFixture.php';
/**
 * Keep synthetic manifest/tokenizer allocations out of the parent multipart budget proof.
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class SelectedQualificationProtocolTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Selected_Qualification_Fixture;
    public function test_all_six_reserved_frames_round_trip_and_old_no_fault_parser_refuses_them() {
        require_once __DIR__ . '/Support/QualificationNativeProtocol.php';
        foreach ( $this->selectedFrames() as $m ) {
            $line = Alynt_Test_Selected_Qualification_Protocol::line( $m ); $this->assertIsString( $line );
            $this->assertSame( $m, Alynt_Test_Selected_Qualification_Protocol::parse( substr( $line, 0, -1 ) ) );
            $this->assertFalse( Alynt_Test_Qualification_Native_Protocol::parse( substr( $line, 0, -1 ) ) );
        }
    }
    public function test_actual_release_boolean_is_preserved_not_promoted_by_the_codec() {
        $m = $this->selectedFrames()[5]; $m['observation']['result']['client_release_claimed'] = false;
        $line = Alynt_Test_Selected_Qualification_Protocol::line( $m ); $this->assertIsString( $line );
        $this->assertFalse( json_decode( $line, true )['observation']['result']['client_release_claimed'] );
    }
    public function test_each_final_field_receipt_predicate_authority_and_source_pin_is_required() {
        $m = $this->selectedFrames()[5]; $bad = array();
        foreach ( array( 'domain' => 'alynt.qualification-observation.native.v1', 'v' => 2, 'kind' => '__proto__', 'seq' => 2, 'attempt' => str_repeat( 'd', 63 ), 'root' => $m['attempt'], 'pair' => $m['root'], 'profile' => 'qualification_no_fault', 'root_returned' => true, 'pre_guards' => 7, 'post_guards' => 1, 'protected_reads' => 39, 'writes' => 0, 'original_slots_empty' => false ) as $k => $v ) { $b = $m; $b[$k] = $v; $bad[$k] = $b; }
        foreach ( array_keys( $m ) as $k ) { $b = $m; unset( $b[$k] ); $bad['missing_' . $k] = $b; }
        foreach ( array_keys( $m['source'] ) as $k ) { $b = $m; $b['source'][$k] = str_repeat( '0', 64 ); $bad['source_' . $k] = $b; }
        foreach ( array_keys( $m['observation'] ) as $k ) { $b = $m; $b['observation'][$k] = null; $bad['receipt_' . $k] = $b; }
        foreach ( array_keys( $m['observation']['result'] ) as $k ) {
            if ( 'client_release_claimed' === $k ) $v = 1;
            else $v = 'historical_outcome' === $k ? 'committed' : ! $m['observation']['result'][$k];
            $b = $m; $b['observation']['result'][$k] = $v; $bad['result_' . $k] = $b;
        }
        $b = $m; $b['source'] = array_reverse( $b['source'], true ); $bad['source_order'] = $b;
        $b = $m; $b['nonce'] = 'private'; $bad['extra'] = $b;
        foreach ( $bad as $k => $b ) { $this->assertFalse( Alynt_Test_Selected_Qualification_Protocol::line( $b ), $k ); $this->assertFalse( Alynt_Test_Selected_Qualification_Protocol::parse( json_encode( $b ) ), $k ); }
    }
    public function test_truncation_noncanonical_deep_hostile_or_oversized_bytes_cannot_parse() {
        $line = trim( Alynt_Test_Selected_Qualification_Protocol::line( $this->selectedFrames()[5] ) );
        foreach ( array( null, array(), array( 'toString' => null, 'valueOf' => null ) ) as $kind ) { $m = $this->selectedFrames()[5]; $m['kind'] = $kind; $this->assertFalse( Alynt_Test_Selected_Qualification_Protocol::parse( json_encode( $m ) ) ); }
        for ( $i = 0; $i < strlen( $line ); ++$i ) $this->assertFalse( Alynt_Test_Selected_Qualification_Protocol::parse( substr( $line, 0, $i ) ) );
        foreach ( array( $line . 'x', $line . "\n", ' ' . $line, str_replace( '"v":1', '"v":1,"v":1', $line ), str_replace( '"v":1', '"v":1.0', $line ), str_replace( 'selected.v1', 'selected.\u00761', $line ), "é", str_repeat( 'x', 4096 ), '{"nest":' . str_repeat( '[', 10 ) . '0' . str_repeat( ']', 10 ) . '}' ) as $bad ) $this->assertFalse( Alynt_Test_Selected_Qualification_Protocol::parse( $bad ) );
        $poison = new class implements JsonSerializable {
            #[\ReturnTypeWillChange]
            public function jsonSerialize() { throw new LogicException( 'Must not serialize an object.' ); }
        };
        $m = $this->selectedFrames()[5]; $m['observation']['result'] = $poison;
        $this->assertFalse( Alynt_Test_Selected_Qualification_Protocol::line( $m ) );
        $this->assertFalse( Alynt_Test_Selected_Qualification_Protocol::parse( $poison ) );
        $this->assertFalse( Alynt_Test_Selected_Qualification_Protocol::line( $m, 'provider' ) );
    }
    public function test_bind_and_remaining_types_enforce_exact_reserve_and_distinct_roles() {
        $m = $this->selectedFrames()[3];
        foreach ( array( '6000000000', '10000000000' ) as $ok ) { $m['remaining_ns'] = $ok; $this->assertTrue( Alynt_Test_Selected_Qualification_Protocol::check( $m ) ); }
        foreach ( array( '5999999999', '10000000001', '06000000000', '6e9', 6000000000, null ) as $bad ) { $m['remaining_ns'] = $bad; $this->assertFalse( Alynt_Test_Selected_Qualification_Protocol::check( $m ) ); }
        $m = $this->selectedFrames()[3]; $m['capture'] = $m['auth']; $this->assertFalse( Alynt_Test_Selected_Qualification_Protocol::check( $m ) );
        $m['capture'] = 22; $m['writer'] = 4294967296; $this->assertFalse( Alynt_Test_Selected_Qualification_Protocol::check( $m ) );
    }
}
