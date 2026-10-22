<?php
/** Socket-free fixed failure projection. Never exports exception messages or native handles. */
function alynt_uncertain_helper_failure( $role, $stage, Throwable $error ) {
    $path = __DIR__ . '/uncertain-barrier-helper-diagnostics.json';
    if ( ! in_array( $role, array( 'blocker', 'monitor' ), true ) || ! is_readable( $path ) || filesize( $path ) > 4096 ) return false;
    $policy = json_decode( file_get_contents( $path ), true );
    if ( ! is_array( $policy ) || ! in_array( $stage, array_merge( $policy['common'], $policy[$role] ), true ) ) return false;
    $database = $error instanceof mysqli_sql_exception;
    $reason = $database ? 'database' : $error->getMessage();
    if ( ! in_array( $reason, $policy['reasons'], true ) ) $reason = 'unknown';
    $code = $database && in_array( $error->getCode(), $policy['codes'], true ) ? $error->getCode() : 0;
    return array( 'stage' => $stage, 'reason' => $reason, 'code' => $code );
}
