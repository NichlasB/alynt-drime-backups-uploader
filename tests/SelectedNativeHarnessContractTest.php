<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/includes/class-registration-native-admission.php';
require_once dirname( __DIR__ ) . '/scripts/tests/selected-native-cases.php';
/** Isolated source cleanup contract only; no native handle, authentication or SQL. */
final class SelectedNativeHarnessContractTest extends TestCase {
    public function test_actual_cleanup_poisoning_matches_post_return_harness_and_not_pre_cleanup_phase() {
        $owner = Alynt_Drime_Backups_Uploader_Registration_Native_Admission::create();
        $invoke = function ( $name ) use ( $owner ) { $m = new ReflectionMethod( $owner, $name ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true ); return $m->invoke( $owner ); };
        $this->assertTrue( $invoke( 'start_native_outcome' ) );
        $phase = new ReflectionProperty( $owner, 'phase' ); if ( PHP_VERSION_ID < 80100 ) $phase->setAccessible( true ); $phase->setValue( $owner, 'commit_sent' );
        $this->assertFalse( alynt_selected_refusal_shape( 8, 1, array(), $phase->getValue( $owner ) ) );
        $invoke( 'finish' );
        $this->assertSame( 'poisoned', $phase->getValue( $owner ) );
        $this->assertTrue( alynt_selected_refusal_shape( 8, 1, array(), $phase->getValue( $owner ) ) );
        foreach ( array( array( 7, 1, array(), 'poisoned' ), array( 9, 1, array(), 'poisoned' ), array( 8, 2, array(), 'poisoned' ), array( 8, 1, null, 'poisoned' ), array( 8, 1, array(), 'committed' ) ) as $bad ) $this->assertFalse( alynt_selected_refusal_shape( ...$bad ) );
        $invoke( 'finish' ); $this->assertSame( 'poisoned', $phase->getValue( $owner ) );
    }
}
