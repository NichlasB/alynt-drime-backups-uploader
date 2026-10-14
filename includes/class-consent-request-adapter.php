<?php
/**
 * Dormant test-only request boundary; injected checks never establish real consent.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** No hooks, database, authenticated provenance, production provider or effects. */
final class Alynt_Drime_Backups_Uploader_Consent_Request_Adapter {
	use Alynt_Drime_Backups_Uploader_Consent_Request_Validation;

	const SOURCE = 'fixture_request_v1';

	/**
	 * Explicit test ports, not trustworthy production services.
	 *
	 * @var array
	 */
	private $ports;
	/**
	 * Fixed scope, never discovered/generated.
	 *
	 * @var string
	 */
	private $site_uuid;
	/**
	 * Literal fixture switch, not authorization.
	 *
	 * @var bool
	 */
	private $enabled;

	/**
	 * Construct without invoking any port or registering a handler.
	 *
	 * @param array  $ports Eight named callable test ports.
	 * @param string $site_uuid Fixed fixture scope.
	 * @param bool   $enabled Literal fixture switch.
	 */
	public function __construct( array $ports, $site_uuid, $enabled = false ) {
		$this->ports     = $ports;
		$this->site_uuid = $site_uuid;
		$this->enabled   = true === $enabled && $this->uuid( $site_uuid ) && $this->exact_keys( $ports, array( 'authorize', 'verify_nonce', 'create_nonce', 'context', 'clock', 'uuid', 'consume', 'observe' ) );
		foreach ( $ports as $port ) {
			$this->enabled = $this->enabled && is_callable( $port );
		}
	}

	/**
	 * Mint a fixture-only nonce-bound envelope after protected issuance checks.
	 *
	 * @param string $method Explicit method, never read from a superglobal.
	 * @param mixed  $body Slashed form.
	 * @return array|WP_Error Private ephemeral envelope, not durable permission.
	 */
	public function issue( $method, $body ) {
		if ( ! $this->enabled || 'POST' !== $method ) {
			return $this->error( 'denied' );
		}
		$b = $this->form( $body, array( 'purpose', 'confirm', 'nonce' ) );
		if ( false === $b || ! $this->purpose( $b['purpose'], $b['confirm'] ) || '' === $b['nonce'] ) {
			return $this->error( 'invalid' );
		}
		try {
			if ( ! $this->authorized() || ! $this->nonce( $b['nonce'], $this->issue_action( $b['purpose'] ) ) ) {
				return $this->error( 'denied' );
			}
			$context = call_user_func( $this->ports['context'] );
			$now     = $this->now();
			$id      = call_user_func( $this->ports['uuid'] );
			if ( ! $this->valid_context( $context ) || false === $now || ! $this->uuid( $id ) || ( 'grant' === $b['purpose'] && ! $context['eligible'] ) || ( 'rebind' === $b['purpose'] && null === $context['replacement_binding'] ) ) {
				return $this->error( 'context' );
			}
			$c = array(
				'contract'            => self::SOURCE,
				'command_id'          => $id,
				'purpose'             => $b['purpose'],
				'site_uuid'           => $this->site_uuid,
				'enrollment_binding'  => $context['binding'],
				'expected_generation' => $context['generation'],
				'issued_at'           => $now,
				'expires_at'          => $now + 300,
				'replacement_binding' => 'rebind' === $b['purpose'] ? $context['replacement_binding'] : '',
				'confirm'             => $b['confirm'],
			);
			if ( ! $this->authorized() || ! $this->nonce( $b['nonce'], $this->issue_action( $b['purpose'] ) ) ) {
				return $this->error( 'denied' );
			}
			$nonce = call_user_func( $this->ports['create_nonce'], $this->command_action( $c ) );
			return is_string( $nonce ) && '' !== $nonce && strlen( $nonce ) <= 2048 ? array(
				'envelope'            => $c,
				'nonce'               => $nonce,
				'source'              => self::SOURCE,
				'dispatch_supported'  => false,
				'execution_supported' => false,
			) : $this->error( 'unresolved' );
		} catch ( Throwable $error ) {
			return $this->error( 'unresolved' );
		}
	}

	/**
	 * Consume a verified fixture request, never pass nonce/session to the sink.
	 *
	 * The sink must invoke the scoped guard before EACH conditional write attempt.
	 * Arbitrary injected sinks are not sandboxed or certified by this class.
	 *
	 * @param string $method Explicit method.
	 * @param mixed  $body Slashed form.
	 * @return array|WP_Error Redacted non-executing outcome.
	 */
	public function consume( $method, $body ) {
		if ( ! $this->enabled || 'POST' !== $method ) {
			return $this->error( 'denied' );
		}
		$c = $this->envelope( $body );
		if ( false === $c ) {
			return $this->error( 'invalid' );
		}
		$nonce    = stripslashes( $body['nonce'] );
		$active   = true;
		$admitted = false;
		$attempts = 0;
		try {
			if ( ! $this->request_valid( $c, $nonce ) ) {
				return $this->error( 'denied' );
			}
			$guard  = function () use ( $c, $nonce, &$active, &$admitted, &$attempts ) {
				$admitted = false;
				if ( ! $active || ++$attempts > 3 || ! $this->request_valid( $c, $nonce ) ) {
					return false;
				}
				$ctx      = call_user_func( $this->ports['context'] );
				$admitted = $this->valid_context( $ctx ) && $ctx['generation'] === $c['expected_generation'] && $ctx['binding'] === $c['enrollment_binding']
					&& ( 'grant' !== $c['purpose'] || $ctx['eligible'] ) && ( 'rebind' !== $c['purpose'] || $ctx['replacement_binding'] === $c['replacement_binding'] )
					&& $this->request_valid( $c, $nonce );
				return $admitted;
			};
			$result = call_user_func( $this->ports['consume'], $c, $guard );
			return 'committed' === $result && ! $admitted ? $this->error( 'unresolved' ) : $this->outcome( $result, array( 'committed', 'observed_current', 'observed_superseded' ) );
		} catch ( Throwable $error ) {
			return $this->error( 'unresolved' );
		} finally {
			$active = false; // A retained guard is not a later permission or renewable token.
		}
	}

