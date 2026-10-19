<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RegistrationObservationFixture.php';
/** Private simulated consumed lifetime only; no actual clocks or IO. */
class RegistrationObservationLifetimeTest extends TestCase {
    use Alynt_Test_Registration_Observation_Fixture;
    private function owner() { return Alynt_Drime_Backups_Uploader_Registration_Observation_Lifetime::create(); }
    private function stage( $o, $data = null, $clock = null ) { return $this->observationInvoke( $o, 'stage', $data ?? $this->observationData(), $this->observationIntent(), $this->observationForm(), $clock ?? $this->observationClock() ); }
    private function finish( $o, $clock = null, $intent = null, $form = null ) { $d = $this->observationData(); return $this->observationInvoke( $o, 'finish', $this->registered(), $d['installation'], $d['scope'], $intent ?? $this->observationIntent(), $form ?? $this->observationForm(), $clock ?? $this->observationClock( 300, 511 ) ); }
    public function test_positive_once_only_result_and_every_private_fact_released() {
        $o = $this->owner(); $this->assertTrue( $this->stage( $o ) ); $r = $this->finish( $o ); $this->assertIsArray( $r ); $this->assertSame( 'registered_closed', $r['classification'] );
        foreach ( array( 'origin', 'intent', 'form', 'clock' ) as $key ) $this->assertNull( $this->observationProperty( $o, $key ) );
        $this->assertFalse( $this->finish( $o ) ); $this->assertFalse( $this->stage( $o ) );
        foreach ( array( 'stage', 'finish' ) as $method ) $this->assertTrue( ( new ReflectionMethod( $o, $method ) )->isPrivate() );
        $this->assertFalse( Alynt_Drime_Backups_Uploader_Registration_Observation_Lifetime::create( 'provider' ) );
    }
    public function test_refused_premature_repeated_or_object_import_lifetimes_cannot_reopen() {
        $o = $this->owner(); $this->assertFalse( $this->finish( $o ) ); $this->assertFalse( $this->stage( $o ) );
        foreach ( array( array(), $this->observationOrigin() ) as $bad ) { $o = $this->owner(); $this->assertFalse( $this->stage( $o, $bad ) ); $this->assertFalse( $this->finish( $o ) ); $this->assertFalse( $this->stage( $o ) ); }
        $o = $this->owner(); $this->assertTrue( $this->stage( $o ) ); $this->assertFalse( $this->stage( $o ) ); $this->assertFalse( $this->finish( $o ) );
    }
    public function test_original_clock_and_forms_are_not_renewed_by_final_comparison() {
        foreach ( array( $this->observationClock( 300, 511, 101 ), $this->observationClock( 199, 511 ), $this->observationClock( 300, 509 ), $this->observationClock( 10000000100, 511 ), $this->observationClock( 300, 800 ) ) as $clock ) {
            $o = $this->owner(); $this->assertTrue( $this->stage( $o ) ); $this->assertFalse( $this->finish( $o, $clock ) ); $this->assertNull( $this->observationProperty( $o, 'origin' ) ); $this->assertFalse( $this->finish( $o ) );
        }
        foreach ( array( true, false ) as $small ) { $o = $this->owner(); $this->assertTrue( $this->stage( $o ) ); $f = $small ? $this->observationIntent() : $this->observationForm(); $f['nonce'] .= '-changed'; $this->assertFalse( $small ? $this->finish( $o, null, $f ) : $this->finish( $o, null, null, $f ) ); }
    }
    public function test_caller_edits_cannot_mutate_staged_copies_and_last_valid_budget_is_positive() {
        $o = $this->owner(); $d = $this->observationData(); $intent = $this->observationIntent(); $form = $this->observationForm();
        $this->assertTrue( $this->observationInvoke( $o, 'stage', $d, $intent, $form, $this->observationClock() ) );
        $d['actor_id'] = 99; $intent['nonce'] = 'edited'; $form['nonce'] = 'edited';
        $this->assertSame( 'registered_closed', $this->finish( $o, $this->observationClock( 10000000099, 799 ) )['classification'] );
        foreach ( array( 'origin', 'intent', 'form', 'clock' ) as $key ) $this->assertNull( $this->observationProperty( $o, $key ) );
    }
    public function test_malformed_or_aliased_clocks_consume_and_clear_without_a_second_chance() {
        $bad = array( array(), array( 'started_ns' => 100, 'now_ns' => '200', 'utc' => 510 ), $this->observationClock( 99 ), $this->observationClock( 200, 0 ) );
        $ref = $this->observationClock(); $tick = 200; $ref['now_ns'] =& $tick; $bad[] = $ref;
        foreach ( $bad as $clock ) { $o = $this->owner(); $this->assertFalse( $this->stage( $o, null, $clock ) ); $this->assertFalse( $this->stage( $o ) ); $this->assertFalse( $this->finish( $o ) ); foreach ( array( 'origin', 'intent', 'form', 'clock' ) as $key ) $this->assertNull( $this->observationProperty( $o, $key ) ); }
        $o = $this->owner(); $this->assertTrue( $this->stage( $o ) ); $this->assertSame( $o->disposition(), $o->__debugInfo() );
        $this->expectException( LogicException::class ); serialize( $o );
    }
}
