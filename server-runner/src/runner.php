#!/usr/bin/env php
<?php
/**
 * Alynt server backup runner.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

/**
 * Creates backup packages for the Alynt Drime Backups Uploader plugin.
 */
class Alynt_Server_Backup_Runner {
	use Alynt_Server_Backup_Runner_Inventory_Cleanup;
	use Alynt_Server_Backup_Runner_Package_Restore;
	use Alynt_Server_Backup_Runner_Backup_Archive;
	use Alynt_Server_Backup_Runner_Staging_Restore;
	use Alynt_Server_Backup_Runner_Production_Preflight;
	use Alynt_Server_Backup_Runner_Production_Apply;
	use Alynt_Server_Backup_Runner_Production_Rollback;
	use Alynt_Server_Backup_Runner_Production_Control;
	use Alynt_Server_Backup_Runner_Filesystem_Security;
	use Alynt_Server_Backup_Runner_Package_Support;
	use Alynt_Server_Backup_Runner_Drime_Client;
	use Alynt_Server_Backup_Runner_Config_Runtime;

	const VERSION = '0.4.8';
	const DAY_IN_SECONDS = 86400;

	/**
	 * Config.
	 *
	 * @var array<string,mixed>
	 */
	private $config;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $config Config.
	 */
	public function __construct( array $config ) {
		$this->config = $config;
	}

	/**
	 * Runs a command.
	 *
	 * @param string              $command Command.
	 * @param array<string,mixed> $options Options.
	 * @return int Exit code.
	 */
	public function dispatch( $command, array $options ) {
		switch ( $command ) {
			case 'health':
				return $this->health();
			case 'run':
				return $this->run();
			case 'list':
				return $this->list_packages( $options );
			case 'cleanup-preview':
				return $this->cleanup_preview_command( $options );
			case 'cleanup':
				return $this->cleanup_command( $options );
			case 'verify':
				return $this->verify_command( $options );
			case 'inspect':
				return $this->inspect_command( $options );
			case 'fetch':
				return $this->fetch_command( $options );
			case 'stage-restore':
				return $this->stage_restore_command( $options );
			case 'restore-production-preflight':
				return $this->restore_production_preflight_command( $options );
			case 'restore-production-create-pre-backup':
				return $this->restore_production_create_pre_backup_command( $options );
			case 'restore-production-apply':
				return $this->restore_production_apply_command( $options );
			case 'restore-production-rollback':
				return $this->restore_production_rollback_command( $options );
			case 'restore-dry-run':
				return $this->restore_dry_run_command( $options );
			case 'restore-apply':
				return $this->restore_apply_command( $options );
			default:
				$this->error( 'Unknown command: ' . $command );
				$this->usage();
				return 1;
		}
	}

	/**
	 * Prints usage.
	 *
	 * @return void
	 */
	private function usage() {
		$this->line( alynt_runner_usage() );
	}
}
