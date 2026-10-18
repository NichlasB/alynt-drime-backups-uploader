<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/FixedCoreRequestFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class FixedCoreRequestConstructionTest extends TestCase {
	use Alynt_Test_Fixed_Core_Request_Fixture;
	public function test_constructor_is_private_final_zero_io_and_accepts_no_ports() {
		$this->assertSame( array(), $GLOBALS['alynt_core_request_test']['calls'] );
		$r = new ReflectionClass( $this->owner ); $this->assertTrue( $r->isFinal() ); $this->assertTrue( $r->getConstructor()->isPrivate() );
		$this->assertFalse( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Owner::create( array( 'authorize' => function () { return true; } ) ) );
		$this->assertFalse( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Request_Owner::create( true ) ); $this->assertSame( array(), $GLOBALS['alynt_core_request_test']['calls'] );
		foreach ( $r->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) $this->assertContains( $method->name, array( 'create', 'issue', 'consume', '__serialize', '__unserialize', '__wakeup' ) );
	}
	public function test_core_guards_run_but_no_request_handle_command_or_success_is_minted() {
		$roles = $GLOBALS['wp_roles']; $this->unavailable( $this->issue() ); $this->assertSame( $roles, $GLOBALS['wp_roles'] );
		$this->assertCount( 2, $this->calls( 'validate_cookie' ) ); $this->assertCount( 2, $this->calls( 'capability' ) );
		$this->assertSame( array( 'unit-nonce', 'alynt_drime_backups_closed_writer_issue_v1:1:upgrade_closed' ), $this->calls( 'verify_nonce' )[0][1] );
		$this->assertCount( 0, $this->calls( 'query' ) ); $this->assertSame( 7, $GLOBALS['alynt_core_request_test']['actor'] );
		$this->unavailable( $this->owner->consume( 'POST', $this->command_form() ) ); $this->assertCount( 2, $this->calls( 'verify_nonce' ) );
	}
}
