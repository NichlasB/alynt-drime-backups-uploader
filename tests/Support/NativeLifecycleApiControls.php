<?php
/** Fixed local-source counterfactuals in memory only; never request PHP/disk edits. */
function alynt_native_control( $frame, $control ) {
    $base = ABSPATH . 'includes/trait-registration-native-lifecycle-';
    $specs = array(
        'mutation' => array( 'root', " || ! \$this->guard_native_mutation( \$core, \$body, \$own, \$actor, \$cookie, \$facts )", '', 'commit' ),
        'read' => array( 'read', ' || ! $this->setup_native_barrier( $core ) || ! $this->renew_lifecycle_read( $built, $core, $cookie, $actor, $facts )', ' || ! $this->setup_native_barrier( $core )', 'commit' ),
        'release' => array( 'cleanup', "\$released && 'barrier_released' === \$this->phase && null === \$this->reader && null === \$this->physical", '$released', 'barrier-close-false' ),
        'zero' => array( 'read', ' || ! $this->barrier_zero_dml() || ! $this->barrier_budget( $outer, $e )', ' || ! $this->barrier_budget( $outer, $e )', 'commit' ),
        'erase' => array( 'root', '$this->erase_lifecycle_writer();', '', 'commit' ),
        'postclose' => array( 'read', '! is_array( $result ) || ! $this->renew_lifecycle_read( $built, $core, $cookie, $actor, $facts )', '! is_array( $result )', 'commit' ),
    );
    if ( ! isset( $specs[$control] ) ) throw new LogicException( 'Unknown fixed control.' );
    list( $part, $from, $to, $case ) = $specs[$control]; $path = 'zero' === $control ? ABSPATH . 'includes/trait-registration-observation-native-barrier.php' : $base . $part . '.php'; $hash = hash_file( 'sha256', $path );
    $source = file_get_contents( $path ); $changed = str_replace( $from, $to, $source, $n ); alynt_native_check( 1 === $n && $source !== $changed, 'unique semantic control replacement' );
    preg_match( '/trait (Alynt_[A-Za-z0-9_]+)/', $source, $match ); $old = $match[1]; $mutant = $old . '_Control';
    eval( substr( str_replace( $old, $mutant, $changed ), strpos( $changed, 'trait Alynt_' ) ) );
    $methods = file_get_contents( ABSPATH . 'includes/trait-registration-native-lifecycle-methods.php' );
    $newMethods = 'Alynt_Test_Native_Lifecycle_Control_Methods';
    eval( substr( str_replace( array( 'Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_Methods', $old ), array( $newMethods, $mutant ), $methods ), strpos( $methods, 'trait Alynt_' ) ) );
    $harness = file_get_contents( __DIR__ . '/NativeLifecycleApiHarness.php' ); $class = 'Alynt_Test_Native_Lifecycle_Control';
    eval( substr( str_replace( array( 'Alynt_Test_Native_Lifecycle_Api', 'Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_Methods' ), array( $class, $newMethods ), $harness ), strpos( $harness, 'final class ' ) ) );
    alynt_native_check( class_exists( $class, false ), 'mutant compiled, not killed by bootstrap error' );
    $before = count( $GLOBALS['native_failures'] ); alynt_native_case( $frame, $case, $class );
    $killed = array_splice( $GLOBALS['native_failures'], $before );
    $labels = array( 'mutation' => 'no core callbacks under locks and exact pre-BEGIN guards', 'read' => 'no core callbacks under locks and exact pre-BEGIN guards', 'release' => 'non-vacuous positive/refusal contract', 'zero' => 'non-vacuous positive/refusal contract', 'erase' => 'writer erased before read callback: before', 'postclose' => 'exact twenty-four guards/four nonce domains' );
    alynt_native_check( in_array( $labels[$control], $killed, true ), 'semantic control killed by its intended assertion, not arbitrary failure' );
    if ( 'zero' === $control ) alynt_native_check( 'barrier_released' === $GLOBALS['native_context']['phase'] && 22 === $GLOBALS['native_context']['guards'] && 1 === count( array_keys( $GLOBALS['native_context']['events'], 'zero', true ) ), 'zero-DML removal reached completed barrier with missing inspection' );
    alynt_native_check( $hash === hash_file( 'sha256', $path ), 'source hash unchanged by control' );
}
