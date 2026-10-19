<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RegistrationObservationFixture.php';
/** Syntax/domain data only: nonce strings are never authenticated or generated. */
class RegistrationObservationRequestTest extends TestCase {
    use Alynt_Test_Registration_Observation_Fixture;
    public function test_read_issuance_is_distinct_blog_and_observed_command_bound_with_no_mutation_grammar_change() {
        $c = 'Alynt_Drime_Backups_Uploader_Registration_Observation_Request'; $intent = $this->observationIntent();
        $this->assertSame( $intent, $c::intent( $intent ) ); $this->assertSame( $intent, $c::intent( array_reverse( $intent, true ) ) );
        $a = $c::issue_action( 1, $this->rid( 3 ) ); $this->assertSame( 'alynt_drime_backups_scan_registration_observe_issue_v2:' . hash( 'sha256', json_encode( array( 1, 'observe_registration', $this->rid( 3 ) ) ) ), $a );
        $this->assertNotSame( $a, $c::issue_action( 2, $this->rid( 3 ) ) ); $this->assertNotSame( $a, $c::issue_action( 1, $this->rid( 99 ) ) );
        $this->assertNotSame( $a, Alynt_Drime_Backups_Uploader_Registration_Request_Contract::parse( $this->observationForm() )['action'] );
        $this->assertFalse( Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issuance( $intent ) );
        $this->assertFalse( Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( 1, 'observe_registration' ) );
        $this->assertTrue( $this->observationOrigin()->accepts( $intent, $this->observationForm(), 510 ) );
        foreach ( array( 0, '1', PHP_INT_MAX ) as $blog ) $this->assertFalse( $c::issue_action( $blog, $this->rid( 3 ) ) );
    }
    public function test_exact_issuance_and_query_binding_rejects_cross_purpose_site_identity_and_stale_reads() {
        $c = 'Alynt_Drime_Backups_Uploader_Registration_Observation_Request';
        foreach ( array( 'purpose' => 'observe_reservation', 'confirm' => 'register', 'nonce' => '', 'observed_command_id' => strtoupper( $this->rid( 99 ) ) . 'x', 'actor_id' => '7' ) as $key => $value ) { $f = $this->observationIntent(); $f[$key] = $value; $this->assertFalse( $c::intent( $f ), $key ); }
        foreach ( array( 'purpose' => 'observe_reservation', 'observed_command_id' => $this->rid( 99 ), 'site_uuid' => $this->rid( 99 ), 'command_id' => $this->rid( 4 ), 'issued_at' => '0500', 'expires_at' => '801', 'nonce' => '' ) as $key => $value ) { $f = $this->observationForm(); $f[$key] = $value; $this->assertFalse( $this->observationOrigin()->accepts( $this->observationIntent(), $f, 510 ), $key ); }
        $intent = $this->observationIntent(); $intent['observed_command_id'] = $this->rid( 99 ); $this->assertFalse( $this->observationOrigin()->accepts( $intent, $this->observationForm(), 510 ) );
        foreach ( array( 499, 800, '510', PHP_INT_MAX ) as $now ) $this->assertFalse( $this->observationResult( null, null, null, $now ) );
        $this->assertSame( 'registered_closed', $this->observationResult( null, null, null, 799 )['classification'] );
        $form = $this->observationForm(); $form['command_id'] = $this->rid( 8 ); $this->assertFalse( $this->observationResult( null, null, $form ) ); // Reserved identity, even before intent exists.
        $f = $this->observationIntent(); $nonce = 'synthetic'; $f['nonce'] =& $nonce; $this->assertFalse( $c::intent( $f ) );
    }
    public function test_complete_encoded_issuance_bound_and_unslash_do_not_hide_overflow() {
        $c = 'Alynt_Drime_Backups_Uploader_Registration_Observation_Request'; $f = $this->observationIntent(); $f['nonce'] = str_repeat( 'é', 500 ); $f['nonce'] .= str_repeat( 'x', 4096 - strlen( json_encode( $f ) ) );
        $this->assertSame( 4096, strlen( json_encode( $f ) ) ); $this->assertIsArray( $c::intent( $f ) ); $f['nonce'] .= 'x'; $this->assertFalse( $c::intent( $f ) );
        $f = $this->observationIntent(); $f['nonce'] = str_repeat( '\\', 2048 ); $this->assertFalse( $c::intent( $f ) );
        foreach ( array( array(), new stdClass(), 1, "\xff", str_repeat( 'x', 2049 ) ) as $nonce ) { $f = $this->observationIntent(); $f['nonce'] = $nonce; $this->assertFalse( $c::intent( $f ) ); }
    }
}
