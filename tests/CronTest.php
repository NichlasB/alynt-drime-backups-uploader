<?php
/**
 * Cron tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class CronTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_clear_skips_unscheduled_hooks() {
		Functions\expect( 'wp_next_scheduled' )->twice()->andReturn( false );
		Functions\expect( 'wp_clear_scheduled_hook' )->never();

		$cron = new Alynt_Drime_Backups_Uploader_Cron( $this->createMock( Alynt_Drime_Backups_Uploader_Plugin::class ) );

		$cron->clear();

		$this->addToAssertionCount( 1 );
	}
}
