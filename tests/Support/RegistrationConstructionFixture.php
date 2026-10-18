<?php
require_once __DIR__ . '/RealClosedWriterFixture.php';
require_once __DIR__ . '/FixedCoreRequestShims.php';
/** Isolated API doubles and synthetic ten-row data; never genuine WP/storage. */
trait Alynt_Test_Registration_Construction_Fixture {
    use Alynt_Test_Real_Closed_Writer_Fixture { setUp as protected setupSafety; }
    private $registrationOwner;
    protected function setUp(): void {
        $this->setupSafety(); alynt_test_fixed_core_request_shims();
        if ( ! defined( 'LOGGED_IN_COOKIE' ) ) define( 'LOGGED_IN_COOKIE', 'alynt_unit_logged_in' );
        if ( ! defined( 'DB_NAME' ) ) define( 'DB_NAME', 'synthetic' );
        if ( ! function_exists( 'wp_create_nonce' ) ) { function wp_create_nonce( $action ) { alynt_test_core_call( 'create_nonce', array( $action ) ); return '0123456789'; } }
        $GLOBALS['alynt_core_request_test'] = array( 'calls' => array(), 'on' => array(), 'throw' => '', 'multisite' => false, 'external_cache' => false, 'blog' => 1, 'actor' => 7, 'cookie_actor' => 7, 'logged_in' => true, 'session_now' => true, 'session_cache' => true, 'user_now' => true, 'user_cache' => true, 'role_now' => true, 'role_cache' => true, 'filter_allows' => true, 'nonce_result' => 1 );
        $GLOBALS['wpdb'] = new wpdb(); $GLOBALS['wp_roles'] = new WP_Roles( 1 ); $_COOKIE[LOGGED_IN_COOKIE] = 'unit-cookie'; $_SERVER['REQUEST_METHOD'] = 'POST'; $GLOBALS['alynt_core_request_test']['calls'] = array();
        $source = dirname( __DIR__, 2 ) . '/includes/class-registration-request-owner.php'; if ( file_exists( $source ) ) require_once $source;
        $this->assertTrue( class_exists( 'Alynt_Drime_Backups_Uploader_Registration_Request_Owner' ), 'Fixed construction owner must exist.' );
        $this->registrationOwner = Alynt_Drime_Backups_Uploader_Registration_Request_Owner::create();
    }
    protected function tearDown(): void {
        // Failed isolated tests serialize their instance: release the owned
        // in-memory fixture graph so closures cannot mask the actual assertion.
        $GLOBALS['alynt_core_request_test']['on'] = array();
        foreach ( ( new ReflectionObject( $this ) )->getProperties() as $property ) {
            if ( $property->getDeclaringClass()->getName() !== get_class( $this ) || $property->isStatic() ) continue;
            if ( PHP_VERSION_ID < 80100 ) $property->setAccessible( true );
            $property->setValue( $this, null );
        }
        parent::tearDown();
    }
    private function registrationForm() { return array( 'purpose' => 'register_closed', 'confirm' => 'register', 'nonce' => 'unit-nonce' ); }
    private function registrationFrame( $safety = null ) {
        $s = $safety ?? $this->real_upgraded(); $rows = array(); $n = 0;
        foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $role => $def ) $rows[$role] = array( 'option_id' => (string) ++$n, 'option_name' => $def[0], 'autoload' => 'no', 'octets' => '6', 'value' => 'a:0:{}' );
        $root = array( 'schema_version' => 2, 'domain' => 'scan_registration_closed_v2', 'site_uuid' => $this->site, 'enrollment_binding' => $s['enrollment_binding'], 'authority_generation' => $s['lifecycle']['permission_generation'], 'ledger_revision' => 20, 'participant_digest' => Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::digest(), 'closed' => true, 'origin' => array( 'kind' => 'owned_closed_fixture_v2', 'ledger_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'created_at' => 1 ), 'operations' => array() );
        foreach ( array( 'safety' => json_encode( $s ), 'settings' => serialize( array( 'site_uuid' => $this->site, 'auto_scan_enabled' => false ) ), 'ledger' => json_encode( $root ) ) as $role => $raw ) { $rows[$role]['value'] = $raw; $rows[$role]['octets'] = (string) strlen( $raw ); }
        return array( 'blog_id' => 1, 'prefix' => 'wp_', 'database' => 'synthetic', 'rows' => $rows );
    }
    private function privateRegistration( $frame, $form = null ) { $m = new ReflectionMethod( $this->registrationOwner, 'construct_registration' ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true ); return $m->invoke( $this->registrationOwner, $frame, $form ?? $this->registrationForm() ); }
    private function registrationCalls( $name ) { return array_values( array_filter( $GLOBALS['alynt_core_request_test']['calls'], function ( $c ) use ( $name ) { return $c[0] === $name; } ) ); }
    private function rowValue( &$f, $role, $value ) { $f['rows'][$role]['value'] = $value; $f['rows'][$role]['octets'] = (string) strlen( $value ); }
}
