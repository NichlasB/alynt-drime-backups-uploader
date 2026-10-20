<?php
/** Actual final-class surface/loading checks; malformed calls only, no native IO. */
final class RegistrationNativeQualificationBoundaryTest extends PHPUnit\Framework\TestCase {
    public function test_fixed_native_owner_and_api_harness_use_the_same_private_qualification_body() {
        require_once __DIR__ . '/Support/RegistrationAdmissionJoinHarness.php';
        $class = 'Alynt_Drime_Backups_Uploader_Registration_Native_Admission';
        $r = new ReflectionClass( $class ); $m = $r->getMethod( 'qualify_admission_outcome' );
        $this->assertTrue( $r->isFinal() ); $this->assertTrue( $m->isPrivate() );
        $source = new ReflectionMethod( 'Alynt_Admission_Join_Harness', 'qualify_source' );
        $this->assertSame( $m->getFileName(), $source->getFileName() ); $this->assertSame( $m->getStartLine(), $source->getStartLine() ); $this->assertSame( $m->getEndLine(), $source->getEndLine() );
        $this->assertSame( Alynt_Drime_Backups_Uploader_Registration_Admission_Outcome::EVENTS, $class::EVENTS );
        $names = array_map( function ( $m ) { return $m->name; }, $r->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $names );
        $this->assertSame( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ), $names );
        $o = $class::create(); $call = $r->getMethod( 'admit_retained' ); if ( PHP_VERSION_ID < 80100 ) $call->setAccessible( true );
        $this->assertFalse( $call->invoke( $o, 'POST', array() ) );
        $p = $r->getProperty( 'admission_qualification_spent' ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); $this->assertTrue( $p->getValue( $o ) );
        $this->assertFalse( $call->invoke( $o, 'POST', array() ) );
        foreach ( array( 'native_outcome', 'admission_origin', 'admission_clock', 'reader', 'physical', 'native_outcome_writer', 'before', 'proposal', 'after', 'context' ) as $key ) {
            $p = $r->getProperty( $key ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); $this->assertNull( $p->getValue( $o ), $key );
        }
        $this->assertFalse( $class::create( 'provider' ) ); $this->assertSame( $o->disposition(), $o->__debugInfo() );
        foreach ( array( 'serialize', '__wakeup', '__unserialize' ) as $action ) {
            try { if ( 'serialize' === $action ) serialize( $o ); elseif ( '__wakeup' === $action ) $o->__wakeup(); else $o->__unserialize( array() ); $this->fail( 'No private state export.' ); }
            catch ( LogicException $error ) { $this->assertSame( 'Closed contract data cannot be transferred.', $error->getMessage() ); }
        }
    }
    public function test_only_private_helper_reuse_and_no_production_loading_or_ports() {
        $root = dirname( __DIR__ ); $source = file_get_contents( $root . '/includes/trait-registration-admission-qualification.php' );
        foreach ( array( 'Reflection', '::create(', '->query(', '->prepare(', '->close(', '::observe(', 'wp_verify_nonce(', 'add_action(', 'register_rest_route(' ) as $needle ) $this->assertStringNotContainsString( $needle, $source );
        $this->assertStringContainsString( 'self::closed_qualification_data(', $source );
        foreach ( array( 'alynt-drime-backups-uploader.php', 'includes/class-plugin.php', 'includes/class-registration-lifecycle-request-owner.php' ) as $file ) {
            $source = file_get_contents( $root . '/' . $file );
            foreach ( array( 'class-registration-native-admission.php', 'Registration_Native_Admission', 'Registration_Admission_Qualification', 'trait-registration-admission-qualification.php', 'Registration_Closed_Qualification' ) as $needle ) $this->assertStringNotContainsString( $needle, $source );
        }
    }
}
