<?php
/** Bounded support projection only. No raw exception, trace args, paths or payloads. */
final class Alynt_Test_Qualification_Native_Diagnostic {
    private static $progress;
    public static function remember( $p ) {
        self::$progress = null;
        if ( ! is_array( $p ) || array_keys( $p ) !== array( 'callback_clean', 'root_returned_true', 'guards_observed', 'nonce_creations_observed', 'protected_reads_observed', 'writes_observed' ) || ! is_bool( $p['callback_clean'] ) || ! is_bool( $p['root_returned_true'] ) ) return false;
        foreach ( array_keys( $p ) as $key ) if ( null !== ReflectionReference::fromArrayElement( $p, $key ) ) return false;
        foreach ( array( 'guards_observed' => 11, 'nonce_creations_observed' => 2, 'protected_reads_observed' => 40, 'writes_observed' => 1 ) as $key => $max ) if ( ! is_int( $p[$key] ) || $p[$key] < 0 || $p[$key] > $max ) return false;
        self::$progress = $p; return true; // Primitive diagnostics only; never used for acceptance or authority.
    }
    const CODES = array( 'qualification_guard_identity', 'qualification_early_io', 'qualification_bind_state', 'qualification_bind_budget', 'qualification_recheck', 'qualification_actual_root', 'qualification_nonce_domains', 'qualification_original_proposal', 'qualification_owned_handle', 'qualification_borrowed_auth', 'qualification_final_budget', 'qualification_channel', 'qualification_reentry', 'qualification_boot', 'qualification_root', 'qualification_bind_reuse', 'qualification_pair', 'qualification_final_reuse', 'qualification_eof', 'qualification_target', 'qualification_dropin', 'qualification_marker', 'qualification_isolation_path', 'qualification_core_diagnostic', 'qualification_core_environment', 'qualification_core_marker', 'qualification_genuine_core', 'qualification_escape', 'qualification_site_content', 'qualification_fixed_factory', 'qualification_denial', 'qualification_denial_no_observation', 'qualification_denial_no_rearm', 'qualification_denial_changed_ledger', 'qualification_positive_factory' );
    public static function failure( Throwable $error ) {
        $codes = array_merge( self::CODES, array( 'qualification_callback_state', 'qualification_root_return', 'qualification_guard_count', 'qualification_nonce_creation_count' ) );
        $code = in_array( $error->getMessage(), $codes, true ) ? $error->getMessage() : 'qualification_unclassified';
        $location = array( 'qualification-native-worker.php', 1 ); // Explicit unknown sentinel, not a claimed failing assertion.
        $frames = array_merge( array( array( 'file' => $error->getFile(), 'line' => $error->getLine() ) ), array_slice( $error->getTrace(), 0, 12 ) );
        foreach ( $frames as $f ) {
            $file = isset( $f['file'] ) && is_string( $f['file'] ) ? basename( $f['file'] ) : '';
            if ( in_array( $file, array( 'QualificationNativeWorker.php', 'qualification-native-worker.php', 'qualification-native-channel.php', 'qualification-native-cases.php' ), true ) && isset( $f['line'] ) && is_int( $f['line'] ) && $f['line'] > 0 && $f['line'] <= 10000 ) { $location = array( $file, $f['line'] ); break; }
        }
        $progress = self::$progress; self::$progress = null;
        return array( 'passed' => false, 'redacted_failure' => true, 'code' => $code, 'location' => $location, 'progress' => $progress );
    }
}
