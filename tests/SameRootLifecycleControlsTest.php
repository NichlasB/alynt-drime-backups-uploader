<?php
require_once __DIR__ . '/Support/SameRootLifecycleFixture.php';
require_once __DIR__ . '/Support/SameRootLifecycleControls.php';
/** @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class SameRootLifecycleControlsTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Same_Root_Lifecycle_Fixture;
    public function test_always_unavailable_admission_cannot_satisfy_positive_orchestration() {
        $this->sameFixture->fault = 'unavailable'; $r = $this->sameRun();
        $killed = false; try { $this->assertSameCandidate( $r ); } catch ( PHPUnit\Framework\AssertionFailedError $e ) { $killed = true; }
        $this->assertTrue( $killed, 'An always-refusing IO port is not positive integration.' ); $this->assertSameRootCleared();
    }
    public function test_own_release_history_erasure_and_pre_capture_guards_are_assertion_killed() {
        $controls = array(
            array( 'root', 'if ( ! $this->guard( $core, $f[\'nonce\'], $issue, null )', 'if ( false', 'pre_guard' ),
            array( 'root', 'true !== $released', 'false', 'release' ),
            array( 'root', "array( 'committed', 'uncertain' )", "array( 'committed', 'uncertain', 'rolled_back' )", 'history' ),
            array( 'root', '$this->erase_lifecycle_writer();', '/* removed writer erasure */', 'erase' ),
            array( 'read', '! $this->guard( $core, $f[\'nonce\'], $issue, $original[0][\'mutation\'] )', 'false', 'read_guard' ),
        );
        foreach ( $controls as $n => $c ) {
            $this->sameNew(); $class = alynt_test_same_root_control( $c[0], $c[1], $c[2], $n ); $this->sameRoot = new $class( $this->sameFixture );
            if ( 'release' === $c[3] ) $this->sameFixture->fault = 'release_false';
            elseif ( 'history' === $c[3] ) $this->sameFixture->fault = 'rolled_back';
            else $this->sameFixture->fault = '';
            $observed = false; $facts = array();
            if ( 'pre_guard' === $c[3] ) {
                $GLOBALS['alynt_core_request_test']['nonce_result'] = false;
                $this->sameFixture->on = function ( $event ) use ( &$observed ) { if ( 'capture' === $event ) $observed = true; };
            } elseif ( 'read_guard' === $c[3] ) {
                $this->sameFixture->on = function ( $event ) { if ( 'release' === $event ) $GLOBALS['alynt_core_request_test']['nonce_actions']['unit-nonce'] = 'wrong-mutation-domain'; };
            } elseif ( 'erase' === $c[3] ) {
                $this->sameCheckpoints( $facts, function ( $index, $nonce ) use ( $c, &$observed ) {
                    if ( null === $this->sameRoot->state( 'read_started' ) ) return;
                    if ( 'erase' === $c[3] && null !== $this->sameRoot->state( 'before' ) ) $observed = true;
                } );
            }
            $r = $this->sameRun();
            if ( in_array( $c[3], array( 'release', 'history', 'read_guard' ), true ) ) $observed = is_array( $r ) || in_array( 'read', $this->sameFixture->events, true );
            $killed = false; try { $this->assertFalse( $observed, 'Required boundary removed: ' . $c[3] ); } catch ( PHPUnit\Framework\AssertionFailedError $e ) { $killed = true; }
            $this->assertTrue( $killed, 'Must be an assertion kill, not parse/import/refusal: ' . $c[3] );
        }
    }
}
