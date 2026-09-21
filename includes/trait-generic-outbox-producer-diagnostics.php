<?php
/**
 * Generic outbox diagnostics helpers.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.5.22
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes outbox diagnostics and reads persisted scan snapshots.
 *
 * @since 0.5.22
 */
trait Alynt_Drime_Backups_Uploader_Generic_Outbox_Producer_Diagnostics {
	/**
	 * Writes a scanner diagnostic event.
	 *
	 * @param string              $level Level.
	 * @param string              $code Event code.
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Context.
	 * @return void
	 */
	private function diagnostic( $level, $code, $message, array $context = array() ) {
		if ( $this->logger instanceof Alynt_Drime_Backups_Uploader_Logger ) {
			$this->logger->event( 'filesystem', $level, $code, $message, $context );
		}
	}

	/**
	 * Returns snapshots.
	 *
	 * @return array<string,array<string,int>>
	 */
	private function get_snapshots() {
		$snapshots = get_option( self::SNAPSHOT_OPTION, array() );

		return is_array( $snapshots ) ? $snapshots : array();
	}
}
