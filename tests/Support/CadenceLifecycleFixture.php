<?php
/** In-memory exact-byte CAS and synthetic state only; no WordPress database. */
require_once __DIR__ . '/ScheduleSafetyFixture.php';
require_once dirname( __DIR__, 2 ) . '/includes/trait-cadence-authority-lifecycle-preparation.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-cadence-authority-lifecycle-store.php';

class Alynt_Drime_Backups_Uploader_Test_Lifecycle_CAS extends Alynt_Drime_Backups_Uploader_Test_Safety_Memory_CAS {
	public $fail_create = false;
	public $fail_read = false;
	public $before_create;
	public function create( $value ) {
		if ( $this->fail_create ) return new WP_Error( 'fixture_create_failed', 'Fixture create failure.' );
		if ( $this->before_create ) { $callback = $this->before_create; $this->before_create = null; $callback(); }
		return parent::create( $value );
	}
	public function read( $name ) {
		return $this->fail_read ? new WP_Error( 'fixture_read_failed', 'Fixture read failure.' ) : parent::read( $name );
	}
}

trait Alynt_Drime_Backups_Uploader_Test_Cadence_Lifecycle_Fixture {
	private $backend;
	private $model;
	private $now = 1800000000;
	private $binding;
	private function prepare_fixture() {
		$this->binding = str_repeat( 'a', 64 ); $this->backend = new Alynt_Drime_Backups_Uploader_Test_Lifecycle_CAS(); $this->model = $this->new_lifecycle();
	}
	private function new_lifecycle() { return new Alynt_Drime_Backups_Uploader_Cadence_Authority_Lifecycle_Store( $this->backend, function () { return $this->now; } ); }
	private function prepare() { return $this->model->prepare_lifecycle( $this->binding, 'every_15_minutes', 1800000900 ); }
	private function replace_state( array $state ) { $this->backend->rows[Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION]['option_value'] = json_encode( $state ); }
	private function assert_error( $code, $result ) { $this->assertInstanceOf( WP_Error::class, $result ); $this->assertSame( $code, $result->get_error_code() ); }
	private function legacy_claim() {
		$legacy = new Alynt_Drime_Backups_Uploader_Schedule_Safety_Store( $this->backend, function () { return $this->now; } );
		$legacy->initialize( $this->binding ); $e = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $this->now );
		$legacy->claim( $this->binding, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE, $e );
		return array( $legacy, $e );
	}
}
