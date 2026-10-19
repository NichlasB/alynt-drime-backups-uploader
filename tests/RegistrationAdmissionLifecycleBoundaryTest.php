<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RegistrationAdmissionLifecycleFixture.php';
/** Dormant privacy/loading contracts only; no genuine auth or storage claim. */
class RegistrationAdmissionLifecycleBoundaryTest extends TestCase {
    use Alynt_Test_Registration_Admission_Lifecycle_Fixture;
    public function test_no_public_operation_provider_clone_or_private_fact_export() {
        $f = $this->lifeFixture(); $o = $this->lifeOwner(); $this->assertTrue( $this->lifeRetain( $o, $f ) );
        $r = new ReflectionClass( $o ); $this->assertTrue( $r->isFinal() ); $this->assertTrue( $r->getConstructor()->isPrivate() );
        $names = array_map( function ( $m ) { return $m->name; }, $r->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $names );
        $this->assertSame( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ), $names );
        foreach ( array( 'retain', 'advance', 'read', 'finish', 'abort', 'stage_read', 'claim_read', 'finish_read', '__clone' ) as $method ) $this->assertTrue( $r->getMethod( $method )->isPrivate(), $method );
        $this->assertSame( $o->disposition(), $o->__debugInfo() );
        $this->assertSame( array( 'candidate_only' => true, 'storage_supported' => false, 'dispatch_supported' => false, 'execution_supported' => false ), $o->disposition() );
        $this->assertFalse( $o::create( new stdClass() ) ); $this->expectException( Error::class ); clone $o;
    }
    public function test_serialization_and_restoration_are_refused() {
        foreach ( array( 'serialize', '__wakeup', '__unserialize' ) as $action ) {
            $o = $this->lifeOwner(); $f = $this->lifeFixture(); $this->lifeRelease( $o, $f );
            try {
                if ( 'serialize' === $action ) serialize( $o ); elseif ( '__wakeup' === $action ) $o->__wakeup(); else $o->__unserialize( array( 'root_data' => $f['data'] ) );
                $this->fail( 'Private simulated lifecycle must not be transferable.' );
            } catch ( LogicException $error ) { $this->assertSame( 'Closed contract data cannot be transferred.', $error->getMessage() ); }
            $this->admissionInvoke( $o, 'abort' ); $this->assertLifeCleared( $o );
        }
    }
    public function test_new_source_has_no_native_auth_clock_or_external_operation() {
        $root = dirname( __DIR__ ); $this->lifeOwner();
        foreach ( array( 'class-registration-admission-lifecycle.php', 'trait-registration-admission-lifecycle-data.php' ) as $file ) {
            $source = file_get_contents( $root . '/includes/' . $file ); $this->assertLessThanOrEqual( 300, count( explode( "\n", $source ) ), $file );
            foreach ( token_get_all( $source ) as $token ) if ( is_array( $token ) && T_STRING === $token[0] ) {
                $this->assertFalse( (bool) preg_match( '/^(wp_|mysqli|current_user_can$|get_option$|update_option$|add_action$|add_filter$|hrtime$|time$|microtime$|sleep$|fopen$|file_put_contents$|curl_)/i', $token[1] ), $token[1] );
            }
        }
        foreach ( array( 'alynt-drime-backups-uploader.php', 'includes/class-plugin.php', 'includes/class-registration-native-admission.php', 'includes/class-registration-observation-construction.php' ) as $file ) {
            $this->assertStringNotContainsString( 'class-registration-admission-lifecycle.php', file_get_contents( $root . '/' . $file ), $file );
            $this->assertStringNotContainsString( 'Registration_Admission_Lifecycle', file_get_contents( $root . '/' . $file ), $file );
        }
    }
    public function test_wrong_arity_never_leaves_a_reusable_origin() {
        $f = $this->lifeFixture();
        foreach ( array( 'retain', 'advance', 'read', 'finish', 'abort' ) as $method ) {
            $o = $this->lifeOwner(); $this->lifeRelease( $o, $f );
            $args = array( 'retain' => array( $f['data'], $f['proposal'], $f['ids'], $f['clock'], 'extra' ), 'advance' => array( 'writer_released', $f['clock'], 'extra' ), 'read' => array( $f['intent'], $f['form'], $f['clock'], 'extra' ), 'finish' => array( $f['frame'], $f['intent'], $f['form'], $f['clock'], 'extra' ), 'abort' => array( 'extra' ) );
            $this->assertFalse( $this->admissionInvoke( $o, $method, ...$args[$method] ), $method ); $this->assertLifeCleared( $o );
        }
    }
}
