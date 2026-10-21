<?php
require_once __DIR__ . '/Support/SameRootLifecycleFixture.php';
/** @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class SameRootLifecycleIntegrationTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Same_Root_Lifecycle_Fixture;
    public function test_committed_and_false_uncertain_roots_reach_real_reused_candidate_path() {
        foreach ( array( '' => 'committed', 'uncertain' => 'uncertain', 'uncertain_throw' => 'uncertain' ) as $fault => $history ) {
            $this->sameNew(); $this->sameFixture->fault = $fault; $facts = array(); $this->sameCheckpoints( $facts );
            $this->sameFixture->on = function ( $event, $owner ) {
                if ( 'attempt' !== $event ) return;
                $this->assertIsArray( $owner->state( 'admission_origin' ) ); $this->assertIsArray( $owner->state( 'proposal' ) );
                $this->assertSame( $owner->state( 'started' ), $owner->state( 'admission_clock' )['started_ns'] );
            };
            $r = $this->sameRun(); $this->assertSameCandidate( $r );
            $q = $this->sameFixture->qualification; $this->assertIsArray( $q ); $this->assertSame( $history, $q['historical_outcome'] );
            $this->assertTrue( $q['client_release_claimed'] ); $this->assertFalse( $q['current_acceptance_claimed'] ); $this->assertFalse( $q['diagnostic_candidate_only'] );
            $this->assertSame( 1, count( array_keys( $this->sameFixture->events, 'capture', true ) ) );
            $this->assertSame( 1, count( array_keys( $this->sameFixture->events, 'attempt', true ) ) );
            $this->assertSame( 1, count( array_keys( $this->sameFixture->events, 'read', true ) ) );
            $this->assertSameUuidCount( 11 ); $this->assertCount( 3, $this->registrationCalls( 'create_nonce' ) );
            foreach ( $facts as $fact ) if ( null !== $fact['read_start'] ) {
                $this->assertNull( $fact['before'], 'Serialized writer material must be erased BEFORE read authorization.' );
                $this->assertContains( 'release', $fact['events'] ); $this->assertSame( $this->sameFixture->pin[2]['started_ns'], $fact['start'] );
            }
            $actions = array_unique( array_column( $facts, 'action' ) ); $this->assertCount( 4, $actions, 'Small/full mutation/read domains stay separate.' );
            $this->assertCount( 20, $facts, 'Deleting ANY required nonce guard must fail, not shrink a self-derived loop.' );
            $m = $facts[0]['action']; $mf = $facts[5]['action']; $s = $facts[9]['action']; $full = $facts[11]['action'];
            $this->assertSame( array( $m, $m, $m, $m, $m, $mf, $m, $mf, $m, $s, $s, $full, $s, $full, $s, $full, $s, $full, $s, $full ), array_column( $facts, 'action' ) );
            $this->assertNotContains( 'capture', $facts[0]['events'] );
            foreach ( array_slice( $facts, 0, 8 ) as $f ) $this->assertNotContains( 'attempt', $f['events'] );
            foreach ( array_slice( $facts, 12, 2 ) as $f ) { $this->assertIsArray( $f['origin'] ); $this->assertNull( $f['staged'] ); }
            foreach ( array_slice( $facts, 14, 2 ) as $f ) { $this->assertNull( $f['origin'] ); $this->assertIsArray( $f['staged'] ); }
            foreach ( array_slice( $facts, 16, 2 ) as $f ) { $this->assertIsArray( $f['inflight'] ); $this->assertNotContains( 'read', $f['events'] ); }
            foreach ( array_slice( $facts, 18, 2 ) as $f ) { $this->assertTrue( $f['finished'] ); $this->assertNull( $f['inflight'] ); }
            $this->assertSameRootCleared(); $this->assertFalse( $this->sameRun() );
        }
    }
    public function test_verified_absence_stays_candidate_only_and_not_a_barrier_or_retry() {
        $this->sameFixture->fault = 'uncertain'; $this->sameFixture->rows = 'empty';
        $this->assertSameCandidate( $this->sameRun(), 'not_observed_in_candidate' ); $this->assertSameRootCleared();
    }
    public function test_fixed_final_owner_has_no_native_implementation_public_run_or_provider() {
        $class = 'Alynt_Drime_Backups_Uploader_Registration_Lifecycle_Integration';
        $o = $class::create(); $this->assertFalse( $class::create( 'provider' ) );
        $methods = array_filter( ( new ReflectionClass( $o ) )->getMethods( ReflectionMethod::IS_PUBLIC ), function ( $m ) { return '__construct' !== $m->name; } );
        $this->assertSame( array( 'create', 'disposition', '__debugInfo', '__serialize', '__wakeup', '__unserialize' ), array_values( array_map( function ( $m ) { return $m->name; }, $methods ) ) );
        $this->assertSame( array( 'candidate_only' => true, 'storage_supported' => false, 'dispatch_supported' => false, 'execution_supported' => false ), $o->__debugInfo() );
        $m = new ReflectionMethod( $o, 'lifecycle_root' ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true );
        $this->assertFalse( $m->invoke( $o, 'POST', $this->registrationForm() ) );
        $this->assertSameUuidCount( 0 ); $this->assertSame( array(), $this->registrationCalls( 'verify_nonce' ) );
        $this->assertSame( array(), $this->registrationCalls( 'protected_io' ) );
        $source = file_get_contents( dirname( __DIR__ ) . '/alynt-drime-backups-uploader.php' );
        $this->assertStringNotContainsString( 'class-registration-lifecycle-integration.php', $source );
    }
}
