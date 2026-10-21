<?php
/** Fixed repository identities only. No WordPress bootstrap, native IO or supplied paths/hashes. */
require_once __DIR__ . '/QualificationSourceManifest.php';
final class Alynt_Test_Qualification_Native_Manifest {
    private static function groups() {
        $groups = array(
            'native_worker' => array( 'tests/Support/QualificationNativeManifest.php', 'tests/Support/QualificationNativeWorker.php', 'tests/Support/QualificationNativeDiagnostic.php', 'scripts/tests/qualification-native-worker.php', 'scripts/tests/qualification-native-cases.php', 'scripts/tests/qualification-native-preflight.php' ),
            'native_channel' => array( 'tests/Support/QualificationNativeProtocol.php', 'scripts/tests/qualification-native-channel.php', 'scripts/tests/qualification-native-grammar.mjs', 'scripts/tests/qualification-native-control.mjs', 'scripts/tests/qualification-native-identity.mjs', 'scripts/tests/owned-stdio-io.php', 'scripts/tests/owned-stdio-lines.mjs' ),
            'native_supervisor' => array( 'scripts/tests/qualification-native-proof.mjs', 'scripts/tests/qualification-native-process.mjs', 'scripts/tests/qualification-native-relay.mjs', 'scripts/tests/qualification-native-proof.ps1', 'scripts/tests/selected-native-fixtures.php', 'scripts/tests/fixed-core-proof-storage.php', 'scripts/tests/fixed-core-proof-fixtures.php', 'scripts/tests/fixed-core-proof-cases.php', 'scripts/tests/fixed-core-proof-denials.php', 'scripts/tests/selected-native-tree-proof.mjs', 'scripts/tests/owned-stdio-worker.php', 'scripts/tests/registration-join-proof-fixtures.php', 'scripts/tests/registration-outcome-proof-fixtures.php', 'scripts/tests/registration-admission-proof-fixtures.php', 'scripts/tests/registration-proof-fixtures.php', 'scripts/tests/native-scan-proof-fixtures.php', 'scripts/tests/native-scan-ledger-fixtures.php', 'scripts/tests/native-observer-proof-fixtures.php', 'scripts/tests/relay-no-fault-frames.mjs', 'scripts/tests/observation-passive-sink.mjs', 'scripts/tests/observation-native-registry.mjs', 'scripts/tests/native-witness-profile.mjs', 'scripts/tests/fixed-drain-grammar.mjs', 'scripts/tests/selected-native-lifetime.mjs', 'scripts/tests/owned-stdio-runtime.mjs' ),
        );
        $groups['native_worker'][] = 'scripts/tests/qualification-native-bootstrap.php';
        $groups['native_worker'][] = 'scripts/tests/qualification-native-prelude.php';
        $groups['native_supervisor'][] = 'scripts/tests/qualification-native-stages.mjs';
        return $groups;
    }
    public static function identities() {
        if ( 0 !== func_num_args() ) return false;
        $p = dirname( __DIR__, 2 ) . '/scripts/tests/qualification-native-identities.json';
        if ( ! is_readable( $p ) || filesize( $p ) > 4096 ) return false;
        $m = json_decode( file_get_contents( $p ), true, 4 );
        if ( ! is_array( $m ) || array_keys( $m ) !== array( 'production', 'composition', 'projection', 'isolated_worker', 'isolated_transcript', 'native_worker', 'native_channel', 'native_supervisor' ) ) return false;
        foreach ( $m as $v ) if ( ! is_string( $v ) || 64 !== strlen( $v ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $v ) ) return false;
        if ( array_slice( $m, 0, 5 ) !== array( 'production' => Alynt_Test_Qualification_Source_Manifest::PRODUCTION, 'composition' => Alynt_Test_Qualification_Source_Manifest::PROOF, 'projection' => Alynt_Test_Qualification_Source_Manifest::PROJECTION, 'isolated_worker' => Alynt_Test_Qualification_Source_Manifest::WORKER, 'isolated_transcript' => Alynt_Test_Qualification_Source_Manifest::TRANSCRIPT ) ) return false;
        return $m;
    }
    private static function digest( $group ) {
        $groups = self::groups(); if ( ! is_string( $group ) || ! isset( $groups[$group] ) ) return false;
        $rows = array(); $bytes = 0; $root = realpath( dirname( __DIR__, 2 ) );
        foreach ( $groups[$group] as $name ) {
            $p = realpath( $root . '/' . $name );
            if ( false === $p || 0 !== strpos( $p, $root . DIRECTORY_SEPARATOR ) || ! is_readable( $p ) || filesize( $p ) > 262144 || ( $bytes += filesize( $p ) ) > 2097152 ) return false;
            $rows[] = array( $name, str_replace( "\r\n", "\n", file_get_contents( $p ) ) );
        }
        return hash( 'sha256', json_encode( $rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
    }
    public static function verify() {
        if ( 0 !== func_num_args() || false === ( $m = self::identities() ) || ! Alynt_Test_Qualification_Source_Manifest::verify() ) return false;
        foreach ( array_keys( self::groups() ) as $group ) if ( self::digest( $group ) !== $m[$group] ) return false;
        return true;
    }
}
