<?php
/**
 * Immutable synthetic origin and candidate history results, never provenance.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-registration-observation-request.php';
require_once __DIR__ . '/class-closed-registration-contract.php';
require_once __DIR__ . '/trait-closed-writer-contract-privacy.php';

/** No public bytes, actual actor/session, native continuation or barrier import. */
final class Alynt_Drime_Backups_Uploader_Registration_Observation_Origin {
	use Alynt_Drime_Backups_Uploader_Registration_Observation_Data;
	use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;

	/**
	 * Private validated copy; synthetic labels are never trusted native origin.
	 *
	 * @var array
	 */
	private $data;

	/** Zero IO. */
	private function __construct() {}

	/**
	 * Explicit immutable DATA only. Genuine owner retention is a later boundary.
	 *
	 * @param mixed $data Synthetic original context.
	 * @return self|false Non-authorizing data object.
	 */
	public static function prepare( $data ) {
		if ( 1 !== func_num_args() || ! self::bounded( $data ) || ! self::keys( $data, array( 'domain', 'installation', 'scope', 'manifest_digest', 'mutation', 'actor_id', 'operation_id', 'context_digest' ) ) || 'registration_observation_origin_v1' !== $data['domain'] || ! self::installation( $data['installation'] ) || ! self::scope( $data['scope'] ) || Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::digest() !== $data['manifest_digest'] || ! self::number( $data['actor_id'] ) || ! self::uuid( $data['operation_id'] ) ) {
			return false;
		}
		$m = $data['mutation'];
		if ( ! self::keys( $m, Alynt_Drime_Backups_Uploader_Registration_Request_Contract::fields( 'register_closed' ) ) ) {
			return false;
		}
		foreach ( $m as $value ) {
			if ( ! is_string( $value ) && ! is_int( $value ) ) {
				return false;
			}
		}
		$form          = array_map( 'strval', $m );
		$form['nonce'] = 'synthetic-data-not-authenticated';
		$parsed        = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::parse( $form );
		$s             = $data['scope'];
		if ( false === $parsed || $parsed['envelope'] !== $m || 'register_closed' !== $m['purpose'] || $m['site_uuid'] !== $s['site_uuid'] || $m['enrollment_binding'] !== $s['enrollment_binding'] || $m['expected_generation'] !== $s['authority_generation'] || $data['context_digest'] !== $m['context_digest'] || $m['issued_at'] < $s['origin_created_at'] ) {
			return false;
		}
		$ids = array( $s['site_uuid'], $s['ledger_id'], $m['command_id'], $data['operation_id'] );
		if ( count( array_unique( $ids ) ) !== 4 ) {
			return false;
		}
		$o       = new self();
		$o->data = $data;
		return $o;
	}

	/**
	 * Exact safe comparison only; never accepts an object as original data.
	 *
	 * @param mixed $data Synthetic comparison.
	 * @return bool
	 */
	public function matches( $data ) {
		return 1 === func_num_args() && self::bounded( $data ) && $data === $this->data;
	}

	/**
	 * Request consistency only; no auth, nonce verification or identity issuance.
	 *
	 * @param mixed $intent Small read form.
	 * @param mixed $form Full read form.
	 * @param mixed $now Synthetic UTC.
	 * @return bool
	 */
	public function accepts( $intent, $form, $now ) {
		return 3 === func_num_args() && false !== $this->query( $intent, $form, $now );
	}

	/**
	 * Valid pending history is observable; no empty-slot admission check here.
	 *
	 * @param mixed $raw Synthetic ledger bytes, not proven native storage.
	 * @param mixed $intent Original small read form.
	 * @param mixed $form Original full read form.
	 * @param mixed $installation Explicit current synthetic installation.
	 * @param mixed $scope Explicit current synthetic scope.
	 * @param mixed $now Synthetic UTC.
	 * @return array|false Redacted DATA result, never promotes absence to a barrier.
	 */
	public function observe( $raw, $intent, $form, $installation, $scope, $now ) {
		$e = $this->query( $intent, $form, $now );
		if ( 6 !== func_num_args() || false === $e || ! self::bounded( $installation ) || ! self::bounded( $scope ) || $installation !== $this->data['installation'] || $scope !== $this->data['scope'] ) {
			return false;
		}
		$r = Alynt_Drime_Backups_Uploader_Closed_Registration_Contract::lookup( $raw, $scope, $e, $this->data['context_digest'], $now );
		if ( false === $r ) {
			return false;
		}
		// lookup already validated the complete bounded canonical chain before decode.
		$s = json_decode( $raw, true );
		foreach ( $s['operations'] as $o ) {
			if ( $o['command']['command_id'] === $this->data['mutation']['command_id'] && ( $o['command'] !== $this->data['mutation'] || $o['operation_id'] !== $this->data['operation_id'] || $o['provenance']['actor_id'] !== $this->data['actor_id'] ) ) {
				$r['classification'] = 'domain_conflict';
			}
		}
		return $r + array(
			'barrier_verified' => false,
			'closure_proven'   => false,
		);
	}

	/**
	 * Original lookup identity/site and fresh read time; never mutation renewal.
	 *
	 * @param mixed $intent Small form.
	 * @param mixed $form Full form.
	 * @param mixed $now Synthetic UTC.
	 * @return array|false Typed DATA.
	 */
	private function query( $intent, $form, $now ) {
		$e = Alynt_Drime_Backups_Uploader_Registration_Observation_Request::query( $intent, $form, $now );
		$d = $this->data;
		if ( false === $e || $e['observed_command_id'] !== $d['mutation']['command_id'] || $e['site_uuid'] !== $d['scope']['site_uuid'] || $e['issued_at'] < $d['mutation']['issued_at'] || in_array( $e['command_id'], array( $d['scope']['site_uuid'], $d['scope']['ledger_id'], $d['mutation']['command_id'], $d['operation_id'] ), true ) ) {
			return false;
		}
		return $e;
	}

	/**
	 * Explicit installation label, no actual WordPress or DB inspection.
	 *
	 * @param mixed $i Synthetic map.
	 * @return bool
	 */
	private static function installation( $i ) {
		return self::keys( $i, array( 'blog_id', 'prefix', 'database' ) ) && self::number( $i['blog_id'] ) && is_string( $i['prefix'] ) && 1 === preg_match( '/^[A-Za-z0-9_]{1,64}$/D', $i['prefix'] ) && defined( 'DB_NAME' ) && is_string( $i['database'] ) && '' !== $i['database'] && strlen( $i['database'] ) <= 64 && DB_NAME === $i['database'];
	}

	/**
	 * Immutable original genesis/generation, never learned from candidate history.
	 *
	 * @param mixed $s Synthetic scope.
	 * @return bool
	 */
	private static function scope( $s ) {
		return self::keys( $s, array( 'site_uuid', 'enrollment_binding', 'authority_generation', 'ledger_id', 'origin_created_at' ) ) && self::uuid( $s['site_uuid'] ) && self::uuid( $s['ledger_id'] ) && $s['site_uuid'] !== $s['ledger_id'] && is_string( $s['enrollment_binding'] ) && 1 === preg_match( '/^[a-f0-9]{64}$/D', $s['enrollment_binding'] ) && self::number( $s['authority_generation'] ) && self::number( $s['origin_created_at'] );
	}
}
