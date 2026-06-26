<?php
/**
 * Plugin Name:       Alynt Drime Backups Uploader
 * Plugin URI:        https://alynt.com/
 * Description:       Upload completed backup packages to Drime.
 * Version:           0.1.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Alynt
 * Author URI:        https://alynt.com/
 * GitHub Plugin URI: NichlasB/alynt-drime-backups-uploader
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       alynt-drime-backups-uploader
 * Domain Path:       /languages
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ALYNT_DRIME_BACKUPS_UPLOADER_VERSION', '0.1.1' );
define( 'ALYNT_DRIME_BACKUPS_UPLOADER_MINIMUM_WP', '6.0' );
define( 'ALYNT_DRIME_BACKUPS_UPLOADER_MINIMUM_PHP', '7.4' );
define( 'ALYNT_DRIME_BACKUPS_UPLOADER_FILE', __FILE__ );
define( 'ALYNT_DRIME_BACKUPS_UPLOADER_PATH', plugin_dir_path( __FILE__ ) );
define( 'ALYNT_DRIME_BACKUPS_UPLOADER_URL', plugin_dir_url( __FILE__ ) );
define( 'ALYNT_DRIME_BACKUPS_UPLOADER_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Returns whether the current environment can load the plugin safely.
 *
 * @return bool
 */
function alynt_drime_backups_uploader_meets_requirements() {
	$wp_version = isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : ALYNT_DRIME_BACKUPS_UPLOADER_MINIMUM_WP;

	return version_compare( PHP_VERSION, ALYNT_DRIME_BACKUPS_UPLOADER_MINIMUM_PHP, '>=' )
		&& version_compare( $wp_version, ALYNT_DRIME_BACKUPS_UPLOADER_MINIMUM_WP, '>=' );
}

/**
 * Renders a requirements notice when the plugin cannot load.
 *
 * @return void
 */
function alynt_drime_backups_uploader_requirements_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: 1: minimum WordPress version, 2: minimum PHP version. */
				__( 'Alynt Drime Backups Uploader requires WordPress %1$s or higher and PHP %2$s or higher.', 'alynt-drime-backups-uploader' ),
				ALYNT_DRIME_BACKUPS_UPLOADER_MINIMUM_WP,
				ALYNT_DRIME_BACKUPS_UPLOADER_MINIMUM_PHP
			)
		)
	);
}

if ( ! alynt_drime_backups_uploader_meets_requirements() ) {
	add_action( 'admin_notices', 'alynt_drime_backups_uploader_requirements_notice' );
	return;
}

$alynt_drime_backups_uploader_includes = array(
	'includes/trait-scanner-metadata.php',
	'includes/trait-drime-client-direct-upload.php',
	'includes/trait-drime-client-multipart.php',
	'includes/trait-folder-browser-normalization.php',
	'includes/trait-folder-browser-preview.php',
	'includes/trait-failure-notifier-content.php',
	'includes/trait-backup-registry-failed-context.php',
	'includes/trait-uploader-active-upload.php',
	'includes/trait-uploader-destination.php',
	'includes/trait-uploader-multipart.php',
	'includes/trait-uploader-multipart-session.php',
	'includes/trait-uploader-multipart-parts.php',
	'includes/trait-uploader-retry-state.php',
	'includes/trait-uploader-sidecars.php',
	'includes/trait-uploader-wpvivid-set-cleanup.php',
	'includes/trait-option-storage.php',
	'includes/trait-admin-page-failed-uploads.php',
	'includes/trait-admin-page-notices.php',
	'includes/trait-admin-page-settings.php',
	'includes/trait-admin-page-drime-settings.php',
	'includes/trait-admin-page-source-settings.php',
	'includes/trait-admin-page-upload-settings.php',
	'includes/trait-admin-page-notification-settings.php',
	'includes/trait-admin-page-cron-health.php',
	'includes/trait-admin-page-status.php',
	'includes/trait-plugin-failed-upload-actions.php',
	'includes/trait-plugin-admin-actions.php',
	'includes/trait-plugin-notification-actions.php',
	'includes/interface-producer.php',
	'includes/class-settings.php',
	'includes/class-logger.php',
	'includes/class-failure-notifier.php',
	'includes/class-wpvivid-detector.php',
	'includes/class-wpvivid-producer.php',
	'includes/class-generic-outbox-producer.php',
	'includes/class-scanner.php',
	'includes/class-backup-registry.php',
	'includes/class-queue.php',
	'includes/class-drime-client.php',
	'includes/class-workspace-browser.php',
	'includes/class-folder-browser.php',
	'includes/class-uploader.php',
	'includes/class-remote-retention.php',
	'includes/class-cron-health.php',
	'includes/class-health-summary.php',
	'includes/class-cron.php',
	'includes/class-admin-page.php',
	'includes/class-activator.php',
	'includes/class-deactivator.php',
	'includes/class-plugin.php',
	'includes/class-cli-command.php',
);

foreach ( $alynt_drime_backups_uploader_includes as $alynt_drime_backups_uploader_include ) {
	require_once ALYNT_DRIME_BACKUPS_UPLOADER_PATH . $alynt_drime_backups_uploader_include;
}

register_activation_hook( __FILE__, array( 'Alynt_Drime_Backups_Uploader_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Alynt_Drime_Backups_Uploader_Deactivator', 'deactivate' ) );

/**
 * Loads the plugin text domain.
 *
 * @return void
 */
function alynt_drime_backups_uploader_load_textdomain() {
	load_plugin_textdomain(
		'alynt-drime-backups-uploader',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}

add_action( 'plugins_loaded', 'alynt_drime_backups_uploader_load_textdomain', 0 );

/**
 * Returns the plugin singleton.
 *
 * @return Alynt_Drime_Backups_Uploader_Plugin
 */
function alynt_drime_backups_uploader() {
	static $alynt_drime_backups_uploader_plugin = null;

	if ( null === $alynt_drime_backups_uploader_plugin ) {
		$alynt_drime_backups_uploader_plugin = new Alynt_Drime_Backups_Uploader_Plugin();
	}

	return $alynt_drime_backups_uploader_plugin;
}

add_action( 'plugins_loaded', 'alynt_drime_backups_uploader' );

/**
 * Registers WP-CLI commands.
 *
 * @return void
 */
function alynt_drime_backups_uploader_register_cli_commands() {
	if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WP_CLI' ) ) {
		return;
	}

	$alynt_drime_backups_uploader_command = new Alynt_Drime_Backups_Uploader_CLI_Command( alynt_drime_backups_uploader() );

	WP_CLI::add_command( 'alynt-drime-backups', $alynt_drime_backups_uploader_command );
	WP_CLI::add_command( 'alynt-drime-backups upload-next', array( $alynt_drime_backups_uploader_command, 'upload_next' ) );
}

alynt_drime_backups_uploader_register_cli_commands();
