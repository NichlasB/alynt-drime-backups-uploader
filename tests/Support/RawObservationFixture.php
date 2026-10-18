<?php
/** Synthetic row/session facts only; never native storage or authorization. */
require_once __DIR__ . '/RealClosedWriterFixture.php';

trait Alynt_Test_Raw_Observation_Fixture {
	use Alynt_Test_Real_Closed_Writer_Fixture { setUp as protected setUpReal; }
	private $scope;
	private $rows;
	protected function setUp(): void {
		$this->setUpReal();
		foreach ( array( 'closed-writer-json-contract', 'closed-writer-observation-contract', 'closed-writer-session-contract' ) as $part ) {
			$file = dirname( __DIR__, 2 ) . '/includes/class-' . $part . '.php'; $this->assertFileExists( $file ); require_once $file;
		}
		$this->scope = array( 'blog_id' => 1, 'prefix' => 'wp_', 'database' => 'fixture_only', 'site_uuid' => $this->site, 'session_id' => str_repeat( 'd', 64 ) );
		$this->rows = array( $this->row( 'safety', json_encode( $this->real_upgraded() ), '1' ), $this->row( 'settings', $this->settings, '2' ) );
	}
	private function row( $kind, $raw, $id ) {
		return array( 'option_id' => $id, 'option_name' => 'alynt_drime_backups_' . ( 'safety' === $kind ? 'schedule_safety_state' : 'settings' ), 'autoload' => 'no', 'octets' => (string) strlen( $raw ), 'value' => $raw );
	}
	private function observation( $scope = null, $rows = null, $again = null ) {
		$rows = $rows ?? $this->rows; $again = $again ?? $rows;
		return Alynt_Drime_Backups_Uploader_Closed_Writer_Observation_Contract::simulate( $scope ?? $this->scope, $rows[0], $rows[1], $again[0], $again[1] );
	}
	private function session() {
		return Alynt_Drime_Backups_Uploader_Closed_Writer_Session_Contract::simulate( $this->scope['session_id'], $this->real_id( 90 ), 100, 400, 100, 1000 );
	}
	private function facts( $transaction = false ) { return array( 'session_id' => $this->scope['session_id'], 'autocommit' => true, 'in_transaction' => $transaction ); }
	private function step( $s, $event, $transaction = true, $now = 100, $tick = 1000 ) { return $s->step( $event, $this->facts( $transaction ), $now, $tick ); }
	private function locked( $s ) {
		foreach ( array( 'open_idle', 'begin_sent', 'begin_confirmed', 'safety_locked', 'target_locked' ) as $event ) $this->assertTrue( $this->step( $s, $event, ! in_array( $event, array( 'open_idle', 'begin_sent' ), true ) ) );
	}
}
