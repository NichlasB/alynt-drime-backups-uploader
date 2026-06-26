<?php
/**
 * Queue tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_add_rejects_duplicate_local_path() {
		$queue = $this->queue_with_options();

		$this->assertTrue(
			$queue->add(
				array(
					'signature' => 'one',
					'path'      => 'C:\\backups\\backup.zip',
					'name'      => 'backup.zip',
				)
			)
		);

		$this->assertFalse(
			$queue->add(
				array(
					'signature' => 'two',
					'path'      => 'C:/backups/backup.zip',
					'name'      => 'backup.zip',
				)
			)
		);
	}

	public function test_add_rejects_duplicate_wpvivid_backup_file() {
		$queue = $this->queue_with_options();

		$this->assertTrue(
			$queue->add(
				array(
					'signature' => 'one',
					'path'      => 'C:/backups/one.zip',
					'name'      => 'wpvivid-abc_backup_db.zip',
					'wpvivid'   => array( 'backup_id' => 'abc' ),
				)
			)
		);

		$this->assertFalse(
			$queue->add(
				array(
					'signature' => 'two',
					'path'      => 'C:/other/one.zip',
					'name'      => 'wpvivid-abc_backup_db.zip',
					'wpvivid'   => array( 'backup_id' => 'abc' ),
				)
			)
		);
	}

	public function test_add_allows_same_name_from_different_wpvivid_backup() {
		$queue = $this->queue_with_options();

		$this->assertTrue(
			$queue->add(
				array(
					'signature' => 'one',
					'path'      => 'C:/backups/one.zip',
					'name'      => 'backup_db.zip',
					'wpvivid'   => array( 'backup_id' => 'abc' ),
				)
			)
		);

		$this->assertTrue(
			$queue->add(
				array(
					'signature' => 'two',
					'path'      => 'C:/backups/two.zip',
					'name'      => 'backup_db.zip',
					'wpvivid'   => array( 'backup_id' => 'def' ),
				)
			)
		);
	}

	public function test_add_reports_failed_persistence() {
		$options = array(
			Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION => array(),
		);
		$queue   = $this->queue_with_options( $options, false );

		$this->assertFalse(
			$queue->add(
				array(
					'signature' => 'one',
					'path'      => 'C:/backups/one.zip',
					'name'      => 'one.zip',
				)
			)
		);
		$this->assertSame( array(), $options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ] );
	}

	public function test_add_many_persists_once_for_multiple_items() {
		$options = null;
		$updates = 0;
		$queue   = $this->queue_with_options( $options, true, $updates );

		$added = $queue->add_many(
			array(
				array(
					'signature' => 'one',
					'path'      => 'C:/backups/one.zip',
					'name'      => 'one.zip',
				),
				array(
					'signature' => 'two',
					'path'      => 'C:/backups/two.zip',
					'name'      => 'two.zip',
				),
			)
		);

		$this->assertSame( 2, $added );
		$this->assertSame( 1, $updates );
	}

	public function test_add_many_preserves_producer_neutral_package_context() {
		$options = null;
		$queue   = $this->queue_with_options( $options );

		$added = $queue->add_many(
			array(
				array(
					'signature'          => 'generic-one',
					'path'               => 'C:/backups/site-one.tar.gz',
					'name'               => 'site-one.tar.gz',
					'producer_key'       => 'generic_outbox',
					'producer_label'     => 'Generic Outbox',
					'package_id'         => 'site-one-20260626',
					'filename'           => 'site-one.tar.gz',
					'backup_set_id'      => 'set-one',
					'backup_set_index'   => 1,
					'backup_set_total'   => 1,
					'manifest_path'      => 'C:/backups/site-one.tar.gz.manifest.json',
					'checksum_path'      => 'C:/backups/site-one.tar.gz.sha256',
					'checksum_algorithm' => 'sha256',
					'checksum'           => 'abc123',
					'metadata'           => array(
						'generic_outbox' => array(
							'archive_format' => 'tar.gz',
						),
					),
				),
			)
		);

		$item = $options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['generic-one'];

		$this->assertSame( 1, $added );
		$this->assertSame( 'generic_outbox', $item['producer_key'] );
		$this->assertSame( 'Generic Outbox', $item['producer_label'] );
		$this->assertSame( 'site-one-20260626', $item['package_id'] );
		$this->assertSame( 'site-one.tar.gz', $item['filename'] );
		$this->assertSame( 'set-one', $item['backup_set_id'] );
		$this->assertSame( 1, $item['backup_set_index'] );
		$this->assertSame( 1, $item['backup_set_total'] );
		$this->assertSame( 'C:/backups/site-one.tar.gz.manifest.json', $item['manifest_path'] );
		$this->assertSame( 'C:/backups/site-one.tar.gz.sha256', $item['checksum_path'] );
		$this->assertSame( 'sha256', $item['checksum_algorithm'] );
		$this->assertSame( 'abc123', $item['checksum'] );
		$this->assertSame( 'tar.gz', $item['metadata']['generic_outbox']['archive_format'] );
	}

	public function test_prepend_adds_retry_item_to_front_of_queue() {
		$options = array(
			Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION => array(
				'two' => array(
					'signature' => 'two',
					'path'      => 'C:/backups/two.zip',
					'name'      => 'two.zip',
				),
			),
		);
		$queue   = $this->queue_with_options( $options );

		$this->assertTrue(
			$queue->prepend(
				array(
					'signature' => 'one',
					'path'      => 'C:/backups/one.zip',
					'name'      => 'one.zip',
				)
			)
		);

		$this->assertSame( array( 'one', 'two' ), array_keys( $options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ] ) );
		$this->assertSame( 0, $options[ Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION ]['one']['attempts'] );
	}

	public function test_clear_active_deletes_active_upload_state() {
		$options = array(
			Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION  => array(),
			Alynt_Drime_Backups_Uploader_Queue::ACTIVE_OPTION => array(
				'signature'  => 'one',
				'updated_at' => time(),
			),
		);
		$queue   = $this->queue_with_options( $options );

		$queue->clear_active();

		$this->assertArrayNotHasKey( Alynt_Drime_Backups_Uploader_Queue::ACTIVE_OPTION, $options );
	}

	/**
	 * Creates a queue with mocked option storage.
	 *
	 * @return Alynt_Drime_Backups_Uploader_Queue
	 */
	private function queue_with_options( ?array &$options = null, $persist = true, ?int &$updates = null ) {
		if ( null === $options ) {
			$options = array(
				Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION => array(),
			);
		}

		Functions\when( 'get_option' )->alias(
			function ( $name, $default = array() ) use ( &$options ) {
				return array_key_exists( $name, $options ) ? $options[ $name ] : $default;
			}
		);

		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) use ( &$options, $persist, &$updates ) {
				if ( null !== $updates ) {
					++$updates;
				}

				if ( $persist ) {
					$options[ $name ] = $value;
				}

				return $persist;
			}
		);

		Functions\when( 'delete_option' )->alias(
			function ( $name ) use ( &$options ) {
				unset( $options[ $name ] );
				return true;
			}
		);

		return new Alynt_Drime_Backups_Uploader_Queue();
	}
}
