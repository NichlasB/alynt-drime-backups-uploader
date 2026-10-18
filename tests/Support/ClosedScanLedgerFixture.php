<?php
/** Synthetic data only. Nothing here proves an authenticated event. */
trait Alynt_Test_Closed_Scan_Ledger_Fixture {
	private function uuid( $n ) { return sprintf( '00000000-0000-4000-8000-%012x', $n ); }
	private function ledger( $count = 0, $terminal = false ) {
		$s = array( 'schema_version' => 1, 'domain' => 'scan_effect_storage_closed_v1', 'site_uuid' => $this->uuid( 9999 ), 'enrollment_binding' => str_repeat( 'a', 64 ), 'authority_generation' => 10, 'ledger_revision' => 1000, 'participant_digest' => Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::digest(), 'closed' => true, 'operations' => array() );
		for ( $i = 0; $i < $count; ++$i ) {
			$base = 1 + $i * 10;
			$o = array( 'operation_id' => $this->uuid( $base ), 'command_id' => $this->uuid( $base + 1 ), 'context_digest' => str_repeat( 'b', 64 ), 'entry_point' => 'scheduled', 'generation' => 10, 'participant_digest' => $s['participant_digest'], 'issued_at' => 100, 'expires_at' => 400, 'phase' => $terminal ? 'terminal' : 'registered', 'effects' => array(), 'terminal_id' => $this->uuid( $base + 2 ), 'resolution_id' => $this->uuid( $base + 3 ), 'terminal' => null );
			$j = 0; $hashes = array();
			foreach ( array( 'wpvivid_snapshot', 'outbox_snapshot', 'queue_addition' ) as $purpose ) {
				$e = array( 'effect_id' => $this->uuid( $base + 4 + $j ), 'purpose' => $purpose, 'state' => 'reserved', 'dependency_digest' => null, 'before_hash' => null, 'after_hash' => null, 'result_revision' => null, 'receipt' => null );
				if ( $terminal ) {
					$e['state'] = 'committed'; $e['dependency_digest'] = str_repeat( 'c', 64 ); $e['before_hash'] = str_repeat( 'd', 64 ); $e['after_hash'] = str_repeat( 'e', 64 ); $e['result_revision'] = $base + $j;
					$e['receipt'] = array( 'domain' => $s['domain'], 'purpose' => $purpose, 'operation_id' => $o['operation_id'], 'effect_id' => $e['effect_id'], 'context_digest' => $o['context_digest'], 'dependency_digest' => $e['dependency_digest'], 'before_hash' => $e['before_hash'], 'after_hash' => $e['after_hash'], 'result_revision' => $e['result_revision'], 'classification' => 'committed' );
					$hashes[$purpose] = hash( 'sha256', json_encode( $e['receipt'] ) );
				}
				$o['effects'][$purpose] = $e; ++$j;
			}
			$o['context_digest'] = hash( 'sha256', json_encode( array( $s['domain'], $s['site_uuid'], $s['enrollment_binding'], $o['operation_id'], $o['command_id'], $o['entry_point'], $o['generation'], $o['participant_digest'], $o['issued_at'], $o['expires_at'], $o['terminal_id'], $o['resolution_id'], array_column( $o['effects'], 'effect_id' ) ) ) );
			if ( $terminal ) {
				foreach ( $o['effects'] as $purpose => &$effect ) { $effect['receipt']['context_digest'] = $o['context_digest']; $hashes[$purpose] = hash( 'sha256', json_encode( $effect['receipt'] ) ); } unset( $effect );
				$o['terminal'] = array( 'domain' => $s['domain'], 'purpose' => 'terminal', 'terminal_id' => $o['terminal_id'], 'operation_id' => $o['operation_id'], 'context_digest' => $o['context_digest'], 'result_revision' => $base + 3, 'effect_receipts' => $hashes );
			}
			$s['operations'][$o['operation_id']] = $o;
		}
		return $s;
	}
	private function inspect( $s ) {
		return Alynt_Drime_Backups_Uploader_Closed_Scan_Effect_Ledger::inspect( json_encode( $s ), array( 'site_uuid' => $s['site_uuid'], 'enrollment_binding' => $s['enrollment_binding'], 'authority_generation' => $s['authority_generation'] ) );
	}
}
