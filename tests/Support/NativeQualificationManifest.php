<?php
/** Fixed repository reads only; no supplied paths, WordPress or native IO. */
require_once dirname( __DIR__, 2 ) . '/scripts/tests/native-lifecycle-proof-manifest.php';
final class Alynt_Test_Native_Qualification_Manifest {
    const DIGEST = '2d61b8b249e694c25d3b50f8c87af410b3339a8df56a608d4c9cc910348756e1';
    public static function identities() {
        if ( 0 !== func_num_args() ) return false;
        $path = dirname( __DIR__, 2 ) . '/scripts/tests/native-qualification-identities.json';
        if ( ! is_readable( $path ) || filesize( $path ) > 16384 ) return false;
        $raw = str_replace( "\r\n", "\n", file_get_contents( $path ) );
        if ( ! hash_equals( self::DIGEST, hash( 'sha256', $raw ) ) ) return false;
        $groups = json_decode( $raw, true, 4 );
        if ( ! is_array( $groups ) || array_keys( $groups ) !== array( 'production', 'composition', 'projection', 'protocol', 'transcript', 'eligibility', 'isolated_worker' ) ) return false;
        $ids = array(); foreach ( $groups as $key => $rows ) { if ( ! is_array( $rows ) || ! $rows || count( $rows ) > 32 ) return false; $ids[$key] = hash( 'sha256', json_encode( $rows, JSON_UNESCAPED_SLASHES ) ); }
        return $ids;
    }
    public static function verify() {
        if ( 0 !== func_num_args() || false === self::identities() || ! Alynt_Test_Native_Lifecycle_Manifest::verify() ) return false;
        $base = realpath( dirname( __DIR__, 2 ) ); $bytes = 0;
        $groups = json_decode( str_replace( "\r\n", "\n", file_get_contents( $base . '/scripts/tests/native-qualification-identities.json' ) ), true, 4 );
        foreach ( $groups as $rows ) foreach ( $rows as $name => $hash ) {
            if ( ! is_string( $name ) || 1 !== preg_match( '~^(?:includes|tests/Support|scripts/tests)/[A-Za-z0-9_-]+\.(?:php|mjs|json)$~D', $name ) || ! is_string( $hash ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $hash ) ) return false;
            $path = realpath( $base . '/' . $name );
            if ( ! $path || 0 !== strpos( $path, $base . DIRECTORY_SEPARATOR ) || ! is_readable( $path ) || filesize( $path ) > 262144 || ( $bytes += filesize( $path ) ) > 2097152 ) return false;
            if ( ! hash_equals( $hash, hash( 'sha256', str_replace( "\r\n", "\n", file_get_contents( $path ) ) ) ) ) return false;
        }
        return true;
    }
    private static function method( ReflectionMethod $m ) {
        $parameters = array(); foreach ( $m->getParameters() as $p ) $parameters[] = array( $p->getName(), (string) $p->getType(), $p->isPassedByReference(), $p->isVariadic(), $p->isOptional(), $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null );
        return array( realpath( $m->getFileName() ), $m->getStartLine(), $m->getEndLine(), $m->getModifiers(), (string) $m->getReturnType(), $m->returnsReference(), $parameters );
    }
    private static function methods( ReflectionClass $class, array $aliases ) {
        // PHP 7.4 enumerates nested aliases under original names and omits their class alias map.
        // Recover names from the separately verified direct trait; resolve every row by that name.
        $names = array_keys( $class->getTraitAliases() + $aliases );
        foreach ( $class->getMethods() as $method ) $names[] = $method->getName();
        $rows = array(); foreach ( array_unique( $names ) as $name ) $rows[$name] = self::method( $class->getMethod( $name ) );
        return $rows;
    }
    public static function composition() {
        if ( 0 !== func_num_args() || ! self::verify() ) return false;
        require_once __DIR__ . '/NativeQualificationOwner.php';
        $source = new ReflectionClass( Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle::class ); $proof = new ReflectionClass( Alynt_Test_Native_Qualification_Owner::class );
        if ( ! $source->isFinal() || ! $proof->isFinal() || $proof->getParentClass() || $proof->getInterfaceNames() !== $source->getInterfaceNames() || $proof->getConstants() !== $source->getConstants() ||
            $proof->getTraitNames() !== array( 'Alynt_Test_Native_Qualification_Observation' ) ) return false;
        $trait = new ReflectionClass( 'Alynt_Test_Native_Qualification_Observation' );
        if ( $trait->getTraitNames() !== array( 'Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_Methods', 'Alynt_Test_Qualification_Observation_Wrapper' ) ||
            $trait->getTraitAliases() !== array( 'qualification_source' => 'Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_Methods::qualify_admission_outcome', 'session_source' => 'Alynt_Drime_Backups_Uploader_Registration_Native_Lifecycle_Methods::open_lifecycle_session' ) + $source->getTraitAliases() ) return false;
        $defaults = $proof->getDefaultProperties();
        foreach ( array( 'observed_sessions' => array(), 'session_observation_busy' => false, 'session_observation_failed' => false, 'observation_taken' => false, 'qualification_observation_spent' => false, 'qualification_observation_interrupted' => false, 'qualification_observation' => null ) as $k => $v ) {
            if ( ! array_key_exists( $k, $defaults ) || $defaults[$k] !== $v || ! $proof->getProperty( $k )->isPrivate() ) return false; unset( $defaults[$k] );
        }
        $originalDefaults = $source->getDefaultProperties(); ksort( $defaults ); ksort( $originalDefaults ); if ( $defaults !== $originalDefaults ) return false;
        $a = self::methods( $source, array() ); $b = self::methods( $proof, $trait->getTraitAliases() );
        foreach ( array( 'qualify_admission_outcome' => array( 'qualification_source', 'Alynt_Test_Qualification_Observation_Wrapper' ), 'open_lifecycle_session' => array( 'session_source', 'Alynt_Test_Native_Qualification_Observation' ) ) as $name => $spec ) {
            if ( ! isset( $b[$spec[0]] ) || $b[$spec[0]] !== $a[$name] || $b[$name] !== self::method( new ReflectionMethod( $spec[1], $name ) ) ) return false;
            $b[$name] = $b[$spec[0]]; unset( $b[$spec[0]] );
        }
        $take = $proof->getMethod( 'take_native_qualification_observation' ); if ( ! $take->isPrivate() || 0 !== $take->getNumberOfParameters() ) return false; unset( $b[$take->name] );
        foreach ( array( '__construct', 'create', 'native_entry_available' ) as $name ) {
            if ( realpath( __DIR__ . '/NativeQualificationOwner.php' ) !== $b[$name][0] ) return false;
            if ( $b[$name][3] !== $a[$name][3] || $b[$name][4] !== $a[$name][4] || $b[$name][5] !== $a[$name][5] || $b[$name][6] !== $a[$name][6] ) return false;
            $b[$name] = $a[$name];
        }
        ksort( $a ); ksort( $b ); if ( $a !== $b ) return false;
        foreach ( array( array( $source->getName(), false ), array( $proof->getName(), false ) ) as $row ) { $m = new ReflectionMethod( $row[0], 'native_entry_available' ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true ); if ( ! $m->isPrivate() || $row[1] !== $m->invoke( $row[0]::create() ) ) return false; }
        return true;
    }
}
