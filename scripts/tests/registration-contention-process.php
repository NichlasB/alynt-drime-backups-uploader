<?php
/** Test supervisor for one fixed native peer. Never a production injection seam. */
if ( 'cli' !== PHP_SAPI || ! isset( $GLOBALS['alynt_core_proof'] ) ) exit( 1 );
final class Alynt_Registration_Contention_Process {
    private $process;
    private $channel;
    private $out = '';
    private $bytes = 0;
    private $exit;
    public function __construct( $mode, $before, $replacement ) {
        alynt_core_check( in_array( $mode, array( 'commit', 'rollback', 'timeout', 'disconnect', 'late' ), true ), 'Unknown owned peer mode.' );
        $server = stream_socket_server( 'tcp://127.0.0.1:0', $errno, $message ); alynt_core_check( is_resource( $server ), 'Owned loopback channel unavailable.' );
        try {
            $port = explode( ':', stream_socket_get_name( $server, false ) )[1];
            $command = array( PHP_BINARY, '-d', 'extension_dir=' . ini_get( 'extension_dir' ), '-d', 'extension=mysqli', '-d', 'display_errors=0', '-d', 'log_errors=0', __DIR__ . '/registration-contention-peer.php', $GLOBALS['argv'][2], $GLOBALS['alynt_core_proof']['prefix'], $GLOBALS['argv'][4], $mode, $port );
            // Windows proc pipes cannot reliably be made nonblocking. Events use TCP.
            $null = 'Windows' === PHP_OS_FAMILY ? 'NUL' : '/dev/null'; $pipes = array();
            $this->process = proc_open( $command, array( 0 => array( 'file', $null, 'r' ), 1 => array( 'file', $null, 'w' ), 2 => array( 'file', $null, 'w' ) ), $pipes );
            alynt_core_check( is_resource( $this->process ), 'Owned peer did not start.' );
            $this->channel = stream_socket_accept( $server, 3 ); alynt_core_check( is_resource( $this->channel ), 'Owned peer did not connect.' ); stream_set_blocking( $this->channel, false );
            $hello = $this->event( 'hello' ); alynt_core_check( $GLOBALS['argv'][4] === ( $hello['marker'] ?? null ), 'Owned peer channel identity differs.' );
            $this->send( json_encode( array( 'before' => $before, 'replacement' => $replacement ) ) );
        } catch ( Throwable $error ) { $this->abort(); throw $error; } finally { fclose( $server ); }
    }
    public function send( $command ) {
        alynt_core_check( is_string( $command ) && strlen( $command ) < 262143 && false === strpos( $command, "\n" ), 'Invalid owned peer command.' );
        $command .= "\n";
        alynt_core_check( strlen( $command ) === fwrite( $this->channel, $command ), 'Owned peer command write failed.' );
    }
    private function read() {
        $out = stream_get_contents( $this->channel ); $this->bytes += strlen( $out ); $this->out .= $out;
        alynt_core_check( $this->bytes <= 65536, 'Owned peer response ceiling exceeded.' );
        $s = proc_get_status( $this->process ); if ( ! $s['running'] && null === $this->exit ) $this->exit = $s['exitcode'];
        return $s['running'];
    }
    public function event( $expected ) {
        $end = hrtime( true ) + 10000000000;
        do {
            $running = $this->read(); $at = strpos( $this->out, "\n" );
            if ( false !== $at ) {
                $r = json_decode( substr( $this->out, 0, $at ), true ); $this->out = substr( $this->out, $at + 1 );
                alynt_core_check( is_array( $r ) && $expected === ( $r['event'] ?? null ), 'Owned peer event differs: ' . ( $r['event'] ?? 'invalid' ) . ( isset( $r['reason'] ) ? ' / ' . $r['reason'] : '' ) ); return $r;
            }
            alynt_core_check( $running, 'Owned peer exited before expected event.' ); usleep( 10000 );
        } while ( hrtime( true ) < $end );
        throw new RuntimeException( 'Owned peer event deadline.' );
    }
    public function finish() {
        $end = hrtime( true ) + 5000000000;
        do { if ( ! $this->read() ) break; usleep( 10000 ); } while ( hrtime( true ) < $end );
        alynt_core_check( null !== $this->exit && 0 === $this->exit && '' === $this->out, 'Owned peer did not finish cleanly.' );
        fclose( $this->channel ); $this->channel = null; proc_close( $this->process ); $this->process = null;
    }
    public function abort() {
        if ( ! is_resource( $this->process ) ) return;
        // Channel EOF wakes a held peer for rollback before fallback termination.
        if ( is_resource( $this->channel ) ) fclose( $this->channel ); $this->channel = null;
        $end = hrtime( true ) + 6000000000;
        do { $s = proc_get_status( $this->process ); if ( ! $s['running'] ) break; usleep( 10000 ); } while ( hrtime( true ) < $end );
        if ( $s['running'] ) proc_terminate( $this->process );
        proc_close( $this->process ); $this->process = null;
    }
    public function __destruct() { $this->abort(); }
}
