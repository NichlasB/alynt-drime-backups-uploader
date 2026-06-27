<?php
/**
 * Admin page.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the plugin admin UI.
 *
 * @since 0.1.0
 */
class Alynt_Drime_Backups_Uploader_Admin_Page {
	use Alynt_Drime_Backups_Uploader_Admin_Page_Failed_Uploads;
	use Alynt_Drime_Backups_Uploader_Admin_Page_Notices;
	use Alynt_Drime_Backups_Uploader_Admin_Page_Settings;
	use Alynt_Drime_Backups_Uploader_Admin_Page_Drime_Settings;
	use Alynt_Drime_Backups_Uploader_Admin_Page_Source_Settings;
	use Alynt_Drime_Backups_Uploader_Admin_Page_Upload_Settings;
	use Alynt_Drime_Backups_Uploader_Admin_Page_Notification_Settings;
	use Alynt_Drime_Backups_Uploader_Admin_Page_Cron_Health;
	use Alynt_Drime_Backups_Uploader_Admin_Page_Status;

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
	 *
	 * @since 0.1.0
	 */
	public function __construct( Alynt_Drime_Backups_Uploader_Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Registers the admin menu.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function register_menu() {
		add_management_page(
			__( 'Drime Backups Uploader', 'alynt-drime-backups-uploader' ),
			__( 'Drime Backups', 'alynt-drime-backups-uploader' ),
			'manage_options',
			'alynt-drime-backups-uploader',
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueues admin assets.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function enqueue_assets( $hook ) {
		if ( 'tools_page_alynt-drime-backups-uploader' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'alynt-drime-backups-uploader-admin',
			ALYNT_DRIME_BACKUPS_UPLOADER_URL . 'assets/admin.css',
			array(),
			ALYNT_DRIME_BACKUPS_UPLOADER_VERSION
		);

		wp_enqueue_script(
			'alynt-drime-backups-uploader-admin',
			ALYNT_DRIME_BACKUPS_UPLOADER_URL . 'assets/admin.js',
			array(),
			ALYNT_DRIME_BACKUPS_UPLOADER_VERSION,
			true
		);

		wp_enqueue_script(
			'alynt-drime-backups-uploader-workspaces',
			ALYNT_DRIME_BACKUPS_UPLOADER_URL . 'assets/admin-workspaces.js',
			array( 'alynt-drime-backups-uploader-admin' ),
			ALYNT_DRIME_BACKUPS_UPLOADER_VERSION,
			true
		);

		wp_localize_script(
			'alynt-drime-backups-uploader-admin',
			'alyntDrimeWPvivid',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'alynt_drime_backups_folder_browser' ),
				'i18n'    => array(
					'loading'                 => __( 'Loading...', 'alynt-drime-backups-uploader' ),
					'previewing'              => __( 'Previewing...', 'alynt-drime-backups-uploader' ),
					'requestFailed'           => __( 'The request failed. Check your connection and try again.', 'alynt-drime-backups-uploader' ),
					'loadFailed'              => __( 'Could not load Drime folders.', 'alynt-drime-backups-uploader' ),
					'workspacesLoadFailed'    => __( 'Could not load Drime workspaces.', 'alynt-drime-backups-uploader' ),
					'noWorkspaces'            => __( 'No allowed non-personal workspaces were found for this token.', 'alynt-drime-backups-uploader' ),
					'workspacesLoaded'        => __( 'Workspaces loaded. Choose one, then save settings.', 'alynt-drime-backups-uploader' ),
					'workspaceIdPrefix'       => __( 'Workspace ID', 'alynt-drime-backups-uploader' ),
					'workspaceSelectedPrefix' => __( 'Selected workspace:', 'alynt-drime-backups-uploader' ),
					'workspaceIdLabel'        => __( 'ID', 'alynt-drime-backups-uploader' ),
					'workspaceMemberSingular' => __( '1 member', 'alynt-drime-backups-uploader' ),
					/* translators: %d: workspace member count. */
					'workspaceMembers'        => __( '%d members', 'alynt-drime-backups-uploader' ),
					'selectedRootFolder'      => __( 'Selected base folder: Drime root or manually entered folder ID.', 'alynt-drime-backups-uploader' ),
					'previewFailed'           => __( 'Could not preview the Drime destination.', 'alynt-drime-backups-uploader' ),
					'noFolders'               => __( 'No folders found.', 'alynt-drime-backups-uploader' ),
					'noFoldersHint'           => __( 'Folders matching this view will appear here.', 'alynt-drime-backups-uploader' ),
					'selectedPrefix'          => __( 'Selected base folder:', 'alynt-drime-backups-uploader' ),
					'exists'                  => __( 'Destination exists:', 'alynt-drime-backups-uploader' ),
					'missing'                 => __( 'Missing folders:', 'alynt-drime-backups-uploader' ),
					'open'                    => __( 'Open', 'alynt-drime-backups-uploader' ),
					'useBase'                 => __( 'Use as Base Folder', 'alynt-drime-backups-uploader' ),
				),
			)
		);
	}

	/**
	 * Renders the page.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage this plugin.', 'alynt-drime-backups-uploader' ) );
		}

		$settings      = $this->plugin->settings()->get();
		$detected_path = $this->plugin->detector()->get_backup_dir( $settings );
		$queue         = $this->plugin->queue()->all();
		$active        = $this->plugin->queue()->get_active();
		$uploaded      = $this->plugin->registry()->get_uploaded();
		$failed        = $this->plugin->registry()->get_failed();
		$retention     = $this->plugin->retention()->preview();
		$events        = $this->plugin->logger()->get_events();
		$diagnostics   = $this->plugin->logger()->stats();
		$cron_health   = $this->plugin->cron_health();
		$health        = $this->plugin->health_summary()->status( wp_next_scheduled( Alynt_Drime_Backups_Uploader_Cron::SCAN_EVENT ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Notice rendering is read-only.
		$notice = isset( $_GET['alynt_notice'] ) ? sanitize_key( wp_unslash( $_GET['alynt_notice'] ) ) : '';

		?>
		<div class="wrap alynt-drime-wpvivid">
			<h1><?php esc_html_e( 'Drime Backups Uploader', 'alynt-drime-backups-uploader' ); ?></h1>
			<?php $this->render_notice( $notice ); ?>
			<?php $this->render_cron_health_notice( $settings, $cron_health ); ?>
			<hr class="wp-header-end">
			<?php
			$this->render_settings_form( $settings, $detected_path );
			$this->render_manual_actions();
			$this->render_status_summary( $queue, $uploaded, $failed );
			$this->render_health_summary( $health );
			$this->render_scan_state( $settings, $events, $cron_health );
			$this->render_active_upload_state( $active );
			$this->render_failed_uploads( $failed );
			$this->render_remote_retention_status( $settings, $retention );
			$this->render_diagnostics_panel( $settings, $diagnostics );
			$this->render_recent_events( $events );
			?>
		</div>
		<?php
	}
}
