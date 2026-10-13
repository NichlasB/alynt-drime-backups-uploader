<?php
/** Synthetic enrollment and in-memory native model-change fixture only. */
require_once __DIR__ . '/ScheduleSafetyFixture.php';

trait Alynt_Drime_Backups_Uploader_Test_Authority_Evidence_Fixture {
	private $now = 1800000000;
	private $backend;
	private $model;
	private $producer;
	private $connection;
	private $pairing;
	private $binding;
	private $change;
	private $site_uuid = '11111111-1111-4111-8111-111111111111';
	private $preview_id = '44444444-4444-4444-8444-444444444444';

	private function prepare_authority_evidence() {
		$this->backend = new Alynt_Drime_Backups_Uploader_Test_Safety_Memory_CAS();
		$this->model = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Store( $this->backend, function () { return $this->now; } );
		$this->pairing = array_merge( Alynt_Drime_Backups_Uploader_Dashboard_Connection::defaults(), array(
			'connection_status' => 'paired',
			'dashboard_origin' => 'https://dashboard.example.com',
			'expected_client_origin' => 'https://client.example.com',
			'dashboard_site_public_id' => '55555555-5555-4555-8555-555555555555',
			'polling_key_id' => 'pk_fixture',
			'polling_credential_verifier' => str_repeat( 'e', 64 ),
			'action_key_id' => 'ak_fixture',
			'action_public_key' => str_repeat( 'A', 43 ),
			'paired_at' => $this->now - 120,
			'remote_actions_opted_in_at' => $this->now - 60,
			'status_endpoint_enabled' => true,
			'remote_actions_enabled' => true,
			'schedule_rollback_preview_enabled' => true,
		) );
		$this->connection = $this->createMock( Alynt_Drime_Backups_Uploader_Dashboard_Connection::class );
		$this->connection->method( 'get' )->willReturnCallback( function () { return $this->pairing; } );
		$this->connection->method( 'site_uuid_for_remote_actions' )->willReturnCallback( function () { return $this->site_uuid; } );
		$this->producer = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Evidence( $this->model, $this->connection, function () { return $this->now; } );
		$this->binding = $this->producer->enrollment_binding();
		$this->model->initialize_authority( $this->binding, 'every_30_minutes', $this->now );
		$s = $this->model->snapshot();
		$candidate = $s;
		$candidate['schedule_revision']++;
		$candidate['authority']['cadence'] = 'every_15_minutes';
		$candidate['authority']['next_due_at'] = $this->now + 900;
		$this->change = Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::evidence( $this->now );
		$this->change['before_fingerprint'] = $this->model->authority_fingerprint( $s );
		$this->change['intended_fingerprint'] = $this->model->authority_fingerprint( $candidate );
		$this->model->reserve_change( $this->binding, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE, $this->change );
		$this->assertIsArray( $this->model->commit_change( $this->binding, Alynt_Drime_Backups_Uploader_Test_Safety_Fixture::SOURCE, $this->change['action_id'], $this->change['request_hash'], $this->now + 900 ) );
	}
	private function inspection() {
		return $this->producer->inspect( $this->change['action_id'], $this->preview_id, $this->now + 300 );
	}
	private function replace_state( array $state ) {
		$this->backend->rows[ Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION ]['option_value'] = json_encode( $state );
	}
	private function assert_inspection_error( $code ) {
		$rows = $this->backend->rows;
		$result = $this->inspection();
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'authority_evidence_' . $code, $result->get_error_code() );
		$this->assertSame( $rows, $this->backend->rows );
	}
}
