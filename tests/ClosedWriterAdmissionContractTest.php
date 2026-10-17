<?php
use Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Contract as AdmissionContract;
require_once __DIR__ . '/Support/ClosedWriterAdmissionFixture.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ClosedWriterAdmissionContractTest extends PHPUnit\Framework\TestCase {
	use Alynt_Test_Closed_Writer_Admission_Fixture;
	public function test_fixture_only_canonical_actions_bind_all_typed_fields_independent_of_form_order(): void {
		$form = $this->issue_form(); $typed = $this->typed_form( $form ); $action = AdmissionContract::action( $typed );
		$this->assertStringContainsString( 'closed_writer_fixture_command_v1:', $action );
		$this->assertSame( $typed, AdmissionContract::parse( array_reverse( $form, true ) )['envelope'] );
		foreach ( array( 'command_id' => '88888888-8888-4888-8888-888888888888', 'origin_digest' => str_repeat( 'b', 64 ), 'issued_at' => $typed['issued_at'] + 1, 'expected_generation' => $typed['expected_generation'] + 1, 'enrollment_binding' => str_repeat( 'c', 64 ) ) as $key => $value ) {
			$changed = $typed; $changed[$key] = $value; $this->assertNotSame( $action, AdmissionContract::action( $changed ) );
		}
	}
	/** @dataProvider malformed_values */
	public function test_rejects_malformed_form_without_private_reads( $key, $value ): void {
		$form = $this->issue_form(); $form[$key] = $value; $reads = $this->memory->reads;
		$this->unavailable( $this->admission->consume( 'POST', $form ) ); $this->assertSame( $reads, $this->memory->reads ); $this->assertSame( 0, $this->memory->writes );
	}
	public static function malformed_values(): array { return array(
		'array' => array( 'nonce', array() ), 'object' => array( 'nonce', new stdClass() ), 'int' => array( 'expected_generation', 1 ),
		'leading_zero' => array( 'issued_at', '01800000000' ), 'negative' => array( 'expected_generation', '-1' ), 'negative_zero' => array( 'expected_generation', '-0' ),
		'overflow' => array( 'expected_generation', (string) PHP_INT_MAX . '0' ), 'float' => array( 'issued_at', '1800000000.0' ), 'utf8' => array( 'nonce', "\xff" ),
		'oversize' => array( 'nonce', str_repeat( 'x', 2049 ) ), 'extra' => array( 'target', 'settings' ), 'upper_uuid' => array( 'site_uuid', 'AAAAAAAA-AAAA-4AAA-8AAA-AAAAAAAAAAAA' ),
		'wrong_contract' => array( 'contract', 'fixture_request_v1' ), 'real_domain' => array( 'source', 'closed_writer_admission_v1' ), 'long_window' => array( 'expires_at', '1800000301' ),
		'finish' => array( 'purpose', 'finish' ), 'grant' => array( 'purpose', 'grant' ), 'enable' => array( 'purpose', 'enable' ), 'activate' => array( 'purpose', 'activate' ),
		'dispatch' => array( 'purpose', 'dispatch' ), 'rollback' => array( 'purpose', 'rollback' ), 'rebind' => array( 'purpose', 'rebind' ), 'wrong_confirm' => array( 'confirm', 'grant' ),
	); }
	public function test_unslashes_once_and_bounds_combined_raw_and_unslashed_encoding(): void {
		$form = $this->issue_form(); $form['nonce'] = "a\\\\b"; $parsed = AdmissionContract::parse( $form ); $this->assertSame( 'a\\b', $parsed['nonce'] );
		$this->assertFalse( AdmissionContract::strings( array( 'a' => str_repeat( 'x', 2048 ), 'b' => str_repeat( 'x', 2048 ) ) ) );
		$this->assertFalse( AdmissionContract::strings( array( 'a' => str_repeat( '"', 2048 ) ) ) );
		unset( $form['confirm'] ); $this->assertFalse( AdmissionContract::parse( $form ) );
	}
	public function test_default_off_and_disallowed_issuance_are_zero_io(): void {
		$off = $this->new_admission( false ); $this->unavailable( $off->issue( 'POST', array() ) ); $this->unavailable( $off->consume( 'POST', array() ) );
		$this->assertSame( 0, $this->memory->reads ); $this->assertSame( 0, $this->memory->auth_calls ); $this->assertSame( 0, $this->memory->uuid_calls );
		foreach ( array( array( 'purpose' => 'grant', 'confirm' => 'grant', 'nonce' => 'x' ), array( 'purpose' => 'upgrade_closed', 'confirm' => 'disable', 'nonce' => 'x' ) ) as $form ) $this->unavailable( $this->admission->issue( 'POST', $form ) );
		$this->assertSame( 0, $this->memory->reads ); $this->unavailable( $this->admission->issue( 'GET', array() ) );
	}
	public function test_nonce_does_not_survive_session_change_and_cross_site_is_denied(): void {
		$form = $this->issue_form(); $reads = $this->memory->reads; $this->memory->session = 'another-session'; $this->unavailable( $this->admission->consume( 'POST', $form ) );
		$this->memory->session = 'fixture-session'; $form['site_uuid'] = '88888888-8888-4888-8888-888888888888'; $form = $this->resign( $form );
		$this->unavailable( $this->admission->consume( 'POST', $form ) ); $this->assertSame( $reads, $this->memory->reads );
	}
	public function test_zero_counter_is_canonical_but_cannot_match_existing_closed_source_epoch(): void {
		$form = $this->issue_form(); $form['expected_generation'] = '0'; $form = $this->resign( $form );
		$this->assertSame( 0, AdmissionContract::parse( $form )['envelope']['expected_generation'] ); $row = $this->memory->row;
		$this->unavailable( $this->admission->consume( 'POST', $form ) ); $this->assertSame( $row, $this->memory->row ); $this->assertSame( 0, $this->memory->writes );
		$form['issued_at'] = '0'; $this->assertFalse( AdmissionContract::parse( $form ) );
	}
}
