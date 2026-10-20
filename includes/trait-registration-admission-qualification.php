<?php
/**
 * Fixed private same-root sealed qualification; no receipt or authority export.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/trait-registration-sealed-outcome-data.php';

/** Only own original slots and actual existing guard observations are joined. */
trait Alynt_Drime_Backups_Uploader_Registration_Admission_Qualification {
	use Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome_Data;
	use Alynt_Drime_Backups_Uploader_Registration_Sealed_Outcome_Data;

	/**
	 * Independent consumption, never synthetic lifecycle phase or native tick.
	 *
	 * @var bool
	 */
	private $admission_qualification_spent = false;

	/**
	 * Called explicitly after cleanup, never from catch/finally or under row locks.
	 * Does not call authentication, native IO, another consuming owner or observer.
	 *
	 * @param array $pin Existing exact full root-local writer/origin pin.
	 * @param array $guards Existing postrelease checks; null means not performed.
	 * @return array|false Private DATA only; root returns only its existing boolean.
	 */
	private function qualify_admission_outcome( $pin, $guards ) {
		$available                           = ! $this->admission_qualification_spent;
		$this->admission_qualification_spent = true;
		if ( ! $available || ! $this->admission_origin_same( $pin ) ) {
			return false;
		}
		$clock = array(
			'started_ns' => $this->started,
			'now_ns'     => hrtime( true ),
			'utc'        => time(),
		);
		if ( $clock['utc'] < $this->last_clock ) {
			return false;
		}
		$owner                 = array(
			'started'            => $this->native_outcome_started,
			'cleanup_done'       => $this->native_cleanup_done,
			'sealed'             => $this->native_outcome_closed,
			'tick'               => $this->native_outcome_tick,
			'reader_remaining'   => null !== $this->reader,
			'writer_remaining'   => null !== $this->native_outcome_writer,
			'physical_remaining' => null !== $this->physical || null !== $this->read_physical,
		);
		$guards['unreentered'] = $this->admission_join_busy && ! $this->admission_join_interrupted;
		$acceptance            = array(
			'domain'    => 'closed_qualification_claims_v1',
			'guards'    => $guards,
			'final_pin' => true, // Exact full-pin continuity above, after all callbacks.
		);
		return self::closed_qualification_data( $this->admission_origin, $this->proposal, $this->admission_ids, $this->admission_clock, $clock, $this->native_outcome, $owner, $acceptance );
	}
}
