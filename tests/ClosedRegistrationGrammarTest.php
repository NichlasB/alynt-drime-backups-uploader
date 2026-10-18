<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/includes/class-closed-registration-contract.php';
require_once dirname( __DIR__ ) . '/includes/class-closed-scan-effect-ledger.php';
require_once __DIR__ . '/Support/ClosedRegistrationFixture.php';

/** Exact grammar, scalar extremes and corrupt-history controls. No site access. */
class ClosedRegistrationGrammarTest extends TestCase {
    use Alynt_Test_Closed_Registration_Fixture;
    public function test_legacy_and_new_domains_cannot_import_or_promote_each_other() {
        $c = $this->registration_class(); $s = $this->rroot(); $this->assertIsArray( $c::inspect( json_encode( $s ), $this->rscope() ) );
        $legacy_scope = array_slice( $this->rscope(), 0, 3 );
        $this->assertFalse( Alynt_Drime_Backups_Uploader_Closed_Scan_Effect_Ledger::inspect( json_encode( $s ), $legacy_scope ) );
        foreach ( array( 'schema_version' => 1, 'domain' => 'scan_effect_storage_closed_v1', 'closed' => false, 'ledger_revision' => '20', 'participant_digest' => str_repeat( 'e', 64 ), 'operations' => null, 'authenticated_grant' => true ) as $key => $value ) { $bad = $s; $bad[$key] = $value; $this->assertFalse( $c::inspect( json_encode( $bad ), $this->rscope() ), $key ); }
        foreach ( array_keys( $this->rscope() ) as $key ) { $scope = $this->rscope(); $scope[$key] = is_int( $scope[$key] ) ? $scope[$key] + 1 : ( 'enrollment_binding' === $key ? str_repeat( 'e', 64 ) : $this->rid( 99 ) ); $this->assertFalse( $c::inspect( json_encode( $s ), $scope ), $key ); }
    }
    public function test_every_retained_identity_and_provenance_field_is_bound() {
        $c = $this->registration_class(); $root = json_decode( $this->registered(), true ); $id = key( $root['operations'] );
        foreach ( array( 'operation_id' => $this->rid( 99 ), 'context_digest' => str_repeat( 'e', 64 ), 'registration_dependency_digest' => str_repeat( 'e', 64 ), 'entry_point' => 'scheduled', 'generation' => 11, 'participant_digest' => str_repeat( 'e', 64 ), 'issued_at' => 101, 'expires_at' => 401, 'registration_revision' => 22, 'terminal_id' => $this->rid( 99 ), 'resolution_id' => $this->rid( 99 ), 'phase' => 'terminal', 'terminal' => array() ) as $key => $value ) { $bad = $root; $bad['operations'][$id][$key] = $value; $this->assertFalse( $c::inspect( json_encode( $bad ), $this->rscope() ), $key ); }
        foreach ( array( 'source' => 'authenticated', 'actor_id' => 8, 'issued_command_digest' => str_repeat( 'e', 64 ), 'recorded_at' => 400 ) as $key => $value ) { $bad = $root; $bad['operations'][$id]['provenance'][$key] = $value; $this->assertFalse( $c::inspect( json_encode( $bad ), $this->rscope() ), $key ); }
        foreach ( array( 'effect_ids', 'reservation_command_ids' ) as $map ) foreach ( array( 'wpvivid_snapshot', 'outbox_snapshot', 'queue_addition' ) as $role ) { $p = $this->rproposal(); $p[$map][$role] = $this->rid( 99 ); $this->assertFalse( $c::register( json_encode( $this->rroot() ), $this->rscope(), $p, 110 ), "$map/$role" ); }
        foreach ( array( 'effect_ids', 'reservation_command_ids' ) as $map ) { $p = $this->rproposal(); $p[$map]['queue_addition'] = $p['operation_id']; $p['command']['context_digest'] = $c::registration_context( $this->rscope(), $p ); $this->assertFalse( $c::register( json_encode( $this->rroot() ), $this->rscope(), $p, 110 ) ); }
    }
    public function test_receipt_corruption_and_fake_effect_success_cannot_pass() {
        $c = $this->registration_class(); $raw = $this->registered(); $reserved = $c::reserve( $raw, $this->rscope(), $this->reserve_proposal( $raw ), 120 ); $s = json_decode( $reserved, true ); $id = key( $s['operations'] );
        foreach ( array( 'registration_receipt', 'reservation_receipt' ) as $slot ) {
            foreach ( array( 'domain' => 'scan_effect_storage_closed_v1', 'purpose' => 'register_closed', 'command_id' => $this->rid( 99 ), 'operation_id' => $this->rid( 99 ), 'context_digest' => str_repeat( 'e', 64 ), 'result_revision' => 23, 'recorded_at' => 400, 'classification' => 'committed', 'extra' => true ) as $key => $value ) {
                if ( $value === ( $s['operations'][$id][$slot][$key] ?? null ) ) continue;
                $bad = $s; $bad['operations'][$id][$slot][$key] = $value; $this->assertFalse( $c::inspect( json_encode( $bad ), $this->rscope() ), "$slot/$key" );
            }
        }
        foreach ( array( 'state' => 'committed', 'dependency_digest' => str_repeat( 'f', 64 ), 'before_hash' => str_repeat( 'e', 64 ), 'after_hash' => str_repeat( 'e', 64 ), 'result_revision' => 22, 'receipt' => array() ) as $key => $value ) { $bad = $s; $bad['operations'][$id]['effects']['wpvivid_snapshot'][$key] = $value; $this->assertFalse( $c::inspect( json_encode( $bad ), $this->rscope() ), $key ); }
        $bad = $s; $bad['operations'][$this->rid( 99 )] = $bad['operations'][$id]; $this->assertFalse( $c::inspect( json_encode( $bad ), $this->rscope() ) );
        $bad = $s; $bad['ledger_revision'] = 23; $this->assertFalse( $c::inspect( json_encode( $bad ), $this->rscope() ) );
    }
    public function test_canonical_json_resources_references_and_objects_fail_without_callbacks() {
        $c = $this->registration_class(); $raw = json_encode( $this->rroot() );
        foreach ( array( null, false, new stdClass(), ' ' . $raw, '{"schema_version":2,' . substr( $raw, 1 ), str_replace( '"domain":', '"doma\u0069n":', $raw ), '{"x":1e309}', str_repeat( ' ', 131073 ), '{"x":' . str_repeat( '[', 17 ) . '0' . str_repeat( ']', 17 ) . '}', '{"x":[' . implode( ',', array_fill( 0, 3000, 1 ) ) . ']}', "{\"x\":\"\xff\"}" ) as $bad ) $this->assertFalse( $c::inspect( $bad, $this->rscope() ) );
        $p = $this->rproposal(); $p['actor_id'] = new class implements JsonSerializable { public function jsonSerialize(): array { throw new RuntimeException( 'must not execute' ); } };
        $this->assertFalse( $c::register( $raw, $this->rscope(), $p, 110 ) );
        $p = $this->rproposal(); $actor = 7; $p['actor_id'] =& $actor; $this->assertFalse( $c::register( $raw, $this->rscope(), $p, 110 ) );
        $p = $this->rproposal(); $p['actor_id'] =& $p; $this->assertFalse( $c::registration_context( $this->rscope(), $p ) );
    }
    public function test_worst_scalar_shapes_fit_with_supported_intent_and_terminal_headroom() {
        $c = $this->registration_class(); $s = $this->rroot(); $s['ledger_revision'] = PHP_INT_MAX - 7; $scope = $this->rscope(); $scope['authority_generation'] = PHP_INT_MAX - 5; $s['authority_generation'] = $scope['authority_generation'];
        $p = $this->rproposal(); $p['actor_id'] = PHP_INT_MAX - 5; $p['command']['expected_generation'] = $scope['authority_generation']; $p['command']['expected_ledger_revision'] = $s['ledger_revision']; $p['command']['issued_at'] = PHP_INT_MAX - 305; $p['command']['expires_at'] = PHP_INT_MAX - 5; $p['command']['context_digest'] = $c::registration_context( $scope, $p );
        $raw = $c::register( json_encode( $s ), $scope, $p, PHP_INT_MAX - 300 ); $this->assertIsString( $raw );
        $next = json_decode( $raw, true ); $o = reset( $next['operations'] ); $this->assertLessThanOrEqual( 6144, strlen( json_encode( $o ) ) + 1536 );
        $reserve = $this->reserve_proposal( $raw ); $reserve['actor_id'] = $p['actor_id']; $reserve['command']['context_digest'] = $c::reservation_context( $p['command']['context_digest'], $reserve );
        $after = $c::reserve( $raw, $scope, $reserve, PHP_INT_MAX - 299 ); $this->assertIsString( $after ); $this->assertLessThanOrEqual( 108544, strlen( $after ) );
        $final = json_decode( $after, true ); $o = reset( $final['operations'] ); $this->assertSame( PHP_INT_MAX - 5, $final['ledger_revision'] );
        foreach ( array( 'registration_receipt', 'reservation_receipt' ) as $slot ) $this->assertLessThanOrEqual( 1024, strlen( json_encode( $o[$slot] ) ) );
        $s['ledger_revision'] = PHP_INT_MAX - 6; $p['command']['expected_ledger_revision'] = $s['ledger_revision']; $p['command']['context_digest'] = $c::registration_context( $scope, $p ); $this->assertFalse( $c::register( json_encode( $s ), $scope, $p, PHP_INT_MAX - 300 ) );
    }
    public function test_reservation_aliases_are_refused_before_hashing_can_erase_reference_shape() {
        $c = $this->registration_class(); $raw = $this->registered();
        foreach ( array( 'command', 'actor_id', 'dependency_digest', 'before', 'replacement' ) as $key ) {
            $p = $this->reserve_proposal( $raw ); $alias = $p[$key]; $p[$key] =& $alias;
            $this->assertFalse( $c::reserve( $raw, $this->rscope(), $p, 120 ), $key );
            $this->assertFalse( $c::reservation_context( $this->rproposal()['command']['context_digest'], $p ), $key );
            unset( $alias, $p );
        }
    }
    public function test_dormant_public_api_has_no_writer_nonce_auth_or_execution_surface() {
        $reflection = new ReflectionClass( $this->registration_class() ); $names = array();
        foreach ( $reflection->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) { $this->assertTrue( $method->isStatic() ); $names[] = $method->getName(); }
        sort( $names ); $this->assertSame( array( 'inspect', 'lookup', 'register', 'registration_context', 'reservation_context', 'reserve' ), $names );
        $this->assertTrue( $reflection->isFinal() );
        $bootstrap = file_get_contents( dirname( __DIR__ ) . '/includes/class-plugin.php' );
        $this->assertStringNotContainsString( 'class-closed-registration-contract.php', $bootstrap );
    }
}
