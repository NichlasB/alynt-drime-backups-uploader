<?php
/**
 * Failed upload email notification content helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds safe plain-text failure notification content.
 *
 * @since 0.1.0
 */
trait Alynt_Drime_Backups_Uploader_Failure_Notifier_Content {
	/**
	 * Builds the failure email body.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @param string              $failure_state Failure state.
	 * @param string              $reason Failure reason.
	 * @param int                 $attempts Attempt count.
	 * @return string
	 */
	private function failure_message( array $item, $failure_state, $reason, $attempts ) {
		return implode(
			"\n",
			array(
				__( 'A Drime backup upload failed.', 'alynt-drime-backups-uploader' ),
				'',
				sprintf(
					/* translators: %s: site URL. */
					__( 'Site URL: %s', 'alynt-drime-backups-uploader' ),
					$this->site_url()
				),
				sprintf(
					/* translators: %s: backup filename. */
					__( 'Backup file: %s', 'alynt-drime-backups-uploader' ),
					$this->backup_filename( $item )
				),
				sprintf(
					/* translators: %s: failure status label. */
					__( 'Failure status: %s', 'alynt-drime-backups-uploader' ),
					$this->status_label( $failure_state )
				),
				sprintf(
					/* translators: %s: sanitized failure reason. */
					__( 'Reason: %s', 'alynt-drime-backups-uploader' ),
					$this->safe_reason( $reason )
				),
				sprintf(
					/* translators: %d: upload attempt count. */
					__( 'Attempts: %d', 'alynt-drime-backups-uploader' ),
					absint( $attempts )
				),
				sprintf(
					/* translators: %s: UTC timestamp. */
					__( 'Timestamp: %s UTC', 'alynt-drime-backups-uploader' ),
					gmdate( 'Y-m-d H:i:s' )
				),
				sprintf(
					/* translators: %s: plugin admin page URL. */
					__( 'Admin page: %s', 'alynt-drime-backups-uploader' ),
					$this->admin_page_url()
				),
			)
		);
	}

	/**
	 * Builds the test email body.
	 *
	 * @return string
	 */
	private function test_message() {
		return implode(
			"\n",
			array(
				__( 'This is a test email from Alynt Drime Backups Uploader.', 'alynt-drime-backups-uploader' ),
				'',
				sprintf(
					/* translators: %s: site URL. */
					__( 'Site URL: %s', 'alynt-drime-backups-uploader' ),
					$this->site_url()
				),
				sprintf(
					/* translators: %s: UTC timestamp. */
					__( 'Timestamp: %s UTC', 'alynt-drime-backups-uploader' ),
					gmdate( 'Y-m-d H:i:s' )
				),
				sprintf(
					/* translators: %s: plugin admin page URL. */
					__( 'Admin page: %s', 'alynt-drime-backups-uploader' ),
					$this->admin_page_url()
				),
			)
		);
	}

	/**
	 * Builds the email subject.
	 *
	 * @param string $label Subject label.
	 * @return string
	 */
	private function subject( $label ) {
		return '[' . $this->site_name() . '] ' . $label;
	}

	/**
	 * Returns plain-text headers.
	 *
	 * @return array<int,string>
	 */
	private function headers() {
		return array( 'Content-Type: text/plain; charset=UTF-8' );
	}

	/**
	 * Returns a safe backup filename.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @return string
	 */
	private function backup_filename( array $item ) {
		$name = isset( $item['name'] ) ? (string) $item['name'] : '';

		if ( '' === $name && isset( $item['remote_name'] ) ) {
			$name = (string) $item['remote_name'];
		}

		if ( '' === $name && isset( $item['path'] ) ) {
			$name = (string) $item['path'];
		}

		return basename( str_replace( '\\', '/', $name ) );
	}

	/**
	 * Sanitizes a failure reason for plain-text email.
	 *
	 * @param string $reason Failure reason.
	 * @return string
	 */
	private function safe_reason( $reason ) {
		$reason = $this->plain_text( $reason );
		$reason = preg_replace( '#https?://\S+#i', '[redacted-url]', $reason );
		$reason = preg_replace( '/[A-Za-z]:[\\\\\/][^\s]+/', '[redacted-path]', (string) $reason );

		return strlen( (string) $reason ) > 300 ? substr( (string) $reason, 0, 300 ) . '...' : (string) $reason;
	}

	/**
	 * Converts a failure state to a human label.
	 *
	 * @param string $failure_state Failure state.
	 * @return string
	 */
	private function status_label( $failure_state ) {
		return ucwords( str_replace( '_', ' ', sanitize_key( $failure_state ) ) );
	}

	/**
	 * Returns the site name.
	 *
	 * @return string
	 */
	private function site_name() {
		$name = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'name' ) : '';

		return '' === $name ? __( 'WordPress Site', 'alynt-drime-backups-uploader' ) : $this->plain_text( (string) $name );
	}

	/**
	 * Returns the site URL.
	 *
	 * @return string
	 */
	private function site_url() {
		return function_exists( 'home_url' ) ? home_url( '/' ) : '';
	}

	/**
	 * Returns the plugin admin page URL.
	 *
	 * @return string
	 */
	private function admin_page_url() {
		return add_query_arg( array( 'page' => 'alynt-drime-backups-uploader' ), admin_url( 'tools.php' ) );
	}

	/**
	 * Converts a value to sanitized plain text.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function plain_text( $value ) {
		$value = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $value ) : strip_tags( $value );

		return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( (string) $value );
	}
}
