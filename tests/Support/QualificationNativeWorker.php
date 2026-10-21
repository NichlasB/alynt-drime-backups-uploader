<?php
/** Fixed proof-only genuine entry. No public operation, supplied owner or callback. */
require_once __DIR__ . '/QualificationNativeManifest.php';
require_once __DIR__ . '/QualificationNativeProtocol.php';
require_once __DIR__ . '/QualificationNativeDiagnostic.php';
final class Alynt_Test_Qualification_Native_Worker {
    use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;
    private $owner;
    private $entered = false;
    private $taken = false;
    private $channel;
    private $gates = array();
    private $creates = 0;
    private $pin;
    private $capture;
    private $capture_id;
    private $writer;
    private $bound_at;
    private $failed = false;
    private $callback_failure;
    private function __construct() { $this->owner = Alynt_Test_Qualification_Observation_Owner::create(); }
    public static function create() { return 0 === func_num_args() && Alynt_Test_Qualification_Native_Manifest::verify() ? new self() : false; }
    private function slot( $name, ...$set ) {
        $p = new ReflectionProperty( Alynt_Test_Qualification_Observation_Owner::class, $name ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true );
        if ( $set ) $p->setValue( $this->owner, $set[0] ); return $p->getValue( $this->owner );
    }
    private function enter( $method, $body ) {
        if ( $this->entered ) return false;
        $this->entered = true;
        if ( 2 !== func_num_args() || ! Alynt_Test_Qualification_Native_Manifest::verify() ) return false;
        $m = new ReflectionMethod( Alynt_Test_Qualification_Observation_Owner::class, 'admit_retained' ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true );
        return $m->invoke( $this->owner, $method, $body ); // Only fixed original retained root; never invoke qualifier separately.
    }
    private function pin() { return array_map( function ( $k ) { return $this->slot( $k ); }, array( 'admission_origin', 'admission_ids', 'admission_clock', 'before', 'proposal', 'context', 'after', 'recorded_at' ) ); }
    private function empty_slots() {
        foreach ( array( 'reader', 'native_outcome_writer', 'physical', 'read_physical', 'scope', 'before', 'proposal', 'context', 'after', 'recorded_at', 'native_outcome', 'admission_origin', 'admission_clock' ) as $k ) if ( null !== $this->slot( $k ) ) return false;
        if ( array() !== $this->slot( 'admission_ids' ) || 0 !== $this->slot( 'native_outcome_tick' ) ) return false;
        foreach ( array( 'active', 'admission_join_busy', 'qualification_observation_interrupted' ) as $k ) if ( false !== $this->slot( $k ) ) return false;
        foreach ( array( 'consumed', 'admission_qualification_spent', 'qualification_observation_spent', 'native_outcome_started', 'native_outcome_closed', 'native_cleanup_done' ) as $k ) if ( true !== $this->slot( $k ) ) return false;
        return true;
    }
    private function take() {
        if ( $this->taken ) return false; $this->taken = true;
        $r = $this->slot( 'qualification_observation' ); $this->slot( 'qualification_observation', null );
        return 0 === func_num_args() && Alynt_Test_Qualification_Native_Manifest::verify() && $this->empty_slots() ? $r : false;
    }
    private function probe( $life, $action ) {
        $stack = array_column( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 14 ), 'function' );
        if ( ! in_array( 'wp_verify_nonce', $stack, true ) ) { if ( in_array( 'wp_create_nonce', $stack, true ) ) ++$this->creates; return $life; }
        try {
            $this->gates[] = $action; $n = count( $this->gates ); $auth = $GLOBALS['wpdb']->dbh;
            alynt_core_assert( $n <= 10 && $this->slot( 'scope' )['db'] === $GLOBALS['wpdb'], 'qualification_guard_identity' );
            if ( 1 === $n ) alynt_core_assert( 0 === $this->slot( 'protected_reads' ), 'qualification_early_io' );
            if ( 2 === $n ) $this->capture = $this->slot( 'reader' );
            if ( 7 === $n ) {
                $this->writer = $this->slot( 'reader' ); $this->pin = $this->pin(); $start = $this->slot( 'started' ); $now = hrtime( true );
                $closed = false; try { $unused = $this->capture->thread_id; } catch ( Error $e ) { $closed = true; }
                $roles = array( 'auth' => $auth->thread_id, 'capture' => $this->capture_id, 'writer' => $this->writer->thread_id );
                alynt_core_assert( $closed && Alynt_Test_Qualification_Native_Protocol::roles( array_values( $roles ) ) && $now >= $start && $now - $start <= 4000000000 && 'idle' === $this->slot( 'phase' ) && 20 === $this->slot( 'protected_reads' ) && 0 === $this->slot( 'writes' ), 'qualification_bind_state' );
                $this->bound_at = hrtime( true ); $this->channel->bind( $roles, (string) ( 10000000000 - ( $now - $start ) ) );
                alynt_core_assert( hrtime( true ) - $this->bound_at <= 1000000000 && hrtime( true ) - $start <= 4000000000, 'qualification_bind_budget' );
            }
            if ( 2 === $n ) $this->capture_id = $this->capture->thread_id;
            if ( 8 === $n ) alynt_core_assert( $this->pin === $this->pin() && 'idle' === $this->slot( 'phase' ) && 20 === $this->slot( 'protected_reads' ) && 0 === $this->slot( 'writes' ) && hrtime( true ) - $this->slot( 'started' ) <= 4000000000, 'qualification_recheck' );
        } catch ( Throwable $error ) { $this->failed = true; $this->callback_failure = $error; throw $error; }
        return $life;
    }
    private function check_callback() {
        $error = $this->callback_failure; $this->callback_failure = null;
        if ( $error instanceof Throwable ) throw $error; // Outside the original root's expected refusal catch.
    }
    private function require_root( $actual ) {
        Alynt_Test_Qualification_Native_Diagnostic::remember( array( 'callback_clean' => ! $this->failed, 'root_returned_true' => true === $actual, 'guards_observed' => count( $this->gates ), 'nonce_creations_observed' => $this->creates, 'protected_reads_observed' => $this->slot( 'protected_reads' ), 'writes_observed' => $this->slot( 'writes' ) ) );
        $this->check_callback();
        if ( $this->failed ) throw new RuntimeException( 'qualification_callback_state' );
        if ( true !== $actual ) throw new RuntimeException( 'qualification_root_return' );
        if ( 10 !== count( $this->gates ) ) throw new RuntimeException( 'qualification_guard_count' );
        if ( 1 !== $this->creates ) throw new RuntimeException( 'qualification_nonce_creation_count' );
        return true; // Same conjunction as before, now with separate non-authorizing failure predicates.
    }
    private function run( $form ) {
        $db = alynt_scan_db(); $before = alynt_registration_frame( $db ); $auth = $GLOBALS['wpdb']->dbh;
        $facts = $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row();
        require_once dirname( __DIR__, 2 ) . '/scripts/tests/qualification-native-channel.php'; $this->channel = new Alynt_Test_Qualification_Native_Channel();
        $this->channel->root(); $filter = function ( $life, $action ) { return $this->probe( $life, $action ); }; add_filter( 'nonce_life', $filter, 10, 2 );
        try {
            $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
            try { $actual = $this->enter( 'POST', $form ); }
            finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = false; remove_filter( 'nonce_life', $filter, 10 ); }
            $this->require_root( $actual );
            $small = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( get_current_blog_id(), 'register_closed' );
            $f = array_map( 'strval', $this->pin[4]['command'] ); $f['nonce'] = 'pending'; $parsed = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::parse( $f ); $command = $parsed['action'];
            alynt_core_assert( $small !== $command && array( $small, $small, $small, $small, $small, $command, $small, $command, $small, $command ) === $this->gates, 'qualification_nonce_domains' );
            alynt_core_assert( $this->pin[4] === alynt_admission_receipt( $db, $before ), 'qualification_original_proposal' );
            foreach ( array( $this->capture, $this->writer ) as $client ) { $closed = false; try { $client->query( 'SELECT 1' ); } catch ( Error $e ) { $closed = true; } alynt_core_assert( $closed, 'qualification_owned_handle' ); }
            alynt_core_assert( $facts === $auth->query( 'SELECT CONNECTION_ID(),@@autocommit,@@in_transaction,@@tx_read_only' )->fetch_row(), 'qualification_borrowed_auth' );
            foreach ( Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows() as $def ) alynt_ledger_nowait( $db, $def[0], false );
            $receipt = $this->take(); $m = array( 'root_returned' => $actual, 'pre_guards' => count( array_slice( $this->gates, 0, 8 ) ), 'post_guards' => count( array_slice( $this->gates, 8 ) ), 'protected_reads' => $this->slot( 'protected_reads' ), 'writes' => $this->slot( 'writes' ), 'original_slots_empty' => $this->empty_slots(), 'observation' => $receipt );
            alynt_core_done( 'qualification_genuine_same_root_actual_observation_closed_handles_proposal' );
            alynt_core_assert( hrtime( true ) >= $this->slot( 'started' ) && hrtime( true ) - $this->slot( 'started' ) < 10000000000, 'qualification_final_budget' );
            $this->channel->finish( $m );
        } finally {
            remove_filter( 'nonce_life', $filter, 10 ); $db->close();
            $this->slot( 'qualification_observation', null ); $this->owner = null; $this->pin = null; $this->capture = null; $this->writer = null; $this->channel = null; $this->gates = array(); $this->callback_failure = null;
        }
    }
}
