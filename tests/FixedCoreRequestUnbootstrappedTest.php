<?php
use PHPUnit\Framework\TestCase;
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class FixedCoreRequestUnbootstrappedTest extends TestCase {
	public function test_zero_io_construction_and_missing_core_denial_do_not_autoload_or_bootstrap() {
		$f = dirname( __DIR__ ) . '/includes/class-real-closed-writer-request-owner.php'; $this->assertFileExists( $f ); require_once $f; $calls = array();
		$loader = function ( $class ) use ( &$calls ) { $calls[] = $class; }; spl_autoload_register( $loader );
		try {
			$owner = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Owner::create(); $_SERVER['REQUEST_METHOD'] = 'POST';
			$r = $owner->issue( 'POST', array( 'purpose' => 'upgrade_closed', 'confirm' => 'close', 'nonce' => 'unit-nonce' ) );
			$this->assertSame( 'unavailable', $r['outcome'] ); $this->assertFalse( $r['storage_supported'] ); $this->assertSame( array(), $calls ); $this->assertFalse( class_exists( 'wpdb', false ) );
		} finally { spl_autoload_unregister( $loader ); }
	}
}
