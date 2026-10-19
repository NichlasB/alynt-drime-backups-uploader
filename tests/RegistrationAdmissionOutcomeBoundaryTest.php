<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RegistrationAdmissionOutcomeFixture.php';
/** Pure identity/packet/time/privacy boundaries, no actual timer or WordPress. */
class RegistrationAdmissionOutcomeBoundaryTest extends TestCase {
    use Alynt_Test_Registration_Admission_Outcome_Fixture;
    public function test_all_original_ids_and_context_are_required() {
        $good = $this->lifeFixture( 'empty' );
        foreach ( range( 0, 11 ) as $i ) { $f = $good; array_splice( $f['ids'], $i, 1 ); $this->outcomeRefused( $this->outcomeFacts(), $f ); }
        foreach ( array( 'actor', 'context', 'operation', 'scope', 'manifest', 'proposal', 'duplicate', 'sparse', 'too_many' ) as $fault ) {
            $f = $good;
            if ( 'actor' === $fault ) $f['data']['actor_id']++;
            elseif ( 'context' === $fault ) $f['data']['context_digest'] = str_repeat( 'f', 64 );
            elseif ( 'operation' === $fault ) $f['data']['operation_id'] = $this->rid( 700 );
            elseif ( 'scope' === $fault ) $f['data']['scope']['authority_generation']++;
            elseif ( 'manifest' === $fault ) $f['data']['manifest_digest'] = str_repeat( 'f', 64 );
            elseif ( 'proposal' === $fault ) $f['proposal']['terminal_id'] = $this->rid( 700 );
            elseif ( 'duplicate' === $fault ) $f['ids'][] = $f['ids'][0];
            elseif ( 'sparse' === $fault ) $f['ids'][999] = $this->rid( 700 );
            else while ( count( $f['ids'] ) < 129 ) $f['ids'][] = $this->rid( 1000 + count( $f['ids'] ) );
            $this->outcomeRefused( $this->outcomeFacts(), $f );
        }
    }
    public function test_half_open_original_writer_budget_and_expiry_preserve_history_without_acceptance() {
        $f = $this->lifeFixture( 'empty' );
        $this->assertOutcome( $this->outcome( $this->outcomeOwner(), $this->outcomeFacts(), $f, $this->admissionClock( 10000000099, 111 ) ), 'uncertain', true );
        foreach ( array( $this->admissionClock( 10000000100, 111 ), $this->admissionClock( 199, 111 ), $this->admissionClock( 300, 109 ), $this->admissionClock( 300, 111, 101 ), $this->admissionClock( 300, $f['data']['mutation']['expires_at'] ), $this->admissionClock( PHP_INT_MAX, 111 ) ) as $clock ) {
            foreach ( array( 'uncertain', 'committed' ) as $history ) $this->assertOutcome( $this->outcome( $this->outcomeOwner(), $this->outcomeFacts( $history ), $f, $clock ), $history, false, true, false );
        }
    }
    public function test_malformed_unknown_missing_reference_or_nonprimitive_facts_consume() {
        foreach ( array( 'missing', 'extra', 'reference', 'object', 'recursive', 'utf8', 'float', 'negative', 'overflow', 'bool', 'null', 'reorder', 'guard' ) as $fault ) {
            $facts = $this->outcomeFacts();
            if ( 'missing' === $fault ) unset( $facts['acks']['close'] );
            elseif ( 'extra' === $fault ) $facts['phase'] = 'committed';
            elseif ( 'reference' === $fault ) { $value = $facts['events']['dml_sent']; $facts['events']['dml_sent'] =& $value; }
            elseif ( 'object' === $fault ) $facts['acks']['dml'] = new class implements JsonSerializable { public function jsonSerialize(): array { throw new LogicException( 'Must not invoke.' ); } };
            elseif ( 'recursive' === $fault ) $facts['loop'] =& $facts;
            elseif ( 'utf8' === $fault ) $facts['acks']['dml'] = "\xff";
            elseif ( 'reorder' === $fault ) $facts['events'] = array_reverse( $facts['events'], true );
            elseif ( 'guard' === $fault ) $facts['guards']['auth'] = 'true';
            else $facts['events']['dml_sent'] = array( 'float' => 7.0, 'negative' => -1, 'overflow' => 33, 'bool' => true, 'null' => null )[$fault];
            $this->outcomeRefused( $facts ); unset( $facts );
        }
    }
    public function test_existing_packet_parser_exact_boundaries_are_reused() {
        $o = $this->outcomeOwner();
        foreach ( array( 16384 => true, 16385 => false ) as $bytes => $expected ) $this->assertSame( $expected, $this->admissionInvoke( $o, 'packet', str_repeat( 'x', $bytes - 2 ) ) );
        foreach ( array( 4096 => true, 4097 => false ) as $bytes => $expected ) $this->assertSame( $expected, $this->admissionInvoke( $o, 'bounded', str_repeat( 'x', $bytes - 2 ) ) );
        foreach ( array( 8 => true, 9 => false ) as $depth => $expected ) { $value = 1; for ( $i = 0; $i < $depth; $i++ ) $value = array( $value ); $this->assertSame( $expected, $this->admissionInvoke( $o, 'packet', $value ) ); }
        $value = array_fill( 0, 255, 1 ); $this->assertTrue( $this->admissionInvoke( $o, 'packet', $value ) ); $value[] = 1; $this->assertFalse( $this->admissionInvoke( $o, 'packet', $value ) );
    }
    public function test_complete_id_ceiling_and_observation_position_ceiling() {
        $f = $this->lifeFixture( 'empty' ); $f['ids'] = array_slice( $f['ids'], 0, 12 );
        $this->assertOutcome( $this->outcome( $this->outcomeOwner(), $this->outcomeFacts(), $f ), 'uncertain', true );
        while ( count( $f['ids'] ) < 128 ) $f['ids'][] = $this->rid( 1000 + count( $f['ids'] ) );
        $this->assertOutcome( $this->outcome( $this->outcomeOwner(), $this->outcomeFacts(), $f ), 'uncertain', true );
        $facts = $this->outcomeFacts(); $facts['events']['accepted_at'] = 32;
        $this->assertOutcome( $this->outcome( $this->outcomeOwner(), $facts ), 'uncertain', true );
        $facts['events']['accepted_at'] = 33; $this->outcomeRefused( $facts );
    }
    public function test_under_arity_attempt_also_consumes_without_leaving_sensitive_state() {
        $f = $this->lifeFixture( 'empty' ); $args = array( $f['data'], $f['proposal'], $f['ids'], $f['clock'], $f['clock'], $this->outcomeFacts() );
        foreach ( range( 0, 5 ) as $count ) {
            $o = $this->outcomeOwner(); $this->assertFalse( $this->admissionInvoke( $o, 'classify', ...array_slice( $args, 0, $count ) ) );
            $this->assertTrue( $this->admissionProperty( $o, 'spent' ) ); $this->assertFalse( $this->outcome( $o, $this->outcomeFacts() ) );
        }
    }
    public function test_public_surface_is_inert_and_no_input_facts_are_retained() {
        $o = $this->outcomeOwner(); $r = new ReflectionClass( $o ); $this->assertTrue( $r->isFinal() ); $this->assertTrue( $r->getConstructor()->isPrivate() );
        $names = array_map( function( $m ) { return $m->name; }, $r->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $names );
        $this->assertSame( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ), $names );
        $this->assertTrue( $r->getMethod( 'classify' )->isPrivate() ); $this->assertTrue( $r->getMethod( '__clone' )->isPrivate() );
        $this->assertSame( array( 'spent' ), array_map( function( $p ) { return $p->name; }, $r->getProperties() ) );
        $this->assertFalse( $o::create( 'provider' ) ); $this->assertSame( $o->disposition(), $o->__debugInfo() );
        $this->outcome( $o, $this->outcomeFacts() ); $this->assertTrue( $this->admissionProperty( $o, 'spent' ) );
        foreach ( array( 'serialize', '__wakeup', '__unserialize' ) as $action ) {
            try { if ( 'serialize' === $action ) serialize( $o ); elseif ( '__wakeup' === $action ) $o->__wakeup(); else $o->__unserialize( array() ); $this->fail( 'No transferable facts.' ); }
            catch ( LogicException $e ) { $this->assertSame( 'Closed contract data cannot be transferred.', $e->getMessage() ); }
        }
        $f = $this->lifeFixture( 'empty' ); $o = $this->outcomeOwner();
        $this->assertFalse( $this->admissionInvoke( $o, 'classify', $f['data'], $f['proposal'], $f['ids'], $f['clock'], $f['clock'], $this->outcomeFacts(), 'extra' ) );
        $this->assertFalse( $this->outcome( $o, $this->outcomeFacts() ) );
    }
    public function test_no_production_loading_or_native_auth_storage_clock_port() {
        $root = dirname( __DIR__ );
        foreach ( array( 'class-registration-admission-outcome.php', 'trait-registration-admission-outcome-data.php' ) as $file ) {
            $source = file_get_contents( $root . '/includes/' . $file ); $this->assertLessThanOrEqual( 300, count( explode( "\n", $source ) ) );
            foreach ( token_get_all( $source ) as $token ) if ( is_array( $token ) && T_STRING === $token[0] ) $this->assertFalse( (bool) preg_match( '/^(wp_|mysqli|current_user_can$|get_option$|update_option$|add_action$|add_filter$|hrtime$|time$|microtime$|sleep$|fopen$|file_put_contents$|curl_)/i', $token[1] ), $token[1] );
        }
        foreach ( array( 'alynt-drime-backups-uploader.php', 'includes/class-plugin.php', 'includes/class-registration-native-admission.php', 'includes/class-registration-lifecycle-request-owner.php' ) as $file ) $this->assertStringNotContainsString( 'Registration_Admission_Outcome', file_get_contents( $root . '/' . $file ) );
    }
}
