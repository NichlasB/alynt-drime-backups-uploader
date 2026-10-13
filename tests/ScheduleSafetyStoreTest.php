<?php
/** Scheduling safety-state contract tests. */
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/ScheduleSafetyFixture.php';

/**
 * Isolate optional WordPress function definitions from unrelated fixtures.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ScheduleSafetyStoreTest extends TestCase {
	private $backend;
	private $store;
	private $now = 1800000000;
	private $binding;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		$this->backend = new Alynt_Drime_Backups_Uploader_Test_Safety_Memory_CAS();
		$this->binding = str_repeat( 'e', 64 );
		$this->store = $this->new_store();
		$this->store->initialize( $this->binding );
	}
	protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }
	private function new_store() { return new Alynt_Drime_Backups_Uploader_Schedule_Safety_Store( $this->backend, function () { return $this->now; } ); }
	private function claim( $number = 1, $source = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE ) { return $this->store->claim( $this->binding, $source, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $this->now, $number ) ); }

	public function test_duplicate_observes_same_lease_and_new_owner_cannot_reuse_source() {
		$evidence = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $this->now );
		$claim = $this->claim();
		$this->now += 20;
		$this->assertSame( $claim, $this->store->claim( $this->binding, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE, $evidence ) );
		$this->assertSame( 1, $this->backend->swaps );
		$this->assertSame( 'schedule_safety_source_claimed', $this->claim( 2 )->get_error_code() );
	}

	public function test_interleaved_claimants_have_only_one_winner() {
		$competitor = $this->new_store();
		$this->backend->before_swap = function () use ( $competitor ) {
			$this->assertIsArray( $competitor->claim( $this->binding, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $this->now, 2 ) ) );
		};
		$this->assertSame( 'schedule_safety_source_claimed', $this->claim()->get_error_code() );
		$this->assertSame( 1, $this->store->snapshot()['generation'] );
	}

	public function test_failed_write_and_bounded_contention_do_not_claim_source() {
		$this->backend->fail_write = true;
		$this->assertSame( 'schedule_safety_write_failed', $this->claim()->get_error_code() );
		$this->assertSame( array(), $this->store->snapshot()['claims'] );
		$this->backend->fail_write = false;
		$this->backend->conflicts = 4;
		$this->assertSame( 'schedule_safety_contention', $this->claim()->get_error_code() );
		$this->assertSame( 4, $this->backend->swaps );
	}

	public function test_expired_claim_is_never_executable_or_a_fresh_lease() {
		$claim = $this->claim();
		$this->now = $claim['deadline'];
		$result = $this->store->transition( $this->binding, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE, $claim['action_id'], $claim['request_hash'], 'claimed', 'write_started' );
		$this->assertSame( 'schedule_safety_inspection_required', $result->get_error_code() );
		$this->assertSame( 'claimed', $this->store->snapshot()['claims'][ Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE ]['phase'] );
	}

	public function test_restart_and_rebinding_preserve_unresolved_journal() {
		$claim = $this->claim();
		$this->store->transition( $this->binding, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE, $claim['action_id'], $claim['request_hash'], 'claimed', 'write_started' );
		$restart = $this->new_store();
		$this->assertSame( 'write_started', $restart->snapshot()['claims'][ Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE ]['phase'] );
		$this->assertSame( 1, $restart->invalidate( $this->binding, str_repeat( 'f', 64 ) ) );
		$this->assertSame( 'recovery_required', $restart->snapshot()['claims'][ Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE ]['phase'] );
		$this->assertSame( 'schedule_safety_binding_changed', $this->claim( 2 )->get_error_code() );
	}

	public function test_unknown_or_autoloaded_state_is_not_repaired() {
		$name = Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION;
		$original = $this->backend->rows[ $name ];
		$this->backend->rows[ $name ]['autoload'] = 'yes';
		$this->assertSame( 'schedule_safety_invalid_state', $this->store->snapshot()->get_error_code() );
		$this->backend->rows[ $name ] = $original;
		$state = json_decode( $original['option_value'], true );
		$state['schema_version'] = 2;
		$this->backend->rows[ $name ]['option_value'] = json_encode( $state );
		$this->assertSame( 'schedule_safety_invalid_state', $this->store->initialize( $this->binding )->get_error_code() );
		$this->assertSame( 2, json_decode( $this->backend->rows[ $name ]['option_value'], true )['schema_version'] );
	}

	public function test_forged_fields_and_string_timestamps_are_rejected() {
		$evidence = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $this->now );
		$evidence['path'] = '/unsafe';
		$this->assertSame( 'schedule_safety_invalid_state', $this->store->claim( $this->binding, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE, $evidence )->get_error_code() );
		unset( $evidence['path'] );
		$evidence['intent_expires_at'] = (string) $evidence['intent_expires_at'];
		$this->assertInstanceOf( WP_Error::class, $this->store->claim( $this->binding, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE, $evidence ) );
	}

	public function test_capacity_does_not_evict_unresolved_or_expired_claims() {
		for ( $i = 1; $i <= 50; $i++ ) {
			$this->assertIsArray( $this->claim( $i, sprintf( '22222222-2222-4222-8222-%012d', $i ) ) );
		}
		$this->now += 90000;
		$this->assertSame( 'schedule_safety_capacity_reached', $this->claim( 51, '44444444-4444-4444-8444-444444444444' )->get_error_code() );
		$this->assertCount( 50, $this->store->snapshot()['claims'] );
	}

	public function test_terminal_source_remains_consumed_and_unrelated_action_cannot_transition() {
		$claim = $this->claim();
		$source = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE;
		$this->assertSame( 'schedule_safety_owner_mismatch', $this->store->transition( $this->binding, $source, '44444444-4444-4444-8444-444444444444', $claim['request_hash'], 'claimed', 'write_started' )->get_error_code() );
		$this->assertIsArray( $this->store->transition( $this->binding, $source, $claim['action_id'], $claim['request_hash'], 'claimed', 'write_started' ) );
		$this->assertIsArray( $this->store->transition( $this->binding, $source, $claim['action_id'], $claim['request_hash'], 'write_started', 'succeeded' ) );
		$this->assertSame( 'schedule_safety_source_claimed', $this->claim( 2 )->get_error_code() );
		$this->assertInstanceOf( WP_Error::class, $this->store->transition( $this->binding, array(), $claim['action_id'], $claim['request_hash'], 'claimed', 'write_started' ) );
	}

	public function test_earliest_metadata_deadline_and_valid_short_contention_retry() {
		$evidence = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $this->now );
		$evidence['metadata_expires_at'] = $this->now + 30;
		$this->backend->conflicts = 1;
		$claim = $this->store->claim( $this->binding, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE, $evidence );
		$this->assertSame( $this->now + 30, $claim['deadline'] );
		$this->assertSame( 2, $this->backend->swaps );
		$wrong_binding = str_repeat( 'f', 64 );
		$this->assertSame( 'schedule_safety_binding_changed', $this->store->invalidate( $wrong_binding )->get_error_code() );
	}

	public function test_unresolved_write_blocks_another_source_from_starting() {
		$first = $this->claim();
		$this->store->transition( $this->binding, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE, $first['action_id'], $first['request_hash'], 'claimed', 'write_started' );
		$source = '44444444-4444-4444-8444-444444444444';
		$second = $this->claim( 2, $source );
		$this->assertSame( 'schedule_safety_recovery_pending', $this->store->transition( $this->binding, $source, $second['action_id'], $second['request_hash'], 'claimed', 'write_started' )->get_error_code() );
	}
}
