<?php
/**
 * Uploader tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

abstract class Alynt_Drime_Backups_Uploader_Uploader_Test_Case extends TestCase {
	/**
	 * Temporary upload file.
	 *
	 * @var string
	 */
	protected $file = '';

	/**
	 * Additional temporary files.
	 *
	 * @var array<int,string>
	 */
	protected $extra_files = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'alynt-drime-upload-' . uniqid( '', true ) . '.zip';
		$this->write_large_file( $this->file );
	}

	protected function tearDown(): void {
		if ( is_file( $this->file ) ) {
			unlink( $this->file );
		}

		foreach ( $this->extra_files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}

		Monkey\tearDown();
		parent::tearDown();
	}
	/**
	 * Creates base mocked options.
	 *
	 * @return array<string,mixed>
	 */
	protected function base_options() {
		return array(
			Alynt_Drime_Backups_Uploader_Settings::OPTION_NAME => array(
				'api_token'                => 'token',
				'workspace_id'             => 1,
				'multipart_chunk_size_mb'  => 32,
				'diagnostics_enabled'      => false,
			),
			Alynt_Drime_Backups_Uploader_Queue::QUEUE_OPTION => array(
				'sig-one' => array(
					'signature' => 'sig-one',
					'path'      => $this->file,
					'name'      => basename( $this->file ),
					'attempts'  => 0,
				),
			),
			Alynt_Drime_Backups_Uploader_Backup_Registry::UPLOADED_OPTION => array(),
			Alynt_Drime_Backups_Uploader_Backup_Registry::FAILED_OPTION   => array(),
		);
	}

	/**
	 * Creates an uploader with mocked option storage.
	 *
	 * @param array<string,mixed>                            $options Options.
	 * @param Alynt_Drime_Backups_Uploader_Test_Drime_Client $client Client.
	 * @param array<int,string>                              $failed_update_options Options that should fail updates.
	 * @return Alynt_Drime_Backups_Uploader_Uploader
	 */
	protected function uploader_with_options( array &$options, Alynt_Drime_Backups_Uploader_Test_Drime_Client $client, array $failed_update_options = array() ) {
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = array() ) use ( &$options ) {
				return array_key_exists( $name, $options ) ? $options[ $name ] : $default;
			}
		);

		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) use ( &$options, $failed_update_options ) {
				if ( in_array( $name, $failed_update_options, true ) ) {
					return false;
				}

				$options[ $name ] = $value;
				return true;
			}
		);

		Functions\when( 'add_option' )->alias(
			function ( $name, $value ) use ( &$options ) {
				if ( array_key_exists( $name, $options ) ) {
					return false;
				}

				$options[ $name ] = $value;
				return true;
			}
		);

		Functions\when( 'delete_option' )->alias(
			function ( $name ) use ( &$options ) {
				unset( $options[ $name ] );
				return true;
			}
		);

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

		$settings = new Alynt_Drime_Backups_Uploader_Settings();

		return new Alynt_Drime_Backups_Uploader_Uploader(
			$settings,
			$client,
			new Alynt_Drime_Backups_Uploader_Queue(),
			new Alynt_Drime_Backups_Uploader_Backup_Registry(),
			new Alynt_Drime_Backups_Uploader_Logger( $settings )
		);
	}

	/**
	 * Writes a two-part upload fixture.
	 *
	 * @param string $path Path.
	 * @return void
	 */
	protected function write_large_file( $path ) {
		$handle = fopen( $path, 'wb' );
		fseek( $handle, 32 * 1048576 + 100 );
		fwrite( $handle, 'x' );
		fclose( $handle );
	}

	/**
	 * Creates a temporary local backup file.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	protected function temporary_file( $name ) {
		$path                = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'alynt-drime-' . uniqid( '', true ) . '-' . $name;
		$this->extra_files[] = $path;
		file_put_contents( $path, 'backup' );

		return $path;
	}

	/**
	 * Builds WPvivid listed-set metadata.
	 *
	 * @param array<int,string> $set_files Set files.
	 * @return array<string,mixed>
	 */
	protected function wpvivid_metadata( array $set_files ) {
		return array(
			'backup_id'     => 'backup-one',
			'set_signature' => 'set-one',
			'set_files'     => $set_files,
			'from_list'     => true,
			'file_count'    => count( $set_files ),
		);
	}

	/**
	 * Builds the registry location key.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param string $relative_path Relative path.
	 * @return string
	 */
	protected function location_key( $workspace_id, $relative_path, $base_parent_id = 0 ) {
		$key = absint( $workspace_id ) . '|';
		if ( $base_parent_id > 0 ) {
			$key .= absint( $base_parent_id ) . '|';
		}

		return hash( 'sha256', $key . $relative_path );
	}
}
