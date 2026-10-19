<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RegistrationObservationHandoffFixture.php';
/** Complete-frame candidate semantics, not authentication or native proof. */
class RegistrationObservationCandidateTest extends TestCase {
    use Alynt_Test_Registration_Observation_Handoff_Fixture;
    public function test_valid_pending_retained_reservation_and_absence_are_distinct_candidates() {
        foreach ( array( 'registered', 'reserved', 'empty' ) as $phase ) {
            $f = $this->handoffFixture( $phase ); $r = $this->candidateResult( $f );
            $this->assertCandidateOnly( $r, 'empty' === $phase ? 'not_observed_in_candidate' : 'registered_closed' );
            $this->assertGreaterThan( $f['data']['mutation']['expires_at'], $f['clock']['utc'] );
            foreach ( array( $f['data']['context_digest'], $f['intent']['nonce'], $f['form']['nonce'], $f['frame']['rows']['ledger']['value'] ) as $secret ) $this->assertStringNotContainsString( $secret, json_encode( $r ) );
        }
    }
    public function test_current_valid_frame_need_not_equal_pre_registration_or_other_old_bytes() {
        $f = $this->handoffFixture(); $this->assertSame( 1, $this->registration_class()::inspect( $f['frame']['rows']['ledger']['value'], $f['data']['scope'] )['pending_count'] );
        $f['frame'] = $this->handoffRow( $f['frame'], 'connection', serialize( array( 'unknown' => 'changed-current-bytes' ) ) );
        $f['frame'] = $this->handoffRow( $f['frame'], 'settings', serialize( array( 'site_uuid' => $f['data']['scope']['site_uuid'], 'auto_scan_enabled' => false, 'unknown' => array( 'retained' ) ) ) );
        $this->assertCandidateOnly( $this->candidateResult( $f ), 'registered_closed' );
    }
    public function test_every_payload_role_is_validated_not_just_ledger_and_safety() {
        $good = $this->handoffFixture();
        foreach ( array_keys( $good['frame']['rows'] ) as $role ) { $bad = $good; $bad['frame'] = $this->handoffRow( $bad['frame'], $role, 'malformed-not-a-codec' ); $this->assertFalse( $this->candidateResult( $bad ), $role ); }
        foreach ( array( 'safety', 'settings', 'ledger', 'connection' ) as $role ) { $bad = $good; unset( $bad['frame']['rows'][$role] ); $this->assertFalse( $this->candidateResult( $bad ), $role ); }
    }
    public function test_tuple_identity_names_autoload_lengths_aliases_and_bounds_are_not_adopted() {
        $good = $this->handoffFixture();
        foreach ( array( 'option_id' => '01', 'option_name' => 'other', 'autoload' => 'yes', 'octets' => '1', 'value' => new stdClass(), 'extra' => null ) as $key => $value ) { $bad = $good; $bad['frame']['rows']['connection'][$key] = $value; $this->assertFalse( $this->candidateResult( $bad ), $key ); }
        $bad = $good; $bad['frame']['rows']['connection']['option_id'] = $bad['frame']['rows']['ledger']['option_id']; $this->assertFalse( $this->candidateResult( $bad ) );
        $bad = $good; $bad['frame']['rows'] = array_reverse( $bad['frame']['rows'], true ); $this->assertFalse( $this->candidateResult( $bad ) );
        foreach ( $good['frame']['rows'] as $role => $row ) {
            foreach ( array_keys( $row ) as $key ) { $bad = $good; $v = $row[$key]; $bad['frame']['rows'][$role][$key] =& $v; $this->assertFalse( $this->candidateResult( $bad ), "$role/$key reference" ); unset( $bad, $v ); }
            $bad = $good; $limit = Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows()[$role][1]; $bad['frame'] = $this->handoffRow( $bad['frame'], $role, str_repeat( 'x', $limit + 1 ) ); $this->assertFalse( $this->candidateResult( $bad ), "$role bound" );
        }
        $bad = $good; $rows = $bad['frame']['rows']; $bad['frame']['rows'] =& $rows; $this->assertFalse( $this->candidateResult( $bad ) );
        $bad = $good; $row = $bad['frame']['rows']['queue']; $bad['frame']['rows']['queue'] =& $row; $this->assertFalse( $this->candidateResult( $bad ) );
    }
    public function test_disabled_settings_complete_provenance_and_original_scope_are_mandatory() {
        $good = $this->handoffFixture();
        foreach ( array( 'blog_id' => 2, 'prefix' => 'other_', 'database' => 'other' ) as $key => $value ) { $bad = $good; $bad['frame'][$key] = $value; $this->assertFalse( $this->candidateResult( $bad ), $key ); }
        $bad = $good; $bad['frame'] = $this->handoffRow( $bad['frame'], 'settings', serialize( array( 'site_uuid' => $bad['data']['scope']['site_uuid'], 'auto_scan_enabled' => true ) ) ); $this->assertFalse( $this->candidateResult( $bad ) );
        $s = json_decode( $good['frame']['rows']['safety']['value'], true );
        foreach ( array( 'schema_version' => 7, 'enrollment_binding' => str_repeat( 'e', 64 ) ) as $key => $value ) { $broken = $s; $broken[$key] = $value; $bad = $good; $bad['frame'] = $this->handoffRow( $bad['frame'], 'safety', json_encode( $broken ) ); $this->assertFalse( $this->candidateResult( $bad ), $key ); }
        $broken = $s; $broken['writer']['upgrade']['committed_at']++; $bad = $good; $bad['frame'] = $this->handoffRow( $bad['frame'], 'safety', json_encode( $broken ) ); $this->assertFalse( $this->candidateResult( $bad ) );
        $broken = $s; $broken['writer']['authenticated_grant'] = array( 'enabled' => true ); $bad['frame'] = $this->handoffRow( $good['frame'], 'safety', json_encode( $broken ) ); $this->assertFalse( $this->candidateResult( $bad ) );
        $bad = $good; $bad['data']['scope']['ledger_id'] = $this->rid( 998 ); $this->assertFalse( $this->candidateResult( $bad ) );
    }
    public function test_valid_old_writer_pending_is_not_registration_pending_and_is_refused() {
        list( $pending ) = $this->real_pending(); $this->assertTrue( $this->valid_real( $pending ) );
        $bad = $this->handoffFixture( 'empty', $pending ); $this->assertFalse( $this->candidateResult( $bad ) );
        $good = $this->handoffFixture( 'empty' ); $this->assertCandidateOnly( $this->candidateResult( $good ), 'not_observed_in_candidate' );
    }
    public function test_exact_historical_facts_are_not_replaced_by_current_receipt_facts() {
        $good = $this->handoffFixture( 'reserved' );
        foreach ( array( 'actor_id' => 8, 'operation_id' => $this->rid( 999 ) ) as $key => $value ) { $bad = $good; $bad['data'][$key] = $value; $this->assertCandidateOnly( $this->candidateResult( $bad ), 'domain_conflict' ); }
        $bad = $good; $bad['data']['mutation']['expected_ledger_revision']--; $this->assertCandidateOnly( $this->candidateResult( $bad ), 'domain_conflict' );
        $bad = $good; $bad['data']['context_digest'] = str_repeat( 'e', 64 ); $bad['data']['mutation']['context_digest'] = $bad['data']['context_digest']; $this->assertCandidateOnly( $this->candidateResult( $bad ), 'domain_conflict' );
        $raw = json_decode( $good['frame']['rows']['ledger']['value'], true ); $id = array_keys( $raw['operations'] )[0]; $raw['operations'][$id]['provenance']['actor_id'] = 8;
        $bad = $good; $bad['frame'] = $this->handoffRow( $bad['frame'], 'ledger', json_encode( $raw ) ); $this->assertFalse( $this->candidateResult( $bad ), 'Invalid chain is unavailable, not conflict evidence.' );
    }
    public function test_lookup_cannot_reuse_current_reserved_or_safety_identities() {
        $good = $this->handoffFixture();
        foreach ( $good['ids'] as $id ) { $bad = $good; $bad['form']['command_id'] = $id; $this->assertFalse( $this->candidateResult( $bad ), $id ); }
        $bad = $good; $root = json_decode( $bad['frame']['rows']['ledger']['value'], true ); $id = array_keys( $root['operations'] )[0];
        $p = $this->rproposal(); $p['command'] = $root['operations'][$id]['command']; $p['effect_ids']['queue_addition'] = $good['ids'][12];
        // Rebuild a valid history with a safety-domain collision, not malformed JSON.
        $scope = $good['data']['scope']; $root['operations'] = array(); $root['ledger_revision'] = 20; $p['dependency_digest'] = str_repeat( 'b', 64 ); $p['command']['context_digest'] = $this->registration_class()::registration_context( $scope, $p );
        $raw = $this->registration_class()::register( json_encode( $root ), $scope, $p, 110 ); $this->assertIsString( $raw ); $this->assertIsArray( $this->registration_class()::inspect( $raw, $scope ) );
        $bad['frame'] = $this->handoffRow( $bad['frame'], 'ledger', $raw ); $this->assertFalse( $this->candidateResult( $bad ) );
    }
    public function test_forged_qualifiers_extra_arguments_and_stale_read_do_not_mint_evidence() {
        $good = $this->handoffFixture();
        foreach ( array( 'frame', 'data', 'intent', 'form' ) as $part ) { $bad = $good; $bad[$part]['barrier_verified'] = true; $this->assertFalse( $this->candidateResult( $bad ), $part ); }
        $bad = $good; $bad['clock']['utc'] = 800; $this->assertFalse( $this->candidateResult( $bad ) );
        $this->assertFalse( Alynt_Drime_Backups_Uploader_Registration_Observation_Candidate::observe( $good['frame'], $good['data'], $good['intent'], $good['form'], 510, array( 'commit_ack' => true ) ) );
    }
}
