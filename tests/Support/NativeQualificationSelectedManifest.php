<?php
/** Fixed local source closure; no supplied path, WordPress bootstrap or native IO. */
require_once __DIR__ . '/NativeQualificationNoFaultManifest.php';
final class Alynt_Test_Native_Qualification_Selected_Manifest {
    const DIGEST = '444997f74d1d40207f0f546f9dee10b503b38948dd045cf5592ae9c8ba8d4658';
    private static function groups() {
        $path = dirname( __DIR__, 2 ) . '/scripts/tests/native-qualification-selected-identities.json';
        if ( ! is_readable( $path ) || filesize( $path ) > 16384 ) return false;
        $raw = str_replace( "\r\n", "\n", file_get_contents( $path ) );
        if ( ! hash_equals( self::DIGEST, hash( 'sha256', $raw ) ) ) return false;
        $groups = json_decode( $raw, true, 4 );
        return is_array( $groups ) && array_keys( $groups ) === array( 'production', 'composition', 'projection', 'protocol', 'transcript', 'eligibility', 'isolated_worker' ) ? $groups : false;
    }
    public static function identities() {
        if ( 0 !== func_num_args() || false === ( $groups = self::groups() ) ) return false;
        $result = array(); foreach ( $groups as $key => $rows ) { if ( ! is_array( $rows ) || ! $rows || count( $rows ) > 32 ) return false; $result[$key] = hash( 'sha256', json_encode( $rows, JSON_UNESCAPED_SLASHES ) ); }
        return $result;
    }
    public static function verify() {
        if ( 0 !== func_num_args() || false === self::identities() || ! Alynt_Test_Native_Qualification_No_Fault_Manifest::verify() ) return false;
        $base = realpath( dirname( __DIR__, 2 ) ); $bytes = 0;
        foreach ( self::groups() as $rows ) foreach ( $rows as $name => $hash ) {
            if ( ! is_string( $name ) || ! preg_match( '~^(?:tests/Support|scripts/tests)/[A-Za-z0-9_-]+\.(?:php|mjs|json|ps1)$~D', $name ) || ! is_string( $hash ) || ! preg_match( '/^[a-f0-9]{64}$/D', $hash ) ) return false;
            $path = realpath( $base . '/' . $name );
            if ( ! $path || 0 !== strpos( $path, $base . DIRECTORY_SEPARATOR ) || ! is_readable( $path ) || filesize( $path ) > 262144 || ( $bytes += filesize( $path ) ) > 2097152 || ! hash_equals( $hash, hash( 'sha256', str_replace( "\r\n", "\n", file_get_contents( $path ) ) ) ) ) return false;
        }
        return true;
    }
    public static function composition() {
        return 0 === func_num_args() && self::verify() && Alynt_Test_Native_Qualification_No_Fault_Manifest::composition();
    }
}
