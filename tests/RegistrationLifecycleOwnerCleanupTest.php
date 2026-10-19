<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RegistrationLifecycleOwnerFixture.php';
/** @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class RegistrationLifecycleOwnerCleanupTest extends TestCase {
    use Alynt_Test_Registration_Lifecycle_Owner_Fixture;
    public function test_reentry_after_retention_poisons_outer_even_without_throw() {
        $frame = $this->registrationFrame(); $nested = null; $facts = array();
        $this->lifecycleCheckpoints( $facts, function ( $n ) use ( &$nested, $frame ) { if ( 5 === $n ) $nested = $this->lifecycleCall( $frame, $this->registrationForm() ); } );
        $this->assertFalse( $this->lifecycleCall( $frame, $this->registrationForm() ) ); $this->assertFalse( $nested ); $this->assertTrue( $this->lifecycleProperty( 'interrupted' ) );
        $this->assertCount( 1, $this->registrationCalls( 'create_nonce' ) ); $this->assertLifecycleCleared();
    }
    public function test_throw_or_warning_after_retention_restores_error_handler_roles_and_owned_filters() {
        $frame = $this->registrationFrame(); $roles = $GLOBALS['wp_roles'];
        foreach ( array( 'throw', 'warning' ) as $fault ) {
            $this->lifecycleNew(); $GLOBALS['alynt_core_request_test']['calls'] = array(); $facts = array();
            $handler = function () { throw new LogicException( 'Prior handler must survive.' ); }; set_error_handler( $handler );
            try {
                $this->lifecycleCheckpoints( $facts, function ( $n ) use ( $fault ) { if ( 5 !== $n ) return; if ( 'throw' === $fault ) throw new RuntimeException( 'Synthetic private failure.' ); trigger_error( 'Synthetic private warning.', E_USER_WARNING ); } );
                $this->assertFalse( $this->lifecycleCall( $frame, $this->registrationForm() ) ); $this->assertLifecycleCleared();
                $current = set_error_handler( $handler ); restore_error_handler(); $this->assertSame( $handler, $current ); $this->assertSame( $roles, $GLOBALS['wp_roles'] );
            } finally { restore_error_handler(); }
        }
    }
    public function test_fixed_role_only_query_and_autoload_fences_are_present_during_auth() {
        $frame = $this->registrationFrame(); $facts = array();
        $GLOBALS['alynt_core_request_test']['on']['capability'] = function () use ( &$facts ) {
            $f = $GLOBALS['alynt_test_owned_filter_inventory']; $this->assertCount( 2, $f );
            $autoload = reset( $f['pre_wp_load_alloptions'] ); $query = reset( $f['query'] );
            $this->assertSame( PHP_INT_MAX, $autoload[1] ); $this->assertSame( array(), $autoload[0]( array( 'unsafe' => true ) ) );
            $sql = "SELECT option_value FROM wp_options WHERE option_name = 'wp_user_roles' LIMIT 1";
            $this->assertSame( $sql, $query[0]( $sql ) ); $facts[] = true;
            $query[0]( "SELECT option_value FROM wp_options WHERE option_name = 'alynt_drime_backups_settings' LIMIT 1" );
        };
        $this->assertFalse( $this->lifecycleCall( $frame, $this->registrationForm() ) ); $this->assertSame( array( true ), $facts ); $this->assertLifecycleCleared();
    }
    public function test_privacy_and_no_public_native_or_synthetic_outcome_import_surface() {
        $o = $this->lifecycleOwner; $r = new ReflectionClass( $o ); $this->assertTrue( $r->isFinal() ); $this->assertTrue( $r->getConstructor()->isPrivate() );
        $names = array_map( function ( $m ) { return $m->name; }, $r->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $names );
        $this->assertSame( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ), $names ); $this->assertTrue( $r->getMethod( 'prepare_admission' )->isPrivate() );
        foreach ( array( 'advance', 'read', 'admit', 'observe_native', 'open_writer', 'admission_write', 'stage', 'claim' ) as $method ) $this->assertFalse( $r->hasMethod( $method ), $method );
        $this->assertFalse( $o::create( 'provider' ) ); $this->assertSame( $o->disposition(), $o->__debugInfo() );
        $facts = array(); $this->lifecycleCheckpoints( $facts, function ( $n ) use ( $o ) { if ( 5 !== $n ) return; try { serialize( $o ); $this->fail( 'Private origin must not serialize.' ); } catch ( LogicException $e ) { $this->assertSame( 'Closed contract data cannot be transferred.', $e->getMessage() ); } } );
        $this->assertTrue( $this->lifecycleCall( $this->registrationFrame(), $this->registrationForm() ) ); $this->assertLifecycleCleared();
    }
    public function test_no_native_call_or_production_loading_and_no_read_nonce_minted() {
        $root = dirname( __DIR__ ); $file = 'class-registration-lifecycle-request-owner.php'; $source = file_get_contents( $root . '/includes/' . $file );
        foreach ( array( 'mysqli', 'admission_write', 'open_writer', 'observe_native', 'new Reflection', 'get_option(', 'update_option(', 'wp_schedule', 'Registration_Observation_Read_Construction' ) as $forbidden ) $this->assertStringNotContainsString( $forbidden, $source );
        foreach ( array( 'alynt-drime-backups-uploader.php', 'includes/class-plugin.php', 'includes/class-registration-native-admission.php', 'includes/class-registration-observation-construction.php' ) as $path ) $this->assertStringNotContainsString( $file, file_get_contents( $root . '/' . $path ) );
        $this->assertTrue( $this->lifecycleCall( $this->registrationFrame(), $this->registrationForm() ) );
        $this->assertCount( 1, $this->registrationCalls( 'create_nonce' ) ); $this->assertStringStartsWith( 'alynt_drime_backups_scan_registration_command_v2:', $this->registrationCalls( 'create_nonce' )[0][1][0] );
        foreach ( $this->registrationCalls( 'verify_nonce' ) as $call ) $this->assertStringNotContainsString( 'observe', $call[1][1] ); $this->assertLifecycleCleared();
    }
    public function test_metadata_reentry_before_construction_does_not_mint_ids_or_nonces() {
        $frame = $this->registrationFrame(); $nested = null;
        $GLOBALS['alynt_core_request_test']['on']['multisite'] = function () use ( &$nested, $frame ) { $nested = $this->lifecycleCall( $frame, $this->registrationForm() ); };
        mt_srand( 113 ); $expected = wp_generate_uuid4(); mt_srand( 113 );
        $this->assertFalse( $this->lifecycleCall( $frame, $this->registrationForm() ) ); $this->assertFalse( $nested ); $this->assertSame( $expected, wp_generate_uuid4() );
        $this->assertCount( 0, $this->registrationCalls( 'create_nonce' ) ); $this->assertCount( 0, $this->registrationCalls( 'verify_nonce' ) ); $this->assertLifecycleCleared();
    }
}
