<?php
/** One bounded synthetic private frame. NOT an IPC adapter, authority or outcome classifier. */
require_once __DIR__ . '/QualificationSourceManifest.php';
final class Alynt_Test_Qualification_Private_Transcript {
    use Alynt_Drime_Backups_Uploader_Closed_Writer_Contract_Privacy;
    const DOMAIN = 'alynt.qualification-observation.isolated.v1';
    const MAX_BYTES = 4096;
    private $context;
    private $line = '';
    private $bytes = 0;
    private $frame;
    private $failed = false;
    private $spent = false;
    private function __construct( array $context ) { $this->context = $context; }
    public static function context( $c ) {
        if ( ! is_array( $c ) || array_keys( $c ) !== array( 'attempt', 'root', 'pair' ) ) return false;
        foreach ( $c as $value ) if ( ! is_string( $value ) || 64 !== strlen( $value ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $value ) ) return false;
        return 3 === count( array_unique( $c ) );
    }
    private static function receipt( $r ) {
        if ( ! is_array( $r ) || array_keys( $r ) !== array( 'valid', 'entries', 'returns', 'kind', 'result', 'sealed', 'empty_slots', 'capture_unchanged', 'io_unchanged' ) || 1 !== $r['entries'] || 1 !== $r['returns'] || 'closed_array' !== $r['kind'] ) return false;
        foreach ( array( 'valid', 'sealed', 'empty_slots', 'capture_unchanged', 'io_unchanged' ) as $k ) if ( true !== $r[$k] ) return false;
        $v = $r['result'];
        if ( ! is_array( $v ) || array_keys( $v ) !== array( 'historical_outcome', 'client_release_claimed', 'current_acceptance_claimed', 'request_lifecycle_closed_claimed', 'diagnostic_candidate_only', 'candidate_only', 'authenticated', 'barrier_verified', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported' ) || ! in_array( $v['historical_outcome'], array( 'committed', 'uncertain', 'rolled_back', 'not_written', 'unsupported' ), true ) ) return false;
        foreach ( array_slice( $v, 1 ) as $value ) if ( ! is_bool( $value ) ) return false;
        foreach ( array( 'diagnostic_candidate_only', 'authenticated', 'barrier_verified', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported' ) as $k ) if ( false !== $v[$k] ) return false;
        return true === $v['candidate_only'] && true === $v['request_lifecycle_closed_claimed'] && ( ! $v['current_acceptance_claimed'] || ( 'committed' === $v['historical_outcome'] && $v['client_release_claimed'] ) );
    }
    private static function envelope( array $c, array $receipt ) {
        return array( 'domain' => self::DOMAIN, 'v' => 1, 'kind' => 'qualification_observation', 'seq' => 1, 'attempt' => $c['attempt'], 'root' => $c['root'], 'pair' => $c['pair'], 'production' => Alynt_Test_Qualification_Source_Manifest::PRODUCTION, 'composition' => Alynt_Test_Qualification_Source_Manifest::PROOF, 'projection' => Alynt_Test_Qualification_Source_Manifest::PROJECTION, 'worker' => Alynt_Test_Qualification_Source_Manifest::WORKER, 'transcript' => Alynt_Test_Qualification_Source_Manifest::TRANSCRIPT, 'isolated_only' => true, 'original_slots_empty' => true, 'root_invocation_proven' => false, 'root_erasure_proven' => false, 'authentication_proven' => false, 'native_proven' => false, 'ipc_proven' => false, 'storage_supported' => false, 'dispatch_supported' => false, 'execution_supported' => false, 'observation' => $receipt );
    }
    public static function encode( $context, $receipt ) {
        if ( 2 !== func_num_args() || ! self::context( $context ) || ! self::receipt( $receipt ) ) return false;
        $line = json_encode( self::envelope( $context, $receipt ), JSON_UNESCAPED_SLASHES ) . "\n";
        return strlen( $line ) <= self::MAX_BYTES ? $line : false;
    }
    public static function create( $context ) { return 1 === func_num_args() && self::context( $context ) ? new self( $context ) : false; }
    private function fail() { $this->failed = true; $this->line = ''; $this->frame = null; $this->context = null; return false; }
    public function feed( $bytes ) {
        if ( $this->spent || $this->failed || null !== $this->frame || 1 !== func_num_args() || ! is_string( $bytes ) || '' === $bytes || strlen( $bytes ) > self::MAX_BYTES - $this->bytes ) return $this->fail();
        $this->bytes += strlen( $bytes );
        for ( $i = 0, $n = strlen( $bytes ); $i < $n; ++$i ) {
            $byte = ord( $bytes[$i] );
            if ( 10 === $byte ) {
                if ( $i !== $n - 1 ) return $this->fail();
                $m = json_decode( $this->line, true, 8, JSON_BIGINT_AS_STRING );
                if ( ! is_array( $m ) || ! isset( $m['observation'] ) || ! self::receipt( $m['observation'] ) || $m !== self::envelope( $this->context, $m['observation'] ) || json_encode( $m, JSON_UNESCAPED_SLASHES ) !== $this->line ) return $this->fail();
                $this->frame = $m; $this->line = '';
            } elseif ( $byte < 32 || $byte > 126 || 92 === $byte || strlen( $this->line ) >= self::MAX_BYTES - 1 ) {
                return $this->fail();
            } else { $this->line .= $bytes[$i]; }
        }
        return true;
    }
    public function finish() {
        if ( $this->spent ) return false;
        $this->spent = true;
        $result = 0 === func_num_args() && ! $this->failed && '' === $this->line && is_array( $this->frame ) ? $this->frame : false;
        $this->fail(); // Explicit EOF consumes/erases even a valid frame; never a process-exit/cleanup certificate.
        return $result;
    }
}
