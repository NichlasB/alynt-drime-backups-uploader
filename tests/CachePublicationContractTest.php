<?php
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-cache-publication-contract.php';

/** In-memory observations only: no cache API, WordPress auth or storage proof. */
class CachePublicationContractTest extends TestCase {
	private function events( $ack = 'confirmed', $kind = 'pair', $observation = 'true' ) {
		$events = array( array( 'event' => 'commit', 'ack' => $ack ), array( 'event' => 'release' ), array( 'event' => 'publish', 'blog' => 7 ) );
		foreach ( 'pair' === $kind ? array( 'safety', 'settings', 'alloptions', 'notoptions' ) : array( 'safety', 'alloptions', 'notoptions' ) as $key ) $events[] = array( 'event' => 'delete', 'blog' => 7, 'key' => $key, 'observation' => $observation );
		return $events;
	}
	private function model( $events, $kind = 'pair', $profile = null ) {
		$r = Alynt_Drime_Backups_Uploader_Cache_Publication_Contract::simulate( 7, $kind, $profile ?? array( 'multisite' => false, 'dropins' => false, 'external_cache' => false ), $events );
		$this->assertTrue( $r['candidate_only'] ); $this->assertTrue( $r['publication_unconfirmed'] );
		foreach ( array( 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $key ) $this->assertFalse( $r[$key] );
		return $r;
	}
	public function test_fixed_key_positive_observations_are_not_cross_process_convergence() {
		foreach ( array( 'safety', 'pair' ) as $kind ) {
			$r = $this->model( $this->events( 'confirmed', $kind ), $kind );
			$this->assertSame( array_merge( array( 'commit_confirmed', 'transaction_released', 'fixed_scope_hygiene_candidate' ), array_fill( 0, 'pair' === $kind ? 4 : 3, 'delete_true' ) ), $r['outcomes'] );
			$this->assertTrue( $r['historical_commit'] ); $this->assertTrue( $r['publication_attempted'] ); $this->assertTrue( $r['observations_complete'] ); $this->assertTrue( $r['outward_candidate'] );
		}
	}
	public function test_false_absence_and_throw_remain_ambiguous_without_undoing_commit() {
		foreach ( array( 'false', 'threw' ) as $observation ) {
			$r = $this->model( $this->events( 'confirmed', 'pair', $observation ) );
			$this->assertSame( array_fill( 0, 4, 'delete_' . $observation ), array_slice( $r['outcomes'], 3 ) ); $this->assertTrue( $r['historical_commit'] ); $this->assertTrue( $r['observations_complete'] );
		}
	}
	public function test_no_publication_before_commit_or_while_transaction_unreleased() {
		foreach ( array( array(), array( array( 'event' => 'commit', 'ack' => 'confirmed' ) ), array( array( 'event' => 'release' ) ) ) as $prefix ) {
			$r = $this->model( array_merge( $prefix, array( array( 'event' => 'publish', 'blog' => 7 ) ) ) ); $this->assertFalse( $r['publication_attempted'] ); $this->assertSame( 'rejected', end( $r['outcomes'] ) );
		}
		$r = $this->model( $this->events( 'rejected' ) ); $this->assertFalse( $r['historical_commit'] ); $this->assertFalse( $r['publication_attempted'] );
	}
	public function test_post_commit_revocation_withholds_outward_result_but_not_hygiene() {
		$events = $this->events(); array_splice( $events, 1, 0, array( array( 'event' => 'revoke' ) ) );
		$r = $this->model( $events ); $this->assertFalse( $r['outward_candidate'] ); $this->assertTrue( $r['historical_commit'] ); $this->assertTrue( $r['observations_complete'] );
	}
	public function test_crash_before_publication_preserves_historical_fact_without_convergence() {
		$r = $this->model( array_slice( $this->events(), 0, 2 ) ); $this->assertTrue( $r['historical_commit'] ); $this->assertFalse( $r['publication_attempted'] ); $this->assertFalse( $r['observations_complete'] );
	}
	public function test_unknown_commit_is_not_resolved_by_eviction_replay_or_caller_receipt() {
		$events = $this->events( 'unknown' ); $events[] = array( 'event' => 'commit', 'ack' => 'confirmed' );
		$r = $this->model( $events ); $this->assertTrue( $r['commit_unresolved'] ); $this->assertFalse( $r['historical_commit'] ); $this->assertFalse( $r['outward_candidate'] ); $this->assertTrue( $r['observations_complete'] ); $this->assertSame( 'rejected', end( $r['outcomes'] ) );
		$events[] = array( 'event' => 'receipt', 'found' => true ); $r = $this->model( $events ); $this->assertTrue( $r['commit_unresolved'] ); $this->assertFalse( $r['historical_commit'] );
	}
	public function test_wrong_scope_cannot_evict_or_switch_back_to_retry() {
		$events = $this->events(); $events[2]['blog'] = 8; $events[] = array( 'event' => 'publish', 'blog' => 7 );
		$r = $this->model( $events ); $this->assertFalse( $r['publication_attempted'] ); $this->assertFalse( $r['observations_complete'] ); $this->assertTrue( $r['historical_commit'] ); $this->assertFalse( $r['outward_candidate'] );
	}
	public function test_scope_swap_after_publication_stops_remaining_observations() {
		$events = $this->events(); $events[4]['blog'] = 8;
		$r = $this->model( $events );
		$this->assertSame( array( 'delete_true', 'rejected', 'rejected', 'rejected' ), array_slice( $r['outcomes'], 3 ) );
		$this->assertTrue( $r['publication_attempted'] ); $this->assertFalse( $r['observations_complete'] ); $this->assertFalse( $r['outward_candidate'] );
	}
	public function test_fixed_order_no_arbitrary_keys_and_no_repeat_attempts() {
		$events = $this->events(); $events[] = array( 'event' => 'publish', 'blog' => 7 ); $events[] = $events[3];
		$r = $this->model( $events ); $this->assertSame( array( 'rejected', 'rejected' ), array_slice( $r['outcomes'], -2 ) );
		$events = $this->events(); $events[3]['key'] = 'settings'; $r = $this->model( $events ); $this->assertFalse( $r['observations_complete'] );
		foreach ( array( 'flush_all', 'cache_set', 'updated_option', 'raw_read', 'retry_job', 'repopulate' ) as $name ) {
			$events = $this->events(); $events[] = array( 'event' => $name ); $r = $this->model( $events ); $this->assertSame( 'rejected', end( $r['outcomes'] ) ); $this->assertTrue( $r['historical_commit'] );
		}
	}
	public function test_profile_strict_false_null_and_untrusted_shapes() {
		foreach ( array( false, null ) as $external ) {
			$r = $this->model( $this->events(), 'pair', array( 'multisite' => false, 'dropins' => false, 'external_cache' => $external ) ); $this->assertTrue( $r['observations_complete'] );
		}
		foreach ( array( true, 0, '', array(), new stdClass() ) as $bad ) {
			foreach ( array( 'multisite', 'dropins', 'external_cache' ) as $key ) {
				$profile = array( 'multisite' => false, 'dropins' => false, 'external_cache' => false ); $profile[$key] = $bad;
				$this->assertSame( array( 'unsupported_trace' ), $this->model( $this->events(), 'pair', $profile )['outcomes'] );
			}
		}
		foreach ( array( array(), array( 'multisite' => false, 'dropins' => false ), array( 'multisite' => false, 'dropins' => false, 'external_cache' => false, 'provider' => function() {} ) ) as $profile ) $this->assertSame( array( 'unsupported_trace' ), $this->model( array(), 'pair', $profile )['outcomes'] );
	}
	public function test_trace_resource_limits_and_flat_values_are_rejected_without_callbacks() {
		$this->assertCount( 12, $this->model( array_fill( 0, 12, array( 'event' => 'revoke' ) ) )['outcomes'] );
		foreach ( array( array_fill( 0, 13, array() ), array( 1 => array() ), null, new stdClass() ) as $events ) $this->assertSame( array( 'unsupported_trace' ), $this->model( $events )['outcomes'] );
		foreach ( array( array(), new stdClass(), array( 'event' => array() ), array( 'event' => 'delete', 'key' => str_repeat( 'x', 65536 ), 'observation' => 'true' ), array( 'event' => 'commit', 'ack' => true ), array( 'event' => 'publish', 'blog' => '7' ), array( 'event' => 'release', 'callback' => function() { throw new LogicException(); } ) ) as $bad ) {
			$this->assertSame( array( 'rejected' ), $this->model( array( $bad ) )['outcomes'] );
		}
		foreach ( array( 0, -1, '7', null ) as $blog ) {
			$r = Alynt_Drime_Backups_Uploader_Cache_Publication_Contract::simulate( $blog, 'pair', array(), array() ); $this->assertSame( array( 'unsupported_trace' ), $r['outcomes'] );
		}
	}
}
