<?php
/** Explicit test loading and in-memory providers, never actual settings or database reads. */
require_once __DIR__ . '/CadenceLifecycleFixture.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-cadence-authority-entry-point-policy.php';
require_once dirname( __DIR__, 2 ) . '/includes/trait-cadence-authority-context-facts.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-cadence-authority-context-observer.php';

class Alynt_Test_Observer_CAS extends Alynt_Drime_Backups_Uploader_Test_Lifecycle_CAS {
	public $reads = 0;
	public $throw_read = false;
	public function read( $name ) { $this->reads++; if ( $this->throw_read ) throw new RuntimeException( 'secret-bearing backend failure' ); return parent::read( $name ); }
}
trait Alynt_Test_Context_Observer_Fixture {
	private $backend;
	private $store;
	private $settings;
	private $connection;
	private $binding;
	private $evidence;
	private $now = 1800000000;
	private $calls = array( 'settings' => 0, 'connection' => 0, 'clock' => 0 );
	private $settings_hook;
	private $connection_hook;
	private $clock_hook;
	private function prepare_observer() {
		$this->settings = array( 'site_uuid' => '11111111-1111-4111-8111-111111111111', 'auto_scan_enabled' => true );
		$this->connection = array( 'connection_status' => 'paired', 'status_endpoint_enabled' => true, 'remote_actions_enabled' => true,
			'dashboard_origin' => 'https://dashboard.example.com', 'expected_client_origin' => 'https://client.example.com', 'dashboard_site_public_id' => '55555555-5555-4555-8555-555555555555',
			'polling_key_id' => 'pk_fixture', 'polling_credential_verifier' => str_repeat( 'e', 64 ), 'action_key_id' => 'ak_fixture', 'action_public_key' => str_repeat( 'A', 43 ), 'paired_at' => $this->now - 120, 'remote_actions_opted_in_at' => $this->now - 60 );
		$this->backend = new Alynt_Test_Observer_CAS();
		$this->store = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Lifecycle_Store( $this->backend, function () { return $this->now; } );
		$legacy = $this->createMock( Alynt_Drime_Backups_Uploader_Dashboard_Connection::class );
		$legacy->method( 'get' )->willReturnCallback( function () { return array_merge( $this->connection, array( 'schedule_rollback_preview_enabled' => false ) ); } );
		$legacy->method( 'site_uuid_for_remote_actions' )->willReturnCallback( function () { return $this->settings['site_uuid']; } );
		$this->evidence = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Evidence( $this->store, $legacy, function () { return $this->now; } );
		$this->binding = $this->evidence->enrollment_binding();
		$this->assertIsArray( $this->store->prepare_lifecycle( $this->binding, 'every_15_minutes', $this->now + 900 ) ); $this->backend->reads = 0;
	}
	private function observer( $enabled = true ) {
		return new Alynt_Drime_Backups_Uploader_Cadence_Authority_Context_Observer( $this->store,
			function () { $this->calls['settings']++; if ( $this->settings_hook ) return call_user_func( $this->settings_hook, $this->calls['settings'] ); return $this->settings; },
			function () { $this->calls['connection']++; if ( $this->connection_hook ) return call_user_func( $this->connection_hook, $this->calls['connection'] ); return $this->connection; },
			function () { $this->calls['clock']++; return $this->clock_hook ? call_user_func( $this->clock_hook, $this->calls['clock'] ) : $this->now; }, $enabled );
	}
	private function blocked( $code, $entry = 'scheduled_scan', $observer = null ) {
		$rows = $this->backend->rows; $swaps = $this->backend->swaps;
		$r = ( $observer ?: $this->observer() )->observe( $entry );
		$this->assertSame( array( 'disposition' => 'blocked', 'code' => $code, 'dispatch_supported' => false, 'execution_supported' => false ), $r );
		$this->assertSame( $rows, $this->backend->rows ); $this->assertSame( $swaps, $this->backend->swaps );
		return $r;
	}
	private function replace_observer_state( $state ) { $this->backend->rows[Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION]['option_value'] = json_encode( $state ); }
}
