<?php
/**
 * Fixed dormant request construction, with all persistence unavailable.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-real-closed-writer-request-contract.php';
require_once __DIR__ . '/trait-real-closed-writer-core-guard.php';

/** No port injection, registration, state reader, command issuance or write sink. */
final class Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Owner {
	use Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Core_Guard;

	/**
	 * Non-reentrant, stack-owned inspection lifetime only, never a continuation.
	 *
	 * @var bool
	 */
	private $active = false;
	/**
	 * Fixed installation metadata once first inspected, not an auth certificate.
	 *
	 * @var array|null
	 */
	private $scope;
	/**
	 * Last observed real UTC seconds; no caller clock.
	 *
	 * @var int
	 */
	private $last_clock = 0;

	/** Zero IO; no WordPress dependency checks or identity creation here. */
	private function __construct() {}

	/**
	 * Zero-argument fixed owner. Extra arguments cannot select an enablement port.
	 *
	 * @return self|false
	 */
	public static function create() {
		return 0 === func_num_args() ? new self() : false;
	}

	/**
	 * Inspect an issuance boundary only; no nonce/UUID/context is issued.
	 *
	 * @param string $method POST only.
	 * @param mixed  $body Exact small form.
	 * @return array Fixed redacted unavailable disposition.
	 */
	public function issue( $method, $body ) {
		return 2 === func_num_args() ? $this->inspect( $method, $body, true ) : $this->unavailable();
	}

	/**
	 * Inspect original command/read intent only; never consume a storage command.
	 *
	 * @param string $method POST only.
	 * @param mixed  $body Original form.
	 * @return array Fixed redacted unavailable disposition.
	 */
	public function consume( $method, $body ) {
		return 2 === func_num_args() ? $this->inspect( $method, $body, false ) : $this->unavailable();
	}

	/**
	 * Every exit/exception invalidates the private request lifetime.
	 *
	 * @param string $method Method.
	 * @param mixed  $body Form.
	 * @param bool   $issue Select fixed grammar, never a request flag.
	 * @return array
	 */
	private function inspect( $method, $body, $issue ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Exact literal comparison; normalization would broaden accepted methods.
		if ( $this->active || 'POST' !== $method || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? null ) ) {
			return $this->unavailable();
		}
		$f = $issue ? Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::issuance( $body ) : Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::parse( $body );
		if ( false === $f ) {
			return $this->unavailable();
		}
		$this->active = true;
		try {
			$frame = $this->frame();
			if ( false === $frame ) {
				return $this->unavailable();
			}
			if ( null === $this->scope ) {
				$this->scope = $frame;
			}
			$e      = $issue ? null : $f['envelope'];
			$action = $issue ? Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::issue_action( $frame['blog'], $f['purpose'] ) : Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Contract::action( $e );
			if ( $frame !== $this->scope || false === $action || ! $this->guard( $frame, $f['nonce'], $action, $e ) ) {
				return $this->unavailable();
			}
			// No authoritative raw reader/backend is part of this foundation.
			// Guard truth cannot create a command, receipt or admission handle.
			return $this->unavailable();
		} catch ( Throwable $error ) {
			return $this->unavailable();
		} finally {
			$this->active = false;
		}
	}

	/**
	 * No auth, target, state or provider details in any response.
	 *
	 * @return array
	 */
	private function unavailable() {
		return array(
			'outcome'             => 'unavailable',
			'source'              => 'closed_writer_construction_v1',
			'storage_supported'   => false,
			'dispatch_supported'  => false,
			'execution_supported' => false,
		);
	}

	/**
	 * A retained or copied owner must not transfer inspection state.
	 *
	 * @return void
	 * @throws LogicException Copy refused.
	 */
	private function __clone() {
		throw new LogicException( 'Closed request construction is unavailable.' );
	}

	/**
	 * No serialized private metadata or request lifetime.
	 *
	 * @return never
	 * @throws LogicException Serialization refused.
	 */
	public function __serialize() {
		throw new LogicException( 'Closed request construction is unavailable.' );
	}

	/**
	 * Reject legacy object restoration as well as modern restoration.
	 *
	 * @return void
	 * @throws LogicException Restoration refused.
	 */
	public function __wakeup() {
		throw new LogicException( 'Closed request construction is unavailable.' );
	}

	/**
	 * Data arrays cannot construct this fixed owner or a live request lifetime.
	 *
	 * @param array $data Untrusted restoration data, never interpreted.
	 * @return void
	 * @throws LogicException Restoration refused.
	 */
	public function __unserialize( array $data ) {
		throw new LogicException( 'Closed request construction is unavailable.' );
	}
}
