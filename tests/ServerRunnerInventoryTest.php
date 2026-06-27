<?php
/**
 * Server runner inventory tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

/**
 * Covers server runner package inventory output.
 */
class ServerRunnerInventoryTest extends Alynt_Drime_Backups_Uploader_Server_Runner_Cli_Test_Case {
	public function test_list_json_outputs_package_inventory() {
		$outbox = $this->make_directory( 'outbox' );
		$config = $this->write_config( $outbox );

		$archive = $outbox . DIRECTORY_SEPARATOR . 'example-com-20260627-120000.tar.gz';
		file_put_contents( $archive, 'fake archive' );
		file_put_contents(
			$archive . '.manifest.json',
			json_encode(
				array(
					'package_id'     => 'example-com-20260627-120000',
					'site_url'       => 'https://example.com',
					'created_at'     => '2026-06-27T12:00:00+00:00',
					'producer'       => 'alynt_server_runner',
					'backup_type'    => 'logical_wordpress_backup',
					'archive_format' => 'tar.gz',
					'file_root'      => 'htdocs',
					'database_dump'  => 'database.sql',
				)
			)
		);
		file_put_contents( $archive . '.sha256', str_repeat( 'a', 64 ) . '  example-com-20260627-120000.tar.gz' );
		file_put_contents(
			$archive . '.remote-index.json',
			json_encode(
				array(
					'schema_version' => 1,
					'index_type'     => 'single_package_restore_index',
					'package_count'  => 1,
					'packages'       => array(
						array(
							'package_id'   => 'example-com-20260627-120000',
							'archive_name' => 'example-com-20260627-120000.tar.gz',
						),
					),
				)
			)
		);

		$result = $this->run_runner( 'list', $config, array( '--format=json' ) );

		$this->assertSame( 0, $result['exit_code'], implode( "\n", $result['error'] ) );

		$inventory = json_decode( implode( "\n", $result['output'] ), true );

		$this->assertIsArray( $inventory );
		$this->assertSame( 1, $inventory['schema_version'] );
		$this->assertSame( 'tar.gz', $inventory['archive_format'] );
		$this->assertSame( 1, $inventory['package_count'] );
		$this->assertCount( 1, $inventory['packages'] );

		$package = $inventory['packages'][0];
		$this->assertSame( 'example-com-20260627-120000', $package['package_id'] );
		$this->assertSame( 'example-com-20260627-120000.tar.gz', $package['archive_name'] );
		$this->assertSame( 'example-com-20260627-120000.tar.gz.manifest.json', $package['manifest_name'] );
		$this->assertTrue( $package['manifest_present'] );
		$this->assertTrue( $package['manifest_valid'] );
		$this->assertSame( 'example-com-20260627-120000.tar.gz.sha256', $package['checksum_name'] );
		$this->assertTrue( $package['checksum_present'] );
		$this->assertTrue( $package['checksum_valid'] );
		$this->assertSame( 'example-com-20260627-120000.tar.gz.remote-index.json', $package['remote_index_name'] );
		$this->assertTrue( $package['remote_index_present'] );
		$this->assertTrue( $package['remote_index_valid'] );
		$this->assertSame( 'sha256', $package['checksum_algorithm'] );
		$this->assertSame( str_repeat( 'a', 64 ), $package['checksum_value'] );
		$this->assertSame( 'https://example.com', $package['site_url'] );
		$this->assertSame( 'logical_wordpress_backup', $package['backup_type'] );
		$this->assertTrue( $package['verification_ready'] );
	}

