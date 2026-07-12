<?php
/**
 * Server runner automatic pre-restore backup creation tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

/**
 * Covers apply-time pre-restore backup evidence creation.
 */
class ServerRunnerPreRestoreBackupCreationTest extends Alynt_Drime_Backups_Uploader_Server_Runner_Cli_Test_Case {
	public function test_restore_apply_can_create_pre_restore_backup_evidence_before_combined_apply() {
		if ( ! $this->tar_available() ) {
			$this->markTestSkipped( 'The tar command is required for pre-restore backup creation coverage.' );
		}

		$wp_cli_log = $this->root . DIRECTORY_SEPARATOR . 'wp-cli-pre-restore.log';
		$wp_cli     = $this->create_fake_wp_cli( $wp_cli_log );
		$fixture    = $this->create_auto_pre_restore_fixture( 'example-com-20260712-100000', $wp_cli );

		$result = $this->run_runner(
			'restore-apply',
			$fixture['config'],
			array(
				'--staged-path=' . escapeshellarg( $fixture['staged_path'] ),
				'--scope=files-and-database',
				'--create-pre-restore-backup=1',
				'--confirm=restore-staging-site',
				'--format=json',
			)
		);

		$this->assertSame( 0, $result['exit_code'], implode( "\n", array_merge( $result['error'], $result['output'] ) ) );

		$apply = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'succeeded', $apply['status'] );
		$this->assertTrue( $apply['pre_restore_backup_created'] );
		$this->assertSame( 'succeeded', $apply['pre_restore_backup_creation']['status'] );
		$this->assertFileExists( $apply['pre_restore_evidence_path'] );
		$this->assertFileExists( $apply['pre_restore_backup_creation']['database_export_path'] );
		$this->assertFileExists( $apply['pre_restore_backup_creation']['file_backup_path'] );
		$this->assertSame( $apply['pre_restore_evidence_path'], $apply['pre_restore_backup_creation']['evidence_path'] );

		$evidence = json_decode( (string) file_get_contents( $apply['pre_restore_evidence_path'] ), true );
		$this->assertSame( 'pre_restore_backup', $evidence['evidence_type'] );
		$this->assertSame( 'files-and-database', $evidence['scope'] );
		$this->assertSame( str_replace( '\\', '/', $fixture['wordpress_path'] ), $evidence['target_wordpress_path'] );
		$this->assertSame( $apply['pre_restore_backup_creation']['database_export_path'], $evidence['database_export_path'] );
		$this->assertSame( $apply['pre_restore_backup_creation']['file_backup_path'], $evidence['file_backup_path'] );

		$this->assertFileExists( $fixture['wordpress_path'] . DIRECTORY_SEPARATOR . 'index.php' );
		$this->assertSame( '<?php echo "restored";', file_get_contents( $fixture['wordpress_path'] . DIRECTORY_SEPARATOR . 'index.php' ) );
		$this->assertFileDoesNotExist( $fixture['wordpress_path'] . DIRECTORY_SEPARATOR . 'old.php' );

