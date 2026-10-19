<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RegistrationAdmissionFixture.php';
/** Positive data plans are mandatory; no authentication or transaction proof. */
class RegistrationAdmissionPlanTest extends TestCase {
    use Alynt_Test_Registration_Admission_Fixture;
    public function test_positive_plan_preserves_exact_successor_identity_and_receipt_without_authority() {
        list( $f, $p, $c, $scope ) = $this->admissionFixture(); $original = array( $f, $p, $c );
        $plan = $this->prepareAdmission( $f, $p, $c ); $this->assertIsObject( $plan );
        $this->assertTrue( $plan->matches( $f, $p, $c, 111 ) );
        $after = $this->admissionProperty( $plan, 'after' ); $this->assertSame( $this->registration_class()::register( $f['rows']['ledger']['value'], $scope, $p, 110 ), $after );
        $root = json_decode( $after, true ); $o = reset( $root['operations'] );
        $this->assertSame( 21, $root['ledger_revision'] ); $this->assertSame( $scope['authority_generation'], $root['authority_generation'] );
        $this->assertSame( $p['command'], $o['command'] ); $this->assertSame( $p['operation_id'], $o['operation_id'] );
        $this->assertSame( $p['reservation_command_ids'], $o['reservation_command_ids'] ); $this->assertSame( $p['terminal_id'], $o['terminal_id'] ); $this->assertSame( $p['resolution_id'], $o['resolution_id'] );
        foreach ( $p['effect_ids'] as $role => $id ) { $this->assertSame( $id, $o['effects'][$role]['effect_id'] ); $this->assertSame( 'reserved', $o['effects'][$role]['state'] ); $this->assertNull( $o['effects'][$role]['receipt'] ); }
        $this->assertSame( 'registered_closed', $o['registration_receipt']['classification'] ); $this->assertSame( 110, $o['registration_receipt']['recorded_at'] ); $this->assertNull( $o['terminal'] );
        $this->assertSame( $original, array( $f, $p, $c ) );
        foreach ( array( 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $flag ) $this->assertFalse( $plan->disposition()[$flag] );
        $this->assertSame( $plan->disposition(), $plan->__debugInfo() ); $this->assertArrayNotHasKey( 'after', $plan->__debugInfo() );
    }
    public function test_every_role_and_tuple_field_is_compared_not_just_ledger_or_digest() {
        list( $f, $p, $c ) = $this->admissionFixture(); $plan = $this->prepareAdmission( $f, $p, $c );
        foreach ( array_keys( $f['rows'] ) as $role ) foreach ( array_keys( $f['rows'][$role] ) as $field ) {
            $bad = $f; $bad['rows'][$role][$field] .= 'x'; $this->assertFalse( $plan->matches( $bad, $p, $c, 111 ), "$role/$field" );
        }
        foreach ( array( 'blog_id' => 2, 'prefix' => 'other_', 'database' => 'other' ) as $key => $value ) { $bad = $f; $bad[$key] = $value; $this->assertFalse( $plan->matches( $bad, $p, $c, 111 ), $key ); $this->assertFalse( $this->prepareAdmission( $bad, $p, $c ) ); }
        foreach ( $c as $key => $value ) { $bad = $c; $bad[$key] = is_int( $value ) ? $value + 1 : $value . 'x'; $this->assertFalse( $plan->matches( $f, $p, $bad, 111 ), $key ); $this->assertFalse( $this->prepareAdmission( $f, $p, $bad ) ); }
        $this->assertTrue( $plan->matches( $f, $p, $c, 111 ) );
    }
    public function test_proposal_actor_context_and_full_semantic_eligibility_are_required() {
        list( $f, $p, $c, $scope ) = $this->admissionFixture(); $plan = $this->prepareAdmission( $f, $p, $c );
        foreach ( array( 'actor_id' => 8, 'operation_id' => $this->rid( 50 ), 'dependency_digest' => str_repeat( 'f', 64 ), 'terminal_id' => $this->rid( 51 ) ) as $key => $value ) { $bad = $p; $bad[$key] = $value; $this->assertFalse( $plan->matches( $f, $bad, $c, 111 ) ); $this->assertFalse( $this->prepareAdmission( $f, $bad, $c ) ); }
        $bad = $p; $bad['actor_id'] = 8; $bad['command']['context_digest'] = $this->registration_class()::registration_context( $scope, $bad ); $this->assertFalse( $this->prepareAdmission( $f, $bad, $c ), 'Even a self-consistent proposal cannot change the pinned actor.' );
        foreach ( array( 'safety', 'settings', 'connection', 'wpvivid_snapshot', 'outbox_snapshot', 'queue', 'active', 'uploaded', 'failed', 'ledger' ) as $role ) {
            $bad = $f; $raw = 'a:1:{s:1:"x";O:8:"stdClass":0:{}}'; $bad['rows'][$role]['value'] = $raw; $bad['rows'][$role]['octets'] = (string) strlen( $raw );
            $proposal = $p; $proposal['dependency_digest'] = hash( 'sha256', 'registration_full_frame_v2:' . json_encode( $bad ) ); $proposal['command']['context_digest'] = $this->registration_class()::registration_context( $scope, $proposal );
            $this->assertFalse( $this->prepareAdmission( $bad, $proposal, $c ), $role );
        }
    }
    public function test_times_domains_aliases_and_malformed_inputs_cannot_form_or_match_a_plan() {
        list( $f, $p, $c ) = $this->admissionFixture(); $plan = $this->prepareAdmission( $f, $p, $c );
        foreach ( array( 99, 400, '110', PHP_INT_MAX, null ) as $now ) $this->assertFalse( $this->prepareAdmission( $f, $p, $c, $now ) );
        foreach ( array( 109, 400, '111', null ) as $now ) $this->assertFalse( $plan->matches( $f, $p, $c, $now ) );
        $this->assertTrue( $plan->matches( $f, $p, $c, 399 ) );
        foreach ( array( 'purpose' => 'observe_registration', 'confirm' => 'reserve', 'expected_generation' => 99, 'expected_ledger_revision' => 19, 'context_digest' => str_repeat( 'e', 64 ) ) as $key => $value ) { $bad = $p; $bad['command'][$key] = $value; $this->assertFalse( $this->prepareAdmission( $f, $bad, $c ) ); $this->assertFalse( $plan->matches( $f, $bad, $c, 111 ) ); }
        $value = $f['rows']['queue']['value']; $bad = $f; $bad['rows']['queue']['value'] =& $value;
        $this->assertFalse( $this->prepareAdmission( $bad, $p, $c ) ); $this->assertFalse( $plan->matches( $bad, $p, $c, 111 ) ); unset( $bad );
        $actor = $p['actor_id']; $bad = $p; $bad['actor_id'] =& $actor; $this->assertFalse( $this->prepareAdmission( $f, $bad, $c ) ); $this->assertFalse( $plan->matches( $f, $bad, $c, 111 ) ); unset( $bad );
        foreach ( array( null, new stdClass(), array() ) as $bad ) { $this->assertFalse( $this->prepareAdmission( $bad, $p, $c ) ); $this->assertFalse( $plan->matches( $bad, $p, $c, 111 ) ); }
        foreach ( array( null, new stdClass(), array(), array( 'actor_id' => 7 ), array( 'actor_id' => new stdClass() ) ) as $bad ) { $this->assertFalse( $this->prepareAdmission( $f, $bad, $c ) ); $this->assertFalse( $this->prepareAdmission( $f, $p, $bad ) ); $this->assertFalse( $plan->matches( $f, $bad, $c, 111 ) ); $this->assertFalse( $plan->matches( $f, $p, $bad, 111 ) ); }
        $this->assertFalse( Alynt_Drime_Backups_Uploader_Registration_Admission_Plan::prepare( $f, $p, $c, 110, 'extra' ) );
        $this->expectException( LogicException::class ); serialize( $plan );
    }
    public function test_recomputed_hashes_do_not_replace_dependency_or_safety_identity_guards() {
        list( $f, $p, $c, $scope ) = $this->admissionFixture();
        $bad = $p; $bad['dependency_digest'] = str_repeat( 'f', 64 ); $bad['command']['context_digest'] = $this->registration_class()::registration_context( $scope, $bad );
        $this->assertIsString( $this->registration_class()::register( $f['rows']['ledger']['value'], $scope, $bad, 110 ), 'Self-consistent pure ledger grammar alone is insufficient.' );
        $this->assertFalse( $this->prepareAdmission( $f, $bad, $c ), 'Complete captured dependency must match, even after context recomputation.' );
        $s = json_decode( $f['rows']['safety']['value'], true ); $bad = $p; $bad['terminal_id'] = $s['writer']['upgrade']['command']['command_id'];
        $bad['command']['context_digest'] = $this->registration_class()::registration_context( $scope, $bad );
        $this->assertIsString( $this->registration_class()::register( $f['rows']['ledger']['value'], $scope, $bad, 110 ) );
        $this->assertFalse( $this->prepareAdmission( $f, $bad, $c ), 'Schema-8 history collision is not checked by the independent schema-2 ledger codec.' );
    }
}
