<?php
/** Pure synthetic policy projections, not stored options or authenticated input. */
trait Alynt_Drime_Backups_Uploader_Test_Cadence_Policy_Fixture {

	private function active_policy() {
		return array(
			'policy_version' => 1, 'mode' => 'client_owned_v1', 'lifecycle' => 'active',
			'ever_activated' => true, 'handoff_verified' => false, 'consent' => true,
			'auto_scan_enabled' => true, 'permission_generation' => 2, 'current_permission_generation' => 2,
			'binding' => str_repeat( 'a', 64 ), 'current_binding' => str_repeat( 'a', 64 ),
			'model_enabled' => true, 'pending' => 'none', 'next_due_at' => 1800000000,
			'observed_at' => 1800000000, 'expires_at' => 1800000300,
		);
	}

	private function policy_for( $lifecycle ) {
		$s = $this->active_policy();
		if ( 'legacy' === $lifecycle || 'handoff' === $lifecycle ) {
			$s['mode'] = 'legacy'; $s['lifecycle'] = 'legacy'; $s['consent'] = false;
			$s['model_enabled'] = false; $s['next_due_at'] = 0; $s['binding'] = ''; $s['current_binding'] = '';
			$s['ever_activated'] = 'handoff' === $lifecycle; $s['handoff_verified'] = 'handoff' === $lifecycle;
			$s['permission_generation'] = 'handoff' === $lifecycle ? 2 : 0;
			$s['current_permission_generation'] = $s['permission_generation'];
		} else {
			$s['lifecycle'] = $lifecycle;
			if ( 'disabled' === $lifecycle ) { $s['model_enabled'] = false; $s['consent'] = false; }
		}
		return $s;
	}

	private function assert_disposition( $disposition, $code, $result ) {
		$this->assertSame( array( 'disposition' => $disposition, 'code' => $code, 'dispatch_supported' => false, 'execution_supported' => false ), $result );
	}
}
