<?php
/** Fixture-only loading and CAS faults; never a WordPress authorization/storage provider. */
require_once __DIR__ . '/CadenceLifecycleFixture.php';
foreach ( array( 'validation', 'commands', 'migration', 'api-boundary' ) as $part ) require_once dirname( __DIR__, 2 ) . '/includes/trait-consent-ledger-' . $part . '.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-cadence-consent-ledger.php';

class Alynt_Test_Consent_Ledger_CAS extends Alynt_Drime_Backups_Uploader_Test_Lifecycle_CAS {
	public $reads = 0;
	public $creates = 0;
	public $ack_fault;
	public $throw_read = false;
	public function read( $name ) { $this->reads++; if ( $this->throw_read ) throw new RuntimeException( 'secret read detail' ); return parent::read( $name ); }
	public function create( $value ) { $this->creates++; return parent::create( $value ); }
	public function swap( $name, $expected, $replacement ) {
		$result = parent::swap( $name, $expected, $replacement );
		if ( true === $result && $this->ack_fault ) { $fault = $this->ack_fault; $this->ack_fault = null; if ( 'throw' === $fault ) throw new RuntimeException( 'secret committed detail' ); if ( 'false' === $fault ) return false; return new WP_Error( 'private_commit', 'secret committed detail' ); }
		return $result;
	}
}

trait Alynt_Test_Consent_Ledger_Fixture {
	private $backend;
	private $ledger;
	private $lifecycle;
	private $binding;
	private $site = '11111111-1111-4111-8111-111111111111';
	private $now = 1800000000;
	protected function setUp(): void {
		parent::setUp(); \Brain\Monkey\setUp(); \Brain\Monkey\Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		$this->binding = str_repeat( 'a', 64 ); $this->backend = new Alynt_Test_Consent_Ledger_CAS();
		$this->lifecycle = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Lifecycle_Store( $this->backend, function () { return $this->now; } );
		$this->ledger = $this->new_ledger(); $this->assertIsArray( $this->lifecycle->prepare_lifecycle( $this->binding, 'every_15_minutes', $this->now + 900 ) );
	}
	protected function tearDown(): void { \Brain\Monkey\tearDown(); parent::tearDown(); }
	private function new_ledger( $enabled = true, $site = null ) { return new Alynt_Drime_Backups_Uploader_Cadence_Consent_Ledger( $this->backend, null === $site ? $this->site : $site, function () { return $this->now; }, $enabled ); }
	private function migrate() { $result = $this->ledger->prepare_ledger( $this->binding, $this->lifecycle->snapshot()['lifecycle']['permission_generation'] ); $this->assertIsArray( $result ); return $result; }
	private function intent( $purpose = 'grant', $number = 1, $generation = null, $binding = null ) {
		return array( 'command_id' => sprintf( '22222222-2222-4222-8222-%012d', $number ), 'purpose' => $purpose, 'site_uuid' => $this->site,
			'enrollment_binding' => null === $binding ? $this->binding : $binding, 'expected_generation' => null === $generation ? $this->ledger->snapshot()['lifecycle']['permission_generation'] : $generation,
			'issued_at' => 'grant' === $purpose ? $this->now : 0, 'expires_at' => 'grant' === $purpose ? $this->now + 300 : 0, 'replacement_binding' => 'rebind' === $purpose ? str_repeat( 'b', 64 ) : null );
	}
	private function result( $outcome, $result ) { $this->assertSame( array( 'outcome' => $outcome, 'dispatch_supported' => false, 'execution_supported' => false ), $result ); }
	private function error( $suffix, $result ) { $this->assertInstanceOf( WP_Error::class, $result ); $this->assertSame( 'schedule_safety_ledger_' . $suffix, $result->get_error_code() ); $this->assertSame( 'Schedule safety operation is unavailable.', $result->get_error_message() ); }
	private function replace( array $state ) { $this->backend->rows[Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION]['option_value'] = json_encode( $state ); }
	private function pending_state( array $state, $dispatch = false ) {
		if ( $dispatch ) $state['dispatch'] = array( 'owner' => '44444444-4444-4444-8444-444444444444', 'request_hash' => str_repeat( 'c', 64 ), 'enrollment_binding' => $this->binding, 'schedule_revision' => 0, 'due_at' => $this->now - 900, 'claimed_at' => $this->now - 900, 'lease_deadline' => $this->now - 600, 'phase' => 'recovery_required', 'completed_at' => 0 );
		else {
			$memory = new Alynt_Drime_Backups_Uploader_Test_Safety_Memory_CAS(); $legacy = new Alynt_Drime_Backups_Uploader_Schedule_Safety_Store( $memory, function () { return $this->now; } );
			$legacy->initialize( $this->binding ); $legacy->claim( $this->binding, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $this->now ) );
			$state['claims'] = $legacy->snapshot()['claims'];
		}
		$state['lifecycle']['phase'] = 'draining'; return $state;
	}
}
