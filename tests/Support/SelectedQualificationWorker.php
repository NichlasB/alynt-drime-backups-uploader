<?php
/** Dormant selected extraction. Positive tests seed synthetic state; no genuine root is invoked. */
require_once __DIR__ . '/SelectedQualificationProtocol.php';
final class Alynt_Test_Selected_Qualification_Worker {
    use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;
    private $owner;
    private $context;
    private $spent = false;
    private $root_returned;
    private $callback_clean = false;
    private $nonce_creations = 0;
    private $gates = array();
    private function __construct( array $context ) { $this->owner = Alynt_Test_Qualification_Observation_Owner::create(); $this->context = $context; }
    public static function create( $context ) {
        return 1 === func_num_args() && Alynt_Test_Selected_Qualification_Protocol::context( $context ) && Alynt_Test_Selected_Qualification_Manifest::verify() ? new self( $context ) : false;
    }
    private function slot( $name, ...$set ) {
        $p = new ReflectionProperty( Alynt_Test_Qualification_Observation_Owner::class, $name ); if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true );
        if ( $set ) $p->setValue( $this->owner, $set[0] ); return $p->getValue( $this->owner );
    }
    private function empty_slots() {
        foreach ( array( 'reader', 'native_outcome_writer', 'physical', 'read_physical', 'scope', 'before', 'proposal', 'context', 'after', 'recorded_at', 'native_outcome', 'admission_origin', 'admission_clock' ) as $k ) if ( null !== $this->slot( $k ) ) return false;
        if ( array() !== $this->slot( 'admission_ids' ) || 0 !== $this->slot( 'native_outcome_tick' ) ) return false;
        foreach ( array( 'active', 'admission_join_busy', 'qualification_observation_interrupted' ) as $k ) if ( false !== $this->slot( $k ) ) return false;
        foreach ( array( 'consumed', 'admission_qualification_spent', 'qualification_observation_spent', 'native_outcome_started', 'native_outcome_closed', 'native_cleanup_done' ) as $k ) if ( true !== $this->slot( $k ) ) return false;
        return true;
    }
    private function discard() {
        if ( $this->owner instanceof Alynt_Test_Qualification_Observation_Owner ) $this->slot( 'qualification_observation', null );
        $this->owner = null; $this->context = null; $this->root_returned = null; $this->callback_clean = false; $this->nonce_creations = 0; $this->gates = array();
    }
    private function enter() { $this->spent = true; $this->discard(); return false; } // No original root/WordPress/native execution, under any arguments.
    private function take() {
        if ( $this->spent ) return false; $this->spent = true;
        try {
            if ( ! $this->owner instanceof Alynt_Test_Qualification_Observation_Owner ) return false;
            $r = $this->slot( 'qualification_observation' ); $this->slot( 'qualification_observation', null ); // Consume before every validation.
            if ( 0 !== func_num_args() || ! Alynt_Test_Selected_Qualification_Manifest::verify() || ! $this->empty_slots() || false !== $this->root_returned || true !== $this->callback_clean || 1 !== $this->nonce_creations || array( 'small', 'small', 'small', 'small', 'small', 'command', 'small', 'command' ) !== $this->gates ) return false;
            $c = $this->context;
            $m = array( 'domain' => Alynt_Test_Selected_Qualification_Protocol::DOMAIN, 'v' => 1, 'kind' => 'final', 'seq' => 3, 'attempt' => $c['attempt'], 'root' => $c['root'], 'pair' => $c['pair'], 'profile' => Alynt_Test_Selected_Qualification_Protocol::PROFILE, 'source' => Alynt_Test_Selected_Qualification_Manifest::identities(), 'root_returned' => $this->root_returned, 'pre_guards' => count( $this->gates ), 'post_guards' => 0, 'protected_reads' => $this->slot( 'protected_reads' ), 'writes' => $this->slot( 'writes' ), 'original_slots_empty' => $this->empty_slots(), 'observation' => $r );
            return Alynt_Test_Selected_Qualification_Protocol::line( $m ); // Synthetic claims only; no root or erasure provenance.
        } catch ( Throwable $ignored ) { return false; }
        finally { $this->discard(); }
    }
}
