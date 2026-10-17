<?php
use Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Model as AdmissionModel;
use Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Projection as AdmissionProjection;
require_once __DIR__ . '/Support/ClosedWriterAdmissionFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ClosedWriterAdmissionModelTest extends PHPUnit\Framework\TestCase {
	use Alynt_Test_Closed_Writer_Admission_Fixture;
	public function test_upgrade_is_lossless_finite_and_does_not_change_the_target_or_old_validator(): void {
		$target = $this->memory->row['target']; $form = $this->upgraded_admission(); $s = $this->assert_admission_closed();
		$this->assertSame( $this->six, AdmissionProjection::original( $s ) ); $this->assertSame( $target, $this->memory->row['target'] );
		$this->assertSame( $this->six['generation'] + 1, $s['generation'] ); $this->assertSame( $this->six['schedule_revision'] + 1, $s['schedule_revision'] );
		$this->assertSame( $form['command_id'], $s['writer']['upgrade']['command']['command_id'] );
		$this->assertSame( 'closed_writer_admission_fixture_v1', $s['writer']['upgrade']['domain'] );
		$reader = new Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Snapshot( json_encode( $s ) );
		$old = new Alynt_Drime_Backups_Uploader_Closed_Writer_Barrier_Store( $reader, $this->site, function () { return $this->now; }, true );
		$this->assertInstanceOf( WP_Error::class, $old->snapshot() ); $this->assertFalse( $reader->create( 'anything' ) ); $this->assertFalse( $reader->swap( 'cron', '', '' ) ); $this->assertNull( $reader->read( 'cron' ) );
	}
	public function test_completed_schema_six_receipts_are_frozen_without_source_promotion(): void {
		$a = $this->begin_command(); $this->barrier->begin( $a, $this->permitted() ); $this->barrier->finish( $this->terminal_command( $a ), $this->permitted() );
		$this->six = $this->origin(); $this->memory->row['state'] = $this->six; $this->upgraded_admission(); $s = $this->assert_admission_closed();
		$this->assertSame( $this->six['barrier'], $s['legacy_writer'] ); $this->assertSame( 'domain_conflict', AdmissionModel::lookup( $s, $a['command_id'] ) );
	}
	/** @dataProvider bad_successor */
	public function test_successor_rejects_tamper_and_reserved_real_domains( $path, $value ): void {
		$this->upgraded_admission(); $s = $this->memory->row['state']; $ref = &$s; foreach ( explode( '.', $path ) as $key ) $ref = &$ref[$key]; $ref = $value; unset( $ref );
		$this->assertFalse( AdmissionModel::valid( $s, $this->site ) );
	}
	public static function bad_successor(): array { return array(
		array( 'schema_version', 6 ), array( 'generation', '6' ), array( 'schedule_revision', 0 ), array( 'enrollment_binding', str_repeat( 'b', 64 ) ),
		array( 'authority.enabled', true ), array( 'lifecycle.consent', true ), array( 'lifecycle.phase', 'active' ), array( 'lifecycle.permission_generation', PHP_INT_MAX ),
		array( 'lifecycle.changed_at', 1 ), array( 'lifecycle.extra', false ), array( 'writer.source', 'closed_writer_admission_v1' ), array( 'writer.authenticated_grant', true ),
		array( 'writer.authenticated_receipts', array( true ) ), array( 'writer.internal_receipts', array( true ) ), array( 'writer.origin.source_digest', str_repeat( 'b', 64 ) ),
		array( 'writer.origin.extra', false ), array( 'writer.origin.generation', 0 ), array( 'writer.upgrade.domain', 'authenticated_closed_writer_v1' ),
		array( 'writer.upgrade.domain', 'internal_closed_writer_v1' ), array( 'writer.upgrade.result_generation', 1 ), array( 'writer.upgrade.command.origin_digest', str_repeat( 'c', 64 ) ),
		array( 'legacy_writer.authenticated_receipts', array( true ) ), array( 'legacy_writer.checkpoint.schema_version', 7 ), array( 'writer.pending', true ), array( 'writer.fixture_receipts', false ),
	); }
	public function test_pending_schema_six_and_earlier_sources_cannot_upgrade(): void {
		$form = $this->issue_form(); $a = $this->begin_command(); $this->barrier->begin( $a, $this->permitted() ); $this->memory->row['state'] = $this->origin();
		$row = $this->memory->row; $this->unavailable( $this->admission->consume( 'POST', $form ) ); $this->assertSame( $row, $this->memory->row );
		foreach ( array( $this->checkpoint, $this->schema_three(), array(), array( 'schema_version' => 99 ) ) as $s ) $this->assertFalse( AdmissionModel::supported( $s, $this->site ) );
	}
	public function test_consistency_hash_is_order_independent_but_retains_lists_and_types(): void {
		$s = $this->six; $ordered = array_reverse( $s, true ); $ordered['barrier'] = array_reverse( $s['barrier'], true );
		$this->assertSame( AdmissionProjection::hash( $s ), AdmissionProjection::hash( $ordered ) );
		$this->assertNotSame( AdmissionProjection::hash( array( 'list' => array( 1, 2 ) ) ), AdmissionProjection::hash( array( 'list' => array( 2, 1 ) ) ) );
		$this->assertNotSame( AdmissionProjection::hash( array( 'a' => true ) ), AdmissionProjection::hash( array( 'a' => 1 ) ) );
	}
	public function test_object_and_oversized_or_deep_sources_reject_before_encoding(): void {
		$object = new class implements JsonSerializable { public function jsonSerialize(): array { throw new RuntimeException( 'must not execute' ); } };
		$s = $this->six; $s['claims'] = array( $object ); $this->assertFalse( AdmissionModel::supported( $s, $this->site ) );
		$s = $this->six; $s['extra'] = str_repeat( 'x', 131073 ); $this->assertFalse( AdmissionModel::supported( $s, $this->site ) );
		$deep = array(); for ( $i = 0; $i < 17; ++$i ) $deep = array( $deep ); $this->assertFalse( AdmissionProjection::plain( $deep ) );
	}
	public function test_context_preserves_unrelated_fields_and_binds_exact_bytes_and_epoch(): void {
		$this->upgraded_admission(); $s = $this->memory->row['state']; $raw = $this->memory->row['target']; $ctx = AdmissionProjection::context( $s, $raw, $this->site );
		$t = json_decode( $raw, true ); $t['auto_scan_enabled'] = false; $this->assertSame( $t, json_decode( $ctx['after'], true ) );
		$this->assertNotSame( $ctx['context_digest'], AdmissionProjection::context( $s, $raw . ' ', $this->site )['context_digest'] );
		$this->assertFalse( AdmissionProjection::context( $s, serialize( $t ), $this->site ) ); $this->assertFalse( AdmissionProjection::context( $s, str_repeat( 'x', 1048577 ), $this->site ) );
		foreach ( array( '{}', 'null', '{"auto_scan_enabled":1}', '{"auto_scan_enabled":false,"site_uuid":"wrong"}' ) as $bad ) $this->assertFalse( AdmissionProjection::context( $s, $bad, $this->site ) );
	}
}
