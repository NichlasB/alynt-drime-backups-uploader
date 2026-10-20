<?php
/** Repository-only private boundary; no actual WordPress/native execution. */
final class RegistrationAdmissionJoinBoundaryTest extends PHPUnit\Framework\TestCase {
    public function test_fixed_join_has_no_public_entry_or_production_loading() {
        $root = dirname( __DIR__ );
        $this->assertFileExists( $root . '/includes/trait-registration-admission-root.php', 'Approved single root must exist.' );
        require_once $root . '/includes/class-registration-native-admission.php';
        $r = new ReflectionClass( 'Alynt_Drime_Backups_Uploader_Registration_Native_Admission' );
        foreach ( array( 'admit', 'admit_retained', 'admission_root', 'retain_admission_origin', 'admission_origin_same', 'clear_admission_origin' ) as $method ) $this->assertTrue( $r->getMethod( $method )->isPrivate() );
        $names = array_map( function ( $m ) { return $m->name; }, $r->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $names );
        $this->assertSame( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ), $names );
        $this->assertContains( 'Alynt_Drime_Backups_Uploader_Registration_Admission_Root', $r->getTraitNames() );
        $this->assertContains( 'Alynt_Drime_Backups_Uploader_Registration_Admission_Join', $r->getTraitNames() );
        foreach ( array( 'alynt-drime-backups-uploader.php', 'includes/class-plugin.php', 'includes/class-registration-lifecycle-request-owner.php' ) as $file ) {
            $source = file_get_contents( $root . '/' . $file );
            foreach ( array( 'admit_retained', 'trait-registration-admission-root.php', 'trait-registration-admission-join.php' ) as $needle ) $this->assertStringNotContainsString( $needle, $source );
        }
    }
    public function test_actual_private_join_refuses_and_consumes_malformed_input_without_io() {
        require_once dirname( __DIR__ ) . '/includes/class-registration-native-admission.php';
        $o = Alynt_Drime_Backups_Uploader_Registration_Native_Admission::create();
        $call = new ReflectionMethod( $o, 'admit_retained' ); if ( PHP_VERSION_ID < 80100 ) $call->setAccessible( true );
        $this->assertFalse( $call->invoke( $o, 'POST', array() ) );
        foreach ( array( 'admission_origin', 'admission_clock', 'reader', 'native_outcome' ) as $key ) {
            $p = new ReflectionProperty( $o, $key ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); $this->assertNull( $p->getValue( $o ) );
        }
        foreach ( array( 'protected_reads', 'writes' ) as $key ) {
            $p = new ReflectionProperty( $o, $key ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); $this->assertSame( 0, $p->getValue( $o ) );
        }
        $this->assertFalse( $call->invoke( $o, 'POST', array() ) );
        foreach ( array( 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $flag ) $this->assertFalse( $o->disposition()[$flag] );
    }
    public function test_actual_fixed_entries_delegate_only_to_the_exercised_root_with_fixed_modes() {
        require_once dirname( __DIR__ ) . '/includes/class-registration-native-admission.php';
        foreach ( array( 'admit' => 'false', 'admit_retained' => 'true' ) as $name => $mode ) {
            $m = new ReflectionMethod( 'Alynt_Drime_Backups_Uploader_Registration_Native_Admission', $name );
            $lines = file( $m->getFileName() ); $source = implode( '', array_slice( $lines, $m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1 ) );
            $actual = ''; foreach ( token_get_all( '<?php ' . $source ) as $t ) {
                if ( is_array( $t ) && in_array( $t[0], array( T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) continue;
                $actual .= is_array( $t ) ? $t[1] : $t;
            }
            $this->assertSame( 'privatefunction' . $name . '($method,$body){return$this->admission_root(2===func_num_args()?$method:null,$body,' . $mode . ');}', $actual, 'An unavailable/disconnected actual entry must not pass API-harness positives.' );
        }
    }
}
