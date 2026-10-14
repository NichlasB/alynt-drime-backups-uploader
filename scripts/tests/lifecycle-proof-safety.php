<?php
/** Shared read-only audits/assertions for explicitly approved owned-table proofs. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
function alynt_lifecycle_check( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); }
function alynt_lifecycle_error( $result, $code ) { return is_wp_error( $result ) && $code === $result->get_error_code(); }
function alynt_lifecycle_actual_digest( $table ) {
	global $wpdb;
	alynt_lifecycle_check( 1 === preg_match( '/^[A-Za-z0-9_]+$/D', $table ), 'Invalid actual options identifier.' );
	$rows = $wpdb->get_results( "SELECT option_name,SHA2(option_value,256) AS digest,autoload FROM {$table} ORDER BY option_name", ARRAY_A );
	alynt_lifecycle_check( '' === $wpdb->last_error && is_array( $rows ), 'Actual-option digest read failed.' );
	return hash( 'sha256', wp_json_encode( $rows ) ); // Never emit option values or names.
}
function alynt_lifecycle_owned( $table, $token ) {
	global $wpdb;
	alynt_lifecycle_check( 1 === preg_match( '/^wp_alynt_lifecycle_probe_[a-f0-9]{12}$/D', $table ) && preg_match( '/^[a-f0-9]{24}$/D', $token ), 'Invalid fixture identity.' );
	alynt_lifecycle_check( $token === $wpdb->get_var( "SELECT option_value FROM {$table} WHERE option_name='probe_owner'" ), 'Fixture ownership missing.' );
}
function alynt_provenance_users_digest() {
	global $wpdb; $digests = array();
	foreach ( array( array( $wpdb->users, 'ID', 'ID,user_login,user_pass,user_nicename,user_email,user_url,user_registered,user_activation_key,user_status,display_name' ), array( $wpdb->usermeta, 'umeta_id', 'umeta_id,user_id,meta_key,meta_value' ) ) as $spec ) {
		list( $table, $order, $columns ) = $spec; alynt_lifecycle_check( 1 === preg_match( '/^[A-Za-z0-9_]+$/D', $table ), 'Invalid actual user table identifier.' );
		$rows = $wpdb->get_col( "SELECT SHA2(JSON_ARRAY({$columns}),256) FROM {$table} ORDER BY {$order}" );
		alynt_lifecycle_check( '' === $wpdb->last_error && is_array( $rows ), 'Actual-user digest read failed.' );
		$digests[] = hash( 'sha256', wp_json_encode( $rows ) ); // Never emit private fields or row hashes.
	}
	return $digests;
}
