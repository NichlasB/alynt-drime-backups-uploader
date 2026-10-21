<?php
/** Fixed owned inherited stdio, no supplied streams or operation selector. */
require_once __DIR__ . '/owned-stdio-io.php';
require_once dirname( __DIR__, 2 ) . '/tests/Support/SelectedQualificationProtocol.php';
final class Alynt_Test_Selected_Qualification_Native_Channel {
    private $bytes = 0;
    private $frames = 0;
    private $attempt;
    private $root;
    private $pair;
    private $stage = 0;
    private function message( $m = null ) {
        $calls = 0;
        if ( null === $m ) { $s = alynt_stdio_probe_read( $calls ); $m = Alynt_Test_Selected_Qualification_Protocol::parse( $s ); }
        else { $s = Alynt_Test_Selected_Qualification_Protocol::line( $m ); if ( is_string( $s ) ) $s = substr( $s, 0, -1 ); }
        if ( false === $m || ! is_string( $s ) || ++$this->frames > 6 || ( $this->bytes += strlen( $s ) + 1 ) > 32768 ) throw new RuntimeException( 'qualification_channel' );
        if ( 0 === $calls ) alynt_stdio_probe_write( STDOUT, $s . "\n", $calls );
        return $m;
    }
    private function send( $kind, $seq, array $more ) {
        return $this->message( array_merge( array( 'domain' => Alynt_Test_Selected_Qualification_Protocol::DOMAIN, 'v' => 1, 'kind' => $kind, 'seq' => $seq, 'attempt' => $this->attempt ), $more ) );
    }
    public function root() {
        if ( 0 !== $this->stage++ ) throw new RuntimeException( 'qualification_reentry' );
        $m = $this->message(); if ( 'boot' !== $m['kind'] ) throw new RuntimeException( 'qualification_boot' ); $this->attempt = $m['attempt'];
        $this->send( 'root_request', 1, array( 'source' => Alynt_Test_Selected_Qualification_Manifest::identities() ) );
        $m = $this->message(); if ( 'root' !== $m['kind'] || $m['attempt'] !== $this->attempt ) throw new RuntimeException( 'qualification_root' ); $this->root = $m['root'];
    }
    public function bind( array $roles, $remaining ) {
        if ( 1 !== $this->stage++ ) throw new RuntimeException( 'qualification_bind_reuse' );
        $this->send( 'bind', 2, array_merge( array( 'root' => $this->root ), $roles, array( 'remaining_ns' => $remaining ) ) );
        $m = $this->message(); if ( 'bound' !== $m['kind'] || $m['attempt'] !== $this->attempt || $m['root'] !== $this->root || $m['writer'] !== $roles['writer'] ) throw new RuntimeException( 'qualification_pair' );
        $this->pair = $m['pair'];
    }
    public function finish( array $facts ) {
        if ( 2 !== $this->stage++ ) throw new RuntimeException( 'qualification_final_reuse' );
        $this->send( 'final', 3, array_merge( array( 'root' => $this->root, 'pair' => $this->pair, 'profile' => Alynt_Test_Selected_Qualification_Protocol::PROFILE, 'source' => Alynt_Test_Selected_Qualification_Manifest::identities() ), $facts ) );
        $this->attempt = null; $this->root = null; $this->pair = null;
        if ( '' !== fread( STDIN, 1 ) || ! feof( STDIN ) ) throw new RuntimeException( 'qualification_eof' );
    }
}