	public function test_stage_restore_writes_restore_report() {
		if ( ! $this->tar_available() ) {
			$this->markTestSkipped( 'The tar command is required for restore staging report coverage.' );
		}

		$package_id = 'example-com-20260627-130000';
		$fixture    = $this->create_verified_restore_package( $package_id );
		$config     = $fixture['config'];
		$archive    = $fixture['archive'];

		$result = $this->run_runner( 'stage-restore', $config, array( '--package=' . escapeshellarg( $archive ) ) );

		$this->assertSame( 0, $result['exit_code'], implode( "\n", $result['error'] ) );
		$output = implode( "\n", $result['output'] );
		$this->assertStringContainsString( 'Restore staging completed.', $output );
		$this->assertStringContainsString( 'Review next:', $output );
		$this->assertStringContainsString( 'Open RESTORE_NOTES.txt.', $output );
		$this->assertStringContainsString( 'Open RESTORE_REPORT.json.', $output );
		$this->assertStringContainsString( 'Safety boundary:', $output );
		$this->assertStringContainsString( 'No database import was performed.', $output );
		$this->assertStringContainsString( 'No live WordPress files were overwritten.', $output );

		$report_path = $this->root . DIRECTORY_SEPARATOR . 'restores' . DIRECTORY_SEPARATOR . $package_id . DIRECTORY_SEPARATOR . 'RESTORE_REPORT.json';
		$this->assertFileExists( $report_path );

		$report = json_decode( (string) file_get_contents( $report_path ), true );

		$this->assertIsArray( $report );
		$this->assertSame( 1, $report['schema_version'] );
		$this->assertSame( 'staged_for_inspection', $report['status'] );
		$this->assertSame( $package_id, $report['package_id'] );
		$this->assertSame( basename( $archive ), $report['archive_name'] );
		$this->assertSame( 'sha256', $report['checksum_algorithm'] );
		$this->assertSame( hash_file( 'sha256', $archive ), $report['checksum_value'] );
		$this->assertSame( 'https://example.com', $report['site_url'] );
		$this->assertTrue( $report['package_verified'] );
		$this->assertTrue( $report['archive_members_safe'] );
		$this->assertTrue( $report['extracted_for_inspection'] );
		$this->assertFalse( $report['database_imported'] );
		$this->assertFalse( $report['live_files_overwritten'] );
		$this->assertTrue( $report['manual_restore_required'] );
		$this->assertArrayNotHasKey( 'archive_path', $report );
	}

	public function test_verify_and_inspect_print_restore_guidance() {
		if ( ! $this->tar_available() ) {
			$this->markTestSkipped( 'The tar command is required for restore guidance coverage.' );
		}

		$fixture = $this->create_verified_restore_package( 'example-com-20260627-131500' );

		$verify = $this->run_runner( 'verify', $fixture['config'], array( '--package=' . escapeshellarg( $fixture['archive'] ) ) );
		$this->assertSame( 0, $verify['exit_code'], implode( "\n", $verify['error'] ) );

		$verify_output = implode( "\n", $verify['output'] );
		$this->assertStringContainsString( 'Restore guidance:', $verify_output );
		$this->assertStringContainsString( 'Next: run inspect to review metadata, timing, and archive preview.', $verify_output );
		$this->assertStringContainsString( 'Do not import database.sql or overwrite live files without separate approval.', $verify_output );

		$inspect = $this->run_runner( 'inspect', $fixture['config'], array( '--package=' . escapeshellarg( $fixture['archive'] ) ) );
		$this->assertSame( 0, $inspect['exit_code'], implode( "\n", $inspect['error'] ) );

		$inspect_output = implode( "\n", $inspect['output'] );
		$this->assertStringContainsString( 'Restore guidance:', $inspect_output );
		$this->assertStringContainsString( 'Confirm package ID: example-com-20260627-131500', $inspect_output );
		$this->assertStringContainsString( 'Next: run stage-restore to extract into a private inspection directory.', $inspect_output );
		$this->assertStringContainsString( 'No live restore has been approved by this inspection output.', $inspect_output );
	}

