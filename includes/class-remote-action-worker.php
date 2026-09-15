<?php
/**
 * Remote action worker.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.12
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executes accepted local V2 remote actions.
 *
 * @since 0.5.12
 */
class Alynt_Drime_Backups_Uploader_Remote_Action_Worker {
	const EVENT = 'alynt_drime_backups_remote_action_event';

	/**
	 * Plugin.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Plugin
	 */
	private $plugin;

	/**
	 * Store.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Remote_Action_Store
	 */
	private $store;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Plugin              $plugin Plugin.
	 * @param Alynt_Drime_Backups_Uploader_Remote_Action_Store $store Store.
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Plugin $plugin, Alynt_Drime_Backups_Uploader_Remote_Action_Store $store ) {
		$this->plugin = $plugin;
		$this->store  = $store;
	}

	/**
	 * Registers worker hook.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( self::EVENT, array( $this, 'handle' ), 10, 1 );
	}

	/**
	 * Handles a queued action.
	 *
	 * @param string $action_id Action ID.
	 * @return void
	 */
	public function handle( $action_id ) {
		$record = $this->store->record( $action_id );
		if ( empty( $record ) || empty( $record['action_id'] ) ) {
			return;
		}

		if ( ! $this->store->acquire_lock( $record['action_id'] ) ) {
			$this->store->upsert_action( $record, 'busy', 'action_already_running', __( 'Another remote action is already running on this client site.', 'alynt-drime-backups-uploader' ), array(), Alynt_Drime_Backups_Uploader_Remote_Action_Store::LOCK_TTL );
			return;
		}

		$action_type = isset( $record['action_type'] ) ? sanitize_key( (string) $record['action_type'] ) : '';

		if ( Alynt_Drime_Backups_Uploader_Dashboard_Connection::ACTION_SCHEDULE_PREVIEW === $action_type ) {
			$this->handle_schedule_preview( $record );
			$this->store->release_lock( $record['action_id'] );
			return;
		}

		if ( Alynt_Drime_Backups_Uploader_Dashboard_Connection::ACTION_SCHEDULE_APPLY === $action_type ) {
			$this->handle_schedule_apply( $record );
			$this->store->release_lock( $record['action_id'] );
			return;
		}

		$this->store->upsert_action( $record, 'running', 'action_running', __( 'Remote scan/upload action is running on the client site.', 'alynt-drime-backups-uploader' ) );

		try {
			$this->plugin->cron_health()->record_manual_scan();
			$result = $this->plugin->scan_and_queue();
			$counts = $this->counts_from_scan_result( is_array( $result ) ? $result : array() );

			if ( ! empty( $result['errors'] ) ) {
				$counts['failed'] = count( $result['errors'] );
				$this->store->upsert_action( $record, 'failed', 'action_scan_failed', __( 'Remote scan/upload action finished with scan errors; no unsafe details were stored.', 'alynt-drime-backups-uploader' ), $counts );
				return;
			}

			if ( 0 === $counts['queued'] ) {
				$this->store->upsert_action( $record, 'succeeded', 'action_scan_completed', __( 'Remote scan/upload action scanned for eligible packages and found nothing new to queue.', 'alynt-drime-backups-uploader' ), $counts );
				return;
			}

			$scheduled = $this->schedule_upload_worker();
			if ( is_wp_error( $scheduled ) ) {
				$this->store->upsert_action( $record, 'failed', 'action_upload_schedule_failed', __( 'Remote scan/upload action scanned for eligible packages but could not schedule the upload worker.', 'alynt-drime-backups-uploader' ), $counts );
				return;
			}

			$this->store->upsert_action( $record, 'succeeded', 'action_scan_completed', __( 'Remote scan/upload action scanned for eligible packages and scheduled the upload worker.', 'alynt-drime-backups-uploader' ), $counts );
		} catch ( Exception $exception ) {
			unset( $exception );
			$this->store->upsert_action( $record, 'failed', 'action_worker_failed', __( 'Remote scan/upload action failed without storing unsafe error details.', 'alynt-drime-backups-uploader' ) );
		} finally {
			$this->store->release_lock( $record['action_id'] );
		}
	}

	/**
	 * Builds and stores a read-only schedule preview.
	 *
	 * @param array<string,mixed> $record Action record.
	 * @return void
	 */
	private function handle_schedule_preview( array $record ) {
		$this->store->upsert_action( $record, 'running', 'schedule_preview_running', __( 'Remote schedule preview action is running on the client site.', 'alynt-drime-backups-uploader' ) );

		try {
			$preview = $this->schedule_preview_from_record( $record );
			if ( is_wp_error( $preview ) ) {
				$this->store->upsert_action( $record, 'failed', $preview->get_error_code(), __( 'Remote schedule preview could not be completed.', 'alynt-drime-backups-uploader' ) );
				return;
			}

			$this->store->upsert_action( $record, 'succeeded', 'schedule_preview_ready', __( 'Schedule preview is ready. No schedule was changed.', 'alynt-drime-backups-uploader' ), array(), 0, $preview );
		} catch ( Exception $exception ) {
			unset( $exception );
			$this->store->upsert_action( $record, 'failed', 'schedule_preview_failed', __( 'Remote schedule preview failed without storing unsafe error details.', 'alynt-drime-backups-uploader' ) );
		}
	}

	/**
	 * Applies one fresh-previewed Alynt scan/upload schedule cadence.
	 *
	 * @since 0.5.19
	 *
	 * @param array<string,mixed> $record Action record.
	 * @return void
	 */
	private function handle_schedule_apply( array $record ) {
		$this->store->upsert_action( $record, 'running', 'schedule_apply_running', __( 'Remote schedule apply action is running on the client site.', 'alynt-drime-backups-uploader' ) );

		try {
			$apply = $this->schedule_apply_from_record( $record );
			if ( is_wp_error( $apply ) ) {
				$this->store->upsert_action( $record, 'failed', $apply->get_error_code(), __( 'Remote schedule apply could not be completed.', 'alynt-drime-backups-uploader' ) );
				return;
			}

			$this->store->upsert_action( $record, 'succeeded', 'schedule_apply_succeeded', __( 'Schedule apply completed for Alynt scan/upload.', 'alynt-drime-backups-uploader' ), array(), 0, array(), $apply );
		} catch ( Exception $exception ) {
			unset( $exception );
			$this->store->upsert_action( $record, 'failed', 'schedule_apply_failed', __( 'Remote schedule apply failed without storing unsafe error details.', 'alynt-drime-backups-uploader' ) );
		}
	}

	/**
	 * Returns a bounded non-mutating preview for the Alynt scan/upload schedule.
	 *
	 * @param array<string,mixed> $record Action record.
	 * @return array<string,mixed>|WP_Error
	 */
	private function schedule_preview_from_record( array $record ) {
		$request = isset( $record['schedule_preview'] ) && is_array( $record['schedule_preview'] ) ? $record['schedule_preview'] : array();
		if ( 'alynt_scan_upload' !== ( isset( $request['schedule_id'] ) ? sanitize_key( (string) $request['schedule_id'] ) : '' ) ) {
			return new WP_Error( 'schedule_preview_schedule_invalid', __( 'The requested schedule is not supported.', 'alynt-drime-backups-uploader' ) );
		}

		$proposed_cadence = isset( $request['proposed_cadence'] ) ? sanitize_key( (string) $request['proposed_cadence'] ) : '';
		$proposed_seconds = $this->cadence_seconds( $proposed_cadence );
		if ( $proposed_seconds <= 0 ) {
			return new WP_Error( 'schedule_preview_cadence_invalid', __( 'The requested cadence is not supported.', 'alynt-drime-backups-uploader' ) );
		}

		$current          = $this->current_scan_schedule_state();
		$current_cadence  = $current['cadence'];
		$current_next_run = $current['next_run'];
		$settings         = $this->plugin->settings()->get();
		$warnings         = array();

		if ( empty( $settings['auto_scan_enabled'] ) ) {
			$warnings[] = 'auto_scan_disabled';
		}
		if ( 'unknown' === $current_cadence ) {
			$warnings[] = 'current_cadence_unknown';
		}
		if ( ! is_numeric( $current_next_run ) || $current_next_run <= 0 ) {
			$warnings[] = 'current_next_run_unavailable';
		}

		$created_at                     = time();
		$preview                        = array(
			'preview_action_id'             => isset( $record['action_id'] ) ? (string) $record['action_id'] : '',
			'schedule_id'                   => 'alynt_scan_upload',
			'label'                         => __( 'Alynt scan/upload', 'alynt-drime-backups-uploader' ),
			'owner'                         => 'alynt_uploader',
			'capability_version'            => 1,
			'current_cadence'               => $current_cadence,
			'proposed_cadence'              => $proposed_cadence,
			'current_next_run_at'           => is_numeric( $current_next_run ) && $current_next_run > 0 ? gmdate( 'c', (int) $current_next_run ) : '',
			'proposed_next_run_estimate_at' => gmdate( 'c', time() + $proposed_seconds ),
			'current_schedule_fingerprint'  => $current['fingerprint'],
			'preview_created_at'            => gmdate( 'c', $created_at ),
			'preview_expires_at'            => gmdate( 'c', $created_at + 900 ),
			'would_change'                  => $current_cadence !== $proposed_cadence,
			'apply_supported'               => $this->schedule_apply_locally_available( $settings, $current_cadence ),
			'rollback_supported'            => false,
			'warnings'                      => $warnings,
		);
		$preview['preview_fingerprint'] = $this->preview_fingerprint( $preview );

		return $preview;
	}

	/**
	 * Validates and applies a schedule apply request.
	 *
	 * @since 0.5.19
	 *
	 * @param array<string,mixed> $record Action record.
	 * @return array<string,mixed>|WP_Error
	 */
	private function schedule_apply_from_record( array $record ) {
		$request = isset( $record['schedule_apply'] ) && is_array( $record['schedule_apply'] ) ? $record['schedule_apply'] : array();
		if ( 'alynt_scan_upload' !== ( isset( $request['schedule_id'] ) ? sanitize_key( (string) $request['schedule_id'] ) : '' ) ) {
			return new WP_Error( 'schedule_apply_unavailable', __( 'The requested schedule is not supported.', 'alynt-drime-backups-uploader' ) );
		}

		$proposed_cadence = isset( $request['proposed_cadence'] ) ? sanitize_key( (string) $request['proposed_cadence'] ) : '';
		if ( $this->cadence_seconds( $proposed_cadence ) <= 0 ) {
			return new WP_Error( 'schedule_apply_unsupported_cadence', __( 'The requested cadence is not supported.', 'alynt-drime-backups-uploader' ) );
		}

		if ( ! $this->plugin->dashboard_connection()->is_schedule_mutation_enabled() ) {
			return new WP_Error( 'schedule_apply_unavailable', __( 'Schedule apply is not enabled on this client site.', 'alynt-drime-backups-uploader' ) );
		}

		$settings = $this->plugin->settings()->get();
		$current  = $this->current_scan_schedule_state();
		if ( ! $this->schedule_apply_locally_available( $settings, $current['cadence'] ) ) {
			return new WP_Error( 'schedule_apply_unavailable', __( 'The Alynt scan/upload schedule is not locally manageable.', 'alynt-drime-backups-uploader' ) );
		}

		$preview_action_id   = isset( $request['preview_action_id'] ) ? (string) $request['preview_action_id'] : '';
		$preview_fingerprint = isset( $request['preview_fingerprint'] ) ? (string) $request['preview_fingerprint'] : '';
		$preview_record      = $this->store->record( $preview_action_id );
		$preview             = isset( $preview_record['schedule_preview'] ) && is_array( $preview_record['schedule_preview'] ) ? $preview_record['schedule_preview'] : array();

		if ( empty( $preview_record ) || 'schedule_preview' !== ( isset( $preview_record['action_type'] ) ? sanitize_key( (string) $preview_record['action_type'] ) : '' ) || 'succeeded' !== ( isset( $preview_record['state'] ) ? sanitize_key( (string) $preview_record['state'] ) : '' ) ) {
			return new WP_Error( 'schedule_apply_preview_missing', __( 'The matching schedule preview is unavailable.', 'alynt-drime-backups-uploader' ) );
		}

		if ( empty( $preview['preview_fingerprint'] ) || ! hash_equals( (string) $preview['preview_fingerprint'], $preview_fingerprint ) ) {
			return new WP_Error( 'schedule_apply_preview_missing', __( 'The matching schedule preview fingerprint is unavailable.', 'alynt-drime-backups-uploader' ) );
		}

		if (
			'alynt_scan_upload' !== ( isset( $preview['schedule_id'] ) ? sanitize_key( (string) $preview['schedule_id'] ) : '' )
			|| ( isset( $preview['proposed_cadence'] ) ? sanitize_key( (string) $preview['proposed_cadence'] ) : '' ) !== $proposed_cadence
			|| 1 !== ( isset( $preview['capability_version'] ) ? absint( $preview['capability_version'] ) : 0 )
		) {
			return new WP_Error( 'schedule_apply_preview_missing', __( 'The schedule preview does not match this apply request.', 'alynt-drime-backups-uploader' ) );
		}

		$preview_expires_at = isset( $preview['preview_expires_at'] ) ? strtotime( (string) $preview['preview_expires_at'] ) : false;
		if ( false === $preview_expires_at || $preview_expires_at <= time() ) {
			return new WP_Error( 'schedule_apply_preview_expired', __( 'The matching schedule preview has expired.', 'alynt-drime-backups-uploader' ) );
		}

		if ( empty( $preview['current_schedule_fingerprint'] ) || ! hash_equals( (string) $preview['current_schedule_fingerprint'], (string) $current['fingerprint'] ) ) {
			return new WP_Error( 'schedule_apply_preview_stale', __( 'The local schedule changed after the preview was created.', 'alynt-drime-backups-uploader' ) );
		}

		$changed      = $current['cadence'] !== $proposed_cadence;
		$apply_result = array(
			'schedule_id'          => 'alynt_scan_upload',
			'label'                => __( 'Alynt scan/upload', 'alynt-drime-backups-uploader' ),
			'owner'                => 'alynt_uploader',
			'capability_version'   => 1,
			'preview_action_id'    => $preview_action_id,
			'preview_fingerprint'  => $preview_fingerprint,
			'previous_cadence'     => $current['cadence'],
			'applied_cadence'      => $proposed_cadence,
			'previous_next_run_at' => $current['next_run'] > 0 ? gmdate( 'c', $current['next_run'] ) : '',
			'applied_next_run_at'  => $current['next_run'] > 0 ? gmdate( 'c', $current['next_run'] ) : '',
			'changed'              => $changed,
			'rollback_available'   => false,
			'rollback_expires_at'  => '',
		);

		if ( ! $changed ) {
			return $apply_result;
		}

		$scheduled = $this->plugin->cron()->apply_scan_cadence( $proposed_cadence );
		if ( is_wp_error( $scheduled ) ) {
			return new WP_Error( $scheduled->get_error_code(), __( 'The requested schedule cadence could not be persisted.', 'alynt-drime-backups-uploader' ) );
		}

		$applied_next_run                    = isset( $scheduled['next_run_at'] ) ? absint( $scheduled['next_run_at'] ) : 0;
		$apply_result['applied_next_run_at'] = $applied_next_run > 0 ? gmdate( 'c', $applied_next_run ) : '';

		return $apply_result;
	}

	/**
	 * Returns whether schedule apply is locally available for the current state.
	 *
	 * @since 0.5.19
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param string              $current_cadence Current cadence label.
	 * @return bool
	 */
	private function schedule_apply_locally_available( array $settings, $current_cadence ) {
		return ! empty( $settings['auto_scan_enabled'] )
			&& 'unknown' !== sanitize_key( (string) $current_cadence )
			&& $this->plugin->dashboard_connection()->is_schedule_mutation_enabled();
	}

	/**
	 * Returns the current redacted scan schedule state.
	 *
	 * @since 0.5.19
	 *
	 * @return array{cadence:string,next_run:int,fingerprint:string}
	 */
	private function current_scan_schedule_state() {
		$current_recurrence = function_exists( 'wp_get_schedule' ) ? wp_get_schedule( Alynt_Drime_Backups_Uploader_Cron::SCAN_EVENT ) : '';
		$current_seconds    = is_string( $current_recurrence ) ? $this->recurrence_interval_seconds( $current_recurrence ) : 0;
		$current_cadence    = $this->cadence_from_seconds( $current_seconds );
		$current_next_run   = function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( Alynt_Drime_Backups_Uploader_Cron::SCAN_EVENT ) : false;
		$current_next_run   = is_numeric( $current_next_run ) ? max( 0, absint( $current_next_run ) ) : 0;

		return array(
			'cadence'     => $current_cadence,
			'next_run'    => $current_next_run,
			'fingerprint' => hash( 'sha256', 'alynt_scan_upload|' . $current_cadence . '|' . (string) $current_next_run ),
		);
	}

	/**
	 * Builds a redacted preview fingerprint.
	 *
	 * @since 0.5.19
	 *
	 * @param array<string,mixed> $preview Preview details.
	 * @return string
	 */
	private function preview_fingerprint( array $preview ) {
		return hash(
			'sha256',
			implode(
				'|',
				array(
					isset( $preview['preview_action_id'] ) ? (string) $preview['preview_action_id'] : '',
					isset( $preview['schedule_id'] ) ? (string) $preview['schedule_id'] : '',
					isset( $preview['capability_version'] ) ? (string) absint( $preview['capability_version'] ) : '0',
					isset( $preview['current_cadence'] ) ? (string) $preview['current_cadence'] : '',
					isset( $preview['proposed_cadence'] ) ? (string) $preview['proposed_cadence'] : '',
					isset( $preview['current_schedule_fingerprint'] ) ? (string) $preview['current_schedule_fingerprint'] : '',
					isset( $preview['preview_created_at'] ) ? (string) $preview['preview_created_at'] : '',
					isset( $preview['preview_expires_at'] ) ? (string) $preview['preview_expires_at'] : '',
				)
			)
		);
	}

	/**
	 * Maps a supported cadence label to seconds.
	 *
	 * @param string $cadence Cadence label.
	 * @return int
	 */
	private function cadence_seconds( $cadence ) {
		$map = array(
			'every_15_minutes' => 900,
			'every_30_minutes' => 1800,
			'hourly'           => 3600,
		);

		$cadence = sanitize_key( (string) $cadence );

		return isset( $map[ $cadence ] ) ? $map[ $cadence ] : 0;
	}

	/**
	 * Maps a recurrence name to an interval in seconds.
	 *
	 * @param string $recurrence Recurrence name.
	 * @return int
	 */
	private function recurrence_interval_seconds( $recurrence ) {
		if ( 'fifteen_minutes' === $recurrence ) {
			return 900;
		}

		if ( function_exists( 'wp_get_schedules' ) ) {
			$schedules = wp_get_schedules();
			if ( is_array( $schedules ) && isset( $schedules[ $recurrence ]['interval'] ) ) {
				return max( 0, absint( $schedules[ $recurrence ]['interval'] ) );
			}
		}

		return 0;
	}

	/**
	 * Maps an interval in seconds to the public cadence label.
	 *
	 * @param int $seconds Interval seconds.
	 * @return string
	 */
	private function cadence_from_seconds( $seconds ) {
		$map = array(
			900  => 'every_15_minutes',
			1800 => 'every_30_minutes',
			3600 => 'hourly',
		);

		$seconds = absint( $seconds );

		return isset( $map[ $seconds ] ) ? $map[ $seconds ] : 'unknown';
	}

	/**
	 * Builds safe counts from scan result.
	 *
	 * @param array<string,mixed> $result Scan result.
	 * @return array<string,int>
	 */
	private function counts_from_scan_result( array $result ) {
		$found  = isset( $result['candidates'] ) && is_array( $result['candidates'] ) ? count( $result['candidates'] ) : 0;
		$queued = isset( $result['queued'] ) ? max( 0, absint( $result['queued'] ) ) : 0;

		return array(
			'found'            => $found,
			'queued'           => $queued,
			'already_known'    => max( 0, $found - $queued ),
			'upload_attempted' => 0,
			'failed'           => 0,
		);
	}

	/**
	 * Schedules the existing upload worker.
	 *
	 * @return true|WP_Error
	 */
	private function schedule_upload_worker() {
		if ( ! function_exists( 'wp_schedule_single_event' ) ) {
			return new WP_Error( 'action_upload_scheduler_unavailable', __( 'WordPress cron scheduling is not available for the upload worker.', 'alynt-drime-backups-uploader' ) );
		}

		if ( function_exists( 'wp_next_scheduled' ) && wp_next_scheduled( Alynt_Drime_Backups_Uploader_Cron::UPLOAD_EVENT ) ) {
			return true;
		}

		$scheduled = wp_schedule_single_event( time() + 5, Alynt_Drime_Backups_Uploader_Cron::UPLOAD_EVENT, array(), true );

		if ( is_wp_error( $scheduled ) ) {
			return $scheduled;
		}

		if ( false === $scheduled ) {
			return new WP_Error( 'action_upload_schedule_failed', __( 'WordPress could not schedule the upload worker.', 'alynt-drime-backups-uploader' ) );
		}

		return true;
	}
}
