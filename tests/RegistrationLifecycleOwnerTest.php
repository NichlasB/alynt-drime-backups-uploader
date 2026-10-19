<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RegistrationLifecycleOwnerFixture.php';
/** @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class RegistrationLifecycleOwnerTest extends TestCase {
    use Alynt_Test_Registration_Lifecycle_Owner_Fixture;
    public function test_positive_single_construction_retains_original_before_final_guards_then_clears() {
        $facts = array(); $this->lifecycleCheckpoints( $facts ); $frame = $this->registrationFrame(); $roles = $GLOBALS['wp_roles'];
        mt_srand( 83 ); $expected = array(); for ( $i = 0; $i < 10; $i++ ) $expected[] = wp_generate_uuid4(); $next = wp_generate_uuid4(); mt_srand( 83 );
        $this->assertTrue( $this->lifecycleCall( $frame, $this->registrationForm() ), 'Always-unavailable must fail this test.' );
        $this->assertSame( $next, wp_generate_uuid4(), 'Exactly ten original IDs, never remint for observation.' ); $this->assertCount( 1, $this->registrationCalls( 'create_nonce' ) );
        $this->assertCount( 6, $facts ); $this->assertSame( $roles, $GLOBALS['wp_roles'] );
        $issue = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( 1, 'register_closed' );
        $full = $this->registrationCalls( 'create_nonce' )[0][1][0]; $this->assertNotSame( $issue, $full );
        $this->assertSame( array( $issue, $issue, $issue, $full, $issue, $full ), array_column( $facts, 'action' ) );
        foreach ( array_slice( $facts, 0, 4 ) as $fact ) $this->assertNull( $fact['origin'] );
        foreach ( array_slice( $facts, 4 ) as $fact ) {
            $this->assertSame( 'origin_retained', $fact['phase'] ); $d = $fact['origin']; $this->assertIsArray( $d );
            $this->assertSame( $expected[0], $d['mutation']['command_id'] ); $this->assertSame( $expected[1], $d['operation_id'] ); $this->assertSame( 7, $d['actor_id'] );
            foreach ( $expected as $id ) $this->assertArrayHasKey( $id, $fact['used'] );
            $this->assertSame( $facts[0]['started'], $fact['started'] ); $this->assertSame( $facts[0]['started'], $fact['clock']['started_ns'] );
            $this->assertArrayNotHasKey( 'nonce', $d ); $this->assertArrayNotHasKey( 'rows', $d ); $this->assertArrayNotHasKey( 'db', $d['installation'] );
        }
        $this->assertSame( $facts[4]['origin'], $facts[5]['origin'] ); $this->assertLifecycleCleared();
        $this->assertFalse( $this->lifecycleCall( $frame, $this->registrationForm() ) ); $this->assertCount( 1, $this->registrationCalls( 'create_nonce' ) );
    }
}
