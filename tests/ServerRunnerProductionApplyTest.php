<?php
/**
 * Production-simulation apply tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

/**
 * Covers the separately gated Phase 4 production apply path.
 */
class ServerRunnerProductionApplyTest extends Alynt_Drime_Backups_Uploader_Server_Runner_Cli_Test_Case {
	use Alynt_Drime_Backups_Uploader_Server_Runner_Production_Restore_Fixture;

	/**
	 * Files-only apply replaces files and restores normal availability after checks.
	 *
	 * @return void
	 */
	public function test_files_only_apply_succeeds_with_verified_evidence() {
		$fixture = $this->create_production_restore_fixture( true );
		$evidence = $this->create_production_restore_evidence( $fixture, 'files' );

		$result = $this->run_apply( $fixture, $evidence, 'files' );

		$this->assertSame( 0, $result['exit_code'], implode( "\n", array_merge( $result['error'], $result['output'] ) ) );
		$apply = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'succeeded', $apply['status'] );
		$this->assertTrue( $apply['file_restore_succeeded'] );
		$this->assertFalse( $apply['database_import_attempted'] );
		$this->assertTrue( $apply['maintenance_reactivation_succeeded'] );
		$this->assertTrue( $apply['maintenance_deactivation_succeeded'] );
		$this->assertTrue( $apply['production_rollback_available'] );
		$this->assertFileExists( $apply['production_apply_report_path'] );
		$this->assertSame( '<?php echo "staged";', file_get_contents( $fixture['target_path'] . DIRECTORY_SEPARATOR . 'index.php' ) );
		$this->assertFileDoesNotExist( $fixture['maintenance_state'] );
	}

	/**
	 * Files-only apply preserves enrolled symlinks absent from staged files.
	 *
	 * @return void
	 */
	public function test_files_only_apply_restores_enrolled_symlinks() {
		$fixture       = $this->create_production_restore_fixture( true );
		$relative_link = 'wp-content/mu-plugins/wp-fail2ban.php';
		$target_plugin = $fixture['target_path'] . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'wp-fail2ban';
		$staged_plugin = $fixture['staged_path'] . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'wp-fail2ban';
		$mu_plugins    = $fixture['target_path'] . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'mu-plugins';
		$link_path     = $fixture['target_path'] . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative_link );

		mkdir( $target_plugin, 0755, true );
		mkdir( $staged_plugin, 0755, true );
		mkdir( $mu_plugins );
		file_put_contents( $target_plugin . DIRECTORY_SEPARATOR . 'wp-fail2ban.php', '<?php // target.' );
		file_put_contents( $staged_plugin . DIRECTORY_SEPARATOR . 'wp-fail2ban.php', '<?php // staged.' );
		$link_target = $target_plugin . DIRECTORY_SEPARATOR . 'wp-fail2ban.php';
		if ( ! @symlink( $link_target, $link_path ) ) {
			$this->markTestSkipped( 'Filesystem symlinks are unavailable in this test runtime.' );
		}

		$config = json_decode( file_get_contents( $fixture['config'] ), true );
		$config['production_expected_symlink_paths'] = array( $relative_link );
		$config['production_expected_symlink_targets'] = array( $relative_link => $link_target );
		file_put_contents( $fixture['config'], json_encode( $config ) );
		$evidence = $this->create_production_restore_evidence( $fixture, 'files' );

		$result = $this->run_apply( $fixture, $evidence, 'files' );

		$this->assertSame( 0, $result['exit_code'], implode( "\n", array_merge( $result['error'], $result['output'] ) ) );
		$apply = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'succeeded', $apply['status'] );
		$this->assertTrue( $apply['enrolled_symlink_restore_attempted'] );
		$this->assertTrue( $apply['enrolled_symlink_restore_succeeded'] );
		$this->assertSame( 1, $apply['enrolled_symlink_restore_count'] );
		$this->assertTrue( is_link( $link_path ) );
		$this->assertSame( $link_target, readlink( $link_path ) );
		$this->assertFileDoesNotExist( $fixture['maintenance_state'] );
	}

	/**
	 * Database-only apply imports the staged dump without replacing files.
	 *
	 * @return void
	 */
	public function test_database_only_apply_succeeds_with_verified_evidence() {
		$fixture = $this->create_production_restore_fixture( true );
		$evidence = $this->create_production_restore_evidence( $fixture, 'database' );

		$result = $this->run_apply( $fixture, $evidence, 'database' );

		$this->assertSame( 0, $result['exit_code'], implode( "\n", array_merge( $result['error'], $result['output'] ) ) );
		$apply = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'succeeded', $apply['status'] );
		$this->assertTrue( $apply['database_import_succeeded'] );
		$this->assertFalse( $apply['file_restore_attempted'] );
		$this->assertSame( '<?php echo "before";', file_get_contents( $fixture['target_path'] . DIRECTORY_SEPARATOR . 'index.php' ) );
		$this->assertStringContainsString( 'db import', file_get_contents( $fixture['wp_cli_log'] ) );
		$this->assertFileDoesNotExist( $fixture['maintenance_state'] );
	}

	/**
	 * A staged file changed after evidence creation must be refused before maintenance.
	 *
	 * @return void
	 */
	public function test_files_apply_refuses_staged_file_tampering_before_maintenance() {
		$fixture = $this->create_production_restore_fixture( true );
		$evidence = $this->create_production_restore_evidence( $fixture, 'files' );
		file_put_contents( $fixture['staged_path'] . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "tampered";' );

		$result = $this->run_apply( $fixture, $evidence, 'files' );

		$this->assertSame( 1, $result['exit_code'] );
		$apply = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'production-apply-preflight', $apply['failure_step'] );
		$this->assertFalse( $this->check_passed( $apply, 'staged_file_integrity_matches' ) );
		$this->assertFalse( $apply['maintenance_activation_attempted'] );
		$this->assertFalse( $apply['destructive_actions_performed'] );
		$this->assertSame( '<?php echo "before";', file_get_contents( $fixture['target_path'] . DIRECTORY_SEPARATOR . 'index.php' ) );
	}

	/**
	 * Rewriting the staged report after evidence creation cannot authorize tampering.
	 *
	 * @return void
	 */
	public function test_apply_refuses_rewritten_integrity_report_after_evidence_creation() {
		$fixture = $this->create_production_restore_fixture( true );
		$evidence = $this->create_production_restore_evidence( $fixture, 'database' );
		$database = $fixture['staged_path'] . DIRECTORY_SEPARATOR . 'database.sql';
		file_put_contents( $database, '-- tampered staged database' );
		$report_path = $fixture['staged_path'] . DIRECTORY_SEPARATOR . 'RESTORE_REPORT.json';
		$report = json_decode( file_get_contents( $report_path ), true );
		$report['staged_integrity'] = $this->staged_integrity_fixture( $fixture['staged_path'] . DIRECTORY_SEPARATOR . 'htdocs', $database );
		file_put_contents( $report_path, json_encode( $report ) );

		$result = $this->run_apply( $fixture, $evidence, 'database' );

		$this->assertSame( 1, $result['exit_code'] );
		$apply = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertFalse( $this->check_passed( $apply, 'staged_package_identity_unchanged_since_pre_backup' ) );
		$this->assertFalse( $apply['maintenance_activation_attempted'] );
		$this->assertFalse( $apply['destructive_actions_performed'] );
	}

	/**
	 * A change during maintenance activation must be caught immediately before writes.
	 *
	 * @return void
	 */
	public function test_apply_rechecks_staged_integrity_after_maintenance_activation() {
		$fixture = $this->create_production_restore_fixture( true );
		$evidence = $this->create_production_restore_evidence( $fixture, 'files' );
		file_put_contents( $fixture['maintenance_tamper_trigger'], 'tamper' );

		$result = $this->run_apply( $fixture, $evidence, 'files' );

		$this->assertSame( 1, $result['exit_code'] );
		$apply = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertSame( 'staged-input-integrity', $apply['failure_step'] );
		$this->assertTrue( $apply['maintenance_activation_succeeded'] );
		$this->assertFalse( $this->check_passed( $apply, 'immediate_staged_file_integrity_matches' ) );
		$this->assertFalse( $apply['destructive_actions_performed'] );
		$this->assertSame( '<?php echo "before";', file_get_contents( $fixture['target_path'] . DIRECTORY_SEPARATOR . 'index.php' ) );
		$this->assertFileExists( $fixture['maintenance_state'] );
	}

	/**
	 * Exact target confirmation is mandatory before maintenance or writes.
	 *
	 * @return void
	 */
	public function test_wrong_confirmation_site_is_refused_before_target_changes() {
		$fixture = $this->create_production_restore_fixture( true );
		$evidence = $this->create_production_restore_evidence( $fixture, 'database' );
		$result = $this->run_runner(
			'restore-production-apply',
			$fixture['config'],
			array(
				'--staged-path=' . escapeshellarg( $fixture['staged_path'] ),
				'--scope=database',
				'--pre-restore-evidence=' . escapeshellarg( $evidence ),
				'--target-site=example.com',
				'--confirm=restore-production-site',
				'--confirm-site=wrong.example.com',
				'--format=json',
			)
		);

		$this->assertSame( 1, $result['exit_code'] );
		$apply = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertFalse( $apply['confirmation_site_accepted'] );
		$this->assertFalse( $apply['maintenance_activation_attempted'] );
		$this->assertFalse( $apply['destructive_actions_performed'] );
	}

	/**
	 * Production apply remains unavailable when its private flag is disabled.
	 *
	 * @return void
	 */
	public function test_disabled_apply_flag_is_refused_before_target_changes() {
		$fixture = $this->create_production_restore_fixture( true );
		$evidence = $this->create_production_restore_evidence( $fixture, 'database' );
		$config = json_decode( file_get_contents( $fixture['config'] ), true );
		$config['production_restore_enabled'] = false;
		file_put_contents( $fixture['config'], json_encode( $config ) );

		$result = $this->run_apply( $fixture, $evidence, 'database' );

		$this->assertSame( 1, $result['exit_code'] );
		$apply = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertFalse( $apply['maintenance_activation_attempted'] );
		$this->assertFalse( $apply['destructive_actions_performed'] );
	}

	/**
	 * Stale pre-restore evidence cannot unlock production apply.
	 *
	 * @return void
	 */
	public function test_stale_evidence_is_refused_before_target_changes() {
		$fixture = $this->create_production_restore_fixture( true );
		$evidence_path = $this->create_production_restore_evidence( $fixture, 'database' );
		$evidence = json_decode( file_get_contents( $evidence_path ), true );
		$evidence['generated_at'] = gmdate( 'c', time() - 7200 );
		file_put_contents( $evidence_path, json_encode( $evidence ) );

		$result = $this->run_apply( $fixture, $evidence_path, 'database' );

		$this->assertSame( 1, $result['exit_code'] );
		$apply = json_decode( implode( "\n", $result['output'] ), true );
		$this->assertFalse( $apply['maintenance_activation_attempted'] );
		$this->assertFalse( $apply['destructive_actions_performed'] );
	}

	/**
	 * Runs production apply with exact confirmation values.
	 *
	 * @param array<string,string> $fixture Fixture.
	 * @param string               $evidence Evidence path.
	 * @param string               $scope Scope.
	 * @return array{exit_code:int,output:array<int,string>,error:array<int,string>}
	 */
	private function run_apply( array $fixture, $evidence, $scope ) {
		return $this->run_runner(
			'restore-production-apply',
			$fixture['config'],
			array(
				'--staged-path=' . escapeshellarg( $fixture['staged_path'] ),
				'--scope=' . escapeshellarg( $scope ),
				'--pre-restore-evidence=' . escapeshellarg( $evidence ),
				'--target-site=example.com',
				'--confirm=restore-production-site',
				'--confirm-site=example.com',
				'--format=json',
			)
		);
	}

	/**
	 * Returns whether a named runner check passed.
	 *
	 * @param array<string,mixed> $result Runner result.
	 * @param string              $name Check name.
	 * @return bool
	 */
	private function check_passed( array $result, $name ) {
		foreach ( $result['checks'] as $check ) {
			if ( $name === $check['name'] ) {
				return (bool) $check['passed'];
			}
		}

		return false;
	}
}
