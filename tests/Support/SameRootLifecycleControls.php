<?php
/** In-memory fixed-source counterfactuals only. Never native code or disk rewrites. */
function alynt_test_same_root_control( $part, $needle, $replacement, $number ) {
    $parts = array( 'root', 'read', 'state' ); PHPUnit\Framework\Assert::assertContains( $part, $parts );
    $file = dirname( __DIR__, 2 ) . '/includes/trait-registration-lifecycle-integration-' . $part . '.php';
    $original = file_get_contents( $file ); PHPUnit\Framework\Assert::assertSame( 1, substr_count( $original, $needle ), 'Unique source predicate, not syntax failure.' );
    $suffix = ucfirst( $part ); $old = 'Alynt_Drime_Backups_Uploader_Registration_Lifecycle_Integration_' . $suffix;
    $trait = 'Alynt_Same_Root_Control_Trait_' . $number; $class = 'Alynt_Same_Root_Control_' . $number;
    $code = str_replace( $needle, $replacement, $original ); $code = str_replace( 'trait ' . $old . ' {', 'trait ' . $trait . ' {', $code );
    // Only our fixed local trait and fixed substitutions, never request-provided PHP.
    eval( substr( $code, 5 ) );
    $methods = 'Alynt_Drime_Backups_Uploader_Registration_Lifecycle_Integration_Methods'; $ports = 'Alynt_Test_Same_Root_Lifecycle_Ports';
    $adapt = $methods . '::qualify_admission_outcome as private source_qualification; ' . $ports . '::qualify_admission_outcome insteadof ' . $methods . ';';
    foreach ( ( new ReflectionClass( $trait ) )->getMethods() as $m ) $adapt .= $trait . '::' . $m->name . ' insteadof ' . $methods . ';';
    eval( 'final class ' . $class . ' { const EVENTS = Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome::EVENTS; use ' . $methods . ', ' . $ports . ', ' . $trait . ' {' . $adapt . '} }' );
    PHPUnit\Framework\Assert::assertTrue( class_exists( $class, false ), 'Mutant must load and execute to count as killed.' );
    PHPUnit\Framework\Assert::assertSame( hash( 'sha256', $original ), hash_file( 'sha256', $file ), 'Disk source unchanged.' );
    return $class;
}
