<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/ConsentRequestFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ConsentRequestGuardTest extends TestCase {
	use Alynt_Test_Consent_Request_Fixture;
	/** @dataProvider changed_write_facts */
	public function test_each_attempt_rechecks_original_intent_after_contention( $field, $value ) {
		$b = $this->body(); $this->ports->conflicts = 1; $this->ports->before_write = function ( $p, $attempt ) use ( $field, $value ) { if ( 1 === $attempt ) $p->$field = $value; };
		$this->error( 'unresolved', $this->adapter->consume( 'POST', $b ) ); $this->assertSame( 0, $this->ports->writes ); $this->assertSame( 2, $this->ports->calls['attempt'] ); $this->assertFalse( $this->ports->consent );
	}
	public function changed_write_facts() { return array(
		array( 'capable', false ), array( 'logged_in', false ), array( 'session', 'different-session' ),
		array( 'now', 1800000300 ), array( 'generation', 2 ), array( 'binding', str_repeat( 'c', 64 ) ), array( 'eligible', false )
	); }
	public function test_guard_checks_again_after_context_read_and_expires_on_return() {
		$b = $this->body(); $this->ports->on_context = function ( $p ) { $p->capable = false; }; $this->error( 'unresolved', $this->adapter->consume( 'POST', $b ) ); $this->assertSame( 0, $this->ports->writes );
		$this->ports->capable = true; $guard = $this->ports->retained_guard; $this->ports->calls = array(); $this->assertFalse( $guard() ); $this->assertSame( array(), $this->ports->calls );
	}
	public function test_three_contentions_do_not_adopt_another_epoch_or_extend_deadline() {
		$b = $this->body(); $this->ports->conflicts = 10; $this->error( 'unresolved', $this->adapter->consume( 'POST', $b ) );
		$this->assertSame( 3, $this->ports->calls['attempt'] ); $this->assertSame( 1, $this->ports->generation ); $this->assertSame( $b['expires_at'], (string) $this->ports->last_command['expires_at'] ); $this->assertSame( 0, $this->ports->writes );
	}
	public function test_successful_contention_retry_commits_once_and_retained_guard_stays_dead() {
		$b = $this->body(); $this->ports->conflicts = 2; $this->outcome( 'committed', $this->adapter->consume( 'POST', $b ) ); $this->assertSame( 3, $this->ports->calls['attempt'] ); $this->assertSame( 1, $this->ports->writes );
		$this->assertFalse( ( $this->ports->retained_guard )() );
	}
	public function test_local_revoke_issuance_and_consumption_do_not_require_grant_eligibility() {
		$this->adapter->consume( 'POST', $this->body() ); $this->ports->eligible = false; $this->ports->now += 90000;
		$this->outcome( 'committed', $this->adapter->consume( 'POST', $this->body( $this->issued( 'revoke' ) ) ) ); $this->assertFalse( $this->ports->consent ); $this->assertSame( 2, $this->ports->writes );
	}
	public function test_rebind_checks_reviewed_replacement_and_never_reuses_old_consent() {
		$this->adapter->consume( 'POST', $this->body() ); $b = $this->body( $this->issued( 'rebind' ) ); $this->ports->replacement = str_repeat( 'c', 64 );
		$this->error( 'unresolved', $this->adapter->consume( 'POST', $b ) ); $this->assertTrue( $this->ports->consent ); $this->assertSame( 1, $this->ports->writes );
		$this->ports->replacement = str_repeat( 'b', 64 ); $this->outcome( 'committed', $this->adapter->consume( 'POST', $b ) ); $this->assertFalse( $this->ports->consent );
	}
	public function test_sink_cannot_claim_commit_without_guard_or_after_fourth_attempt() {
		$b = $this->body(); $p = $this->ports->ports(); $p['consume'] = function () { return 'committed'; };
		$this->error( 'unresolved', $this->adapter( true, $p )->consume( 'POST', $b ) );
		$p['consume'] = function ( $c, $guard ) { $this->assertTrue( $guard() ); $this->assertTrue( $guard() ); $this->assertTrue( $guard() ); $this->assertFalse( $guard() ); return 'committed'; };
		$this->error( 'unresolved', $this->adapter( true, $p )->consume( 'POST', $b ) ); $this->assertSame( 0, $this->ports->writes );
	}
}
