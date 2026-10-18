<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RegistrationConstructionFixture.php';
/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class RegistrationConstructionTest extends TestCase {
    use Alynt_Test_Registration_Construction_Fixture;
    public function test_private_positive_construction_uses_complete_closed_frame_and_never_writes() {
        $f = $this->registrationFrame(); $original = $f; $r = $this->privateRegistration( $f ); $this->assertIsArray( $r );
        $this->assertSame( 7, $r['proposal']['actor_id'] ); $this->assertSame( $this->site, $r['proposal']['command']['site_uuid'] );
        $this->assertSame( $original, $f ); $this->assertArrayNotHasKey( 'after', $r );
        $this->assertCount( 1, $this->registrationCalls( 'create_nonce' ) ); $this->assertCount( 3, $this->registrationCalls( 'verify_nonce' ) );
        $this->assertSame( $r['action'], $this->registrationCalls( 'create_nonce' )[0][1][0] );
        $this->assertStringStartsWith( 'alynt_drime_backups_scan_registration_command_v2:', $r['action'] );
        foreach ( array( 'storage_supported', 'execution_supported', 'dispatch_supported' ) as $flag ) $this->assertFalse( $r[$flag] );
        $this->assertCount( 0, $this->registrationCalls( 'query' ) ); $this->assertCount( 0, $this->registrationCalls( 'protected_io' ) );
        $this->assertFalse( $this->privateRegistration( $f ) ); $this->assertCount( 1, $this->registrationCalls( 'create_nonce' ) );
    }
    public function test_invalid_closed_settings_or_safety_refuse_before_nonce_creation() {
        foreach ( array( 'settings', 'schema', 'grant', 'chain', 'pending', 'connection', 'encoding' ) as $bad ) {
            $owner = Alynt_Drime_Backups_Uploader_Registration_Request_Owner::create(); $this->registrationOwner = $owner; $f = $this->registrationFrame();
            if ( 'settings' === $bad ) $this->rowValue( $f, 'settings', serialize( array( 'site_uuid' => $this->site, 'auto_scan_enabled' => true ) ) );
            if ( in_array( $bad, array( 'schema', 'grant', 'chain', 'pending' ), true ) ) { $s = json_decode( $f['rows']['safety']['value'], true ); if ( 'schema' === $bad ) $s['schema_version'] = 6; if ( 'grant' === $bad ) $s['writer']['authenticated_grant'] = array( 'active' => true ); if ( 'chain' === $bad ) $s['writer']['upgrade']['request_digest'] = str_repeat( 'f', 64 ); if ( 'pending' === $bad ) { $pending = $this->real_pending(); $s = $pending[0]; $this->assertTrue( $this->valid_real( $s ) ); $f = $this->registrationFrame( $s ); } $this->rowValue( $f, 'safety', json_encode( $s ) ); }
            if ( 'connection' === $bad ) $this->rowValue( $f, 'connection', 'O:8:"stdClass":0:{}' );
            if ( 'encoding' === $bad ) $this->rowValue( $f, 'connection', "\xff" );
            $this->assertFalse( $this->privateRegistration( $f ), $bad ); $this->assertCount( 0, $this->registrationCalls( 'create_nonce' ) );
        }
    }
    public function test_all_ten_dependency_tuples_change_the_private_context_and_scope_changes_refuse() {
        $f = $this->registrationFrame(); mt_srand( 53 ); $first = $this->privateRegistration( $f ); $this->assertIsArray( $first );
        foreach ( array_keys( $f['rows'] ) as $role ) { $this->registrationOwner = Alynt_Drime_Backups_Uploader_Registration_Request_Owner::create(); $changed = $f; $changed['rows'][$role]['autoload'] = 'off'; mt_srand( 53 ); $r = $this->privateRegistration( $changed ); $this->assertIsArray( $r, $role ); $this->assertNotSame( $first['proposal']['command']['context_digest'], $r['proposal']['command']['context_digest'], $role ); }
        foreach ( array( 'blog_id' => 2, 'prefix' => 'other_', 'database' => 'other' ) as $key => $value ) { $this->registrationOwner = Alynt_Drime_Backups_Uploader_Registration_Request_Owner::create(); $bad = $f; $bad[$key] = $value; $this->assertFalse( $this->privateRegistration( $bad ), $key ); }
    }
    public function test_denied_authorization_and_post_nonce_revocation_cannot_construct() {
        $f = $this->registrationFrame(); $GLOBALS['alynt_core_request_test']['session_now'] = false; $GLOBALS['alynt_core_request_test']['session_cache'] = false;
        mt_srand( 83 ); $first = wp_generate_uuid4(); mt_srand( 83 );
        $this->assertFalse( $this->privateRegistration( $f ) ); $this->assertSame( $first, wp_generate_uuid4(), 'Denied authorization must precede identity generation.' ); $this->assertCount( 0, $this->registrationCalls( 'create_nonce' ) ); $this->assertCount( 0, $this->registrationCalls( 'verify_nonce' ) );
        $this->registrationOwner = Alynt_Drime_Backups_Uploader_Registration_Request_Owner::create(); $GLOBALS['alynt_core_request_test']['session_now'] = true; $GLOBALS['alynt_core_request_test']['session_cache'] = true;
        $GLOBALS['alynt_core_request_test']['on']['create_nonce'] = function () { $GLOBALS['alynt_core_request_test']['actor'] = 8; };
        $this->assertFalse( $this->privateRegistration( $f ) ); $this->assertCount( 1, $this->registrationCalls( 'create_nonce' ) );
        $this->assertCount( 0, $this->registrationCalls( 'query' ) ); $this->assertCount( 0, $this->registrationCalls( 'protected_io' ) );
    }
    public function test_public_surface_stays_unavailable_without_a_reader_and_cannot_export_a_blueprint() {
        $r = $this->registrationOwner->issue( 'POST', $this->registrationForm() );
        $this->assertSame( 'unavailable', $r['outcome'] ); $this->assertArrayNotHasKey( 'proposal', $r ); $this->assertCount( 1, $this->registrationCalls( 'verify_nonce' ) );
        $this->assertCount( 0, $this->registrationCalls( 'create_nonce' ) ); $this->assertCount( 0, $this->registrationCalls( 'protected_io' ) );
        foreach ( array( array( 'GET', $this->registrationForm() ), array( 'POST', array( 'purpose' => 'grant', 'confirm' => 'register', 'nonce' => 'unit-nonce' ) ) ) as $args ) $this->assertSame( 'unavailable', $this->registrationOwner->issue( ...$args )['outcome'] );
        $this->expectException( LogicException::class ); serialize( $this->registrationOwner );
    }
    public function test_generated_identity_collision_with_genesis_or_valid_schema8_history_is_not_retried() {
        foreach ( array( 'genesis', 'history' ) as $domain ) {
            $this->registrationOwner = Alynt_Drime_Backups_Uploader_Registration_Request_Owner::create(); $f = $this->registrationFrame(); mt_srand( 73 ); $id = wp_generate_uuid4();
            if ( 'genesis' === $domain ) { $s = json_decode( $f['rows']['ledger']['value'], true ); $s['origin']['ledger_id'] = $id; $this->rowValue( $f, 'ledger', json_encode( $s ) ); }
            else { $s = json_decode( $f['rows']['safety']['value'], true ); $e = $s['writer']['upgrade']['command']; $e['command_id'] = $id; $s['writer']['upgrade'] = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Data::record( 'upgrade_closed', $e, $s['writer']['upgrade']['committed_at'] ); $this->assertTrue( $this->valid_real( $s ) ); $this->rowValue( $f, 'safety', json_encode( $s ) ); }
            mt_srand( 73 ); $this->assertFalse( $this->privateRegistration( $f ), $domain ); $this->assertFalse( $this->privateRegistration( $f ) ); $this->assertCount( 0, $this->registrationCalls( 'create_nonce' ) );
        }
    }
    public function test_throw_and_reentrancy_consume_private_lifetime_without_exporting_it() {
        $f = $this->registrationFrame(); $roles = $GLOBALS['wp_roles']; $nested = null;
        $GLOBALS['alynt_core_request_test']['on']['create_nonce'] = function () use ( $f, &$nested ) { $nested = $this->privateRegistration( $f ); throw new RuntimeException( 'Synthetic private failure' ); };
        $this->assertFalse( $this->privateRegistration( $f ) ); $this->assertFalse( $nested ); $this->assertSame( $roles, $GLOBALS['wp_roles'] ); $this->assertFalse( $this->privateRegistration( $f ) );
        $this->assertCount( 1, $this->registrationCalls( 'create_nonce' ) ); $r = new ReflectionObject( $this->registrationOwner ); $p = $r->getProperty( 'active' ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true ); $this->assertFalse( $p->getValue( $this->registrationOwner ) );
        $this->assertArrayNotHasKey( 'scope', $this->registrationOwner->__debugInfo() );
    }
}
