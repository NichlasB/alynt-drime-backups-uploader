<?php
/**
 * Remote retention tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class RemoteRetentionTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'sanitize_key' )->alias(
			function ( $key ) {
				return strtolower( preg_replace( '/[^a-zA-Z0-9_\\-]/', '', (string) $key ) );
			}
		);

		Functions\when( 'sanitize_text_field' )->alias(
			function ( $value ) {
				return is_scalar( $value ) ? trim( (string) $value ) : '';
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_preview_is_empty_when_remote_retention_is_disabled() {
		$options   = $this->base_options( false );
		$retention = $this->retention_with_options( $options, new Alynt_Drime_Backups_Uploader_Test_Retention_Client( new Alynt_Drime_Backups_Uploader_Settings() ) );

		$this->assertSame( array(), $retention->preview() );
	}

	public function test_preview_selects_only_old_uploaded_entries_with_drime_ids() {
		$options   = $this->base_options( true );
		$retention = $this->retention_with_options( $options, new Alynt_Drime_Backups_Uploader_Test_Retention_Client( new Alynt_Drime_Backups_Uploader_Settings() ) );
		$preview   = $retention->preview();

		$this->assertCount( 1, $preview );
		$this->assertSame( 'old-good', $preview[0]['signature'] );
		$this->assertSame( 123, $preview[0]['file_entry_id'] );
	}

	public function test_cleanup_trashes_candidates_and_preserves_registry_record() {
		$options   = $this->base_options( true );
		$client    = new Alynt_Drime_Backups_Uploader_Test_Retention_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$retention = $this->retention_with_options( $options, $client );

		$result = $retention->cleanup();

		$this->assertSame( array( 'candidates' => 1, 'trashed' => 1, 'failed' => 0, 'skipped' => 0 ), $result );
		$this->assertSame( array( 123 ), $client->trashed_ids );
		$this->assertArrayHasKey( 'old-good', $options[ Alynt_Drime_Backups_Uploader_Backup_Registry::UPLOADED_OPTION ] );
		$this->assertSame( 'trashed', $options[ Alynt_Drime_Backups_Uploader_Backup_Registry::UPLOADED_OPTION ]['old-good']['remote_status'] );
	}

	public function test_cleanup_marks_failed_trash_attempts_without_removing_record() {
		$options                = $this->base_options( true );
		$client                 = new Alynt_Drime_Backups_Uploader_Test_Retention_Client( new Alynt_Drime_Backups_Uploader_Settings() );
		$client->trash_response = new WP_Error( 'drime_error', 'Delete failed.' );
		$retention              = $this->retention_with_options( $options, $client );

		$result = $retention->cleanup();

		$this->assertSame( 1, $result['failed'] );
		$this->assertSame( 'trash_failed', $options[ Alynt_Drime_Backups_Uploader_Backup_Registry::UPLOADED_OPTION ]['old-good']['remote_status'] );
		$this->assertSame( 'Delete failed.', $options[ Alynt_Drime_Backups_Uploader_Backup_Registry::UPLOADED_OPTION ]['old-good']['remote_status_context']['message'] );
	}

	/**
	 * Creates retention service with mocked option storage.
	 *
	 * @param array<string,mixed>                                  $options Options.
	 * @param Alynt_Drime_Backups_Uploader_Test_Retention_Client $client Client.
	 * @return Alynt_Drime_Backups_Uploader_Remote_Retention
	 */
	private function retention_with_options( array &$options, Alynt_Drime_Backups_Uploader_Test_Retention_Client $client ) {
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = array() ) use ( &$options ) {
				return array_key_exists( $name, $options ) ? $options[ $name ] : $default;
			}
		);

		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) use ( &$options ) {
				$options[ $name ] = $value;
				return true;
			}
		);

		$settings = new Alynt_Drime_Backups_Uploader_Settings();

		return new Alynt_Drime_Backups_Uploader_Remote_Retention(
			$settings,
			$client,
			new Alynt_Drime_Backups_Uploader_Backup_Registry(),
			new Alynt_Drime_Backups_Uploader_Logger( $settings )
		);
	}

	/**
	 * Builds base option state.
	 *
	 * @param bool $enabled Whether remote retention is enabled.
	 * @return array<string,mixed>
	 */
	private function base_options( $enabled ) {
		$old = time() - ( 70 * 86400 );
		$new = time() - ( 10 * 86400 );

		return array(
			Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME         => array(
				'remote_retention_enabled' => $enabled,
				'remote_retention_days'    => 60,
				'diagnostics_enabled'      => false,
			),
			Alynt_Drime_Backups_Uploader_Backup_Registry::UPLOADED_OPTION => array(
				'old-good'       => $this->uploaded_record( 123, $old ),
				'new-good'       => $this->uploaded_record( 456, $new ),
				'missing-id'     => $this->uploaded_record( 0, $old ),
				'already-trash'  => array_merge( $this->uploaded_record( 789, $old ), array( 'remote_status' => 'trashed' ) ),
				'previous-error' => array_merge( $this->uploaded_record( 987, $old ), array( 'remote_status' => 'trash_failed' ) ),
			),
		);
	}

	/**
	 * Builds an uploaded registry record.
	 *
	 * @param int $id Drime ID.
	 * @param int $uploaded_at Upload timestamp.
	 * @return array<string,mixed>
	 */
	private function uploaded_record( $id, $uploaded_at ) {
		$entry = array();

		if ( $id > 0 ) {
			$entry['id'] = $id;
		}

		return array(
			'remote_name' => 'backup-' . $id . '.zip',
			'uploaded_at' => $uploaded_at,
			'drime'       => array(
				'fileEntry' => $entry,
			),
		);
	}
}

class Alynt_Drime_Backups_Uploader_Test_Retention_Client extends Alynt_Drime_Backups_Uploader_Drime_Client {
	public $trashed_ids    = array();
	public $trash_response = array( 'status' => 'success' );

	public function trash_file_entry( $file_entry_id ) {
		$this->trashed_ids[] = $file_entry_id;

		return $this->trash_response;
	}
}
