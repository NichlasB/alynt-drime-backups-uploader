<?php
/**
 * Dormant request-policy DATA model, not an authentication or dispatch owner.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Synthetic events are forgeable DATA. No result is an authorization handle.
 * No callbacks, identifiers, nonce values, native objects or core APIs enter here.
 * A future genuine owner must earn these transitions; it cannot trust a transcript.
 */
final class Alynt_Drime_Backups_Uploader_Registration_Entry_Policy {
	/**
	 * Fixed remaining synthetic events.
	 *
	 * @var array
	 */
	private $events = array();
	/**
	 * Original virtual start.
	 *
	 * @var int
	 */
	private $start;
	/**
	 * Last virtual tick.
	 *
	 * @var int
	 */
	private $last;
	/**
	 * Virtual read start after writer erasure.
	 *
	 * @var int|null
	 */
	private $read_start;
	/**
	 * Consumed on refusal or completion.
	 *
	 * @var bool
	 */
	private $used = false;
	/**
	 * Fixed model kind.
	 *
	 * @var string
	 */
	private $kind;

	/**
	 * Private construction; start() accepts no authority/provider objects.
	 *
	 * @param string $kind Fixed model.
	 * @param int    $now Virtual seconds.
	 */
	private function __construct( $kind, $now ) {
		$this->kind   = $kind;
		$this->start  = $now;
		$this->last   = $now;
		$this->events = array( 'authenticate', 'manage_options', 'installation', 'authenticate_again' );
		if ( 'closed_post' === $kind ) {
			$this->events = array( 'authenticate', 'manage_options', 'small_mutation_nonce', 'authenticate_again', 'protected_capture', 'full_mutation_nonce', 'context_recheck', 'pre_begin', 'writer_released', 'writer_erased', 'read_authenticate', 'read_manage_options', 'small_read_nonce', 'read_authenticate_again', 'read_capture', 'full_read_nonce', 'read_claim', 'read_pre_begin', 'barrier_closed', 'post_close_authenticate', 'erased' );
		}
	}

	/**
	 * Separate exact GET issuer / POST consumer method policies, DATA only.
	 *
	 * @param mixed $kind issuer_get or closed_post.
	 * @param mixed $method Exact method.
	 * @param mixed $now Virtual clock, never a wall-clock source.
	 * @return self|false
	 */
	public static function start( $kind, $method, $now ) {
		if ( ! is_int( $now ) || $now < 0 || $now > PHP_INT_MAX - 20 || ! in_array( $kind, array( 'issuer_get', 'closed_post' ), true ) || ( 'issuer_get' === $kind ? 'GET' : 'POST' ) !== $method ) {
			return false;
		}
		return new self( $kind, $now );
	}

	/**
	 * Consume a fixed ordered synthetic event. Failure permanently erases state.
	 *
	 * @param mixed $event Event token, not evidence of real work.
	 * @param mixed $now Original nondecreasing virtual clock.
	 * @return bool
	 */
	public function step( $event, $now ) {
		if ( ! $this->timely( $now ) || ! $this->events || $event !== $this->events[0] ) {
			$this->erase();
			return false;
		}
		array_shift( $this->events );
		$this->last = $now;
		if ( 'writer_erased' === $event ) {
			$this->read_start = $now;
		}
		return true;
	}

	/**
	 * Close the model once. Successful GET yields no token; POST yields DATA only.
	 *
	 * @param mixed $outcome Fixed diagnostic token, not a native receipt.
	 * @param mixed $clean Synthetic cleanup acknowledgement, not real proof.
	 * @param mixed $now Virtual clock.
	 * @return array|false No HTTP emission or write-capable value.
	 */
	public function finish( $outcome, $clean, $now ) {
		$ready = $this->timely( $now ) && ! $this->events && true === $clean;
		$kind  = $this->kind;
		$this->erase();
		if ( ! $ready ) {
			return false;
		}
		if ( 'issuer_get' === $kind ) {
			return null === $outcome ? array(
				'policy_complete' => true,
				'nonce_issued'    => false,
			) : false;
		}
		return in_array( $outcome, array( 'closed_receipt_observed', 'not_observed_at_barrier' ), true ) ? self::response( 200, $outcome ) : false;
	}

	/**
	 * Fixed refusal projection, not a response emitter.
	 *
	 * @param mixed $status Requested fixed refusal code.
	 * @return array
	 */
	public static function refusal( $status ) {
		$status = in_array( $status, array( 400, 403, 405, 503 ), true ) ? $status : 503;
		return self::response(
			$status,
			array(
				400 => 'malformed',
				403 => 'forbidden',
				405 => 'method_not_allowed',
				503 => 'unavailable',
			)[ $status ]
		);
	}

	/**
	 * Original 20s root and 10s admission/read ceilings, no refresh by events.
	 *
	 * @param mixed $now Virtual clock.
	 * @return bool
	 */
	private function timely( $now ) {
		return ! $this->used && is_int( $now ) && $now >= $this->last && $now - $this->start < 20 && ( null === $this->read_start ? $now - $this->start < 10 : $now - $this->read_start < 10 );
	}

	/** Erase process-local model state on all terminal paths. */
	private function erase() {
		$this->used       = true;
		$this->events     = array();
		$this->kind       = '';
		$this->start      = 0;
		$this->last       = 0;
		$this->read_start = null;
	}

	/**
	 * Constant bounded diagnostic shape; no arbitrary downstream data survives.
	 *
	 * @param int    $status Fixed status.
	 * @param string $outcome Fixed outcome.
	 * @return array
	 */
	private static function response( $status, $outcome ) {
		return array(
			'status'        => $status,
			'cache_control' => 'no-store',
			'body'          => array(
				'outcome'             => $outcome,
				'execution_supported' => false,
				'dispatch_supported'  => false,
				'retry_supported'     => false,
			),
		);
	}
}
