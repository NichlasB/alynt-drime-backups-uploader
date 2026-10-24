<?php
/** Fixed owned-proof SQL policy. Never loaded by the plugin or an HTTP entry. */
function alynt_bootstrap_query_kind( $sql, $prefix ) {
    if ( ! is_string( $sql ) || strlen( $sql ) > 4096 || ! is_string( $prefix ) || 1 !== preg_match( '/\Awp_alynt_bootstrap_probe_[a-f0-9]{12}_\z/D', $prefix ) ) return 'unknown';
    $table = preg_quote( $prefix . 'options', '/' );
    // One exact useful read, not arbitrary SELECT, keys, projections or identifiers.
    if ( "SELECT option_value FROM {$prefix}options WHERE option_name = 'proof_safe' LIMIT 1" === $sql ) return 'safe';
    if ( 1 === preg_match( '/\ASELECT option_name, option_value FROM ' . $table . ' WHERE autoload IN \( \'[a-z-]+\'(?:, \'[a-z-]+\')* \)\z/D', $sql ) ) return 'bulk_autoload';
    if ( "SELECT option_name, option_value FROM {$prefix}options" === $sql ) return 'bulk_all';
    if ( false !== strpos( $sql, 'alynt_drime_backups_settings' ) || false !== strpos( $sql, 'alynt_drime_backups_schedule_safety_state' ) ) return 'protected_key';
    return 'unknown'; // Includes writes, joins, comments, unions and application tables.
}
