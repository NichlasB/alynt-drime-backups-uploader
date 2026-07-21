<?php
/**
 * Plugin scan orchestration tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use PHPUnit\Framework\TestCase;

class PluginScanTest extends TestCase {
	public function test_scan_reports_queue_persistence_failure() {
		$candidate = array(
			'signature' => 'one',
			'path'      => 'C:/backups/one.zip',
			'name'      => 'one.zip',
		);
		$scanner   = $this->createMock( Alynt_Drime_Backups_Uploader_Scanner::class );
		$scanner->method( 'scan' )->willReturn(
			array(
				'directory'  => 'C:/backups',
				'candidates' => array( $candidate ),
				'errors'     => array(),
				'producers'  => array(),
			)
		);

		$queue = $this->createMock( Alynt_Drime_Backups_Uploader_Queue::class );
		$queue->method( 'add_many' )->willReturn( 0 );
		$queue->method( 'last_persistence_failed' )->willReturn( true );

		$registry = $this->createMock( Alynt_Drime_Backups_Uploader_Backup_Registry::class );
		$registry->method( 'get_uploaded' )->willReturn( array() );

		$logger = $this->createMock( Alynt_Drime_Backups_Uploader_Logger::class );
		$logger->expects( $this->atLeastOnce() )->method( 'event' );

		$reflection = new ReflectionClass( Alynt_Drime_Backups_Uploader_Plugin::class );
		$plugin     = $reflection->newInstanceWithoutConstructor();
		$this->set_property( $reflection, $plugin, 'scanner', $scanner );
		$this->set_property( $reflection, $plugin, 'queue', $queue );
		$this->set_property( $reflection, $plugin, 'registry', $registry );
		$this->set_property( $reflection, $plugin, 'logger', $logger );

		$result = $plugin->scan_and_queue();

		$this->assertSame( 0, $result['queued'] );
		$this->assertCount( 1, $result['errors'] );
		$this->assertStringContainsString( 'upload queue could not be saved', $result['errors'][0] );
	}

	private function set_property( ReflectionClass $reflection, $object, $name, $value ) {
		$property = $reflection->getProperty( $name );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		$property->setValue( $object, $value );
	}
}
