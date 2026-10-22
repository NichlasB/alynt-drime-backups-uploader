<?php
/** Source-only successor preflight, no authentication, socket or WordPress bootstrap. */
require_once __DIR__ . '/CacheWitnessNativeManifest.php';
final class Alynt_Test_Cache_Contention_Native_Manifest {
    const DIGEST = '6e5101b4f308d0e191abdcb143ecf79adb730f8804b7c8a5f8b33aa3c5943998';
    public static function verify() {
        if ( 0 !== func_num_args() || ! Alynt_Test_Cache_Witness_Native_Manifest::verify() ) return false;
        $base = realpath( dirname( __DIR__, 2 ) ); $file = $base . '/scripts/tests/cache-contention-native-identities.json';
        if ( ! is_readable( $file ) || filesize( $file ) > 16384 ) return false;
        $raw = str_replace( "\r\n", "\n", file_get_contents( $file ) );
        if ( ! hash_equals( self::DIGEST, hash( 'sha256', $raw ) ) ) return false;
        $groups = json_decode( $raw, true, 4 ); $bytes = 0;
        if ( ! is_array( $groups ) || array_keys( $groups ) !== array( 'model','integration' ) ) return false;
        foreach ( $groups as $rows ) {
            if ( ! is_array( $rows ) || ! $rows || count( $rows ) > 32 ) return false;
            foreach ( $rows as $name => $hash ) {
                if ( ! is_string( $name ) || ! preg_match( '~^scripts/tests/[A-Za-z0-9_-]+\.(?:mjs|php)$~D', $name ) || ! is_string( $hash ) || ! preg_match( '/^[a-f0-9]{64}$/D', $hash ) ) return false;
                $path = realpath( $base . '/' . $name );
                if ( ! $path || 0 !== strpos( $path, $base . DIRECTORY_SEPARATOR ) || ! is_readable( $path ) || filesize( $path ) > 262144 || ( $bytes += filesize( $path ) ) > 2097152 || ! hash_equals( $hash, hash( 'sha256', str_replace( "\r\n", "\n", file_get_contents( $path ) ) ) ) ) return false;
            }
        }
        return true;
    }
}
