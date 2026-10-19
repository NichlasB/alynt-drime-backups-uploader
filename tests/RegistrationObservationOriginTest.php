<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RegistrationObservationFixture.php';
/** Pure origin/result consistency, not genuine provenance or physical barriers. */
class RegistrationObservationOriginTest extends TestCase {
    use Alynt_Test_Registration_Observation_Fixture;
    public function test_pending_history_positive_survives_mutation_expiry_and_reservation_without_authority() {
        $raw = $this->registered(); $reserved = $this->registration_class()::reserve( $raw, $this->rscope(), $this->reserve_proposal( $raw ), 120 );
        foreach ( array( $raw, $reserved ) as $candidate ) {
            $r = $this->observationResult( $candidate ); $this->assertIsArray( $r ); $this->assertSame( 'registered_closed', $r['classification'] );
            foreach ( array( 'barrier_verified', 'closure_proven', 'retry_supported', 'authenticated', 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $flag ) $this->assertFalse( $r[$flag] );
            foreach ( array( $this->rid( 3 ), $this->rid( 4 ), 'synthetic-receipt', $this->rproposal()['command']['context_digest'] ) as $secret ) $this->assertStringNotContainsString( $secret, json_encode( $r ) );
        }
        $this->assertSame( 'not_observed_in_candidate', $this->observationResult( json_encode( $this->rroot() ) )['classification'] );
    }
    public function test_factory_rejects_malformed_unbounded_aliased_and_internally_unbound_origins() {
        $good = $this->observationData(); $this->assertIsObject( $this->observationOrigin( $good ) );
        foreach ( array( 'domain' => 'other', 'manifest_digest' => str_repeat( 'e', 64 ), 'actor_id' => '7', 'operation_id' => $this->rid( 3 ), 'context_digest' => str_repeat( 'f', 64 ), 'provider' => 'database' ) as $key => $value ) { $bad = $good; $bad[$key] = $value; $this->assertFalse( $this->observationOrigin( $bad ), $key ); }
        foreach ( array( 'blog_id' => 0, 'prefix' => 'wp-evil', 'database' => 'other' ) as $key => $value ) { $bad = $good; $bad['installation'][$key] = $value; $this->assertFalse( $this->observationOrigin( $bad ), $key ); }
        foreach ( array( 'site_uuid' => $this->rid( 99 ), 'enrollment_binding' => str_repeat( 'e', 64 ), 'authority_generation' => 11, 'origin_created_at' => 101 ) as $key => $value ) { $bad = $good; $bad['scope'][$key] = $value; $this->assertFalse( $this->observationOrigin( $bad ), $key ); }
        foreach ( array( 'issued_at' => '100', 'nonce' => 'never-retained', 'purpose' => 'reserve_wpvivid_intent' ) as $key => $value ) { $bad = $good; $bad['mutation'][$key] = $value; $this->assertFalse( $this->observationOrigin( $bad ), $key ); }
        $bad = $good; $site = $bad['scope']['site_uuid']; $bad['scope']['site_uuid'] =& $site; $this->assertFalse( $this->observationOrigin( $bad ) );
        foreach ( array( new stdClass(), fopen( 'php://memory', 'r' ), str_repeat( 'x', 4097 ), NAN, INF, array_fill( 0, 300, null ), array( array( array( array( array( array( array( 'deep' ) ) ) ) ) ) ) ) as $value ) { $bad = $good; $bad['installation']['database'] = $value; $this->assertFalse( $this->observationOrigin( $bad ) ); if ( is_resource( $value ) ) fclose( $value ); }
    }
    public function test_current_scope_and_exact_historical_bindings_cannot_be_adopted_from_receipt() {
        $data = $this->observationData(); $o = $this->observationOrigin();
        foreach ( array( 'blog_id' => 2, 'prefix' => 'new_', 'database' => 'other' ) as $key => $value ) { $install = $data['installation']; $install[$key] = $value; $this->assertFalse( $o->observe( $this->registered(), $this->observationIntent(), $this->observationForm(), $install, $data['scope'], 510 ) ); }
        foreach ( $data['scope'] as $key => $value ) { $scope = $data['scope']; $scope[$key] = is_int( $value ) ? $value + 1 : ( 'enrollment_binding' === $key ? str_repeat( 'e', 64 ) : $this->rid( 99 ) ); $this->assertFalse( $o->observe( $this->registered(), $this->observationIntent(), $this->observationForm(), $data['installation'], $scope, 510 ), $key ); }
        foreach ( array( 'actor_id' => 8, 'operation_id' => $this->rid( 99 ) ) as $key => $value ) { $other = $data; $other[$key] = $value; $this->assertSame( 'domain_conflict', $this->observationResult( null, $other )['classification'], $key ); }
        $other = $data; $other['mutation']['context_digest'] = str_repeat( 'f', 64 ); $other['context_digest'] = str_repeat( 'f', 64 ); $this->assertSame( 'domain_conflict', $this->observationResult( null, $other )['classification'] );
        $other = $data; $other['mutation']['expected_ledger_revision'] = 19; $this->assertSame( 'domain_conflict', $this->observationResult( null, $other )['classification'] );
        $this->assertTrue( $o->matches( $data ) ); $data['actor_id'] = 9; $this->assertFalse( $o->matches( $data ) ); $this->assertSame( 'registered_closed', $this->observationResult()['classification'] );
        foreach ( array( '{}', $this->registered() . ' ', str_repeat( 'x', 131073 ) ) as $bad ) $this->assertFalse( $this->observationResult( $bad ) );
    }
    public function test_privacy_and_dormancy_do_not_export_origin_or_accept_a_barrier_claim() {
        $o = $this->observationOrigin(); $this->assertSame( $o->disposition(), $o->__debugInfo() );
        foreach ( array( 'get', 'export', 'execute', 'admit', 'reader', 'write', 'barrier' ) as $method ) $this->assertFalse( method_exists( $o, $method ) );
        $form = $this->observationForm(); $form['barrier_verified'] = 'true'; $this->assertFalse( $this->observationResult( null, null, $form ) );
        foreach ( array_merge( array( dirname( __DIR__ ) . '/alynt-drime-backups-uploader.php' ), glob( dirname( __DIR__ ) . '/includes/class-plugin*.php' ), array( dirname( __DIR__ ) . '/includes/class-registration-native-admission.php' ) ) as $path ) $this->assertStringNotContainsString( 'registration-observation-', file_get_contents( $path ) );
        $this->expectException( LogicException::class ); serialize( $o );
    }
    public function test_a_valid_replacement_genesis_and_ledger_cannot_hide_a_changed_original_scope() {
        $d = $this->observationData(); $scope = $d['scope']; $scope['ledger_id'] = $this->rid( 99 ); $root = $this->rroot(); $root['origin']['ledger_id'] = $scope['ledger_id'];
        $raw = json_encode( $root ); $pure = $this->registration_class()::lookup( $raw, $scope, $this->observe_command( $this->rid( 3 ) ), $d['context_digest'], 510 );
        $this->assertSame( 'not_observed_in_candidate', $pure['classification'] );
        $this->assertFalse( $this->observationOrigin()->observe( $raw, $this->observationIntent(), $this->observationForm(), $d['installation'], $scope, 510 ) );
        $intent = $this->observationIntent(); $form = $this->observationForm(); $intent['observed_command_id'] = $this->rid( 99 ); $form['observed_command_id'] = $this->rid( 99 );
        $this->assertFalse( $this->observationOrigin()->accepts( $intent, $form, 510 ) ); // Both forms agree; original origin must still bind them.
        $o = $this->observationOrigin( $d ); $d['mutation']['command_id'] = $this->rid( 98 );
        $this->assertFalse( $o->matches( $d ) ); $this->assertTrue( $o->matches( $this->observationData() ) );
    }
    public function test_refs_unknown_fields_invalid_wire_types_and_privacy_cannot_cross_any_origin_depth() {
        $good = $this->observationData();
        foreach ( array( 'installation', 'scope', 'mutation' ) as $section ) {
            foreach ( $good[$section] as $key => $unused ) { $bad = $good; $v = $bad[$section][$key]; $bad[$section][$key] =& $v; $this->assertFalse( $this->observationOrigin( $bad ), "$section/$key reference" ); unset( $bad, $v ); }
        }
        foreach ( array_keys( $good ) as $key ) { $bad = $good; unset( $bad[$key] ); $this->assertFalse( $this->observationOrigin( $bad ), "$key missing" ); }
        foreach ( array( 'installation', 'scope', 'mutation' ) as $section ) { $bad = $good; $bad[$section]['extra'] = null; $this->assertFalse( $this->observationOrigin( $bad ) ); }
        foreach ( array( 0, -1, true, 7.0, PHP_INT_MAX ) as $value ) { $bad = $good; $bad['actor_id'] = $value; $this->assertFalse( $this->observationOrigin( $bad ) ); }
        foreach ( array( true, false ) as $clone ) {
            try { if ( $clone ) { $copy = clone $this->observationOrigin(); } else { $this->observationOrigin()->__unserialize( array( 'provider' => 'native' ) ); } $this->fail( 'Private data was restored/copied.' ); }
            catch ( Throwable $error ) { $this->assertTrue( $error instanceof Error || $error instanceof LogicException ); }
        }
    }
}