		$wp_cli_args = (string) file_get_contents( $wp_cli_log );
		$this->assertStringContainsString( 'db export', $wp_cli_args );
		$this->assertStringContainsString( 'db import', $wp_cli_args );
	}

	public function test_restore_apply_refuses_to_overwrite_requested_pre_restore_evidence_path() {
		if ( ! $this->tar_available() ) {
			$this->markTestSkipped( 'The tar command is required for pre-restore backup creation coverage.' );
		}

		$wp_cli_log = $this->root . DIRECTORY_SEPARATOR . 'wp-cli-pre-restore-refuse.log';
		$wp_cli     = $this->create_fake_wp_cli( $wp_cli_log );
		$fixture    = $this->create_auto_pre_restore_fixture( 'example-com-20260712-110000', $wp_cli );
		$evidence   = $fixture['pre_backup_path'] . DIRECTORY_SEPARATOR . 'existing-evidence.json';
		file_put_contents( $evidence, '{"already":"exists"}' );

		$result = $this->run_runner(
			'restore-apply',
			$fixture['config'],
			array(
				'--staged-path=' . escapeshellarg( $fixture['staged_path'] ),
				'--scope=files-and-database',
				'--pre-restore-evidence=' . escapeshellarg( $evidence ),
				'--create-pre-restore-backup=1',
				'--confirm=restore-staging-site',
				'--format=json',
			)
		);

		$this->assertSame( 1, $result['exit_code'] );

		$creation = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'failed', $creation['status'] );
		$this->assertFalse( $creation['created'] );
		$this->assertSame( '{"already":"exists"}', file_get_contents( $evidence ) );
		$this->assertSame( 'old file', file_get_contents( $fixture['wordpress_path'] . DIRECTORY_SEPARATOR . 'old.php' ) );
	}

	/**
	 * Creates a fixture for automatic pre-restore evidence generation.
	 *
	 * @param string $package_id Package ID.
	 * @param string $wp_cli Fake WP-CLI path.
	 * @return array{config:string,staged_path:string,wordpress_path:string,pre_backup_path:string}
	 */
	private function create_auto_pre_restore_fixture( $package_id, $wp_cli ) {
		$outbox          = $this->make_directory( 'outbox-' . $package_id );
		$wordpress_path  = $this->make_directory( 'target-' . $package_id );
		$pre_backup_path = $this->make_directory( 'pre-restore-' . $package_id );
		$reports_path    = $this->make_directory( 'restore-reports-' . $package_id );
		$config          = $this->write_config(
			$outbox,
			array(
				'wordpress_path'                => $wordpress_path,
				'wp_cli_path'                   => $wp_cli,
				'restore_apply_enabled'         => true,
				'restore_environment'           => 'staging',
				'restore_target_wordpress_path' => $wordpress_path,
				'restore_pre_backup_path'       => $pre_backup_path,
				'restore_reports_path'          => $reports_path,
				'minimum_free_space_bytes'      => 0,
			)
		);

		$archive = $outbox . DIRECTORY_SEPARATOR . $package_id . '.tar.gz';
		$source  = $this->make_directory( 'package-source-' . $package_id );
		$this->write_restore_source( $source );
		$this->create_tar_archive( $archive, $source );
		$this->write_restore_sidecars( $archive, $package_id );

		file_put_contents( $wordpress_path . DIRECTORY_SEPARATOR . 'old.php', 'old file' );
		file_put_contents( $wordpress_path . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "old";' );

		$stage = $this->run_runner( 'stage-restore', $config, array( '--package=' . escapeshellarg( $archive ) ) );
		$this->assertSame( 0, $stage['exit_code'], implode( "\n", $stage['error'] ) );

		return array(
			'config'          => $config,
			'staged_path'     => $this->root . DIRECTORY_SEPARATOR . 'restores' . DIRECTORY_SEPARATOR . $package_id,
			'wordpress_path'  => $wordpress_path,
			'pre_backup_path' => $pre_backup_path,
		);
	}

	private function write_restore_source( $source ) {
		mkdir( $source . DIRECTORY_SEPARATOR . 'htdocs' );
		mkdir( $source . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'wp-content' );
		file_put_contents( $source . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "restored";' );
		file_put_contents( $source . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'restored.txt', 'restored file' );
		file_put_contents( $source . DIRECTORY_SEPARATOR . 'database.sql', '-- restored db' );
		file_put_contents( $source . DIRECTORY_SEPARATOR . 'manifest.json', '{}' );
	}

	private function write_restore_sidecars( $archive, $package_id ) {
		$manifest = array(
			'package_id'         => $package_id,
			'site_url'           => 'https://example.com',
			'created_at'         => '2026-07-12T10:00:00+00:00',
			'producer'           => 'alynt_server_runner',
			'backup_type'        => 'logical_wordpress_backup',
			'archive_format'     => 'tar.gz',
			'file_root'          => 'htdocs',
			'database_dump'      => 'database.sql',
			'consistency_status' => 'clean',
		);
		file_put_contents( $archive . '.manifest.json', json_encode( $manifest ) );
		file_put_contents( $archive . '.sha256', hash_file( 'sha256', $archive ) . '  ' . basename( $archive ) );
	}

	private function create_fake_wp_cli( $log_path ) {
		$path = $this->root . DIRECTORY_SEPARATOR . ( '\\' === DIRECTORY_SEPARATOR ? 'fake-wp.bat' : 'fake-wp' );

		if ( '\\' === DIRECTORY_SEPARATOR ) {
			file_put_contents(
				$path,
				"@echo off\r\n"
				. "echo %* >> \"" . str_replace( '"', '', $log_path ) . "\"\r\n"
				. "set \"ARG=\"\r\n"
				. "set \"PREV=\"\r\n"
				. ":alynt_arg_loop\r\n"
				. "if \"%~1\"==\"\" goto alynt_arg_done\r\n"
				. "set \"PREV=%ARG%\"\r\n"
				. "set \"ARG=%~1\"\r\n"
				. "shift\r\n"
				. "goto alynt_arg_loop\r\n"
				. ":alynt_arg_done\r\n"
				. "if \"%ARG%\"==\"--quiet\" set \"ARG=%PREV%\"\r\n"
				. "if /I \"%ARG:~-4%\"==\".sql\" echo -- current db> \"%ARG%\"\r\n"
				. "exit /b 0\r\n"
			);
			return $path;
		}

		file_put_contents(
			$path,
			"#!/bin/sh\n"
			. "printf '%s\n' \"$*\" >> " . escapeshellarg( $log_path ) . "\n"
			. "for arg in \"$@\"; do case \"$arg\" in *.sql) printf '%s\n' '-- current db' > \"$arg\";; esac; done\n"
			. "exit 0\n"
		);
		chmod( $path, 0755 );

		return $path;
	}
}
