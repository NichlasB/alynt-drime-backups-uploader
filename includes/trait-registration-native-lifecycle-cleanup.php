<?php
/**
 * At-most-once phase cleanup, separate from DATA finish and erased history.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Terminal false receipts never become successful merely because slots are null. */
trait Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_Cleanup {
	/**
	 * Detach before close; never close the borrowed authentication connection.
	 *
	 * @return bool Confirmed own capture close.
	 */
	private function release_native_capture() {
		if ( 'capture' !== $this->native_resource ) {
			return true === $this->capture_receipt;
		}
		$owned                 = $this->native_handle;
		$same                  = $this->reader === $owned;
		$this->native_resource = null;
		$this->native_handle   = null;
		$this->reader          = null;
		$this->read_physical   = null;
		$this->physical        = null;
		$this->capture_receipt = false;
		try {
			$closed                = $owned instanceof mysqli && $owned !== $this->barrier_auth && true === $owned->close();
			$this->capture_receipt = $same && $closed;
		} catch ( Throwable $error ) {
			$this->capture_receipt = false;
		}
		return $this->capture_receipt;
	}

	/**
	 * Seal raw admission facts once; after erasure only the inert receipt remains.
	 *
	 * @return bool Actual closed-client observation, not server quiescence.
	 */
	private function lifecycle_release() {
		if ( $this->admission_release_done || 'admission' !== $this->native_resource ) {
			return true === $this->admission_receipt;
		}
		$this->admission_release_done = true;
		$this->native_resource        = null;
		try {
			$this->finish_admission();
			$this->admission_receipt = $this->native_cleanup_done && $this->native_outcome_closed && null === $this->reader && null === $this->native_outcome_writer && null === $this->physical && null === $this->read_physical && 'yes' === ( $this->native_outcome['facts']['acks']['close'] ?? null );
		} catch ( Throwable $error ) {
			$this->admission_receipt = false;
		}
		return $this->admission_receipt;
	}

	/**
	 * Separate barrier ownership; no admission-outcome instrumentation or reset.
	 *
	 * @return bool Acknowledged barrier and client close only.
	 */
	private function release_native_barrier() {
		if ( 'barrier' !== $this->native_resource ) {
			return true === $this->barrier_receipt;
		}
		$owned                 = $this->native_handle;
		$released              = 'barrier_released' === $this->phase && $owned instanceof mysqli && $owned !== $this->barrier_auth && $this->reader === $owned;
		$this->native_resource = null;
		$this->native_handle   = null;
		$this->barrier_receipt = false;
		try {
			if ( $this->reader !== $owned ) {
				$this->reader = $owned;
				$this->phase  = 'poisoned';
			}
			$this->finish_barrier();
			$this->barrier_receipt = $released && 'barrier_released' === $this->phase && null === $this->reader && null === $this->physical;
		} catch ( Throwable $error ) {
			$this->barrier_receipt = false;
		}
		return $this->barrier_receipt;
	}

	/**
	 * Settle current phase, never reopen/re-close after history erasure.
	 *
	 * @return bool No remaining resource and no failed receipt.
	 */
	private function settle_native_lifecycle() {
		try {
			if ( 'capture' === $this->native_resource ) {
				$this->release_native_capture();
			} elseif ( 'admission' === $this->native_resource ) {
				$this->lifecycle_release();
			} elseif ( 'barrier' === $this->native_resource ) {
				$this->release_native_barrier();
			}
			return null === $this->native_resource && null === $this->native_handle && null === $this->reader && null === $this->native_outcome_writer && null === $this->physical && null === $this->read_physical && false !== $this->capture_receipt && false !== $this->admission_receipt && false !== $this->barrier_receipt && $this->within_budget();
		} catch ( Throwable $error ) {
			return false;
		}
	}
}
