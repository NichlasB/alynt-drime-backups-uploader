<?php
/**
 * Dormant fixed closed-entry owner. No hook, HTTP emitter or native handoff.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-registration-entry-ingress.php';
require_once __DIR__ . '/class-registration-request-contract.php';
require_once __DIR__ . '/trait-real-closed-writer-core-guard.php';
require_once __DIR__ . '/trait-registration-closed-entry-transport.php';
require_once __DIR__ . '/trait-registration-closed-entry-reader.php';
require_once __DIR__ . '/trait-registration-closed-entry-scope.php';
require_once __DIR__ . '/trait-registration-closed-entry-get-auth.php';

/** Private branches are not an isolation boundary against hostile PHP. */
final class Alynt_Drime_Backups_Uploader_Registration_Closed_Entry_Request_Owner {
	use Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Core_Guard;
	use Alynt_Drime_Backups_Uploader_Registration_Closed_Entry_Transport;
	use Alynt_Drime_Backups_Uploader_Registration_Closed_Entry_Reader;
	use Alynt_Drime_Backups_Uploader_Registration_Closed_Entry_Scope;
	use Alynt_Drime_Backups_Uploader_Registration_Closed_Entry_Get_Auth;

	/**
	 * Request-wide, including refused attempts and other owner instances.
	 *
	 * @var bool
	 */
	private static $consumed = false;
	/**
	 * Reentry permanently poisons the outer attempt.
	 *
	 * @var bool
	 */
	private static $poisoned = false;
	/**
	 * Private original input/identity/clock, erased at the end of the stack.
	 *
	 * @var array
	 */
	private $state = array();
	/**
	 * Only callbacks installed by this instance, never a global reset.
	 *
	 * @var array
	 */
	private $fences = array();
	/**
	 * Owned warning handler, or null before installation.
	 *
	 * @var Closure|null
	 */
	private $error_handler;
	/**
	 * Compatibility field for the unchanged POST core guard trait.
	 *
	 * @var int
	 */
	private $last_clock = 0;

	/** Zero arguments, zero IO. */
	private function __construct() {}
	/** No duplicated stack lifetime. */
	private function __clone() {}

	/**
	 * No dependency, context, stream or enable parameter.
	 *
	 * @return self|false
	 */
	public static function create() {
		return 0 === func_num_args() ? new self() : false;
	}

	/**
	 * No retained owner/continuation, including serialization of an idle owner.
	 *
	 * @throws LogicException Always.
	 * @return void
	 */
	public function __serialize() {
		throw new LogicException( 'Closed entry unavailable.' );
	}

	/**
	 * No manufactured owner state through unserialization.
	 *
	 * @param array $data Rejected serialized data.
	 * @throws LogicException Always.
	 */
	public function __unserialize( array $data ) {
		throw new LogicException( 'Closed entry unavailable.' );
	}

	/**
	 * No supported server/SAPI input profile has been proved or installed.
	 *
	 * @return bool
	 */
	private function entry_available() {
		return false;
	}

	/**
	 * Consume before any dependency, clock, callback or input IO, even on refusal.
	 *
	 * @return bool
	 */
	private function claim() {
		if ( self::$consumed ) {
			self::$poisoned = true;
			return false;
		}
		self::$consumed = true;
		return $this->entry_available();
	}

	/**
	 * Fixed private POST checkpoint; no protected capture or native continuation.
	 *
	 * @return array|false Original slashed three strings, private and stack-only.
	 */
	private function inspect_post() {
		if ( ! $this->claim() ) {
			return false;
		}
		$result = false;
		try {
			if ( 0 === func_num_args() && $this->start() ) {
				$transport = $this->post_transport();
				if ( false !== $transport ) {
					$this->state['transport'] = $transport;
					$raw                      = $this->read_body();
					$form                     = false === $raw ? false : Alynt_Drime_Backups_Uploader_Registration_Entry_Ingress::parse( 'POST', $transport['type'], '', array(), array(), $raw, $transport['post'], true, array() );
					unset( $raw );
					if ( false !== $form && $this->pin_original() && $this->open_fences() && $this->post_authenticated() ) {
						$intent = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issuance( $form );
						$action = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( $this->state['core']['blog'], 'register_closed' );
						if ( false !== $intent && false !== $action && in_array( wp_verify_nonce( $intent['nonce'], $action ), array( 1, 2 ), true ) && $this->post_scope() && $this->post_authenticated() ) {
							$result = $form;
						}
					}
				}
			}
		} catch ( Throwable $error ) {
			$result = false;
		} finally {
			if ( ! $this->close() ) {
				$result = false;
			}
		}
		return $result;
	}

	/**
	 * Separate fixed private GET issuer; never calls a POST-only guard/reader.
	 *
	 * @return string|false Small nonce only; not an installed/public issuer.
	 */
	private function issue_get() {
		if ( ! $this->claim() ) {
			return false;
		}
		$result = false;
		try {
			if ( 0 === func_num_args() && $this->start() && $this->get_transport() && $this->pin_original() && $this->open_fences() && $this->get_authenticated() ) {
				$action = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( $this->state['core']['blog'], 'register_closed' );
				if ( false !== $action && function_exists( 'wp_create_nonce' ) && $this->get_scope() ) {
					$nonce = wp_create_nonce( $action );
					if ( is_string( $nonce ) && 1 === preg_match( '/\A[A-Za-z0-9]{10}\z/D', $nonce ) && $this->get_scope() && $this->get_authenticated() ) {
						$result = $nonce;
					}
				}
			}
		} catch ( Throwable $error ) {
			$result = false;
		} finally {
			if ( ! $this->close() ) {
				$result = false;
			}
		}
		return $result;
	}
}