	public function test_light_consistency_mode_records_clean_package_metadata() {
		if ( ! $this->tar_available() ) {
			$this->markTestSkipped( 'The tar command is required for consistency metadata coverage.' );
		}

		$outbox = $this->make_directory( 'outbox' );
		$config = $this->write_config( $outbox, array( 'consistency_mode' => 'light' ) );
		file_put_contents( $this->root . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "ok";' );

		$result = $this->run_runner( 'run', $config );

		$this->assertSame( 0, $result['exit_code'], implode( "\n", $result['error'] ) );

		$packages = glob( $outbox . DIRECTORY_SEPARATOR . '*.tar.gz' );
		$this->assertIsArray( $packages );
		$this->assertCount( 1, $packages );

		$manifest = json_decode( (string) file_get_contents( $packages[0] . '.manifest.json' ), true );
		$index    = json_decode( (string) file_get_contents( $packages[0] . '.remote-index.json' ), true );
		$catalog  = json_decode( (string) file_get_contents( $packages[0] . '.remote-catalog.json' ), true );

		$this->assertIsArray( $manifest );
		$this->assertIsArray( $index );
		$this->assertIsArray( $catalog );
		$this->assertSame( 'light', $manifest['consistency_mode'] );
		$this->assertSame( 'clean', $manifest['consistency_status'] );
		$this->assertSame( 0, $manifest['file_archive_exit_code'] );
		$this->assertSame( 0, $manifest['file_archive_warning_count'] );
		$this->assertSame( 0, $manifest['file_archive_live_change_warning_count'] );
		$this->assertSame( array(), $manifest['file_archive_warning_samples'] );
		$this->assertNotEmpty( $manifest['file_archive_started_at'] );
		$this->assertNotEmpty( $manifest['file_archive_finished_at'] );
		$this->assertSame( 1, $index['schema_version'] );
		$this->assertSame( 'single_package_restore_index', $index['index_type'] );
		$this->assertSame( 1, $index['package_count'] );
		$this->assertSame( basename( $packages[0] ), $index['packages'][0]['archive_name'] );
		$this->assertArrayNotHasKey( 'archive_path', $index['packages'][0] );
		$this->assertTrue( $index['packages'][0]['remote_index_present'] );
		$this->assertTrue( $index['packages'][0]['remote_index_valid'] );
		$this->assertTrue( $index['restore_policy']['manual_restore_required'] );
		$this->assertSame( 1, $catalog['schema_version'] );
		$this->assertSame( 'folder_package_catalog_snapshot', $catalog['catalog_type'] );
		$this->assertSame( 1, $catalog['package_count'] );
		$this->assertSame( basename( $packages[0] ), $catalog['packages'][0]['archive_name'] );
		$this->assertArrayNotHasKey( 'archive_path', $catalog['packages'][0] );
		$this->assertTrue( $catalog['packages'][0]['remote_index_present'] );
		$this->assertTrue( $catalog['packages'][0]['remote_index_valid'] );
		$this->assertTrue( $catalog['restore_policy']['manual_restore_required'] );

		$list = $this->run_runner( 'list', $config, array( '--format=json' ) );
		$this->assertSame( 0, $list['exit_code'], implode( "\n", $list['error'] ) );

		$inventory = json_decode( implode( "\n", $list['output'] ), true );
		$this->assertSame( 'light', $inventory['packages'][0]['consistency_mode'] );
		$this->assertSame( 'clean', $inventory['packages'][0]['consistency_status'] );
		$this->assertTrue( $inventory['packages'][0]['remote_index_present'] );
		$this->assertTrue( $inventory['packages'][0]['remote_index_valid'] );
	}

	/**
	 * Creates a verified restore package fixture.
	 *
	 * @param string $package_id Package ID.
	 * @return array{config:string,archive:string}
	 */
	private function create_verified_restore_package( $package_id ) {
		$outbox  = $this->make_directory( 'outbox-' . $package_id );
		$config  = $this->write_config( $outbox );
		$archive = $outbox . DIRECTORY_SEPARATOR . $package_id . '.tar.gz';
		$source  = $this->make_directory( 'package-source-' . $package_id );

		mkdir( $source . DIRECTORY_SEPARATOR . 'htdocs' );
		file_put_contents( $source . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "ok";' );
		file_put_contents( $source . DIRECTORY_SEPARATOR . 'database.sql', '-- db' );
		file_put_contents( $source . DIRECTORY_SEPARATOR . 'manifest.json', '{}' );

		$this->create_tar_archive( $archive, $source );

		$manifest = array(
			'package_id'         => $package_id,
			'site_url'           => 'https://example.com',
			'created_at'         => '2026-06-27T13:00:00+00:00',
			'producer'           => 'alynt_server_runner',
			'backup_type'        => 'logical_wordpress_backup',
			'archive_format'     => 'tar.gz',
			'file_root'          => 'htdocs',
			'database_dump'      => 'database.sql',
			'consistency_status' => 'clean',
		);
		file_put_contents( $archive . '.manifest.json', json_encode( $manifest ) );
		file_put_contents( $archive . '.sha256', hash_file( 'sha256', $archive ) . '  ' . basename( $archive ) );

		return array(
			'config'  => $config,
			'archive' => $archive,
		);
	}
}
