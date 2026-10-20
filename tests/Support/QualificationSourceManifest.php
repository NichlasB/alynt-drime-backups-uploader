<?php
/** Read-only bounded repository-source identity. No WordPress, storage or native IO. */
require_once __DIR__ . '/QualificationObservationOwner.php';
final class Alynt_Test_Qualification_Source_Manifest {
    const PRODUCTION = '22d43c6d3293213f0b287fc2e744852c715d763443e99c425b9458190812d13e';
    const PROOF = '351c0d8ad977067cae2211a9c05489b25e8bae207d2e4f50c98fc03c9260db04';
    const PROJECTION = '8c43fd880c6cc4950ee900f994d6a64d32e6aa0449827b72cfbd69f628ba4170';
    private static function token_digest( $code ) {
        $tokens = array(); foreach ( token_get_all( '<?php ' . $code ) as $t ) {
            if ( is_array( $t ) && in_array( $t[0], array( T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) continue;
            $tokens[] = is_array( $t ) ? array( token_name( $t[0] ), $t[1] ) : $t;
        }
        return hash( 'sha256', json_encode( $tokens ) );
    }
    private static function method( ReflectionMethod $m ) {
        $file = realpath( $m->getFileName() ); $root = realpath( dirname( __DIR__, 2 ) );
        if ( false === $file || 0 !== strpos( $file, $root . DIRECTORY_SEPARATOR ) || filesize( $file ) > 262144 ) throw new LogicException( 'Source boundary unavailable.' );
        $lines = file( $file ); $code = implode( '', array_slice( $lines, $m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1 ) );
        $params = array(); foreach ( $m->getParameters() as $p ) $params[] = array( $p->getName(), (string) $p->getType(), $p->isPassedByReference(), $p->isVariadic(), $p->isOptional(), $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null );
        return array( 'file' => str_replace( '\\', '/', substr( $file, strlen( $root ) + 1 ) ), 'start' => $m->getStartLine(), 'end' => $m->getEndLine(), 'body' => self::token_digest( $code ), 'modifiers' => $m->getModifiers(), 'return' => (string) $m->getReturnType(), 'reference' => $m->returnsReference(), 'parameters' => $params );
    }
    private static function snapshot( $class ) {
        $r = new ReflectionClass( $class ); $methods = array(); foreach ( $r->getMethods() as $m ) $methods[$m->getName()] = self::method( $m ); ksort( $methods );
        $defaults = $r->getDefaultProperties(); $properties = array(); foreach ( $r->getProperties() as $p ) $properties[$p->getName()] = array( $p->getModifiers(), (string) $p->getType(), array_key_exists( $p->getName(), $defaults ), $defaults[$p->getName()] ?? null ); ksort( $properties );
        $constants = array(); foreach ( $r->getReflectionConstants() as $c ) $constants[$c->getName()] = array( $c->getModifiers(), $c->getValue() ); ksort( $constants );
        $traits = array(); $walk = function ( ReflectionClass $type ) use ( &$walk, &$traits ) { foreach ( $type->getTraits() as $name => $trait ) if ( ! isset( $traits[$name] ) ) { $names = $trait->getTraitNames(); sort( $names ); $aliases = $trait->getTraitAliases(); ksort( $aliases ); $traits[$name] = array( $names, $aliases ); $walk( $trait ); } }; $walk( $r ); ksort( $traits );
        $direct = $r->getTraitNames(); sort( $direct ); $aliases = $r->getTraitAliases(); ksort( $aliases ); $interfaces = $r->getInterfaceNames(); sort( $interfaces );
        return array( 'final' => $r->isFinal(), 'parent' => false !== $r->getParentClass(), 'interfaces' => $interfaces, 'direct_traits' => $direct, 'traits' => $traits, 'aliases' => $aliases, 'methods' => $methods, 'properties' => $properties, 'constants' => $constants );
    }
    private static function compare( array $source, array $proof ) {
        if ( ! hash_equals( self::PRODUCTION, hash( 'sha256', json_encode( $source ) ) ) || ! hash_equals( self::PROOF, hash( 'sha256', json_encode( $proof ) ) ) ) return false;
        $observer = 'Alynt_Test_Qualification_Observation_Wrapper';
        if ( ! isset( $proof['methods']['qualification_source'], $proof['methods']['qualify_admission_outcome'] ) || $proof['methods']['qualify_admission_outcome'] !== self::method( new ReflectionMethod( $observer, 'qualify_admission_outcome' ) ) || ( $proof['aliases']['qualification_source'] ?? null ) !== 'Alynt_Drime_Backups_Uploader_Registration_Admission_Qualification::qualify_admission_outcome' ) return false;
        $proof['methods']['qualify_admission_outcome'] = $proof['methods']['qualification_source']; unset( $proof['methods']['qualification_source'], $proof['aliases']['qualification_source'] );
        if ( ( $proof['properties']['qualification_observation_spent'] ?? null ) !== array( ReflectionProperty::IS_PRIVATE, '', true, false ) || ( $proof['properties']['qualification_observation_interrupted'] ?? null ) !== array( ReflectionProperty::IS_PRIVATE, '', true, false ) || ( $proof['properties']['qualification_observation'] ?? null ) !== array( ReflectionProperty::IS_PRIVATE, '', true, null ) || ( $proof['traits'][$observer] ?? null ) !== array( array(), array() ) ) return false;
        unset( $proof['properties']['qualification_observation_spent'], $proof['properties']['qualification_observation_interrupted'], $proof['properties']['qualification_observation'], $proof['traits'][$observer] );
        $at = array_search( $observer, $proof['direct_traits'], true ); if ( false === $at ) return false; array_splice( $proof['direct_traits'], $at, 1 );
        foreach ( array( '__construct', 'create', 'admit', 'admit_retained', 'pin' ) as $name ) {
            if ( ! isset( $proof['methods'][$name], $source['methods'][$name] ) || 'tests/Support/QualificationObservationOwner.php' !== $proof['methods'][$name]['file'] ) return false;
            foreach ( array( 'file', 'start', 'end' ) as $k ) $proof['methods'][$name][$k] = $source['methods'][$name][$k];
        }
        return $source === $proof;
    }
    public static function verify() {
        if ( 0 !== func_num_args() ) return false;
        try {
            $file = __DIR__ . '/QualificationObservationProjection.php';
            if ( ! is_readable( $file ) || filesize( $file ) > 262144 || ! hash_equals( self::PROJECTION, self::token_digest( substr( file_get_contents( $file ), 5 ) ) ) ) return false;
            return self::compare( self::snapshot( Alynt_Drime_Backups_Uploader_Registration_Native_Admission::class ), self::snapshot( Alynt_Test_Qualification_Observation_Owner::class ) );
        }
        catch ( Throwable $ignored ) { return false; }
    }
}
