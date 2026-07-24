<?php
/**
 * Parses CLI options.
 *
 * @param array<int,string> $argv Arguments.
 * @return array{command:string,options:array<string,string>}
 */
function alynt_runner_parse_args( array $argv ) {
	$command = isset( $argv[1] ) ? $argv[1] : 'help';
	$options = array();

	foreach ( array_slice( $argv, 2 ) as $arg ) {
		if ( 0 !== strpos( $arg, '--' ) ) {
			continue;
		}

		$parts = explode( '=', substr( $arg, 2 ), 2 );
		$options[ $parts[0] ] = isset( $parts[1] ) ? $parts[1] : '1';
	}

	return array(
		'command' => $command,
		'options' => $options,
	);
}

/**
 * Loads runner config.
 *
 * @param string $path Config path.
 * @return array<string,mixed>
 */
function alynt_runner_load_config( $path ) {
	if ( '' === $path || ! is_readable( $path ) ) {
		throw new RuntimeException( 'Config file is missing or unreadable.' );
	}

	$config = json_decode( (string) file_get_contents( $path ), true );
	if ( ! is_array( $config ) ) {
		throw new RuntimeException( 'Config file is not valid JSON.' );
	}

	return $config;
}

/**
 * Returns runner CLI usage.
 *
 * @return string
 */
function alynt_runner_usage() {
	return 'Usage: php alynt-backup-runner.php <health|run|list|cleanup-preview|cleanup|verify|inspect|fetch|stage-restore|restore-production-preflight|restore-production-create-pre-backup|restore-production-apply|restore-production-rollback|restore-dry-run|restore-apply> '
		. '--config=/path/to/config.json [--format=json] [--package=/path/to/archive.tar.gz] '
		. '[--package-id=package-id --folder-hash=hash --download-path=/path] '
		. '[--restore-path=/path/to/restores] [--staged-path=/path/to/staged/package] [--scope=files-and-database] [--target-site=example.com] '
		. '[--pre-restore-evidence=/path/to/evidence.json] [--create-pre-restore-backup=1] '
		. '[--apply-report=/path/to/report.json] [--confirm-site=example.com] [--write-report=1] [--older-than-days=14] '
		. '[--confirm=delete-local-artifacts|restore-staging-site|create-production-pre-restore-backup|restore-production-site|rollback-production-site]';
}

if ( defined( 'ALYNT_SERVER_BACKUP_RUNNER_LIBRARY_ONLY' ) && ALYNT_SERVER_BACKUP_RUNNER_LIBRARY_ONLY ) {
	return;
}

$parsed = alynt_runner_parse_args( $argv );

if ( 'help' === $parsed['command'] || '--help' === $parsed['command'] ) {
	fwrite( STDOUT, alynt_runner_usage() . "\n" );
	exit( 0 );
}

try {
	$config = alynt_runner_load_config( isset( $parsed['options']['config'] ) ? $parsed['options']['config'] : '' );
	$runner = new Alynt_Server_Backup_Runner( $config );
	exit( $runner->dispatch( $parsed['command'], $parsed['options'] ) );
} catch ( Exception $exception ) {
	fwrite( STDERR, $exception->getMessage() . "\n" );
	exit( 1 );
}
