<?php
/** Synthetic data only: no UUID issuance, WordPress or storage proof. */
trait Alynt_Test_Closed_Registration_Fixture {
    private function registration_class() { return 'Alynt_Drime_Backups_Uploader_Closed_Registration_Contract'; }
    private function rid( $n ) { return sprintf( '00000000-0000-4000-8000-%012x', $n ); }
    private function rscope() { return array( 'site_uuid' => $this->rid( 1 ), 'enrollment_binding' => str_repeat( 'a', 64 ), 'authority_generation' => 10, 'ledger_id' => $this->rid( 2 ), 'origin_created_at' => 50 ); }
    private function rroot() {
        return array( 'schema_version' => 2, 'domain' => 'scan_registration_closed_v2', 'site_uuid' => $this->rid( 1 ), 'enrollment_binding' => str_repeat( 'a', 64 ), 'authority_generation' => 10, 'ledger_revision' => 20, 'participant_digest' => Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::digest(), 'closed' => true, 'origin' => array( 'kind' => 'owned_closed_fixture_v2', 'ledger_id' => $this->rid( 2 ), 'created_at' => 50 ), 'operations' => array() );
    }
    private function rproposal() {
        $command = array( 'contract' => 'scan_registration_request_v2', 'purpose' => 'register_closed', 'command_id' => $this->rid( 3 ), 'site_uuid' => $this->rid( 1 ), 'enrollment_binding' => str_repeat( 'a', 64 ), 'expected_generation' => 10, 'expected_ledger_revision' => 20, 'context_digest' => str_repeat( '0', 64 ), 'issued_at' => 100, 'expires_at' => 400, 'confirm' => 'register' );
        $proposal = array( 'command' => $command, 'actor_id' => 7, 'operation_id' => $this->rid( 4 ), 'effect_ids' => array( 'wpvivid_snapshot' => $this->rid( 5 ), 'outbox_snapshot' => $this->rid( 6 ), 'queue_addition' => $this->rid( 7 ) ), 'reservation_command_ids' => array( 'wpvivid_snapshot' => $this->rid( 8 ), 'outbox_snapshot' => $this->rid( 9 ), 'queue_addition' => $this->rid( 10 ) ), 'terminal_id' => $this->rid( 11 ), 'resolution_id' => $this->rid( 12 ), 'dependency_digest' => str_repeat( 'b', 64 ) );
        $proposal['command']['context_digest'] = $this->registration_class()::registration_context( $this->rscope(), $proposal );
        return $proposal;
    }
    private function registered() { return $this->registration_class()::register( json_encode( $this->rroot() ), $this->rscope(), $this->rproposal(), 110 ); }
    private function reserve_proposal( $registered ) {
        $root = json_decode( $registered, true ); $o = reset( $root['operations'] );
        $e = $o['command']; $e['purpose'] = 'reserve_wpvivid_intent'; $e['command_id'] = $o['reservation_command_ids']['wpvivid_snapshot']; $e['expected_ledger_revision'] = $root['ledger_revision']; $e['context_digest'] = str_repeat( '0', 64 ); $e['confirm'] = 'reserve'; $e['operation_id'] = $o['operation_id'];
        $p = array( 'command' => $e, 'actor_id' => 7, 'dependency_digest' => str_repeat( 'c', 64 ), 'before' => 'a:0:{}', 'replacement' => serialize( array( str_repeat( 'd', 64 ) => array( 'size' => 12, 'mtime' => 101 ) ) ) );
        $p['command']['context_digest'] = $this->registration_class()::reservation_context( $o['context_digest'], $p );
        return $p;
    }
    private function observe_command( $observed, $purpose = 'observe_registration' ) {
        return array( 'contract' => 'scan_registration_observation_v2', 'purpose' => $purpose, 'command_id' => $this->rid( 90 ), 'site_uuid' => $this->rid( 1 ), 'observed_command_id' => $observed, 'issued_at' => 500, 'expires_at' => 800, 'confirm' => 'observe' );
    }
}
