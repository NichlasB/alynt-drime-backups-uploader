<?php
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/CadenceLifecycleFixture.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CadenceLifecycleValidationTest extends TestCase {
	use Alynt_Drime_Backups_Uploader_Test_Cadence_Lifecycle_Fixture;
	protected function setUp(): void { parent::setUp(); Monkey\setUp(); Functions\when( 'wp_json_encode' )->alias( 'json_encode' ); $this->prepare_fixture(); $this->prepare(); }
	protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

	/** @dataProvider invalid_lifecycle_fields */
	public function test_malformed_or_activation_shaped_lifecycle_is_never_repaired( $field, $value ) {
		$s = $this->model->snapshot(); $s['lifecycle'][$field] = $value; $this->replace_state( $s ); $before = $this->backend->rows;
		$this->assertInstanceOf( WP_Error::class, $this->model->snapshot() ); $this->assertInstanceOf( WP_Error::class, $this->prepare() );
		$this->assertInstanceOf( WP_Error::class, $this->model->record_consent( $this->binding, 0, true ) ); $this->assertSame( $before, $this->backend->rows );
	}
	public function invalid_lifecycle_fields() {
		return array( array( 'phase', 'active' ), array( 'phase', 'legacy' ), array( 'phase', array() ), array( 'phase', 'draining' ), array( 'consent', 1 ), array( 'consent', 'false' ),
			array( 'consent', true ), array( 'permission_generation', '0' ), array( 'permission_generation', -1 ), array( 'permission_generation', PHP_INT_MAX ),
			array( 'history', 'activated' ), array( 'history', null ), array( 'changed_at', 0 ), array( 'changed_at', '1800000000' ), array( 'extra_permission', true ) );
	}
	public function test_unknown_schema_missing_keys_invalid_json_and_autoload_are_not_overwritten() {
		$name = Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION; $baseline = $this->backend->rows;
		foreach ( array( 'json', 'missing', 'schema', 'enabled', 'autoload' ) as $case ) {
			$this->backend->rows = $baseline; $s = $this->model->snapshot();
			if ( 'missing' === $case ) unset( $s['lifecycle']['consent'] );
			if ( 'schema' === $case ) $s['schema_version'] = 4;
			if ( 'enabled' === $case ) $s['authority']['enabled'] = true;
			$this->replace_state( $s ); if ( 'json' === $case ) $this->backend->rows[$name]['option_value'] = '{';
			if ( 'autoload' === $case ) $this->backend->rows[$name]['autoload'] = 'yes'; $before = $this->backend->rows;
			$this->assertInstanceOf( WP_Error::class, $this->prepare() ); $this->assertSame( $before, $this->backend->rows );
		}
	}
	public function test_invalid_preparation_arguments_and_context_cannot_initialize_or_rebind() {
		$before = $this->backend->rows;
		foreach ( array( array( $this->binding, 'daily', 1800000900 ), array( $this->binding, 'every_15_minutes', '1800000900' ), array( 'bad', 'every_15_minutes', 1800000900 ), array( str_repeat( 'b', 64 ), 'every_15_minutes', 1800000900 ) ) as $args ) {
			$this->assertInstanceOf( WP_Error::class, $this->model->prepare_lifecycle( ...$args ) ); $this->assertSame( $before, $this->backend->rows );
		}
		$this->backend->rows = array();
		foreach ( array( $this->now, $this->now + 901 ) as $due ) { $this->assert_error( 'schedule_safety_lifecycle_invalid', $this->model->prepare_lifecycle( $this->binding, 'every_15_minutes', $due ) ); $this->assertSame( array(), $this->backend->rows ); }
	}
	public function test_older_schema_readers_cannot_drop_new_lifecycle_or_reset_consent() {
		$before = $this->backend->rows;
		foreach ( array( 'Alynt_Drime_Backups_Uploader_Schedule_Safety_Store', 'Alynt_Drime_Backups_Uploader_Cadence_Authority_Store' ) as $class ) {
			$old = new $class( $this->backend ); $this->assertInstanceOf( WP_Error::class, $old->snapshot() ); $this->assertInstanceOf( WP_Error::class, $old->initialize( $this->binding ) );
		}
		$this->assertSame( $before, $this->backend->rows );
	}
	public function test_terminal_native_journal_marker_is_preserved_but_never_becomes_consent() {
		$this->backend->rows = array(); $old = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Store( $this->backend, function () { return $this->now; } );
		$old->initialize_authority( $this->binding, 'every_30_minutes', $this->now ); $s = $old->snapshot(); $candidate = $s;
		$candidate['schedule_revision']++; $candidate['authority']['cadence'] = 'every_15_minutes'; $candidate['authority']['next_due_at'] = 1800000900;
		$e = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $this->now ); $e['before_fingerprint'] = $old->authority_fingerprint( $s ); $e['intended_fingerprint'] = $old->authority_fingerprint( $candidate ); $source = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE;
		$old->reserve_change( $this->binding, $source, $e ); $this->assertTrue( $old->commit_change( $this->binding, $source, $e['action_id'], $e['request_hash'], 1800000900 )['changed'] ); $records = $old->snapshot()['claims'];
		$new = $this->prepare(); $this->assertSame( $records, $new['claims'] ); $this->assertSame( 1, $new['claims'][$source]['authority_evidence_version'] );
		$this->assertFalse( $new['lifecycle']['consent'] ); $this->assertFalse( $new['authority']['enabled'] ); $this->assertSame( 'unknown', $new['lifecycle']['history'] );
	}
	public function test_migration_cas_winner_is_observed_without_repeated_schema_or_generation_reset() {
		$this->backend->rows = array(); $old = new Alynt_Drime_Backups_Uploader_Schedule_Safety_Store( $this->backend ); $old->initialize( $this->binding ); $other = $this->new_lifecycle();
		$this->backend->before_swap = function () use ( $other ) { $other->prepare_lifecycle( $this->binding, 'every_15_minutes', 1800000900 ); $other->record_consent( $this->binding, 0, true ); };
		$s = $this->prepare(); $this->assertSame( 3, $s['schema_version'] ); $this->assertSame( 1, $s['lifecycle']['permission_generation'] ); $this->assertTrue( $s['lifecycle']['consent'] ); $this->assertFalse( $s['authority']['enabled'] );
	}
	public function test_full_capacity_uncertainty_is_preserved_even_after_retention_window() {
		$prepared = $this->model->snapshot(); $this->backend->rows = array(); list( $old ) = $this->legacy_claim(); $record = array_values( $old->snapshot()['claims'] )[0];
		$record['phase'] = 'recovery_required';
		for ( $i = 1; $i <= 50; $i++ ) { $r = $record; $r['action_id'] = sprintf( '11111111-1111-4111-8111-%012d', $i ); $r['lease_owner'] = $r['action_id']; $prepared['claims'][sprintf( '22222222-2222-4222-8222-%012d', $i )] = $r; }
		$prepared['lifecycle']['phase'] = 'draining'; $this->replace_state( $prepared ); $this->now += 90000;
		$this->assertSame( 1, $this->model->record_consent( $this->binding, 0, false ) ); $s = $this->model->snapshot(); $this->assertSame( $prepared['claims'], $s['claims'] ); $this->assertCount( 50, $s['claims'] );
	}
}
