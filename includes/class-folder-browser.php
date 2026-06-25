<?php
/**
 * Drime folder browser and destination preview.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides read-only Drime folder browsing helpers.
 *
 * @since 0.3.0
 */
class Alynt_Drime_Backups_Uploader_Folder_Browser {
	use Alynt_Drime_Backups_Uploader_Folder_Browser_Preview;
	use Alynt_Drime_Backups_Uploader_Folder_Browser_Normalization;

	/**
	 * Settings.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Settings
	 */
	private $settings;

	/**
	 * Drime client.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Drime_Client
	 */
	private $client;

	/**
	 * Logger.
	 *
	 * @var Alynt_Drime_Backups_Uploader_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Alynt_Drime_Backups_Uploader_Settings     $settings Settings.
	 * @param Alynt_Drime_Backups_Uploader_Drime_Client $client Client.
	 * @param Alynt_Drime_Backups_Uploader_Logger       $logger Logger.
	 *
	 * @since 0.3.0
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Settings $settings, Alynt_Drime_Backups_Uploader_Drime_Client $client, Alynt_Drime_Backups_Uploader_Logger $logger ) {
		$this->settings = $settings;
		$this->client   = $client;
		$this->logger   = $logger;
	}

	/**
	 * Lists Drime folders.
	 *
	 * @param string $folder_hash Parent folder hash.
	 * @param int    $page Page number.
	 * @param string $query Search query.
	 * @return array<string,mixed>|WP_Error
	 *
	 * @since 0.3.0
	 */
	public function list_folders( $folder_hash = '', $page = 1, $query = '' ) {
		$settings     = $this->settings->get();
		$workspace_id = absint( $settings['workspace_id'] );
		$folder_hash  = $this->sanitize_hash( $folder_hash );
		$page         = max( 1, absint( $page ) );
		$query        = sanitize_text_field( $query );

		$response = '' === $folder_hash
			? $this->client->list_user_folders( $workspace_id )
			: $this->client->list_folder_entries( $workspace_id, $folder_hash, $page, $query );

		if ( is_wp_error( $response ) ) {
			$this->log_event( 'error', 'folder_browser_failed', 'Drime folder browser failed.', array( 'reason' => $response->get_error_message() ) );
			return $response;
		}

		$folders = $this->normalize_folders( $response );
		$folders = $this->filter_folders( $folders, $query );
		$this->log_event( 'info', 'folder_browser_loaded', 'Drime folder browser loaded.', array( 'folders' => count( $folders ) ) );

		return array(
			'folders' => $folders,
			'page'    => $page,
		);
	}

	/**
	 * Builds a read-only destination preview.
	 *
	 * @param string $parent_folder_id Parent folder ID.
	 * @param string $parent_folder_hash Parent folder hash.
	 * @param string $relative_path Relative path.
	 * @return array<string,mixed>|WP_Error
	 *
	 * @since 0.3.0
	 */
	public function preview_destination( $parent_folder_id, $parent_folder_hash, $relative_path ) {
		$this->log_event( 'info', 'destination_preview_started', 'Drime destination preview started.' );

		$parent_folder_id   = '' === trim( (string) $parent_folder_id ) ? '' : (string) absint( $parent_folder_id );
		$parent_folder_hash = $this->sanitize_hash( $parent_folder_hash );
		$relative_path      = $this->sanitize_relative_path( $relative_path );

		if ( '' !== $parent_folder_id && '' === $parent_folder_hash ) {
			$resolved = $this->resolve_folder_by_id( absint( $parent_folder_id ) );
			if ( is_wp_error( $resolved ) ) {
				$this->log_event( 'error', 'destination_preview_failed', 'Drime destination preview failed.', array( 'reason' => $resolved->get_error_message() ) );
				return $resolved;
			}

			$parent_folder_hash = (string) $resolved['hash'];
		}

		$base_path = $this->base_path_for_hash( $parent_folder_hash );
		if ( is_wp_error( $base_path ) ) {
			$this->log_event( 'error', 'destination_preview_failed', 'Drime destination preview failed.', array( 'reason' => $base_path->get_error_message() ) );
			return $base_path;
		}

		$segments = $this->relative_path_segments( $relative_path );
		$walk     = $this->walk_existing_segments( $parent_folder_hash, $segments );
		if ( is_wp_error( $walk ) ) {
			$this->log_event( 'error', 'destination_preview_failed', 'Drime destination preview failed.', array( 'reason' => $walk->get_error_message() ) );
			return $walk;
		}

		$result = array(
			'parent_folder_id'   => $parent_folder_id,
			'parent_folder_hash' => $parent_folder_hash,
			'base_path'          => $base_path,
			'relative_path'      => $relative_path,
			'destination_path'   => $this->join_paths( $base_path, $relative_path ),
			'existing_segments'  => $walk['existing_segments'],
			'missing_segments'   => $walk['missing_segments'],
			'exists'             => empty( $walk['missing_segments'] ),
			'read_only'          => true,
		);

		$this->log_event(
			'info',
			'destination_preview_finished',
			'Drime destination preview finished.',
			array(
				'exists'  => $result['exists'] ? 'yes' : 'no',
				'missing' => count( $result['missing_segments'] ),
			)
		);

		return $result;
	}

	/**
	 * Writes a diagnostics event.
	 *
	 * @param string              $level Level.
	 * @param string              $code Event code.
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context.
	 * @return void
	 */
	private function log_event( $level, $code, $message, array $context = array() ) {
		$this->logger->event( 'folder_browser', $level, $code, $message, $context );
	}
}
