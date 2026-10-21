<?php
/** Fixed local source closure; no supplied path, WordPress bootstrap or native IO. */
require_once __DIR__ . '/NativeQualificationManifest.php';
require_once __DIR__ . '/QualificationNativeManifest.php';
require_once __DIR__ . '/UncertainBarrierManifest.php';
final class Alynt_Test_Uncertain_Barrier_No_Fault_Manifest {
    const DIGEST = '59862a0515d5441adaa19fd36a98a4a8800a105077c51d958df1ef605e19b6b2';
    private static function groups() {
        $path = dirname( __DIR__, 2 ) . '/scripts/tests/uncertain-barrier-no-fault-identities.json';
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
        if ( 0 !== func_num_args() || false === self::identities() || ! Alynt_Test_Native_Qualification_Manifest::verify() || ! Alynt_Test_Qualification_Native_Manifest::verify() || ! Alynt_Test_Uncertain_Barrier_Manifest::verify() ) return false;
        $base = realpath( dirname( __DIR__, 2 ) ); $bytes = 0;
        foreach ( self::groups() as $rows ) foreach ( $rows as $name => $hash ) {
            if ( ! is_string( $name ) || ! preg_match( '~^(?:tests/Support|scripts/tests)/[A-Za-z0-9_-]+\.(?:php|mjs|json|ps1)$~D', $name ) || ! is_string( $hash ) || ! preg_match( '/^[a-f0-9]{64}$/D', $hash ) ) return false;
            $path = realpath( $base . '/' . $name );
            if ( ! $path || 0 !== strpos( $path, $base . DIRECTORY_SEPARATOR ) || ! is_readable( $path ) || filesize( $path ) > 262144 || ( $bytes += filesize( $path ) ) > 2097152 || ! hash_equals( $hash, hash( 'sha256', str_replace( "\r\n", "\n", file_get_contents( $path ) ) ) ) ) return false;
        }
        return true;
    }
    public static function composition() {
        if ( 0 !== func_num_args() || ! self::verify() || ! Alynt_Test_Uncertain_Barrier_Manifest::composition() ) return false;
        require_once dirname( __DIR__, 2 ) . '/scripts/tests/uncertain-barrier-no-fault-owner.php';
        $closed = new ReflectionClass( Alynt_Test_Uncertain_Barrier_Owner::class ); $proof = new ReflectionClass( Alynt_Test_Uncertain_Barrier_No_Fault_Owner::class );
        if ( ! $proof->isFinal() || $proof->getParentClass() || $proof->getInterfaceNames() !== $closed->getInterfaceNames() || $proof->getConstants() !== $closed->getConstants() || $proof->getDefaultProperties() !== $closed->getDefaultProperties() || $proof->getTraitNames() !== $closed->getTraitNames() || $proof->getTraitAliases() !== $closed->getTraitAliases() ) return false;
        $map = new ReflectionMethod( Alynt_Test_Native_Qualification_Manifest::class, 'methods' ); if ( PHP_VERSION_ID < 80100 ) $map->setAccessible( true );
        $aliases = ( new ReflectionClass( 'Alynt_Test_Uncertain_Barrier_Observation' ) )->getTraitAliases() + ( new ReflectionClass( 'Alynt_Test_Native_Qualification_Observation' ) )->getTraitAliases();
        $a = $map->invoke( null, $closed, $aliases ); $b = $map->invoke( null, $proof, $aliases );
        foreach ( array( '__construct', 'create', 'native_entry_available' ) as $name ) {
            if ( realpath( dirname( __DIR__, 2 ) . '/scripts/tests/uncertain-barrier-no-fault-owner.php' ) !== $b[$name][0] || array_slice( $a[$name], 3 ) !== array_slice( $b[$name], 3 ) ) return false;
            $b[$name] = $a[$name];
        }
        ksort( $a ); ksort( $b ); if ( $a !== $b ) return false;
        foreach ( array( array( $closed, false ), array( $proof, true ) ) as $row ) { $m = $row[0]->getMethod( 'native_entry_available' ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true ); $class = $row[0]->getName(); if ( ! $m->isPrivate() || $row[1] !== $m->invoke( $class::create() ) ) return false; }
        return true;
    }
}
