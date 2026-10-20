<?php
/** Fixed isolated worker boundary. No CLI, WordPress bootstrap, native call or IPC. */
require_once __DIR__ . '/QualificationSourceManifest.php';
require_once __DIR__ . '/QualificationPrivateTranscript.php';
final class Alynt_Test_Qualification_Observation_Worker {
    use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;
    private $owner;
    private $context;
    private $spent = false;
    private function __construct( array $context ) {
        $this->owner = Alynt_Test_Qualification_Observation_Owner::create();
        $this->context = $context;
    }
    public static function create( $context ) {
        if ( 1 !== func_num_args() || ! Alynt_Test_Qualification_Source_Manifest::verify() || ! Alynt_Test_Qualification_Private_Transcript::context( $context ) ) return false;
        return new self( $context ); // Fixed class only, after complete source gate; no supplied owner/provider.
    }
    private static function slot( $owner, $name, ...$set ) {
        $property = new ReflectionProperty( Alynt_Test_Qualification_Observation_Owner::class, $name );
        if ( PHP_VERSION_ID < 80100 ) $property->setAccessible( true );
        if ( $set ) $property->setValue( $owner, $set[0] );
        return $property->getValue( $owner );
    }
    private static function empty_original_slots( $owner ) {
        foreach ( array( 'reader', 'native_outcome_writer', 'physical', 'read_physical', 'scope', 'before', 'proposal', 'context', 'after', 'recorded_at', 'native_outcome', 'admission_origin', 'admission_clock' ) as $name ) if ( null !== self::slot( $owner, $name ) ) return false;
        if ( array() !== self::slot( $owner, 'admission_ids' ) || 0 !== self::slot( $owner, 'native_outcome_tick' ) ) return false;
        foreach ( array( 'active', 'admission_join_busy', 'qualification_observation_interrupted' ) as $name ) if ( false !== self::slot( $owner, $name ) ) return false;
        foreach ( array( 'consumed', 'admission_qualification_spent', 'qualification_observation_spent', 'native_outcome_started', 'native_outcome_closed', 'native_cleanup_done' ) as $name ) if ( true !== self::slot( $owner, $name ) ) return false;
        return true;
    }
    private function discard() {
        if ( $this->owner instanceof Alynt_Test_Qualification_Observation_Owner ) self::slot( $this->owner, 'qualification_observation', null );
        $this->owner = null; $this->context = null;
    }
    /** Permanently closed execution entry; no hidden alternate native path in this foundation. */
    private function enter() {
        $this->spent = true;
        $this->discard();
        return false;
    }
    /** Private post-observation extraction only. Tests seed/clean synthetic state, never execute a request here. */
    private function take() {
        if ( $this->spent ) return false;
        $this->spent = true;
        try {
            if ( ! $this->owner instanceof Alynt_Test_Qualification_Observation_Owner ) return false;
            $receipt = self::slot( $this->owner, 'qualification_observation' );
            self::slot( $this->owner, 'qualification_observation', null ); // Erase before validation or any encoding attempt.
            if ( 0 !== func_num_args() || ! Alynt_Test_Qualification_Source_Manifest::verify() || ! self::empty_original_slots( $this->owner ) ) return false;
            return Alynt_Test_Qualification_Private_Transcript::encode( $this->context, $receipt );
        } catch ( Throwable $ignored ) {
            return false; // Inconclusive only; no exception/private graph exported or native cleanup attempted.
        } finally {
            $this->discard();
        }
    }
}
