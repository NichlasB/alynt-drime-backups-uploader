<?php
/**
 * Fixed dormant genuine-core construction boundary, no native reader or writer.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-registration-request-contract.php';
require_once __DIR__ . '/class-closed-registration-contract.php';
require_once __DIR__ . '/class-real-closed-writer-model.php';
require_once __DIR__ . '/class-serialized-settings-parser.php';
require_once __DIR__ . '/class-serialized-settings-target.php';
require_once __DIR__ . '/trait-real-closed-writer-core-guard.php';
require_once __DIR__ . '/trait-closed-writer-contract-privacy.php';
require_once __DIR__ . '/trait-registration-eligibility.php';
require_once __DIR__ . '/trait-registration-construction.php';

/** Private helpers are not a hostile same-process PHP security boundary. */
final class Alynt_Drime_Backups_Uploader_Registration_Request_Owner {
	use Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Core_Guard;
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;
	use Alynt_Drime_Backups_Uploader_Registration_Eligibility;
	use Alynt_Drime_Backups_Uploader_Registration_Construction;

	/**
	 * Non-reentrant stack lifetime.
	 *
	 * @var bool
	 */
	private $active = false;
	/**
	 * Once-attempted private construction, never retried.
	 *
	 * @var bool
	 */
	private $consumed = false;
	/**
	 * Pinned core installation metadata, never an auth token.
	 *
	 * @var array|null
	 */
	private $scope;
	/**
	 * Core guard monotonic UTC observation.
	 *
	 * @var int
	 */
	private $last_clock = 0;

	/** Zero IO and no identity issuance. */
	private function __construct() {}

	/**
	 * Fixed zero-argument creation, no injectable provider/actor/clock.
	 *
	 * @return self|false
	 */
	public static function create() {
		return 0 === func_num_args() ? new self() : false;
	}

	/**
	 * Guard an issuance form; no authoritative reader is installed.
	 *
	 * @param string $method POST only.
	 * @param mixed  $body Original form.
	 * @return array Redacted unavailable only.
	 */
	public function issue( $method, $body ) {
		return 2 === func_num_args() ? $this->boundary( $method, $body, true ) : $this->unavailable();
	}

	/**
	 * Guard a command/read form; no continuation, transaction or execution.
	 *
	 * @param string $method POST only.
	 * @param mixed  $body Original form.
	 * @return array Redacted unavailable only.
	 */
	public function consume( $method, $body ) {
		return 2 === func_num_args() ? $this->boundary( $method, $body, false ) : $this->unavailable();
	}

	/**
	 * Authorization cannot synthesize a protected frame or enable the builder.
	 *
	 * @param string $method Method.
	 * @param mixed  $body Form.
	 * @param bool   $issue Internal grammar selector only.
	 * @return array
	 */
	private function boundary( $method, $body, $issue ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Exact method comparison only.
		if ( $this->active || 'POST' !== $method || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? null ) ) {
			return $this->unavailable();
		}
		$f = $issue ? Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issuance( $body ) : Alynt_Drime_Backups_Uploader_Registration_Request_Contract::parse( $body );
		if ( false === $f ) {
			return $this->unavailable();
		}
		$this->active = true;
		try {
			$core = $this->frame();
			if ( false === $core || ! $this->pin( $core ) ) {
				return $this->unavailable();
			}
			$e      = $issue ? null : $f['envelope'];
			$action = $issue ? Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( $core['blog'], $f['purpose'] ) : $f['action'];
			if ( false === $action || ! $this->guard( $core, $f['nonce'], $action, $e ) ) {
				return $this->unavailable();
			}
			// No native/raw reader exists here: do not call the private builder.
			return $this->unavailable();
		} catch ( Throwable $error ) {
			return $this->unavailable();
		} finally {
			$this->active = false;
		}
	}

	/**
	 * Do not adopt changed installation metadata across owner invocations.
	 *
	 * @param array $core Fresh exact core metadata.
	 * @return bool
	 */
	private function pin( $core ) {
		if ( null === $this->scope ) {
			$this->scope = $core;
		}
		return $core === $this->scope;
	}

	/**
	 * No private envelope, IDs, context, nonce or native capability export.
	 *
	 * @return array
	 */
	private function unavailable() {
		return $this->disposition() + array(
			'outcome' => 'unavailable',
			'source'  => 'registration_construction_v2',
		);
	}
}
