<?php
/** Scoped cron replacement tests. */
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ScheduleSafetyFixture.php';

/**
 * Isolate the WordPress serialization fixture.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ScheduleSafetyCronTest extends TestCase {
	protected function setUp(): void { parent::setUp(); Monkey\setUp(); Functions\when( 'maybe_serialize' )->alias( 'serialize' ); }
	protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

	public function test_replacement_preserves_unrelated_events_and_rejects_stale_snapshot() {
		$backend = new Alynt_Drime_Backups_Uploader_Test_Safety_Memory_CAS();
		$before = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::cron( 1000 );
		$after = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::cron( 1900, 'fifteen_minutes', 900 );
		$backend->rows['cron'] = array( 'option_value' => serialize( $before ), 'autoload' => 'yes' );
		$guard = new Alynt_Drime_Backups_Uploader_Schedule_Safety_Cron_CAS( $backend );
		$this->assertTrue( $guard->replace( serialize( $before ), $before, $after ) );
		$this->assertSame( serialize( $after ), $backend->rows['cron']['option_value'] );
		$this->assertSame( 'schedule_safety_cron_stale', $guard->replace( serialize( $before ), $before, $after )->get_error_code() );
	}

	public function test_unrelated_changes_or_ambiguous_owned_events_fail_before_write() {
		$backend = new Alynt_Drime_Backups_Uploader_Test_Safety_Memory_CAS();
		$guard = new Alynt_Drime_Backups_Uploader_Schedule_Safety_Cron_CAS( $backend );
		$before = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::cron( 1000 );
		$after = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::cron( 1900, 'fifteen_minutes', 900 );
		unset( $after[9999999999] );
		$this->assertSame( 'schedule_safety_cron_scope_invalid', $guard->replace( serialize( $before ), $before, $after )->get_error_code() );
		$before[1000]['alynt_drime_backups_scan_event']['duplicate'] = array();
		$this->assertInstanceOf( WP_Error::class, $guard->replace( serialize( $before ), $before, $after ) );
		$this->assertSame( 0, $backend->swaps );
	}
}
