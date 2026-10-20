<?php
require_once __DIR__ . '/Support/RegistrationAdmissionJoinFixture.php';
/** @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class RegistrationAdmissionJoinTest extends PHPUnit\Framework\TestCase {
    use Alynt_Test_Registration_Admission_Join_Fixture;
    public function test_positive_single_root_retains_exact_origin_before_writer_and_forwarding() {
        $facts = array(); $this->joinGuards( $facts ); mt_srand( 97 ); $ids = array();
        for ( $i = 0; $i < 10; ++$i ) $ids[] = wp_generate_uuid4(); $next = wp_generate_uuid4(); mt_srand( 97 );
        $this->assertTrue( $this->join->run( $this->registrationForm() ), 'Always-unavailable root must fail.' );
        $this->assertSame( $next, wp_generate_uuid4() ); $this->assertCount( 1, $this->registrationCalls( 'create_nonce' ) ); $this->assertCount( 10, $facts );
        $small = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( 1, 'register_closed' ); $full = $this->registrationCalls( 'create_nonce' )[0][1][0];
        $this->assertNotSame( $small, $full ); $this->assertSame( array( $small, $small, $small, $small, $small, $full, $small, $full, $small, $full ), array_column( $facts, 'action' ) );
        foreach ( array_slice( $facts, 0, 6 ) as $fact ) $this->assertNull( $fact['origin'], 'No origin before full command authorization.' );
        foreach ( array_slice( $facts, 6 ) as $fact ) {
            $this->assertSame( $facts[0]['started'], $fact['started'] ); $this->assertSame( $facts[0]['started'], $fact['clock']['started_ns'] );
            $this->assertSame( $ids[0], $fact['origin']['mutation']['command_id'] ); $this->assertSame( $ids[1], $fact['origin']['operation_id'] );
            foreach ( $ids as $id ) $this->assertContains( $id, $fact['ids'] );
            foreach ( array( 'nonce', 'rows', 'reader', 'db' ) as $key ) $this->assertArrayNotHasKey( $key, $fact['origin'] );
        }
        $this->assertSame( $this->join->writer_origin, $this->join->sent_origin ); $this->assertSame( $facts[6]['origin'], $this->join->released_origin );
        $this->assertSame( 1, $this->join->state( 'writes' ) ); $this->joinCleared();
    }
    public function test_standalone_path_keeps_nine_original_guards_and_no_retention() {
        $facts = array(); $this->joinGuards( $facts ); $this->assertTrue( $this->join->run( $this->registrationForm(), false ) );
        $this->assertCount( 9, $facts ); foreach ( $facts as $fact ) $this->assertNull( $fact['origin'] );
        $this->assertNull( $this->join->writer_origin[0] ); $this->joinCleared();
    }
    /** @dataProvider guards */
    public function test_every_independent_guard_refuses_without_replay_or_false_no_write( $gate ) {
        $facts = array(); $this->joinGuards( $facts, function ( $n ) use ( $gate ) { if ( $gate === $n ) $GLOBALS['alynt_core_request_test']['nonce_result'] = false; } );
        $this->assertFalse( $this->join->run( $this->registrationForm() ) ); $this->assertCount( $gate, $facts );
        $this->assertSame( $gate > 8 ? 1 : 0, $this->join->state( 'writes' ) );
        if ( $gate > 8 ) $this->assertSame( 'committed', $this->join->state( 'phase' ), 'Late auth refusal must not erase historical phase.' );
        if ( 1 === $gate ) $this->assertSame( 0, $this->join->state( 'protected_reads' ) );
        $this->joinCleared();
    }
    public function guards() { return array_map( function ( $n ) { return array( $n ); }, range( 1, 10 ) ); }
}
