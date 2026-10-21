<?php
/** Fixed bounded source identities, never supplied by a request/operator argument. */
final class Alynt_Test_Native_Lifecycle_Manifest {
    const DIGEST = '9ce248e71d7c1e8cacab1868a8eba7fc3d8b4e01ae880ad36d824a2d5a4888ab';
    public static function verify() {
        if ( 0 !== func_num_args() ) return false;
        $file = __DIR__ . '/native-lifecycle-proof-identities.json';
        if ( ! is_readable( $file ) || filesize( $file ) > 65536 ) return false;
        $raw = str_replace( "\r\n", "\n", file_get_contents( $file ) );
        if ( ! hash_equals( self::DIGEST, hash( 'sha256', $raw ) ) ) return false;
        $rows = json_decode( $raw, true, 4 ); $root = realpath( dirname( __DIR__, 2 ) ); $bytes = 0;
        if ( ! is_array( $rows ) || count( $rows ) < 30 || count( $rows ) > 250 ) return false;
        foreach ( $rows as $name => $hash ) {
            if ( ! is_string( $name ) || 1 !== preg_match( '~^(?:includes|scripts/tests)/[A-Za-z0-9_-]+\.(?:php|ps1)$~D', $name ) || ! is_string( $hash ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $hash ) ) return false;
            $path = realpath( $root . '/' . $name );
            if ( ! $path || 0 !== strpos( $path, $root . DIRECTORY_SEPARATOR ) || ! is_readable( $path ) || filesize( $path ) > 262144 || ( $bytes += filesize( $path ) ) > 4194304 ) return false;
            if ( ! hash_equals( $hash, hash( 'sha256', str_replace( "\r\n", "\n", file_get_contents( $path ) ) ) ) ) return false;
        }
        return true;
    }
    public static function composition() {
        $source = new ReflectionClass( Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle::class );
        $proof = new ReflectionClass( Alynt_Test_Native_Lifecycle_Owner::class );
        if ( ! $source->isFinal() || ! $proof->isFinal() || $source->getTraitNames() !== $proof->getTraitNames() || $source->getTraitAliases() !== $proof->getTraitAliases() || $source->getDefaultProperties() !== $proof->getDefaultProperties() || $source->getConstants() !== $proof->getConstants() ) return false;
        $a = array(); $b = array();
        foreach ( array( $source, $proof ) as $i => $class ) foreach ( $class->getMethods() as $m ) {
            if ( in_array( $m->name, array( '__construct', 'create', 'native_entry_available' ), true ) ) continue;
            $row = array( $m->getFileName(), $m->getStartLine(), $m->getEndLine(), $m->getModifiers(), $m->getNumberOfParameters(), $m->getNumberOfRequiredParameters() );
            if ( 0 === $i ) $a[$m->name] = $row; else $b[$m->name] = $row;
        }
        ksort( $a ); ksort( $b ); return $a === $b;
    }
}