	/**
	 * Observe a receipt with fresh independent read intent; never consumes a grant.
	 *
	 * @param string $method Explicit method.
	 * @param mixed  $body Slashed form.
	 * @return array|WP_Error Fixed non-permission result.
	 */
	public function reconcile( $method, $body ) {
		if ( ! $this->enabled || 'POST' !== $method ) {
			return $this->error( 'denied' );
		}
		$b = $this->form( $body, array( 'command_id', 'nonce' ) );
		if ( false === $b || ! $this->uuid( $b['command_id'] ) || '' === $b['nonce'] ) {
			return $this->error( 'invalid' );
		}
		try {
			$action = 'alynt_drime_backups_consent_fixture_receipt_v1:' . $this->site_uuid . ':' . $b['command_id'];
			if ( ! $this->authorized() || ! $this->nonce( $b['nonce'], $action ) ) {
				return $this->error( 'denied' );
			}
			return $this->outcome( call_user_func( $this->ports['observe'], $this->site_uuid, $b['command_id'] ), array( 'observed_current', 'observed_superseded', 'not_found' ) );
		} catch ( Throwable $error ) {
			return $this->error( 'unresolved' );
		}
	}

	/**
	 * Revalidate current session capability, nonce and original short deadline.
	 *
	 * @param array  $c Validated envelope.
	 * @param string $nonce Ephemeral intent.
	 * @return bool
	 */
	private function request_valid( array $c, $nonce ) {
		if ( ! $this->authorized() || ! $this->nonce( $nonce, $this->command_action( $c ) ) ) {
			return false;
		}
		$now = $this->now();
		return false !== $now && $c['issued_at'] <= $now && $now < $c['expires_at'];
	}

	/**
	 * Strict fixture authorization, not WordPress session proof.
	 *
	 * @return bool
	 */
	private function authorized() {
		return true === call_user_func( $this->ports['authorize'], $this->site_uuid );
	}

	/**
	 * Accept only exact core-style nonce success codes, not truthy values.
	 *
	 * @param string $nonce Ephemeral intent.
	 * @param string $action Canonical action.
	 * @return bool
	 */
	private function nonce( $nonce, $action ) {
		return in_array( call_user_func( $this->ports['verify_nonce'], $nonce, $action ), array( 1, 2 ), true );
	}

	/**
	 * Bounded server fixture clock.
	 *
	 * @return int|false
	 */
	private function now() {
		$now = call_user_func( $this->ports['clock'] );
		return is_int( $now ) && $now > 0 && $now <= PHP_INT_MAX - 3900 ? $now : false;
	}

	/**
	 * Separate protected issuance from command verification.
	 *
	 * @param string $purpose Purpose.
	 * @return string
	 */
	private function issue_action( $purpose ) {
		return 'alynt_drime_backups_consent_fixture_issue_v1:' . $purpose . ':' . $this->site_uuid;
	}

	/**
	 * Versioned canonical action, independent of form field order; not a signature.
	 *
	 * @param array $c Exact normalized envelope.
	 * @return string
	 */
	private function command_action( array $c ) {
		$ordered = array();
		foreach ( array( 'contract', 'command_id', 'purpose', 'site_uuid', 'enrollment_binding', 'expected_generation', 'issued_at', 'expires_at', 'replacement_binding', 'confirm' ) as $key ) {
			$ordered[ $key ] = $c[ $key ];
		}
		return 'alynt_drime_backups_consent_fixture_command_v1:' . hash( 'sha256', json_encode( $ordered ) );
	}

	/**
	 * Permit only fixed fixture outcomes; never forward port errors/private data.
	 *
	 * @param mixed $value Sink result.
	 * @param array $allowed Exact outcomes.
	 * @return array|WP_Error
	 */
	private function outcome( $value, array $allowed ) {
		return is_string( $value ) && in_array( $value, $allowed, true ) ? array(
			'outcome'             => $value,
			'source'              => self::SOURCE,
			'dispatch_supported'  => false,
			'execution_supported' => false,
		) : $this->error( 'unresolved' );
	}

	/**
	 * Redact all failures, including provider exceptions/ambiguous writes.
	 *
	 * @param string $suffix Fixed code suffix.
	 * @return WP_Error
	 */
	private function error( $suffix ) {
		return new WP_Error( 'consent_request_' . $suffix, 'Consent request fixture is unavailable.' );
	}
}
