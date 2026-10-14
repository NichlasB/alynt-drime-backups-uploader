<?php
/** Fixed owned table scope, write fence and harmless observation-receipt persistence. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
function alynt_auth_write_fence( array $owned ) {
	$names = array_map( function ( $name ) { return preg_quote( $name, '/' ); }, array_values( $owned ) );
	return function ( $sql ) use ( $names ) {
		if ( 1 === preg_match( '/^\s*SELECT\b/i', $sql ) ) return $sql;
		if ( 1 === preg_match( '/^\s*SHOW\s+(?:FULL\s+)?COLUMNS\s+FROM\s+`?(?:' . implode( '|', $names ) . ')`?(?=\s|$)/i', $sql ) ) return $sql;
		$prefix = '(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM|CREATE\s+TABLE|DROP\s+TABLE)';
		alynt_lifecycle_check( 1 === preg_match( '/^\s*' . $prefix . '\s+`?(?:' . implode( '|', $names ) . ')`?(?=\s|\(|$)/i', $sql ), 'SQL operation outside owned auth fixtures denied.' );
		return $sql;
	};
}
function alynt_auth_owned( array $owned, $token ) {
	global $wpdb; $base = substr( $owned['options'], 0, -8 );
	alynt_lifecycle_check( 1 === preg_match( '/^wp_alynt_auth_probe_[a-f0-9]{12}$/D', $base ) && 1 === preg_match( '/^[a-f0-9]{24}$/D', $token ), 'Invalid owned auth fixture scope.' );
	foreach ( array( 'options', 'users', 'usermeta' ) as $key ) alynt_lifecycle_check( isset( $owned[$key] ) && $base . '_' . $key === $owned[$key], 'Auth fixture identifiers differ.' );
	alynt_lifecycle_check( $token === $wpdb->get_var( "SELECT option_value FROM {$owned['options']} WHERE option_name='probe_owner'" ), 'Owned auth marker missing.' );
}
function alynt_auth_receipt_name( $id ) {
	alynt_lifecycle_check( is_string( $id ) && 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', $id ), 'Invalid harmless receipt identity.' ); return 'probe_auth_receipt_' . $id;
}
function alynt_auth_read_receipt( $id ) {
	global $wpdb; $raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", alynt_auth_receipt_name( $id ) ) );
	alynt_lifecycle_check( '' === $wpdb->last_error, 'Harmless receipt read failed.' ); return null === $raw ? null : json_decode( $raw, true );
}
function alynt_auth_replace_state( $state ) {
	global $wpdb; $name = Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION;
	alynt_lifecycle_check( 1 === preg_match( '/^wp_alynt_auth_probe_[a-f0-9]{12}_options$/D', $wpdb->options ), 'Fixture state scope lost.' );
	alynt_lifecycle_check( false !== $wpdb->update( $wpdb->options, array( 'option_value' => wp_json_encode( $state ) ), array( 'option_name' => $name ) ), 'Auth state fixture replacement failed.' );
	foreach ( array( $name, 'alloptions', 'notoptions' ) as $key ) wp_cache_delete( $key, 'options' );
}
