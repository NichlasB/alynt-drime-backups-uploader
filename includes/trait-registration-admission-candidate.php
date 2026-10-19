<?php
/**
 * Fixed private candidate preparation shared without exporting a continuation.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-closed-registration-contract.php';
require_once __DIR__ . '/class-real-closed-writer-data.php';
require_once __DIR__ . '/class-real-closed-writer-model.php';
require_once __DIR__ . '/class-serialized-settings-parser.php';
require_once __DIR__ . '/class-serialized-settings-target.php';
require_once __DIR__ . '/trait-registration-eligibility.php';

/** Bounded data checks only; caller origin is established separately, never here. */
trait Alynt_Drime_Backups_Uploader_Registration_Admission_Candidate {
	use Alynt_Drime_Backups_Uploader_Registration_Eligibility;

	/**
	 * Exact private before-image; not a captured native snapshot.
	 *
	 * @var array
	 */
	private $before;
	/**
	 * Complete synthetic proposal, including all once-reserved identities.
	 *
	 * @var array
	 */
	private $proposal;
	/**
	 * Explicit synthetic installation/actor metadata, not trusted provenance.
	 *
	 * @var array
	 */
	private $context;
	/**
	 * Prepared ledger-only bytes, never publicly exported.
	 *
	 * @var string
	 */
	private $after;
	/**
	 * Preparation timestamp, never COMMIT time.
	 *
	 * @var int
	 */
	private $recorded_at;

	/**
	 * Build an immutable DATA plan. Cannot establish trusted owner origin.
	 *
	 * @param mixed $frame Complete synthetic ten-row frame.
	 * @param mixed $proposal Complete privately shaped, but synthetic proposal.
	 * @param mixed $context Explicit blog/prefix/database/actor metadata.
	 * @param mixed $now Synthetic UTC preparation time.
	 * @return bool Private candidate prepared; never native authorization.
	 */
	private function prepare_candidate( $frame, $proposal, $context, $now ) {
		if ( 4 !== func_num_args() || ! self::unaliased( $frame ) || ! self::unaliased( $proposal ) || ! self::unaliased( $context ) || ! is_array( $context ) || array_keys( $context ) !== array( 'blog_id', 'prefix', 'database', 'actor_id' ) || ! is_int( $context['blog_id'] ) || $context['blog_id'] < 1 || $context['blog_id'] >= PHP_INT_MAX || ! is_int( $context['actor_id'] ) || $context['actor_id'] < 1 || $context['actor_id'] > PHP_INT_MAX - 5 || ! defined( 'DB_NAME' ) || DB_NAME !== $context['database'] || ! is_string( $context['prefix'] ) || 1 !== preg_match( '/^[A-Za-z0-9_]{1,64}$/D', $context['prefix'] ) || ! is_array( $proposal ) || ( $proposal['actor_id'] ?? null ) !== $context['actor_id'] ) {
			return false;
		}
		$data = $this->eligible_registration(
			$frame,
			array(
				'blog'   => $context['blog_id'],
				'prefix' => $context['prefix'],
			)
		);
		if ( false === $data || ( $proposal['dependency_digest'] ?? null ) !== $data['dependency_digest'] ) {
			return false;
		}
		$after = Alynt_Drime_Backups_Uploader_Closed_Registration_Contract::register( $data['before'], $data['scope'], $proposal, $now );
		if ( ! is_string( $after ) ) {
			return false;
		}
		$ids = array_merge( array( $proposal['command']['command_id'], $proposal['operation_id'], $proposal['terminal_id'], $proposal['resolution_id'] ), array_values( $proposal['effect_ids'] ), array_values( $proposal['reservation_command_ids'] ) );
		foreach ( $ids as $id ) {
			if ( isset( $data['used'][ $id ] ) ) {
				return false;
			}
		}
		$this->before      = $frame;
		$this->proposal    = $proposal;
		$this->context     = $context;
		$this->after       = $after;
		$this->recorded_at = $now;
		return true;
	}

	/**
	 * Primitive full equality only; no parsing, successor rebuilding or IO.
	 * It does not acquire locks, authenticate, or certify the supplied row origin.
	 *
	 * @param mixed $frame Synthetic re-observation, not proven locked data.
	 * @param mixed $proposal Same complete proposal.
	 * @param mixed $context Same explicit metadata.
	 * @param mixed $now Synthetic current UTC, original deadline unchanged.
	 * @return bool Candidate consistency only, never write admission.
	 */
	private function same_candidate( $frame, $proposal, $context, $now ) {
		return 4 === func_num_args() && self::unaliased( $frame ) && self::unaliased( $proposal ) && self::unaliased( $context ) && $frame === $this->before && $proposal === $this->proposal && $context === $this->context && is_int( $now ) && $now >= $this->recorded_at && $now < $this->proposal['command']['expires_at'];
	}

	/**
	 * Fixed shallow plain graphs only; reject references before retaining copies.
	 * Frame payload byte/UTF-8 bounds are separately checked by full eligibility.
	 *
	 * @param mixed $value Candidate graph.
	 * @return bool Primitive shape, not semantic eligibility.
	 */
	private static function unaliased( $value ) {
		$nodes = 256;
		return self::plain( $value, 0, $nodes );
	}

	/**
	 * Bounded primitive traversal; no objects, resources, floats or callbacks.
	 *
	 * @param mixed $value Candidate node.
	 * @param int   $depth Current depth.
	 * @param int   $nodes Remaining key/value budget.
	 * @return bool Supported unaliased graph.
	 */
	private static function plain( $value, $depth, &$nodes ) {
		if ( $depth > 4 || --$nodes < 0 ) {
			return false;
		}
		if ( ! is_array( $value ) ) {
			return null === $value || is_string( $value ) || is_int( $value ) || is_bool( $value );
		}
		if ( count( $value ) > intdiv( $nodes, 2 ) ) {
			return false;
		}
		foreach ( $value as $key => $child ) {
			if ( null !== ReflectionReference::fromArrayElement( $value, $key ) || ! self::plain( $key, $depth + 1, $nodes ) || ! self::plain( $child, $depth + 1, $nodes ) ) {
				return false;
			}
		}
		return true;
	}
}
