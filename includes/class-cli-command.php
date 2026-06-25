<?php
/**
 * WP-CLI command integration.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides server-friendly WP-CLI commands for scanning and uploading backups.
 *
 * @since 0.1.0
 */
class Alynt_Drime_Backups_Uploader_CLI_Command {
	/**
	 * Plugin.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Plugin $plugin Plugin.
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Scans configured producers and queues stable packages.
	 *
	 * ## EXAMPLES
	 *
	 *     wp alynt-drime-backups scan
	 *
	 * @return void
	 */
	public function scan() {
		$this->plugin->cron_health()->record_manual_scan();

		$result = $this->plugin->scan_and_queue();
		if ( ! empty( $result['errors'] ) ) {
			$this->error( 'Scan failed: ' . implode( '; ', $result['errors'] ) );
			return;
		}

		$this->success(
			sprintf(
				'Scan complete. Found: %d. Queued: %d.',
				isset( $result['candidates'] ) && is_array( $result['candidates'] ) ? count( $result['candidates'] ) : 0,
				isset( $result['queued'] ) ? absint( $result['queued'] ) : 0
			)
		);
	}

	/**
	 * Uploads the next queued package.
	 *
	 * ## EXAMPLES
	 *
	 *     wp alynt-drime-backups upload-next
	 *
	 * @return void
	 */
	public function upload_next() {
		$result = $this->plugin->uploader()->upload_next();

		if ( is_wp_error( $result ) ) {
			if ( 'alynt_drime_queue_empty' === $result->get_error_code() ) {
				$this->warning( $result->get_error_message() );
				return;
			}

			$this->error( $result->get_error_message() );
			return;
		}

		$this->success( 'Upload complete.' );
	}

	/**
	 * Runs one scan and then uploads queued packages.
	 *
	 * ## OPTIONS
	 *
	 * [--max-uploads=<count>]
	 * : Maximum queued packages to upload after scanning. Defaults to 1.
	 *
	 * ## EXAMPLES
	 *
	 *     wp alynt-drime-backups run --max-uploads=3
	 *
	 * @param array<int,string>    $args Positional args.
	 * @param array<string,string> $assoc_args Associative args.
	 * @return void
	 */
	public function run( $args = array(), $assoc_args = array() ) {
		unset( $args );

		$this->plugin->cron_health()->record_manual_scan();
		$scan = $this->plugin->scan_and_queue();
		if ( ! empty( $scan['errors'] ) ) {
			$this->error( 'Scan failed: ' . implode( '; ', $scan['errors'] ) );
			return;
		}

		$max_uploads = isset( $assoc_args['max-uploads'] ) ? max( 0, absint( $assoc_args['max-uploads'] ) ) : 1;
		$uploaded    = 0;

		for ( $index = 0; $index < $max_uploads; ++$index ) {
			$result = $this->plugin->uploader()->upload_next();
			if ( is_wp_error( $result ) ) {
				if ( 'alynt_drime_queue_empty' === $result->get_error_code() ) {
					break;
				}

				$this->error( $result->get_error_message() );
				return;
			}

			++$uploaded;
		}

		$this->success(
			sprintf(
				'Run complete. Found: %d. Queued: %d. Uploaded: %d.',
				isset( $scan['candidates'] ) && is_array( $scan['candidates'] ) ? count( $scan['candidates'] ) : 0,
				isset( $scan['queued'] ) ? absint( $scan['queued'] ) : 0,
				$uploaded
			)
		);
	}

	/**
	 * Prints backup uploader status.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Supports `table` and `json`. Defaults to `table`.
	 *
	 * ## EXAMPLES
	 *
	 *     wp alynt-drime-backups status
	 *     wp alynt-drime-backups status --format=json
	 *
	 * @param array<int,string>    $args Positional args.
	 * @param array<string,string> $assoc_args Associative args.
	 * @return void
	 */
	public function status( $args = array(), $assoc_args = array() ) {
		unset( $args );

		$status = $this->status_data();
		if ( isset( $assoc_args['format'] ) && 'json' === $assoc_args['format'] ) {
			$this->log( wp_json_encode( $status ) );
			return;
		}

		$rows = array();
		foreach ( $status as $key => $value ) {
			$rows[] = array(
				'key'   => $key,
				'value' => is_bool( $value ) ? ( $value ? 'yes' : 'no' ) : (string) $value,
			);
		}

		$this->format_items( 'table', $rows, array( 'key', 'value' ) );
	}

	/**
	 * Builds a compact status payload for CLI and future health services.
	 *
	 * @return array<string,mixed>
	 */
	public function status_data() {
		$settings = $this->plugin->settings()->get();
		$active   = $this->plugin->queue()->get_active();

		return array(
			'queue_count'          => count( $this->plugin->queue()->all() ),
			'uploaded_count'       => count( $this->plugin->registry()->get_uploaded() ),
			'failed_count'         => count( $this->plugin->registry()->get_failed() ),
			'active_upload'        => ! empty( $active ),
			'auto_scan_enabled'    => ! empty( $settings['auto_scan_enabled'] ),
			'server_cron_expected' => ! empty( $settings['server_cron_expected'] ),
			'server_outbox_path'   => isset( $settings['server_outbox_path'] ) ? (string) $settings['server_outbox_path'] : '',
			'backup_path_override' => isset( $settings['backup_path_override'] ) ? (string) $settings['backup_path_override'] : '',
		);
	}

	/**
	 * Writes an informational line.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	private function log( $message ) {
		WP_CLI::log( $message );
	}

	/**
	 * Writes a success line.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	private function success( $message ) {
		WP_CLI::success( $message );
	}

	/**
	 * Writes a warning line.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	private function warning( $message ) {
		WP_CLI::warning( $message );
	}

	/**
	 * Writes an error line without terminating PHP execution.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	private function error( $message ) {
		WP_CLI::error( $message, false );
	}

	/**
	 * Formats tabular output.
	 *
	 * @param string            $format Format.
	 * @param array<int,array>  $items Items.
	 * @param array<int,string> $fields Fields.
	 * @return void
	 */
	private function format_items( $format, array $items, array $fields ) {
		\WP_CLI\Utils\format_items( $format, $items, $fields );
	}
}
