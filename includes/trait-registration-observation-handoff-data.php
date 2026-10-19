<?php
/**
 * Shared private consumed DATA state, never an imported capability or native IO.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Same bounded machine in the DATA model and fixed private API coordinator. */
trait Alynt_Drime_Backups_Uploader_Registration_Observation_Handoff_Data {
	use Alynt_Drime_Backups_Uploader_Registration_Observation_Data;

	/**
	 * Any staging/claim/finish attempt consumes admission to staging.
	 *
	 * @var bool
	 */
	private $attempted = false;
	/**
	 * Claim is one-shot even when premature or refused.
	 *
	 * @var bool
	 */
	private $claimed = false;
	/**
	 * Finish is one-shot even when premature or refused.
	 *
	 * @var bool
	 */
	private $finished = false;
	/**
	 * Primitive staged packet, no frame or handle.
	 *
	 * @var array|null
	 */
	private $staged;
	/**
	 * Private claimed copy, never exported.
	 *
	 * @var array|null
	 */
	private $inflight;

	/**
	 * Retain bounded synthetic original facts, not a serialized capability.
	 *
	 * @param mixed $data Primitive original context.
	 * @param mixed $intent Exact small form.
	 * @param mixed $form Exact full form.
	 * @param mixed $clock Original synthetic clocks.
	 * @param mixed $ids Synthetic retained issued/reserved UUID list.
	 * @return bool No authentication or trusted identity-set completeness claim.
	 */
	private function stage( $data, $intent, $form, $clock, $ids ) {
		$available       = ! $this->attempted;
		$this->attempted = true;
		$this->staged    = null;
		$this->inflight  = null;
		$packet          = array( $data, $intent, $form, $clock, $ids );
		if ( ! $available || 5 !== func_num_args() || ! self::packet( $packet ) || ! self::clock( $clock ) ) {
			return false;
		}
		$origin = Alynt_Drime_Backups_Uploader_Registration_Observation_Origin::prepare( $data );
		$query  = Alynt_Drime_Backups_Uploader_Registration_Observation_Request::query( $intent, $form, $clock['utc'] );
		if ( false === $origin || false === $query || ! $origin->accepts( $intent, $form, $clock['utc'] ) || ! self::identities( $ids, $data, $query['command_id'] ) ) {
			return false;
		}
		$this->staged = $packet;
		return true;
	}

	/**
	 * Remove the staged slot BEFORE comparison; no restart on any refused claim.
	 *
	 * @param mixed $data Same primitive original context.
	 * @param mixed $intent Same small form, including original nonce bytes.
	 * @param mixed $form Same full form, including original nonce bytes.
	 * @param mixed $clock Same start, later synthetic observations.
	 * @param mixed $ids Same ordered issued/reserved UUID list.
	 * @return bool Private modeled transfer, never a native BEGIN permission.
	 */
	private function claim( $data, $intent, $form, $clock, $ids ) {
		$available       = $this->attempted && ! $this->claimed && ! $this->finished;
		$this->attempted = true;
		$this->claimed   = true;
		$original        = $this->staged;
		$this->staged    = null;
		$this->inflight  = null;
		$packet          = array( $data, $intent, $form, $clock, $ids );
		if ( ! $available || null === $original || 5 !== func_num_args() || ! self::packet( $packet ) || array( $data, $intent, $form, $ids ) !== array( $original[0], $original[1], $original[2], $original[4] ) || ! self::later( $clock, $original[3] ) ) {
			return false;
		}
		// A new clock observation is allowed; the original start is never replaced.
		if ( false === Alynt_Drime_Backups_Uploader_Registration_Observation_Request::query( $intent, $form, $clock['utc'] ) ) {
			return false;
		}
		$this->inflight = $packet;
		return true;
	}

	/**
	 * Consume/clear before parsing a full current candidate; never certify locks.
	 *
	 * @param mixed $frame Full synthetic current frame, never retained in handoff.
	 * @param mixed $intent Same small form.
	 * @param mixed $form Same full form.
	 * @param mixed $clock Same start and later synthetic observations.
	 * @return array|false Candidate-only result; no barrier/closure/retry authority.
	 */
	private function finish( $frame, $intent, $form, $clock ) {
		$available       = $this->claimed && ! $this->finished;
		$this->attempted = true;
		$this->claimed   = true;
		$this->finished  = true;
		$original        = $this->inflight;
		$this->staged    = null;
		$this->inflight  = null;
		if ( ! $available || null === $original || 4 !== func_num_args() || ! self::bounded( $intent ) || ! self::bounded( $form ) || array( $intent, $form ) !== array( $original[1], $original[2] ) || ! self::later( $clock, $original[3] ) ) {
			return false;
		}
		return Alynt_Drime_Backups_Uploader_Registration_Observation_Candidate::observe( $frame, $original[0], $intent, $form, $clock['utc'] );
	}

