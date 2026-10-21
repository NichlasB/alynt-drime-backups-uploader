<?php
/**
 * Phase ownership and non-transferable terminal cleanup observations.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Null receipt means never opened; false is permanently unavailable. */
trait Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_State {
	/** Current exclusive resource phase.
	 *
	 * @var string|null
	 */
	private $native_resource;
	/** Original factory handle, never exported or substituted.
	 *
	 * @var mysqli|null
	 */
	private $native_handle;
	/** Terminal capture close observation.
	 *
	 * @var bool|null
	 */
	private $capture_receipt;
	/** Terminal admission close observation, independent of erased outcome.
	 *
	 * @var bool|null
	 */
	private $admission_receipt;
	/** Terminal barrier close observation, independent of admission.
	 *
	 * @var bool|null
	 */
	private $barrier_receipt;
	/** Admission release is consumed before IO.
	 *
	 * @var bool
	 */
	private $admission_release_done = false;
	/** Original forty-projection admission ceiling.
	 *
	 * @var int
	 */
	private $protected_reads = 0;
	/** Original sole ledger attempt ceiling.
	 *
	 * @var int
	 */
	private $writes = 0;

	/**
	 * Native-only budget; no callbacks while locking rows.
	 *
	 * @param array|null $command Own command, never a replacement clock.
	 * @return bool Original root and UTC intent bounds.
	 */
	private function budget( $command ) {
		return $this->within_budget() && $this->window( $command );
	}
}
