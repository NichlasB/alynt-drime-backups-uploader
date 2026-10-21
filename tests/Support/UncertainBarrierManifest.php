<?php
/** Fixed source reads only, anchored in the unchanged predecessor closure. */
require_once __DIR__ . '/NativeQualificationSelectedManifest.php';
final class Alynt_Test_Uncertain_Barrier_Manifest {
    const DIGEST = '9a337c4aef74a1afe5b08f358d9705a423e4623c57b30e9fd5893a4b5b6bb309';
    public static function verify() {
        if ( 0 !== func_num_args() || ! Alynt_Test_Native_Qualification_Selected_Manifest::verify() ) return false;
        $base = realpath( dirname( __DIR__, 2 ) ); $path = $base . '/scripts/tests/native-qualification-uncertain-identities.json';
        if ( ! is_readable( $path ) || filesize( $path ) > 16384 ) return false;
        $raw = str_replace( "\r\n", "\n", file_get_contents( $path ) );
        if ( ! hash_equals( self::DIGEST, hash( 'sha256', $raw ) ) ) return false;
        $rows = json_decode( $raw, true, 3 ); if ( ! is_array( $rows ) || ! $rows || count( $rows ) > 24 ) return false;
        $bytes = 0;
        foreach ( $rows as $name => $hash ) {
            if ( ! is_string( $name ) || ! preg_match( '~^(?:tests/Support|scripts/tests)/[A-Za-z0-9_-]+\.(?:php|mjs)$~D', $name ) || ! is_string( $hash ) || ! preg_match( '/^[a-f0-9]{64}$/D', $hash ) ) return false;
            $file = realpath( $base . '/' . $name );
            if ( ! $file || 0 !== strpos( $file, $base . DIRECTORY_SEPARATOR ) || ! is_readable( $file ) || filesize( $file ) > 262144 || ( $bytes += filesize( $file ) ) > 2097152 || ! hash_equals( $hash, hash( 'sha256', str_replace( "\r\n", "\n", file_get_contents( $file ) ) ) ) ) return false;
        }
        return true;
    }
    private static function method( ReflectionMethod $m ) {
        $p = array(); foreach ( $m->getParameters() as $v ) $p[] = array( $v->getName(), (string) $v->getType(), $v->isPassedByReference(), $v->isVariadic(), $v->isOptional() );
        return array( realpath( $m->getFileName() ), $m->getStartLine(), $m->getEndLine(), $m->getModifiers(), (string) $m->getReturnType(), $m->returnsReference(), $p );
    }
    public static function composition() {
        if ( 0 !== func_num_args() || ! self::verify() || ! Alynt_Test_Native_Qualification_Manifest::composition() ) return false;
        require_once __DIR__ . '/UncertainBarrierOwner.php';
        $a = new ReflectionClass( 'Alynt_Test_Native_Qualification_Owner' ); $b = new ReflectionClass( 'Alynt_Test_Uncertain_Barrier_Owner' );
        if ( ! $b->isFinal() || $b->getParentClass() || $b->getInterfaceNames() !== $a->getInterfaceNames() || $b->getConstants() !== $a->getConstants() || $b->getTraitNames() !== array( 'Alynt_Test_Uncertain_Barrier_Observation' ) ) return false;
        $t = new ReflectionClass( 'Alynt_Test_Uncertain_Barrier_Observation' );
        if ( $t->getTraitNames() !== array( 'Alynt_Test_Native_Qualification_Observation' ) || ( $t->getTraitAliases()['terminal_source'] ?? null ) !== 'Alynt_Test_Native_Qualification_Observation::lifecycle_root' ) return false;
        $defaults = $b->getDefaultProperties();
        foreach ( array( 'terminal_busy' => false, 'terminal_spent' => false, 'terminal_failed' => false, 'terminal_taken' => false, 'terminal_event' => null ) as $k => $v ) {
            if ( ! array_key_exists( $k, $defaults ) || $defaults[$k] !== $v || ! $b->getProperty( $k )->isPrivate() ) return false; unset( $defaults[$k] );
        }
        $old = $a->getDefaultProperties(); ksort( $old ); ksort( $defaults ); if ( $old !== $defaults ) return false;
        $names = array(); foreach ( $a->getMethods() as $m ) $names[] = $m->getName();
        $aliases = ( new ReflectionClass( 'Alynt_Test_Native_Qualification_Observation' ) )->getTraitAliases();
        $names = array_unique( array_merge( $names, array_keys( $aliases ) ) );
        foreach ( $names as $name ) {
            if ( in_array( $name, array( '__construct', 'create', 'native_entry_available' ), true ) ) continue;
            $target = 'lifecycle_root' === $name ? 'terminal_source' : $name;
            if ( ! $b->hasMethod( $target ) || self::method( $a->getMethod( $name ) ) !== self::method( $b->getMethod( $target ) ) ) return false;
        }
        $allowed = array_merge( $names, array( 'terminal_source', 'take_uncertain_barrier_observation' ) );
        foreach ( $b->getMethods() as $m ) if ( ! in_array( $m->getName(), $allowed, true ) ) return false;
        foreach ( array( 'lifecycle_root', 'take_uncertain_barrier_observation' ) as $name ) if ( ! $b->getMethod( $name )->isPrivate() || realpath( __DIR__ . '/UncertainBarrierObservation.php' ) !== realpath( $b->getMethod( $name )->getFileName() ) ) return false;
        foreach ( array( $a, $b ) as $c ) { $m = $c->getMethod( 'native_entry_available' ); if ( PHP_VERSION_ID < 80100 ) $m->setAccessible( true ); $class = $c->getName(); if ( false !== $m->invoke( $class::create() ) ) return false; }
        return true;
    }
}
