<?php
/**
 * Backup producer interface.
 *
 * @package Alynt_Drime_Backups_Uploader
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines a source that can discover completed backup packages.
 *
 * @since 0.1.0
 */
interface Alynt_Drime_Backups_Uploader_Producer_Interface {
	/**
	 * Returns the stable producer key.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function key();

	/**
	 * Returns the human-readable producer label.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function label();

	/**
	 * Scans for completed backup packages.
	 *
	 * @since 0.1.0
	 *
	 * @return array{directory:string,candidates:array<int,array<string,mixed>>,errors:array<int,string>}
	 */
	public function scan();
}
