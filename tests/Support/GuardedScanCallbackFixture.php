<?php
/** In-memory phase fault hooks; no real WordPress or worker effects. */
require_once __DIR__ . '/ScheduleSafetyFixture.php';

class Alynt_Drime_Backups_Uploader_Test_Scan_CAS extends Alynt_Drime_Backups_Uploader_Test_Safety_Memory_CAS {
	public $fail_phases = array();
	public $throw_phases = array();
	public $after_phase;
	public function swap( $name, $expected, $replacement ) {
		$state = json_decode( $replacement, true ); $phase = $state['dispatch']['phase'] ?? '';
		if ( in_array( $phase, $this->fail_phases, true ) ) return new WP_Error( 'schedule_safety_write_failed', 'Fixture SQL failure.' );
		if ( in_array( $phase, $this->throw_phases, true ) ) throw new RuntimeException( 'private fixture error' );
		$saved = parent::swap( $name, $expected, $replacement );
		if ( true === $saved && $this->after_phase ) { ($this->after_phase)( $phase ); }
		return $saved;
	}
}

trait Alynt_Drime_Backups_Uploader_Test_Guarded_Scan_Fixture {
	private $now = 1800000000;
	private $binding;
	private $backend;
	private $model;
	private $dispatcher;
	private $scan_enabled = true;
	private $callback_count = 0;
	private $policy_count = 0;
	private $after_policy;
	private $owner = '77777777-7777-4777-8777-777777777777';
	private function prepare_guarded_scan() {
		$this->binding = str_repeat( 'e', 64 ); $this->backend = new Alynt_Drime_Backups_Uploader_Test_Scan_CAS();
		$this->model = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Store( $this->backend, function () { return $this->now; } );
		$this->model->initialize_authority( $this->binding, 'every_30_minutes', $this->now );
		$this->dispatcher = $this->new_dispatcher();
	}
	private function new_dispatcher( $enabled = true ) {
		return new Alynt_Drime_Backups_Uploader_Cadence_Authority_Scan_Dispatcher( $this->model, function () {
			$this->policy_count++; if ( $this->after_policy ) { ($this->after_policy)( $this->policy_count ); }
			return array( 'binding' => $this->binding, 'scan_enabled' => $this->scan_enabled );
		}, $enabled, function () { return $this->now; }, function () { return $this->owner; } );
	}
	private function successful_callback() {
		return function () { $this->callback_count++; return array( 'errors' => array(), 'candidates' => array(), 'queued' => 0 ); };
	}
	private function assert_protected( $phase, $count ) {
		$s = $this->model->snapshot(); $this->assertSame( $phase, $s['dispatch']['phase'] );
		$this->assertSame( 1800000000, $s['authority']['next_due_at'] ); $this->assertSame( $count, $this->callback_count );
		$this->assertInstanceOf( WP_Error::class, $this->new_dispatcher()->run( $this->successful_callback() ) );
		$this->assertSame( $count, $this->callback_count );
	}
}
