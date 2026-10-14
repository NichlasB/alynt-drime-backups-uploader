<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ConsentLedgerFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ConsentLedgerMigrationTest extends TestCase {
	use Alynt_Test_Consent_Ledger_Fixture;
	public function test_migration_clears_synthetic_grant_without_resetting_history_or_due() {
		$this->lifecycle->record_consent( $this->binding, 0, true ); $before = $this->lifecycle->snapshot(); $swaps = $this->backend->swaps;
		$s = $this->migrate(); $this->assertSame( $swaps + 1, $this->backend->swaps );
		$this->assertSame( 4, $s['schema_version'] ); $this->assertSame( $before['generation'] + 1, $s['generation'] ); $this->assertSame( 2, $s['lifecycle']['permission_generation'] );
		$this->assertSame( $before['authority'], $s['authority'] ); $this->assertSame( $before['claims'], $s['claims'] ); $this->assertSame( $before['lifecycle']['history'], $s['lifecycle']['history'] );
		$this->assertFalse( $s['lifecycle']['consent'] ); $this->assertSame( 'disabled', $s['lifecycle']['phase'] ); $this->assertNull( $s['ledger']['grant_command_id'] ); $this->assertNull( $s['ledger']['last_receipt'] );
		$rows = $this->backend->rows; $this->assertSame( $s, $this->ledger->prepare_ledger( $this->binding, 2 ) ); $this->assertSame( $rows, $this->backend->rows ); $this->assertSame( $swaps + 1, $this->backend->swaps );
	}
	public function test_absent_corrupt_old_unknown_and_autoloaded_models_are_not_created_or_repaired() {
		$original = $this->backend->rows; $key = Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION;
		foreach ( array( null, '', '{}', '{bad', json_encode( array( 'schema_version' => 2 ) ), str_repeat( 'x', 131073 ) ) as $raw ) {
			$this->backend->rows = null === $raw ? array() : array( $key => array( 'option_value' => $raw, 'autoload' => 'no' ) ); $rows = $this->backend->rows;
			$this->error( 'unavailable', $this->ledger->prepare_ledger( $this->binding, 0 ) ); $this->assertSame( $rows, $this->backend->rows );
		}
		$this->backend->rows = $original; $this->backend->rows[$key]['autoload'] = 'yes'; $rows = $this->backend->rows;
		$this->error( 'unavailable', $this->ledger->prepare_ledger( $this->binding, 0 ) ); $this->assertSame( $rows, $this->backend->rows ); $this->assertSame( 1, $this->backend->creates );
	}
	/** @dataProvider pending_kinds */
	public function test_pending_migration_rejects_even_after_expiry( $dispatch ) {
		$this->replace( $this->pending_state( $this->lifecycle->snapshot(), $dispatch ) ); $this->now += 90000; $rows = $this->backend->rows;
		$this->error( 'migration_blocked', $this->ledger->prepare_ledger( $this->binding, 0 ) ); $this->assertSame( $rows, $this->backend->rows );
	}
	public function pending_kinds() { return array( array( false ), array( true ) ); }
	public function test_unknown_history_and_terminal_native_records_survive_verbatim() {
		$s = $this->pending_state( $this->lifecycle->snapshot() ); $s['lifecycle']['phase'] = 'disabled'; $s['lifecycle']['history'] = 'unknown';
		foreach ( $s['claims'] as &$record ) { $record['phase'] = 'succeeded'; $record['completed_at'] = $this->now; $record['authority_evidence_version'] = 1; } unset( $record );
		$this->replace( $s ); $end = $this->migrate(); $this->assertSame( $s['claims'], $end['claims'] ); $this->assertSame( 'unknown', $end['lifecycle']['history'] ); $this->assertFalse( $end['authority']['enabled'] );
	}
	public function test_failed_migration_keeps_exact_bytes_and_reports_no_success() {
		$rows = $this->backend->rows; $this->backend->fail_write = true;
		$this->error( 'unresolved', $this->ledger->prepare_ledger( $this->binding, 0 ) ); $this->assertSame( $rows, $this->backend->rows );
		$this->backend->fail_write = false; $this->backend->conflicts = 3; $count = $this->backend->swaps;
		$this->error( 'contention', $this->ledger->prepare_ledger( $this->binding, 0 ) ); $this->assertSame( $count + 3, $this->backend->swaps ); $this->assertSame( $rows, $this->backend->rows );
	}
	public function test_default_and_truthy_switches_and_inherited_mutations_do_zero_reads_or_writes() {
		$reads = $this->backend->reads; $writes = $this->backend->swaps;
		foreach ( array( false, 1, 'true' ) as $enabled ) { $off = $this->new_ledger( $enabled ); $this->error( 'disabled', $off->snapshot() ); $this->error( 'disabled', $off->prepare_ledger( $this->binding, 0 ) ); $this->error( 'disabled', $off->command( array() ) ); }
		$this->error( 'api_required', $this->ledger->initialize( $this->binding ) ); $this->error( 'api_required', $this->ledger->claim( $this->binding, '', array() ) );
		$this->error( 'api_required', $this->ledger->transition( '', '', '', '', '', '' ) ); $this->error( 'api_required', $this->ledger->invalidate( $this->binding ) );
		$this->assertSame( $reads, $this->backend->reads ); $this->assertSame( $writes, $this->backend->swaps );
		$bootstrap = file_get_contents( dirname( __DIR__ ) . '/alynt-drime-backups-uploader.php' ); $this->assertStringNotContainsString( 'class-cadence-consent-ledger.php', $bootstrap ); $this->assertStringNotContainsString( 'trait-consent-ledger-', $bootstrap );
	}
}
