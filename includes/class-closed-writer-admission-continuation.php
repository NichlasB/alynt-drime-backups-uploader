<?php
/**
 * One-stack, one-use simulated transaction admission; no real target execution.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Fixture capability, not an authentication sandbox against same-privilege PHP. */
final class Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Continuation {
	/**
	 * Scoped original command, exact successor, identity and result.
	 *
	 * @var array
	 */
	private $scope;

	/**
	 * No posted Boolean or public constructor creates a continuation.
	 *
	 * @param array  $e Original verified fixture intent.
	 * @param array  $state Confirmed closed begin.
	 * @param object $connection Fixed in-memory transaction identity.
	 */
	private function __construct( array $e, array $state, $connection ) {
		$this->scope = array(
			'intent'     => $e,
			'state'      => $state,
			'connection' => $connection,
			'phase'      => 'begin-confirmed',
			'proposed'   => false,
			'target'     => null,
			'candidate'  => false,
		);
	}

	/**
	 * Invoke a test owner only in this stack; invalidate even on exception.
	 * No authentication callback runs inside the simulated transaction.
	 *
	 * @param array    $e Original envelope.
	 * @param array    $state Confirmed post-begin snapshot.
	 * @param object   $connection Fixed fixture identity.
	 * @param callable $owner Simulated fixed transaction owner.
	 * @return array|false Pure proposed pair, not proof of a commit.
	 */
	public static function within_fixture( array $e, array $state, $connection, callable $owner ) {
		if ( ! is_object( $connection ) || ! Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Model::valid( $state, $e['site_uuid'] ?? null ) || ( $state['writer']['closure']['command'] ?? null ) !== $e || null === $state['writer']['pending'] ) {
			return false;
		}
		$self = new self( $e, $state, $connection );
		try {
			$owner( $self );
			return $self->scope['candidate'];
		} finally {
			$self->scope = array( 'phase' => 'invalid' );
		}
	}

	/**
	 * Once only, compare expected successor and locked bytes on fixed identity.
	 * The returned pair has no durable/physical effect until a fixture commits it.
	 *
	 * @param object $connection Original fixture identity.
	 * @param array  $state Current locked state.
	 * @param string $raw Current locked target bytes.
	 * @param int    $now Pure bounded clock value, not a hook.
	 * @return array|false
	 */
	public function enter_fixture( $connection, array $state, $raw, $now ) {
		if ( 'begin-confirmed' !== $this->scope['phase'] ) {
			return false;
		}
		$this->scope['phase'] = 'transaction-entered';
		if ( $connection !== $this->scope['connection'] || $state !== $this->scope['state'] ) {
			$this->scope['phase'] = 'unresolved';
			return false;
		}
		$next                    = Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Model::simulate_locked( $state, $this->scope['intent'], $raw, $now );
		$this->scope['proposed'] = $next;
		$this->scope['target']   = $raw;
		$this->scope['phase']    = false === $next ? 'unresolved' : 'transaction-entered';
		return $next;
	}

	/**
	 * Final pure identity/state/target/clock guard before simulated commit.
	 * A ticking clock rejects rather than committing an old prepared timestamp.
	 *
	 * @param object $connection Original fixture identity.
	 * @param array  $state Still-locked original state.
	 * @param string $raw Still-locked original target bytes.
	 * @param int    $now Fresh pure UTC value, not a hook.
	 * @return array|false The only confirmable candidate pair.
	 */
	public function confirm_fixture( $connection, array $state, $raw, $now ) {
		if ( 'transaction-entered' !== $this->scope['phase'] ) {
			return false;
		}
		$this->scope['phase'] = 'unresolved';
		$pair                 = $this->scope['proposed'];
		if ( $connection !== $this->scope['connection'] || $state !== $this->scope['state'] || $raw !== $this->scope['target'] || ! is_int( $now ) || $now !== $pair['state']['lifecycle']['changed_at'] || $now < $this->scope['intent']['issued_at'] || $now >= $this->scope['intent']['expires_at'] ) {
			return false;
		}
		$this->scope['candidate'] = $pair;
		$this->scope['phase']     = 'terminal';
		return $pair;
	}
}
