<?php
/** Fixed socket-free source gate. Existing no-fault identities are never replaced. */
require_once __DIR__ . '/QualificationNativeManifest.php';
final class Alynt_Test_Selected_Qualification_Manifest {
    public static function identities() {
        if ( 0 !== func_num_args() ) return false;
        $path = dirname( __DIR__, 2 ) . '/scripts/tests/selected-qualification-identities.json';
        if ( ! is_readable( $path ) || filesize( $path ) > 4096 ) return false;
        $m = json_decode( file_get_contents( $path ), true, 4 );
        $old = Alynt_Test_Qualification_Native_Manifest::identities();
        if ( ! is_array( $old ) || ! is_array( $m ) || array_keys( $m ) !== array_merge( array_keys( $old ), array( 'selected_worker', 'selected_channel', 'selected_supervisor', 'selected_relay', 'selected_registry' ) ) || array_slice( $m, 0, 8 ) !== $old ) return false;
        foreach ( $m as $v ) if ( ! is_string( $v ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $v ) ) return false;
        return $m;
    }
    public static function verify() {
        if ( 0 !== func_num_args() ) return false;
        try {
            $expected = self::identities();
            if ( false === $expected || ! Alynt_Test_Qualification_Native_Manifest::verify() ) return false;
            $root = realpath( dirname( __DIR__, 2 ) ); $path = $root . '/scripts/tests/selected-qualification-groups.json';
            if ( ! is_readable( $path ) || filesize( $path ) > 4096 ) return false;
            $groups = json_decode( file_get_contents( $path ), true, 4 );
            if ( ! is_array( $groups ) || array_keys( $groups ) !== array_slice( array_keys( $expected ), 8 ) ) return false;
            foreach ( $groups as $key => $names ) {
                if ( ! is_array( $names ) || count( $names ) < 1 || count( $names ) > 32 ) return false;
                $rows = array(); $bytes = 0;
                foreach ( $names as $name ) {
                    if ( ! is_string( $name ) || strlen( $name ) > 160 || 1 !== preg_match( '~^(?:tests/Support|scripts/tests)/[A-Za-z0-9_-]+\.(?:php|mjs|json)$~D', $name ) ) return false;
                    $file = realpath( $root . '/' . $name );
                    if ( false === $file || 0 !== strpos( $file, $root . DIRECTORY_SEPARATOR ) || ! is_readable( $file ) || filesize( $file ) > 262144 || ( $bytes += filesize( $file ) ) > 2097152 ) return false;
                    $rows[] = array( $name, str_replace( "\r\n", "\n", file_get_contents( $file ) ) );
                }
                if ( hash( 'sha256', json_encode( $rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) !== $expected[$key] ) return false;
            }
            return true;
        } catch ( Throwable $ignored ) { return false; }
    }
}
