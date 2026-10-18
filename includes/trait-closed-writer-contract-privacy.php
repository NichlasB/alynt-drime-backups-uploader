<?php
/**
 * Non-authorizing contract privacy; no transferable native capability.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Shared refusal/redaction for test-loaded in-memory contracts only. */
trait Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy {
	/**
	 * Fixed data-only disposition, never a storage certificate.
	 *
	 * @return array
	 */
	public function disposition() {
		return array(
			'candidate_only'      => true,
			'storage_supported'   => false,
			'dispatch_supported'  => false,
			'execution_supported' => false,
		);
	}

	/**
	 * Redact private observation/session facts in debug output.
	 *
	 * @return array
	 */
	public function __debugInfo() {
		return $this->disposition();
	}

	/**
	 * Prevent copying private simulated lifetime.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Refuse serialization.
	 *
	 * @return never
	 * @throws LogicException Serialization refused.
	 */
	public function __serialize() {
		throw new LogicException( 'Closed contract data cannot be transferred.' );
	}

	/**
	 * Refuse legacy restoration.
	 *
	 * @return void
	 * @throws LogicException Legacy restoration refused.
	 */
	public function __wakeup() {
		throw new LogicException( 'Closed contract data cannot be transferred.' );
	}

	/**
	 * Refuse restoration without interpreting supplied data.
	 *
	 * @param array $data Untrusted restoration, never interpreted.
	 * @return void
	 * @throws LogicException Restoration refused.
	 */
	public function __unserialize( array $data ) {
		unset( $data );
		throw new LogicException( 'Closed contract data cannot be transferred.' );
	}
}
