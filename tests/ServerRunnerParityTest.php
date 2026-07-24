<?php
/**
 * Server runner contract parity tests.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

/**
 * Covers portable runner CLI and example-config compatibility contracts.
 */
class ServerRunnerParityTest extends Alynt_Drime_Backups_Uploader_Server_Runner_Cli_Test_Case {
	/**
	 * Generated CLI usage must match the frozen compatibility snapshot.
	 *
	 * @return void
	 */
	public function test_cli_usage_matches_snapshot() {
		$snapshot = trim(
			(string) file_get_contents(
				ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH . '/tests/fixtures/server-runner-usage.txt'
			)
		);
		$result   = $this->run_runner( 'help', 'unused-config.json' );

		$this->assertSame( 0, $result['exit_code'] );
		$this->assertSame( array(), $result['error'] );
		$this->assertCount( 1, $result['output'] );
		$this->assertSame( $snapshot, $result['output'][0] );
	}

	/**
	 * The published config example must remain valid input for the runner.
	 *
	 * @return void
	 */
	public function test_config_example_remains_runner_compatible() {
		$example_path = ALYNT_DRIME_BACKUPS_UPLOADER_TESTS_PATH . '/server-runner/config.example.json';
		$config_data  = json_decode( (string) file_get_contents( $example_path ), true );

		$this->assertSame( JSON_ERROR_NONE, json_last_error() );
		$this->assertIsArray( $config_data );

		$config_data['wordpress_path'] = $this->make_directory( 'htdocs' );
		$config_data['outbox_path']    = $this->make_directory( 'outbox' );
		$config_data['work_path']      = $this->make_directory( 'work' );
		$config_data['restore_path']   = $this->make_directory( 'restores' );

		$config_path = $this->root . DIRECTORY_SEPARATOR . 'config-example.json';
		file_put_contents( $config_path, json_encode( $config_data ) );

		$result = $this->run_runner( 'list', $config_path, array( '--format=json' ) );

		$this->assertSame( 0, $result['exit_code'], implode( "\n", $result['error'] ) );
		$this->assertSame( array(), $result['error'] );
		$this->assertNotEmpty( $result['output'] );

		$inventory = json_decode( implode( "\n", $result['output'] ), true );

		$this->assertSame( JSON_ERROR_NONE, json_last_error() );
		$this->assertSame( 1, $inventory['schema_version'] );
		$this->assertSame( array(), $inventory['packages'] );
	}
}
