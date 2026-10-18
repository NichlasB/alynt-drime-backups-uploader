<?php
/**
 * Dormant closed registration data contract. Never admission or storage.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-closed-writer-json-contract.php';
require_once __DIR__ . '/class-scan-effect-manifest.php';
require_once __DIR__ . '/class-scan-target-codec.php';
require_once __DIR__ . '/trait-closed-registration-data.php';
require_once __DIR__ . '/trait-closed-registration-records.php';
require_once __DIR__ . '/trait-closed-registration-transitions.php';

/** Pure synthetic candidates only; no constructor, clock, UUID or native port. */
final class Alynt_Drime_Backups_Uploader_Closed_Registration_Contract {
	use Alynt_Drime_Backups_Uploader_Closed_Registration_Data;
	use Alynt_Drime_Backups_Uploader_Closed_Registration_Records;
	use Alynt_Drime_Backups_Uploader_Closed_Registration_Transitions;

	const DOMAIN          = 'scan_registration_closed_v2';
	const MAX_RECORD      = 6144;
	const MAX_RECEIPT     = 1024;
	const TERMINAL_BYTES  = 16384;
	const INTENT_HEADROOM = 1536;

	/**
	 * Redacted structural classification; never exports a stored record.
	 *
	 * @param mixed $raw Synthetic canonical JSON.
	 * @param mixed $scope Explicit synthetic scope, not a trusted context.
	 * @return array|false
	 */
	public static function inspect( $raw, $scope ) {
		$s = self::read( $raw, $scope );
		return false === $s ? false : self::disposition() + array(
			'closed_candidate' => true,
			'operation_count'  => count( $s['operations'] ),
			'pending_count'    => count( $s['operations'] ),
		);
	}

	/**
	 * Data fingerprint, not issuance. Does not mint identities or nonces.
	 *
	 * @param mixed $scope Synthetic pinned scope.
	 * @param mixed $proposal Synthetic closed proposal.
	 * @return string|false
	 */
	public static function registration_context( $scope, $proposal ) {
		if ( ! self::scope( $scope ) || ! self::keys( $proposal, self::proposal_keys() ) || false === self::encoded( $proposal, self::MAX_RECORD ) || ! self::command( $proposal['command'], 'register_closed' ) ) {
			return false;
		}
		$proposal['command'] = self::without_context( $proposal['command'] );
		return self::digest( array( self::DOMAIN, 'register_closed', $scope, Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::digest(), $proposal ) );
	}

	/**
	 * Pin fixed snapshot candidate bytes without applying them.
	 *
	 * @param mixed $registration Original registration digest.
	 * @param mixed $proposal Synthetic reservation proposal.
	 * @return string|false
	 */
	public static function reservation_context( $registration, $proposal ) {
		if ( ! self::hash_value( $registration ) || ! self::keys( $proposal, array( 'command', 'actor_id', 'dependency_digest', 'before', 'replacement' ) ) || ! self::command( $proposal['command'], 'reserve_wpvivid_intent' ) || ! self::number( $proposal['actor_id'] ) || ! self::hash_value( $proposal['dependency_digest'] ) ) {
			return false;
		}
		$target = Alynt_Drime_Backups_Uploader_Scan_Target_Codec::snapshot( 'wpvivid_snapshot', $proposal['before'], $proposal['replacement'] );
		return false === $target ? false : self::intent_digest( $registration, $proposal['command'], $proposal['actor_id'], $proposal['dependency_digest'], $target['before_hash'], $target['after_hash'] );
	}

	/**
	 * Fresh observation data may follow expired mutation, never authorize retry.
	 *
	 * @param mixed $raw Synthetic closed ledger.
	 * @param mixed $scope Pinned synthetic scope.
	 * @param mixed $query Typed observation envelope, without nonce/auth.
	 * @param mixed $context Expected historical context digest.
	 * @param mixed $now Synthetic clock.
	 * @return array|false Redacted consistency only, NOT native receipt evidence.
	 */
	public static function lookup( $raw, $scope, $query, $context, $now ) {
		$s = self::read( $raw, $scope );
		if ( false === $s || ! self::keys( $query, array( 'contract', 'purpose', 'command_id', 'site_uuid', 'observed_command_id', 'issued_at', 'expires_at', 'confirm' ) ) || 'scan_registration_observation_v2' !== $query['contract'] || ! in_array( $query['purpose'], array( 'observe_registration', 'observe_reservation' ), true ) || 'observe' !== $query['confirm'] || $query['site_uuid'] !== $s['site_uuid'] || ! self::uuid( $query['command_id'] ) || ! self::uuid( $query['observed_command_id'] ) || $query['command_id'] === $query['observed_command_id'] || ! self::hash_value( $context ) || ! self::window( $query['issued_at'], $query['expires_at'], $now ) || isset( self::identities( $s )[ $query['command_id'] ] ) ) {
			return false;
		}
		$classification = 'not_observed_in_candidate';
		foreach ( $s['operations'] as $o ) {
			foreach ( array(
				'registration_receipt' => 'observe_registration',
				'reservation_receipt'  => 'observe_reservation',
			) as $slot => $purpose ) {
				$r = $o[ $slot ];
				if ( null !== $r && $r['command_id'] === $query['observed_command_id'] ) {
					$classification = $purpose === $query['purpose'] && $context === $r['context_digest'] ? $r['classification'] : 'domain_conflict';
				}
			}
		}
		return self::disposition() + array( 'classification' => $classification );
	}

	/**
	 * Strict closed-only version, genesis, manifest and complete record validation.
	 * No legacy import, terminal history, active variant or implicit genesis.
	 *
	 * @param mixed $raw Private candidate JSON.
	 * @param mixed $scope Pinned data scope.
	 * @return array|false Internal decoded candidate only.
	 */
	private static function read( $raw, $scope ) {
		$s = Alynt_Drime_Backups_Uploader_Closed_Writer_Json_Contract::decode( $raw );
		if ( false === $s || ! self::scope( $scope ) || self::encoded( $s, 131072 ) !== $raw || ! self::keys( $s, array( 'schema_version', 'domain', 'site_uuid', 'enrollment_binding', 'authority_generation', 'ledger_revision', 'participant_digest', 'closed', 'origin', 'operations' ) ) || 2 !== $s['schema_version'] || self::DOMAIN !== $s['domain'] || true !== $s['closed'] || ! self::number( $s['ledger_revision'] ) || ! self::keys( $s['origin'], array( 'kind', 'ledger_id', 'created_at' ) ) || 'owned_closed_fixture_v2' !== $s['origin']['kind'] || Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::digest() !== $s['participant_digest'] || ! is_array( $s['operations'] ) || count( $s['operations'] ) > 1 ) {
			return false;
		}
		if ( array( $s['site_uuid'], $s['enrollment_binding'], $s['authority_generation'], $s['origin']['ledger_id'], $s['origin']['created_at'] ) !== array_values( $scope ) ) {
			return false;
		}
		$header               = $s;
		$header['operations'] = array();
		if ( false === self::encoded( $header, 4096 ) ) {
			return false;
		}
		foreach ( $s['operations'] as $id => $o ) {
			if ( ! self::record( $o, $id, $s, $scope ) ) {
				return false;
			}
		}
		return $s;
	}
}
