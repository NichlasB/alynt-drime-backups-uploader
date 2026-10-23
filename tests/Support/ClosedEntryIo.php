<?php
/** Synthetic chunks/virtual clocks only. Never opens a stream or calls WordPress. */
final class Alynt_Test_Closed_Entry_Io {
    public static $bytes = ''; public static $position = 0; public static $sizes = array(); public static $reads = array();
    public static $opened = array(); public static $closed = 0; public static $handle; public static $fault = '';
    public static $mono = 1000000000; public static $utc = 2000000000;
    public static function clock( $as_number ) { alynt_entry_check( true === $as_number, 'fixed real monotonic API signature' ); return self::$mono; }
    public static function utc() { return self::$utc; }
    public static function resource( $value ) { return null !== self::$handle && $value === self::$handle; }
    public static function exists( $path ) { return false; } // Exact core drop-in metadata double, no disk probe.
    public static function open( $path, $mode ) {
        self::$opened[] = array( $path, $mode ); alynt_test_core_call( 'input_open' );
        alynt_entry_check( array( 'php://input', 'rb' ) === array( $path, $mode ), 'sole fixed input path/mode' );
        if ( 'open-false' === self::$fault ) return false;
        return self::$handle = new stdClass();
    }
    public static function read( $handle, $length ) {
        alynt_entry_check( self::resource( $handle ), 'reads use only owned synthetic handle' );
        self::$reads[] = $length; alynt_test_core_call( 'input_read' );
        if ( 'read-false' === self::$fault ) return false;
        if ( 'read-throw' === self::$fault ) throw new RuntimeException( 'synthetic private IO detail' );
        if ( 'read-warning' === self::$fault ) trigger_error( 'synthetic private IO detail', E_USER_WARNING );
        if ( 'stall' === self::$fault ) return '';
        $size = self::$sizes ? array_shift( self::$sizes ) : $length;
        $out = substr( self::$bytes, self::$position, min( $size, $length ) ); self::$position += strlen( $out );
        return $out;
    }
    public static function eof( $handle ) {
        alynt_entry_check( self::resource( $handle ), 'EOF uses owned handle' );
        return 'never-eof' !== self::$fault && self::$position === strlen( self::$bytes );
    }
    public static function metadata( $handle ) {
        alynt_entry_check( self::resource( $handle ), 'metadata uses owned handle' );
        $meta = array( 'wrapper_type' => 'PHP', 'stream_type' => 'Input', 'mode' => 'rb', 'uri' => 'php://input', 'timed_out' => false, 'blocked' => true, 'seekable' => false, 'eof' => self::eof( $handle ), 'unread_bytes' => 0 );
        if ( 'timeout' === self::$fault ) $meta['timed_out'] = true;
        if ( 'unknown-meta' === self::$fault ) unset( $meta['blocked'] );
        if ( 'wrong-uri' === self::$fault ) $meta['uri'] = 'php://temp';
        if ( 'false-eof' === self::$fault ) $meta['eof'] = ! $meta['eof'];
        return $meta;
    }
    public static function close( $handle ) {
        alynt_entry_check( self::resource( $handle ), 'close uses owned handle' );
        ++self::$closed; alynt_test_core_call( 'input_close' ); self::$handle = null;
        if ( 'close-throw' === self::$fault ) throw new RuntimeException( 'synthetic private close detail' );
        return 'close-false' !== self::$fault;
    }
}