	/**
	 * Bounded primitive aggregate; existing origin/form sub-limits still apply.
	 *
	 * @param mixed $packet Private synthetic aggregate.
	 * @return bool No conversion, object callbacks or native imports.
	 */
	private static function packet( $packet ) {
		$nodes = 512;
		$bytes = 16384;
		if ( ! self::walk( $packet, 0, $nodes, $bytes ) ) {
			return false;
		}
		$raw = json_encode( $packet );
		return is_string( $raw ) && strlen( $raw ) <= 16384;
	}

	/**
	 * Fixed depth/string/key-value traversal before encoding the aggregate.
	 *
	 * @param mixed $value Node.
	 * @param int   $depth Depth.
	 * @param int   $nodes Remaining key/value budget.
	 * @param int   $bytes Remaining string bytes.
	 * @return bool
	 */
	private static function walk( $value, $depth, &$nodes, &$bytes ) {
		if ( $depth > 8 || --$nodes < 0 ) {
			return false;
		}
		if ( is_array( $value ) ) {
			if ( count( $value ) > intdiv( $nodes, 2 ) ) {
				return false;
			}
			foreach ( $value as $key => $child ) {
				if ( null !== ReflectionReference::fromArrayElement( $value, $key ) || ! self::walk( $key, $depth + 1, $nodes, $bytes ) || ! self::walk( $child, $depth + 1, $nodes, $bytes ) ) {
					return false;
				}
			}
			return true;
		}
		if ( is_string( $value ) ) {
			$bytes -= strlen( $value );
			return $bytes >= 0 && 1 === preg_match( '//u', $value );
		}
		return null === $value || is_int( $value ) || is_bool( $value );
	}

	/**
	 * Explicit modeled used IDs; not proof the genuine owner retained all IDs.
	 *
	 * @param mixed  $ids Synthetic ordered list.
	 * @param array  $data Validated origin.
	 * @param string $lookup Fresh lookup label.
	 * @return bool No reminting, normalization or history adoption.
	 */
	private static function identities( $ids, $data, $lookup ) {
		if ( ! is_array( $ids ) || count( $ids ) < 4 || count( $ids ) > 128 || array_keys( $ids ) !== range( 0, count( $ids ) - 1 ) ) {
			return false;
		}
		$seen = array();
		foreach ( $ids as $id ) {
			if ( ! self::uuid( $id ) || isset( $seen[ $id ] ) || $lookup === $id ) {
				return false;
			}
			$seen[ $id ] = true;
		}
		foreach ( array( $data['scope']['site_uuid'], $data['scope']['ledger_id'], $data['mutation']['command_id'], $data['operation_id'] ) as $id ) {
			if ( ! isset( $seen[ $id ] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Fixed half-open ten-second DATA budget, no actual system timer.
	 *
	 * @param mixed $clock Explicit original/current observations.
	 * @return bool
	 */
	private static function clock( $clock ) {
		if ( ! self::bounded( $clock ) || ! self::keys( $clock, array( 'started_ns', 'now_ns', 'utc' ) ) ) {
			return false;
		}
		foreach ( $clock as $value ) {
			if ( ! is_int( $value ) || $value < 0 ) {
				return false;
			}
		}
		return $clock['utc'] > 0 && $clock['utc'] <= PHP_INT_MAX - 3600 && $clock['now_ns'] >= $clock['started_ns'] && $clock['now_ns'] - $clock['started_ns'] < 10000000000;
	}

	/**
	 * Original start and nonregressing last observation, never a renewed lease.
	 *
	 * @param mixed $clock Current synthetic observation.
	 * @param array $original Prior private observation with original start.
	 * @return bool
	 */
	private static function later( $clock, $original ) {
		return self::clock( $clock ) && $clock['started_ns'] === $original['started_ns'] && $clock['now_ns'] >= $original['now_ns'] && $clock['utc'] >= $original['utc'];
	}
}
