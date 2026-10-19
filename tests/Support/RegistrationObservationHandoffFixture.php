<?php
require_once __DIR__ . '/RegistrationAdmissionFixture.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-registration-observation-handoff.php';
/** Existing complete in-memory schema chains only; no core/auth/storage shims. */
trait Alynt_Test_Registration_Observation_Handoff_Fixture {
    use Alynt_Test_Registration_Admission_Fixture;
    private function handoffFixture( $phase = 'registered', $safety = null ) {
        list( $frame, $proposal, $context, $scope ) = $this->admissionFixture();
        if ( null !== $safety ) {
            $scope['enrollment_binding'] = $safety['enrollment_binding']; $scope['authority_generation'] = $safety['lifecycle']['permission_generation'];
            $root = json_decode( $frame['rows']['ledger']['value'], true ); $root['enrollment_binding'] = $scope['enrollment_binding']; $root['authority_generation'] = $scope['authority_generation'];
            $frame = $this->handoffRow( $frame, 'safety', json_encode( $safety ) ); $frame = $this->handoffRow( $frame, 'ledger', json_encode( $root ) );
            $proposal['command']['enrollment_binding'] = $scope['enrollment_binding']; $proposal['command']['expected_generation'] = $scope['authority_generation'];
            $proposal['dependency_digest'] = hash( 'sha256', 'registration_full_frame_v2:' . json_encode( $frame ) );
            $proposal['command']['context_digest'] = $this->registration_class()::registration_context( $scope, $proposal );
        }
        $origin = array( 'domain' => 'registration_observation_origin_v1', 'installation' => array_slice( $context, 0, 3, true ), 'scope' => $scope, 'manifest_digest' => Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::digest(), 'mutation' => $proposal['command'], 'actor_id' => $proposal['actor_id'], 'operation_id' => $proposal['operation_id'], 'context_digest' => $proposal['command']['context_digest'] );
        $intent = array( 'purpose' => 'observe_registration', 'confirm' => 'observe', 'observed_command_id' => $proposal['command']['command_id'], 'nonce' => 'synthetic-small' );
        $query = $this->observe_command( $proposal['command']['command_id'] ); $query['site_uuid'] = $scope['site_uuid'];
        $form = array_map( 'strval', $query ) + array( 'nonce' => 'synthetic-full' );
        $ids = array_merge( array( $scope['site_uuid'], $scope['ledger_id'], $proposal['command']['command_id'], $proposal['operation_id'], $proposal['terminal_id'], $proposal['resolution_id'] ), array_values( $proposal['effect_ids'] ), array_values( $proposal['reservation_command_ids'] ), array_keys( Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::identities( json_decode( $frame['rows']['safety']['value'], true ) ) ) );
        if ( 'empty' !== $phase ) {
            $raw = $this->registration_class()::register( $frame['rows']['ledger']['value'], $scope, $proposal, 110 ); $this->assertIsString( $raw );
            if ( 'reserved' === $phase ) { $raw = $this->registration_class()::reserve( $raw, $scope, $this->reserve_proposal( $raw ), 120 ); $this->assertIsString( $raw ); }
            $frame = $this->handoffRow( $frame, 'ledger', $raw );
        }
        return array( 'frame' => $frame, 'data' => $origin, 'intent' => $intent, 'form' => $form, 'clock' => $this->admissionClock( 200, 510 ), 'ids' => $ids );
    }
    private function handoffRow( $frame, $role, $raw ) { $frame['rows'][$role]['value'] = $raw; $frame['rows'][$role]['octets'] = (string) strlen( $raw ); return $frame; }
    private function candidateResult( $f ) { return Alynt_Drime_Backups_Uploader_Registration_Observation_Candidate::observe( $f['frame'], $f['data'], $f['intent'], $f['form'], $f['clock']['utc'] ); }
    private function handoffOwner() { return Alynt_Drime_Backups_Uploader_Registration_Observation_Handoff::create(); }
    private function handoffStep( $o, $method, $f ) { return $this->admissionInvoke( $o, $method, $f['data'], $f['intent'], $f['form'], $f['clock'], $f['ids'] ); }
    private function handoffFinish( $o, $f ) { return $this->admissionInvoke( $o, 'finish', $f['frame'], $f['intent'], $f['form'], $f['clock'] ); }
    private function assertHandoffCleared( $o ) { foreach ( array( 'staged', 'inflight' ) as $key ) $this->assertNull( $this->admissionProperty( $o, $key ) ); }
    private function assertCandidateOnly( $r, $classification ) {
        $this->assertIsArray( $r ); $this->assertSame( $classification, $r['classification'] ); $this->assertTrue( $r['candidate_only'] );
        foreach ( array( 'barrier_verified', 'closure_proven', 'retry_supported', 'authenticated', 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $flag ) $this->assertFalse( $r[$flag], $flag );
        $this->assertCount( 9, $r );
    }
}
