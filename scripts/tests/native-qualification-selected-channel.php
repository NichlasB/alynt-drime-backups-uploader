<?php
/** Eight fixed selected-loss messages on inherited stdio; no supplied streams or fault mode. */
require_once __DIR__ . '/owned-stdio-io.php';
final class Alynt_Test_Native_Qualification_Selected_Channel {
    const DOMAIN = 'alynt.native-lifecycle-observation.selected.v1';
    const PROFILE = 'native_lifecycle_selected_commit_response_loss';
    private $attempt; private $root; private $stage = 0; private $bytes = 0;
    /** Validate the exact source result first; only a separate wire copy is reordered. */
    private static function project_native_result( $result ) {
        $source = array( 'candidate_only','storage_supported','dispatch_supported','execution_supported','retry_supported','authenticated','classification','barrier_verified','closure_proven' );
        if ( 1 !== func_num_args() || ! is_array( $result ) || array_keys( $result ) !== $source ) return false;
        foreach ( $source as $key ) {
            if ( null !== ReflectionReference::fromArrayElement( $result, $key ) ) return false;
            $expected = 'classification' === $key ? 'registered_closed' : 'barrier_verified' === $key;
            if ( $result[$key] !== $expected ) return false;
        }
        $wire = array();
        foreach ( array( 'classification','authenticated','barrier_verified','closure_proven','retry_supported','storage_supported','dispatch_supported','execution_supported','candidate_only' ) as $key ) $wire[$key] = $result[$key];
        return $wire;
    }
    private function send( $kind, $seq, array $more ) {
        $m = array_merge( array( 'domain' => self::DOMAIN, 'v' => 1, 'kind' => $kind, 'seq' => $seq, 'attempt' => $this->attempt ), $more );
        $line = json_encode( $m, JSON_UNESCAPED_SLASHES );
        if ( ! is_string( $line ) || strlen( $line ) > 4095 || ! preg_match( '/^[\x20-\x5b\x5d-\x7e]+$/D', $line ) || ( $this->bytes += strlen( $line ) + 1 ) > 32768 ) throw new RuntimeException( 'native_qualification_outbound' );
        $calls = 0; alynt_stdio_probe_write( STDOUT, $line . "\n", $calls );
    }
    private function receive( $kind, $seq, array $more ) {
        $calls = 0; $line = alynt_stdio_probe_read( $calls ); $m = json_decode( $line, true, 8 );
        if ( ! is_array( $m ) || json_encode( $m, JSON_UNESCAPED_SLASHES ) !== $line || ! preg_match( '/^[\x20-\x5b\x5d-\x7e]+$/D', $line ) || ( $this->bytes += strlen( $line ) + 1 ) > 32768 ) throw new RuntimeException( 'native_qualification_inbound' );
        if ( null === $this->attempt ) { $this->attempt = $m['attempt'] ?? null; if ( ! is_string( $this->attempt ) || ! preg_match( '/^[a-f0-9]{64}$/D', $this->attempt ) ) throw new RuntimeException( 'native_qualification_attempt' ); }
        $expected = array_merge( array( 'domain' => self::DOMAIN, 'v' => 1, 'kind' => $kind, 'seq' => $seq, 'attempt' => $this->attempt ), $more );
        if ( 'root' === $kind ) { $token = $m['root'] ?? null; if ( ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{64}$/D', $token ) || $token === $this->attempt ) throw new RuntimeException( 'native_qualification_root' ); $expected['root'] = $token; $this->root = $token; }
        if ( $expected !== $m ) throw new RuntimeException( 'native_qualification_echo' );
    }
    public function root() {
        if ( 0 !== func_num_args() || 0 !== $this->stage++ ) throw new RuntimeException( 'native_qualification_channel_reentry' );
        $identity = array( 'profile' => self::PROFILE, 'source' => Alynt_Test_Native_Qualification_Selected_Manifest::identities() );
        $this->receive( 'boot', 1, $identity ); $this->send( 'root_request', 1, $identity ); $this->receive( 'root', 2, $identity );
    }
    public function writer( array $roles, $remaining ) {
        if ( 2 !== func_num_args() || 1 !== $this->stage++ ) throw new RuntimeException( 'native_qualification_writer_reentry' );
        $more = array_merge( array( 'root' => $this->root ), $roles, array( 'remaining_ns' => $remaining, 'guard_index' => 9 ) );
        $this->send( 'bind_writer', 2, $more ); $this->receive( 'bound_writer', 3, $more );
    }
    public function barrier( array $roles, $root_remaining, $read_remaining ) {
        if ( 3 !== func_num_args() || 2 !== $this->stage++ ) throw new RuntimeException( 'native_qualification_barrier_reentry' );
        $more = array_merge( array( 'root' => $this->root ), $roles, array( 'root_remaining_ns' => $root_remaining, 'read_remaining_ns' => $read_remaining, 'guard_index' => 21 ) );
        $this->send( 'bind_barrier', 3, $more ); $this->receive( 'bound_barrier', 4, $more );
    }
    public function finish( array $facts ) {
        if ( 1 !== func_num_args() || 3 !== $this->stage++ ) throw new RuntimeException( 'native_qualification_final_reentry' );
        $projected = self::project_native_result( $facts['native_result'] ?? null );
        if ( false === $projected ) throw new RuntimeException( 'native_qualification_original_result' );
        $facts['native_result'] = $projected;
        $this->send( 'final', 4, array_merge( array( 'root' => $this->root, 'profile' => self::PROFILE, 'source' => Alynt_Test_Native_Qualification_Selected_Manifest::identities() ), $facts ) );
        $this->attempt = null; $this->root = null;
        if ( '' !== fread( STDIN, 1 ) || ! feof( STDIN ) ) throw new RuntimeException( 'native_qualification_eof' );
    }
}
