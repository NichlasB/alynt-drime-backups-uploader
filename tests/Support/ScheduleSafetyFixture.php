<?php
/** Shared synthetic storage fixtures; never wire/runtime capability data. */
class Alynt_Drime_Backups_Uploader_Test_Safety_Fixture {
	const SOURCE = '22222222-2222-4222-8222-222222222222';

	public static function evidence( $now, $number = 1 ) {
		return array(
			'action_id' => sprintf( '11111111-1111-4111-8111-%012d', $number ),
			'request_hash' => hash( 'sha256', 'request-' . $number ),
			'preview_action_id' => '33333333-3333-4333-8333-333333333333',
			'preview_fingerprint' => str_repeat( 'a', 64 ),
			'rollback_metadata_fingerprint' => str_repeat( 'b', 64 ),
			'previous_cadence' => 'every_30_minutes',
			'intended_cadence' => 'every_15_minutes',
			'before_fingerprint' => str_repeat( 'c', 64 ),
			'intended_fingerprint' => str_repeat( 'd', 64 ),
			'metadata_captured_at' => $now - 60,
			'metadata_expires_at' => $now + 3000,
			'preview_created_at' => $now,
			'preview_expires_at' => $now + 900,
			'intent_expires_at' => $now + 300,
		);
	}

	public static function cron( $timestamp, $recurrence = 'thirty_minutes', $interval = 1800 ) {
		return array(
			$timestamp => array( 'alynt_drime_backups_scan_event' => array( md5( serialize( array() ) ) => array( 'schedule' => $recurrence, 'args' => array(), 'interval' => $interval ) ) ),
			9999999999 => array( 'unrelated_fixture_event' => array( 'fixture' => array( 'schedule' => 'hourly', 'args' => array( 'preserved' ), 'interval' => 3600 ) ) ),
			'version' => 2,
		);
	}
}

/** Shared bytes allow realistic interleaved readers in pure unit tests. */
class Alynt_Drime_Backups_Uploader_Test_Safety_Memory_CAS extends Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS {
	public $rows = array();
	public $conflicts = 0;
	public $swaps = 0;
	public $fail_write = false;
	public $before_swap;

	public function __construct() {}
	public function read( $name ) { return isset( $this->rows[ $name ] ) ? $this->rows[ $name ] : null; }
	public function create( $value ) {
		if ( isset( $this->rows[ self::STATE_OPTION ] ) ) return false;
		$this->rows[ self::STATE_OPTION ] = array( 'option_value' => $value, 'autoload' => 'no' );
		return true;
	}
	public function swap( $name, $expected, $replacement ) {
		$this->swaps++;
		if ( $this->fail_write ) return new WP_Error( 'schedule_safety_write_failed', 'Fixture write failure.' );
		if ( $this->before_swap ) {
			$callback = $this->before_swap;
			$this->before_swap = null;
			$callback();
		}
		if ( $this->conflicts > 0 ) { $this->conflicts--; return false; }
		if ( ! isset( $this->rows[ $name ] ) || $expected !== $this->rows[ $name ]['option_value'] ) return false;
		$this->rows[ $name ]['option_value'] = $replacement;
		return true;
	}
}
