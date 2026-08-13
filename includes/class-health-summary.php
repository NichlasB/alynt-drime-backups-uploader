<?php
/**
 * Internal health summary service.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds a redacted status payload for CLI, admin, and future monitoring.
 *
 * @since 0.1.0
 */
class Alynt_Drime_Backups_Uploader_Health_Summary {
	const SCHEMA_VERSION                  = 1;
	const BACKUP_FRESHNESS_WINDOW_SECONDS = 36 * 60 * 60;

	/**
	 * Settings.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Settings
	 */
	private $settings;

	/**
	 * Queue.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Queue
	 */
	private $queue;

	/**
	 * Registry.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Backup_Registry
	 */
	private $registry;

	/**
	 * Cron health.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Cron_Health
	 */
	private $cron_health;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Settings        $settings Settings.
	 * @param Alynt_Drime_Backups_Uploader_Queue           $queue Queue.
	 * @param Alynt_Drime_Backups_Uploader_Backup_Registry $registry Registry.
	 * @param Alynt_Drime_Backups_Uploader_Cron_Health     $cron_health Cron health.
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Settings $settings, Alynt_Drime_Backups_Uploader_Queue $queue, Alynt_Drime_Backups_Uploader_Backup_Registry $registry, Alynt_Drime_Backups_Uploader_Cron_Health $cron_health ) {
		$this->settings    = $settings;
		$this->queue       = $queue;
		$this->registry    = $registry;
		$this->cron_health = $cron_health;
	}

	/**
	 * Builds a compact health payload.
	 *
	 * @since 0.1.0
	 *
	 * @param int|false $next_scan Next scheduled scan timestamp.
	 * @param bool      $include_paths Whether to include local filesystem paths.
	 * @return array<string,mixed>
	 */
	public function status( $next_scan = false, $include_paths = false ) {
		$settings    = $this->settings->get();
		$queued      = $this->queue->all();
		$uploaded    = $this->registry->get_uploaded();
		$failed      = $this->registry->get_failed();
		$active      = $this->queue->get_active();
		$cron_state  = $this->cron_health->get();
		$cron_status = $this->cron_health->status( $settings, $next_scan );
		$warnings    = $this->warnings( $settings, $cron_status );

		$status = array(
			'schema_version'              => self::SCHEMA_VERSION,
			'site_uuid'                   => $this->settings->site_uuid(),
			'plugin_version'              => ALYNT_DRIME_BACKUPS_UPLOADER_VERSION,
			'queue_count'                 => count( $queued ),
			'uploaded_count'              => count( $uploaded ),
			'failed_count'                => count( $failed ),
			'active_upload'               => ! empty( $active ),
			'auto_scan_enabled'           => ! empty( $settings['auto_scan_enabled'] ),
			'server_cron_expected'        => ! empty( $settings['server_cron_expected'] ),
			'server_outbox_configured'    => ! empty( $settings['server_outbox_path'] ),
			'server_outbox_readable'      => $this->server_outbox_readable( $settings ),
			'wpvivid_override_configured' => ! empty( $settings['backup_path_override'] ),
			'old_wpvivid_uploader_active' => $this->old_wpvivid_uploader_active(),
			'wp_cron_disabled'            => $this->cron_health->is_wp_cron_disabled(),
			'cron_status'                 => isset( $cron_status['status'] ) ? (string) $cron_status['status'] : '',
			'cron_reason'                 => isset( $cron_status['reason'] ) ? (string) $cron_status['reason'] : '',
			'warning_count'               => count( $warnings ),
			'warnings'                    => $warnings,
			'last_runner'                 => isset( $cron_state['last_runner'] ) ? (string) $cron_state['last_runner'] : '',
			'last_runner_at'              => isset( $cron_state['last_runner_at'] ) ? absint( $cron_state['last_runner_at'] ) : 0,
			'last_scheduled_scan_at'      => isset( $cron_state['last_scheduled_scan_at'] ) ? absint( $cron_state['last_scheduled_scan_at'] ) : 0,
			'last_wp_cli_scan_at'         => isset( $cron_state['last_wp_cli_scan_at'] ) ? absint( $cron_state['last_wp_cli_scan_at'] ) : 0,
			'backup_sources'              => $this->backup_sources( $settings, $queued, $uploaded, $failed ),
		);

		if ( $include_paths ) {
			$status['server_outbox_path']   = isset( $settings['server_outbox_path'] ) ? (string) $settings['server_outbox_path'] : '';
			$status['backup_path_override'] = isset( $settings['backup_path_override'] ) ? (string) $settings['backup_path_override'] : '';
		}

		return $status;
	}

	/**
	 * Builds redacted per-source backup freshness evidence.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param array<string,mixed> $queued Queue records.
	 * @param array<string,mixed> $uploaded Uploaded registry records.
	 * @param array<string,mixed> $failed Failed registry records.
	 * @return array<string,array<string,mixed>>
	 */
	private function backup_sources( array $settings, array $queued, array $uploaded, array $failed ) {
		return array(
			'server'  => $this->backup_source_summary(
				'server',
				__( 'Server runner / generic outbox', 'alynt-drime-backups-uploader' ),
				'generic_outbox',
				! empty( $settings['server_outbox_path'] ),
				$settings,
				$queued,
				$uploaded,
				$failed
			),
			'wpvivid' => $this->backup_source_summary(
				'wpvivid',
				__( 'WPvivid', 'alynt-drime-backups-uploader' ),
				'wpvivid',
				! empty( $settings['backup_path_override'] ) || ! empty( $settings['wpvivid_relative_path'] ),
				$settings,
				$queued,
				$uploaded,
				$failed
			),
		);
	}

	/**
	 * Builds one redacted source summary from local registry evidence.
	 *
	 * @param string              $source_key Source key.
	 * @param string              $source_label Source label.
	 * @param string              $producer_key Producer key.
	 * @param bool                $configured Whether the source appears configured.
	 * @param array<string,mixed> $settings Settings.
	 * @param array<string,mixed> $queued Queue records.
	 * @param array<string,mixed> $uploaded Uploaded registry records.
	 * @param array<string,mixed> $failed Failed registry records.
	 * @return array<string,mixed>
	 */
	private function backup_source_summary( $source_key, $source_label, $producer_key, $configured, array $settings, array $queued, array $uploaded, array $failed ) {
		$source_uploaded = $this->records_for_producer( $uploaded, $producer_key );
		$source_failed   = $this->records_for_producer( $failed, $producer_key );
		$source_queued   = $this->records_for_producer( $queued, $producer_key );
		$source_remote   = $this->currently_uploaded_records( $source_uploaded );
		$latest          = $this->latest_record( $source_remote );
		$latest_created  = $this->record_timestamp( $latest, 'created_at' );
		$latest_uploaded = $this->record_timestamp( $latest, 'uploaded_at' );
		$remote_count    = count( $source_remote );
		$inventory_count = $this->latest_inventory_count( $latest, $remote_count );
		$freshness       = $this->freshness_status( (bool) $configured, $latest_uploaded );
		$warnings        = $this->source_warnings( (bool) $configured, count( $source_queued ), $freshness );
		$activity        = $this->source_activity_evidence( (string) $source_key, $settings );

		return array(
			'source_key'                         => sanitize_key( $source_key ),
			'source_label'                       => sanitize_text_field( $source_label ),
			'configured'                         => (bool) $configured,
			'has_upload_evidence'                => ! empty( $source_uploaded ),
			'queued_count'                       => count( $source_queued ),
			'uploaded_count'                     => count( $source_uploaded ),
			'failed_count'                       => count( $source_failed ),
			'remote_registry_count'              => $remote_count,
			'latest_created_at'                  => $latest_created,
			'latest_uploaded_at'                 => $latest_uploaded,
			'latest_upload_age_seconds'          => $latest_uploaded > 0 ? max( 0, time() - $latest_uploaded ) : 0,
			'latest_remote_status'               => isset( $latest['remote_status'] ) ? sanitize_key( (string) $latest['remote_status'] ) : '',
			'latest_inventory_count'             => $inventory_count,
			'latest_inventory_evidence'          => $this->latest_inventory_evidence( $latest, $inventory_count, $remote_count ),
			'latest_source_activity_at'          => $activity['latest_source_activity_at'],
			'latest_source_activity_age_seconds' => $activity['latest_source_activity_at'] > 0 ? max( 0, time() - $activity['latest_source_activity_at'] ) : 0,
			'source_activity_evidence'           => $activity['source_activity_evidence'],
			'local_candidate_count'              => $activity['local_candidate_count'],
			'freshness_status'                   => $freshness,
			'freshness_window_seconds'           => self::BACKUP_FRESHNESS_WINDOW_SECONDS,
			'warning_count'                      => count( $warnings ),
			'warnings'                           => $warnings,
		);
	}

	/**
	 * Returns redacted source-side activity evidence that is not equivalent to upload proof.
	 *
	 * @param string              $source_key Source key.
	 * @param array<string,mixed> $settings Settings.
	 * @return array{latest_source_activity_at:int,source_activity_evidence:string,local_candidate_count:int}
	 */
	private function source_activity_evidence( $source_key, array $settings ) {
		if ( 'wpvivid' !== $source_key ) {
			return $this->empty_source_activity();
		}

		$detector  = new Alynt_Drime_Backups_Uploader_WPvivid_Detector();
		$directory = $detector->get_backup_dir( $settings );

		if ( ! is_dir( $directory ) || ! is_readable( $directory ) ) {
			return $this->empty_source_activity();
		}

		$archive_times            = $this->file_mtimes( glob( trailingslashit( $directory ) . '*.zip' ) );
		$direct_log_times         = $this->file_mtimes( glob( trailingslashit( $directory ) . '*backup_log.txt' ) );
		$nested_log_times         = $this->file_mtimes( glob( trailingslashit( $directory ) . 'wpvivid_log/*backup_log.txt' ) );
		$latest_archive_time      = empty( $archive_times ) ? 0 : max( $archive_times );
		$latest_log_time          = empty( $direct_log_times ) && empty( $nested_log_times ) ? 0 : max( array_merge( $direct_log_times, $nested_log_times ) );
		$latest_activity_time     = max( $latest_archive_time, $latest_log_time );
		$source_activity_evidence = '';

		if ( $latest_archive_time > 0 && $latest_archive_time >= $latest_log_time ) {
			$source_activity_evidence = 'wpvivid_local_archive';
		} elseif ( $latest_log_time > 0 ) {
			$source_activity_evidence = 'wpvivid_backup_log';
		}

		return array(
			'latest_source_activity_at' => $latest_activity_time,
			'source_activity_evidence'  => $source_activity_evidence,
			'local_candidate_count'     => count( $archive_times ),
		);
	}

	/**
	 * Returns an empty source activity summary.
	 *
	 * @return array{latest_source_activity_at:int,source_activity_evidence:string,local_candidate_count:int}
	 */
	private function empty_source_activity() {
		return array(
			'latest_source_activity_at' => 0,
			'source_activity_evidence'  => '',
			'local_candidate_count'     => 0,
		);
	}

	/**
	 * Returns readable non-empty file modification times from a glob result.
	 *
	 * @param array<int,string>|false $files Files.
	 * @return array<int,int>
	 */
	private function file_mtimes( $files ) {
		if ( ! is_array( $files ) ) {
			return array();
		}

		$times = array();

		foreach ( $files as $file ) {
			if ( ! is_file( $file ) || ! is_readable( $file ) ) {
				continue;
			}

			$size  = filesize( $file );
			$mtime = filemtime( $file );

			if ( false === $size || false === $mtime || $size <= 0 ) {
				continue;
			}

			$times[] = max( 0, (int) $mtime );
		}

		return $times;
	}

	/**
	 * Filters records to one producer.
	 *
	 * @param array<string,mixed> $records Records.
	 * @param string              $producer_key Producer key.
	 * @return array<int,array<string,mixed>>
	 */
	private function records_for_producer( array $records, $producer_key ) {
		$filtered = array();

		foreach ( $records as $record ) {
			if ( ! is_array( $record ) || ! isset( $record['producer_key'] ) || (string) $record['producer_key'] !== (string) $producer_key ) {
				continue;
			}

			$filtered[] = $record;
		}

		return $filtered;
	}

	/**
	 * Returns records still marked as uploaded remotely.
	 *
	 * @param array<int,array<string,mixed>> $records Records.
	 * @return array<int,array<string,mixed>>
	 */
	private function currently_uploaded_records( array $records ) {
		$uploaded = array();

		foreach ( $records as $record ) {
			$status = isset( $record['remote_status'] ) ? sanitize_key( (string) $record['remote_status'] ) : 'uploaded';
			if ( 'uploaded' === $status ) {
				$uploaded[] = $record;
			}
		}

		return $uploaded;
	}

	/**
	 * Finds the newest uploaded record.
	 *
	 * @param array<int,array<string,mixed>> $records Records.
	 * @return array<string,mixed>
	 */
	private function latest_record( array $records ) {
		$latest      = array();
		$latest_time = 0;

		foreach ( $records as $record ) {
			$uploaded_at = $this->record_timestamp( $record, 'uploaded_at' );
			$created_at  = $this->record_timestamp( $record, 'created_at' );
			$sort_time   = max( $uploaded_at, $created_at );

			if ( $sort_time >= $latest_time ) {
				$latest      = $record;
				$latest_time = $sort_time;
			}
		}

		return $latest;
	}

	/**
	 * Reads a non-negative timestamp from a record.
	 *
	 * @param array<string,mixed> $record Record.
	 * @param string              $key Key.
	 * @return int
	 */
	private function record_timestamp( array $record, $key ) {
		return isset( $record[ $key ] ) ? max( 0, absint( $record[ $key ] ) ) : 0;
	}

	/**
	 * Returns the latest remote inventory count evidence.
	 *
	 * @param array<string,mixed> $record Latest record.
	 * @param int                 $remote_registry_count Remote registry count.
	 * @return int
	 */
	private function latest_inventory_count( array $record, $remote_registry_count ) {
		$metadata = isset( $record['metadata'] ) && is_array( $record['metadata'] ) ? $record['metadata'] : array();
		$generic  = isset( $metadata['generic_outbox'] ) && is_array( $metadata['generic_outbox'] ) ? $metadata['generic_outbox'] : array();

		foreach ( array( 'remote_catalog', 'remote_index' ) as $key ) {
			if ( isset( $generic[ $key ] ) && is_array( $generic[ $key ] ) && isset( $generic[ $key ]['package_count'] ) ) {
				return max( 0, absint( $generic[ $key ]['package_count'] ) );
			}
		}

		return max( 0, (int) $remote_registry_count );
	}

	/**
	 * Returns a compact inventory evidence label.
	 *
	 * @param array<string,mixed> $record Latest record.
	 * @param int                 $inventory_count Inventory count.
	 * @param int                 $remote_registry_count Remote registry count.
	 * @return string
	 */
	private function latest_inventory_evidence( array $record, $inventory_count, $remote_registry_count ) {
		$metadata = isset( $record['metadata'] ) && is_array( $record['metadata'] ) ? $record['metadata'] : array();
		$generic  = isset( $metadata['generic_outbox'] ) && is_array( $metadata['generic_outbox'] ) ? $metadata['generic_outbox'] : array();

		if ( isset( $generic['remote_catalog'] ) && is_array( $generic['remote_catalog'] ) && isset( $generic['remote_catalog']['package_count'] ) ) {
			return 'generic_outbox_remote_catalog';
		}

		if ( isset( $generic['remote_index'] ) && is_array( $generic['remote_index'] ) && isset( $generic['remote_index']['package_count'] ) ) {
			return 'generic_outbox_remote_index';
		}

		if ( $inventory_count > 0 || $remote_registry_count > 0 ) {
			return 'local_upload_registry';
		}

		return '';
	}

	/**
	 * Classifies latest source freshness using a conservative default window.
	 *
	 * @param bool $configured Whether the source appears configured.
	 * @param int  $latest_uploaded_at Latest upload timestamp.
	 * @return string
	 */
	private function freshness_status( $configured, $latest_uploaded_at ) {
		if ( ! $configured && $latest_uploaded_at <= 0 ) {
			return 'not_configured';
		}

		if ( $latest_uploaded_at <= 0 ) {
			return 'no_upload_evidence';
		}

		return ( time() - $latest_uploaded_at ) > self::BACKUP_FRESHNESS_WINDOW_SECONDS ? 'stale' : 'fresh';
	}

	/**
	 * Builds source-specific warning records.
	 *
	 * @param bool   $configured Whether the source appears configured.
	 * @param int    $queued_count Queued source packages.
	 * @param string $freshness Freshness status.
	 * @return array<int,array<string,string>>
	 */
	private function source_warnings( $configured, $queued_count, $freshness ) {
		$warnings = array();

		if ( $configured && 'no_upload_evidence' === $freshness ) {
			$warnings[] = array(
				'code'    => 'source_no_upload_evidence',
				'message' => __( 'This source is configured but has no uploaded backup evidence yet.', 'alynt-drime-backups-uploader' ),
			);
		}

		if ( 'stale' === $freshness ) {
			$warnings[] = array(
				'code'    => 'source_latest_upload_stale',
				'message' => __( 'The latest uploaded backup evidence is older than the default freshness window.', 'alynt-drime-backups-uploader' ),
			);
		}

		if ( $queued_count > 0 ) {
			$warnings[] = array(
				'code'    => 'source_queue_not_empty',
				'message' => __( 'This source has queued backup packages waiting to upload.', 'alynt-drime-backups-uploader' ),
			);
		}

		return $warnings;
	}

	/**
	 * Returns health warnings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param array<string,mixed> $cron_status Cron status.
	 * @return array<int,array<string,string>>
	 */
	private function warnings( array $settings, array $cron_status ) {
		$warnings = array();

		if ( empty( $settings['server_outbox_path'] ) ) {
			$warnings[] = array(
				'code'    => 'server_outbox_not_configured',
				'message' => __( 'Server outbox path is not configured.', 'alynt-drime-backups-uploader' ),
			);
		} elseif ( ! $this->server_outbox_readable( $settings ) ) {
			$warnings[] = array(
				'code'    => 'server_outbox_unreadable',
				'message' => __( 'Server outbox path is not readable by WordPress.', 'alynt-drime-backups-uploader' ),
			);
		}

		if ( isset( $cron_status['status'] ) && Alynt_Drime_Backups_Uploader_Cron_Health::STATUS_ATTENTION_NEEDED === $cron_status['status'] ) {
			$warnings[] = array(
				'code'    => 'server_cron_attention_needed',
				'message' => isset( $cron_status['reason'] ) ? (string) $cron_status['reason'] : __( 'Server cron attention is needed.', 'alynt-drime-backups-uploader' ),
			);
		}

		if ( $this->old_wpvivid_uploader_active() && ( ! empty( $settings['auto_scan_enabled'] ) || ! empty( $settings['backup_path_override'] ) ) ) {
			$warnings[] = array(
				'code'    => 'old_wpvivid_uploader_active',
				'message' => __( 'The old Alynt Drime WPvivid Uploader plugin is active. Disable automatic uploads in one uploader before using the WPvivid source here to avoid duplicate uploads.', 'alynt-drime-backups-uploader' ),
			);
		}

		return $warnings;
	}

	/**
	 * Returns whether the configured server outbox is readable.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return bool
	 */
	private function server_outbox_readable( array $settings ) {
		$path = isset( $settings['server_outbox_path'] ) ? trim( (string) $settings['server_outbox_path'] ) : '';

		return '' !== $path && is_dir( $path ) && is_readable( $path );
	}

	/**
	 * Returns whether the previous WPvivid-specific uploader line is active.
	 *
	 * @return bool
	 */
	private function old_wpvivid_uploader_active() {
		$basename = 'alynt-drime-wpvivid-uploader/alynt-drime-wpvivid-uploader.php';

		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}

		$active = get_option( 'active_plugins', array() );
		if ( is_array( $active ) && in_array( $basename, $active, true ) ) {
			return true;
		}

		if ( function_exists( 'get_site_option' ) ) {
			$network_active = get_site_option( 'active_sitewide_plugins', array() );
			return is_array( $network_active ) && isset( $network_active[ $basename ] );
		}

		return false;
	}
}
