<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/Support/RegistrationAdmissionLifecycleFixture.php';
/** Exact original proposal/identity binding, not actual issuance or provenance. */
class RegistrationAdmissionLifecycleOriginTest extends TestCase {
    use Alynt_Test_Registration_Admission_Lifecycle_Fixture;
    public function test_every_reserved_identity_is_required_before_dml() {
        $good = $this->lifeFixture();
        foreach ( $good['ids'] as $index => $id ) {
            if ( $index >= 12 ) continue;
            $f = $good; array_splice( $f['ids'], $index, 1 ); $o = $this->lifeOwner();
            $this->assertFalse( $this->lifeRetain( $o, $f ), $id ); $this->assertLifeCleared( $o ); $this->assertFalse( $this->lifeAdvance( $o, 'dml_sent' ) );
        }
    }
    public function test_original_actor_context_operation_scope_and_proposal_cannot_be_substituted() {
        $good = $this->lifeFixture();
        foreach ( array( 'actor', 'context', 'operation', 'scope', 'manifest', 'proposal', 'terminal', 'reference' ) as $fault ) {
            $f = $good;
            if ( 'actor' === $fault ) $f['data']['actor_id']++;
            elseif ( 'context' === $fault ) $f['data']['context_digest'] = str_repeat( 'f', 64 );
            elseif ( 'operation' === $fault ) $f['data']['operation_id'] = $this->rid( 700 );
            elseif ( 'scope' === $fault ) $f['data']['scope']['authority_generation']++;
            elseif ( 'manifest' === $fault ) $f['data']['manifest_digest'] = str_repeat( 'f', 64 );
            elseif ( 'proposal' === $fault ) $f['proposal']['dependency_digest'] = str_repeat( 'd', 64 );
            elseif ( 'terminal' === $fault ) $f['proposal']['terminal_id'] = array();
            else { $actor = $f['data']['actor_id']; $f['data']['actor_id'] =& $actor; }
            $o = $this->lifeOwner(); $this->assertFalse( $this->lifeRetain( $o, $f ), $fault ); $this->assertLifeCleared( $o ); $this->assertFalse( $this->lifeRetain( $o, $good ) );
        }
    }
    public function test_malformed_oversized_or_nonprimitive_input_fails_without_callbacks() {
        $good = $this->lifeFixture();
        foreach ( array( 'object', 'depth', 'bytes', 'ids', 'duplicate', 'sparse', 'utf8', 'recursive' ) as $fault ) {
            $f = $good;
            if ( 'object' === $fault ) $f['data'] = new class implements JsonSerializable { public function jsonSerialize(): array { throw new LogicException( 'Must not call objects.' ); } };
            elseif ( 'depth' === $fault ) { $f['data']['mutation']['extra'] = array(); for ( $i = 0; $i < 20; $i++ ) $f['data']['mutation']['extra'] = array( $f['data']['mutation']['extra'] ); }
            elseif ( 'bytes' === $fault ) $f['data']['installation']['prefix'] = str_repeat( 'x', 20000 );
            elseif ( 'ids' === $fault ) $f['ids'] = array_map( function ( $i ) { return $this->rid( $i ); }, range( 1, 129 ) );
            elseif ( 'duplicate' === $fault ) $f['ids'][] = $f['ids'][0];
            elseif ( 'sparse' === $fault ) $f['ids'][999] = $this->rid( 700 );
            elseif ( 'utf8' === $fault ) $f['data']['installation']['database'] = "\xff";
            else $f['data']['loop'] =& $f['data'];
            $o = $this->lifeOwner(); $this->assertFalse( $this->lifeRetain( $o, $f ), $fault ); $this->assertLifeCleared( $o );
        }
    }
    public function test_retained_copy_cannot_be_edited_or_replaced_by_later_receipt() {
        $f = $this->lifeFixture(); $original = $f; $o = $this->lifeOwner(); $this->assertTrue( $this->lifeRetain( $o, $f ) );
        $this->assertSame( array( 'root_data', 'root_ids', 'root_clock' ), array_values( array_filter( array( 'root_data', 'root_ids', 'root_clock' ), function ( $key ) use ( $o ) { return null !== $this->admissionProperty( $o, $key ); } ) ) );
        $f['data']['actor_id']++; $f['proposal']['actor_id']++; $f['ids'][] = $this->rid( 700 );
        $this->assertSame( $original['data'], $this->admissionProperty( $o, 'root_data' ) ); $this->assertSame( $original['ids'], $this->admissionProperty( $o, 'root_ids' ) );
        foreach ( array( 'begin_sent', 'transaction_owned', 'dml_sent', 'writer_released' ) as $event ) $this->assertTrue( $this->lifeAdvance( $o, $event ) );
        $this->assertTrue( $this->lifeRead( $o, $original ) ); $this->assertCandidateOnly( $this->lifeFinish( $o, $original ), 'registered_closed' ); $this->assertLifeCleared( $o );
    }
    public function test_lookup_collision_with_any_retained_id_and_changed_forms_refuses() {
        $good = $this->lifeFixture();
        foreach ( $good['ids'] as $id ) { $f = $good; $f['form']['command_id'] = $id; $o = $this->lifeOwner(); $this->lifeRelease( $o, $good ); $this->assertFalse( $this->lifeRead( $o, $f ), $id ); $this->assertLifeCleared( $o ); }
        foreach ( array( 'intent', 'form' ) as $part ) {
            $o = $this->lifeOwner(); $this->lifeRelease( $o, $good ); $this->assertTrue( $this->lifeRead( $o, $good ) ); $f = $good; $f[$part]['nonce'] .= '-changed';
            $this->assertFalse( $this->lifeFinish( $o, $f ) ); $this->assertLifeCleared( $o ); $this->assertFalse( $this->lifeFinish( $o, $good ) );
        }
    }
    public function test_all_ten_current_rows_are_validated_not_only_matching_receipt() {
        $good = $this->lifeFixture();
        foreach ( array_keys( $good['frame']['rows'] ) as $role ) {
            $f = $good; $f['frame']['rows'][$role]['value'] = 'malformed'; $f['frame']['rows'][$role]['octets'] = '9';
            $o = $this->lifeOwner(); $this->lifeRelease( $o, $good ); $this->assertTrue( $this->lifeRead( $o, $good ) );
            $this->assertFalse( $this->lifeFinish( $o, $f ), $role ); $this->assertLifeCleared( $o );
        }
    }
    public function test_matching_id_does_not_adopt_historical_actor_or_accept_malformed_read_intent() {
        $good = $this->lifeFixture(); list( $before, $proposal, $context, $scope ) = $this->admissionFixture();
        $proposal['actor_id']++; $proposal['command']['context_digest'] = $this->registration_class()::registration_context( $scope, $proposal );
        $raw = $this->registration_class()::register( $before['rows']['ledger']['value'], $scope, $proposal, 110 ); $this->assertIsString( $raw );
        $f = $good; $f['frame'] = $this->handoffRow( $f['frame'], 'ledger', $raw ); $o = $this->lifeOwner(); $this->lifeRelease( $o, $good );
        $this->assertTrue( $this->lifeRead( $o, $good ) ); $this->assertCandidateOnly( $this->lifeFinish( $o, $f ), 'domain_conflict' ); $this->assertLifeCleared( $o );
        foreach ( array( 'purpose' => 'register_closed', 'nonce' => '', 'observed_command_id' => $this->rid( 700 ), 'extra' => 'x' ) as $key => $value ) {
            foreach ( array( 'intent', 'form' ) as $part ) { $f = $good; $f[$part][$key] = $value; $o = $this->lifeOwner(); $this->lifeRelease( $o, $good ); $this->assertFalse( $this->lifeRead( $o, $f ) ); $this->assertLifeCleared( $o ); }
        }
    }
}
