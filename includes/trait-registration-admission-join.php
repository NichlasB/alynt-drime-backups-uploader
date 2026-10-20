<?php
/**
 * Private same-root original retention; never imports a continuation.
 *
 * @package Alynt_Drime_Backups_Uploader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/class-registration-observation-origin.php';
require_once __DIR__ . '/trait-registration-observation-packet-data.php';
require_once __DIR__ . '/trait-registration-admission-lifecycle-data.php';

/** Reuse exact DATA validators without invoking consuming lifecycle owners. */
trait Alynt_Drime_Backups_Uploader_Registration_Admission_Join {
	use Alynt_Drime_Backups_Uploader_Registration_Observation_Data;
	use Alynt_Drime_Backups_Uploader_Registration_Observation_Packet_Data;
	use Alynt_Drime_Backups_Uploader_Registration_Admission_Lifecycle_Data;

	/**
	 * Separate root latch; inner constructor may toggle its own active flag.
	 *
	 * @var bool
	 */
	private $admission_join_busy = false;
	/**
	 * Joined reentry permanently bars this root, including after restored DATA.
	 *
	 * @var bool
	 */
	private $admission_join_interrupted = false;
	/**
	 * Minimal original actor/installation/command DATA, no rows, nonce or handles.
	 *
	 * @var array|null
	 */
	private $admission_origin;
	/**
	 * Complete historical collision set plus the ten once-constructed identities.
	 *
	 * @var array
	 */
	private $admission_ids = array();
	/**
	 * Actual original root start, not a fresh phase window.
	 *
	 * @var array|null
	 */
	private $admission_clock;

	/**
	 * Called after own command authorization/preparation, before writer creation.
	 *
	 * @param array $core Original pinned core frame.
	 * @param array $captured Original two-sweep candidate.
	 * @param int   $actor Original guarded actor.
	 * @param int   $start Original pre-capture start.
	 * @return array|false Stack-local exact writer/origin pin, never exported.
	 */
	private function retain_admission_origin( $core, $captured, $actor, $start ) {
		if ( ! $this->admission_join_busy || $this->admission_join_interrupted || null !== $this->admission_origin || $start !== $this->started || ! is_array( $this->proposal ) || $this->proposal['actor_id'] !== $actor ) {
			return false;
		}
		$data = $this->eligible_registration( $captured, $core );
		if ( false === $data ) {
			return false;
		}
		$p     = $this->proposal;
		$d     = array(
			'domain'          => 'registration_observation_origin_v1',
			'installation'    => array(
				'blog_id'  => $core['blog'],
				'prefix'   => $core['prefix'],
				'database' => DB_NAME,
			),
			'scope'           => $data['scope'],
			'manifest_digest' => Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::digest(),
			'mutation'        => $p['command'],
			'actor_id'        => $actor,
			'operation_id'    => $p['operation_id'],
			'context_digest'  => $p['command']['context_digest'],
		);
		$ids   = array_merge( array_keys( $data['used'] ), array( $p['command']['command_id'], $p['operation_id'], $p['terminal_id'], $p['resolution_id'] ), array_values( $p['effect_ids'] ), array_values( $p['reservation_command_ids'] ) );
		$clock = array(
			'started_ns' => $start,
			'now_ns'     => hrtime( true ),
			'utc'        => time(),
		);
		if ( ! self::original( $d, $p, $ids ) || ! self::root_time( $clock, $clock, 10000000000 ) || $clock['utc'] < $p['command']['issued_at'] || $clock['utc'] >= $p['command']['expires_at'] ) {
			return false;
		}
		$this->admission_origin = $d;
		$this->admission_ids    = $ids;
		$this->admission_clock  = $clock;
		// Full writer bytes remain stack-local only, not part of minimal origin.
		return array( $d, $ids, $clock, $this->before, $p, $this->context, $this->after, $this->recorded_at );
	}

	/**
	 * Exact continuity after callback phases, outside native row-lock intervals.
	 *
	 * @param array $pin Original fixed-root local pin, never caller input.
	 * @return bool No authority or outcome classification.
	 */
	private function admission_origin_same( $pin ) {
		if ( ! $this->admission_join_busy || $this->admission_join_interrupted || ! is_array( $pin ) || count( $pin ) !== 8 || array_keys( $pin ) !== range( 0, 7 ) || null === $this->admission_origin || array( $this->admission_origin, $this->admission_ids, $this->admission_clock, $this->before, $this->proposal, $this->context, $this->after, $this->recorded_at ) !== $pin || $this->started !== $pin[2]['started_ns'] ) {
			return false;
		}
		$now = array(
			'started_ns' => $this->started,
			'now_ns'     => hrtime( true ),
			'utc'        => time(),
		);
		return self::root_time( $now, $pin[2], 10000000000 ) && $now['utc'] < $pin[0]['mutation']['expires_at'];
	}

	/** No retention past any root return, including commit followed by refusal. */
	private function clear_admission_origin() {
		$this->admission_origin = null;
		$this->admission_ids    = array();
		$this->admission_clock  = null;
	}
}
