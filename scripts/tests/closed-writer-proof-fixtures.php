<?php
/** Explicit closed model loading and synthetic values, never real settings or permission. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
$repo = dirname( __DIR__, 2 );
foreach ( array( 'class-schedule-safety-option-cas', 'trait-schedule-safety-state-validation', 'trait-schedule-safety-state-transitions', 'class-schedule-safety-store', 'trait-cadence-authority-validation', 'trait-cadence-authority-mutations', 'trait-cadence-authority-dispatch', 'class-cadence-authority-store', 'trait-cadence-authority-lifecycle-preparation', 'class-cadence-authority-lifecycle-store', 'trait-consent-ledger-validation', 'trait-consent-ledger-commands', 'trait-consent-ledger-migration', 'trait-consent-ledger-api-boundary', 'class-cadence-consent-ledger', 'trait-closed-consent-provenance-validation', 'class-closed-consent-provenance-store', 'trait-closed-writer-barrier-records', 'trait-closed-writer-barrier-validation', 'trait-closed-writer-barrier-transitions', 'trait-closed-writer-barrier-commands', 'class-closed-writer-barrier-store' ) as $part ) require_once $repo . '/includes/' . $part . '.php';
function alynt_writer_model( $db, $enabled = true ) { return new Alynt_Drime_Backups_Uploader_Closed_Writer_Barrier_Store( new Alynt_Closed_Writer_Proof_CAS( $db ), '99999999-9999-4999-8999-999999999999', function () { return time(); }, $enabled ); }
function alynt_writer_id( $n ) { return sprintf( '55555555-5555-4555-8555-%012d', $n ); }
function alynt_writer_state_name() { return Alynt_Drime_Backups_Uploader_Schedule_Safety_Option_CAS::STATE_OPTION; }
function alynt_writer_ok( $r ) { return is_array( $r ) && isset( $r['outcome'] ) && 'committed' === $r['outcome'] && false === $r['dispatch_supported'] && false === $r['execution_supported']; }
function alynt_writer_closed( $db ) {
	$s = alynt_writer_model( $db )->snapshot();
	alynt_lifecycle_check( is_array( $s ) && 6 === $s['schema_version'] && false === $s['authority']['enabled'] && false === $s['lifecycle']['consent'] && null === $s['barrier']['authenticated_grant'] && array() === $s['barrier']['authenticated_receipts'], 'Closed-only writer invariant failed.' ); return $s;
}
function alynt_writer_target( $raw ) {
	$r = is_string( $raw ) ? json_decode( $raw, true ) : null;
	alynt_lifecycle_check( is_array( $r ) && array( 'auto_scan_enabled', 'polled_at' ) === array_keys( $r ) && is_bool( $r['auto_scan_enabled'] ) && is_int( $r['polled_at'] ) && $r['polled_at'] >= 0 && $r['polled_at'] < PHP_INT_MAX, 'Synthetic target shape invalid.' ); return $r;
}
function alynt_writer_prepare( $db, $n, $lifetime = 300 ) {
	$s = alynt_writer_closed( $db ); $raw = $db->value( 'probe_settings' ); $r = alynt_writer_target( $raw ); $r['auto_scan_enabled'] = ! $r['auto_scan_enabled']; $now = time();
	return array( 'command_id' => alynt_writer_id( $n ), 'site_uuid' => '99999999-9999-4999-8999-999999999999', 'enrollment_binding' => str_repeat( 'e', 64 ), 'expected_generation' => $s['lifecycle']['permission_generation'], 'kind' => 'settings_patch', 'target' => 'settings', 'fields' => array( 'auto_scan_enabled' ), 'before_hash' => hash( 'sha256', $raw ), 'after_hash' => hash( 'sha256', wp_json_encode( $r ) ), 'issued_at' => $now, 'expires_at' => $now + $lifetime );
}
function alynt_writer_begin( $db, $n, $lifetime = 300 ) {
	$c = alynt_writer_prepare( $db, $n, $lifetime ); $r = alynt_writer_model( $db )->begin( $c, function () { return true; } );
	alynt_lifecycle_check( alynt_writer_ok( $r ), 'Separately committed begin failed.' ); return $c;
}
function alynt_writer_seed( $db ) {
	$db->exec( 'DELETE FROM ' . $db->table . ' WHERE option_name<>?', array( 'probe_owner' ) );
	foreach ( array( 'probe_settings' => wp_json_encode( array( 'auto_scan_enabled' => false, 'polled_at' => 0 ) ), 'probe_unrelated' => 'preserved', 'cron' => 'fixture-only-cron' ) as $name => $raw ) $db->exec( 'INSERT INTO ' . $db->table . ' (option_name,option_value,autoload) VALUES (?,?,?)', array( $name, $raw, 'no' ) );
	$backend = new Alynt_Closed_Writer_Proof_CAS( $db ); $now = time();
	$old = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Lifecycle_Store( $backend, function () use ( $now ) { return $now; } );
	$s = $old->prepare_lifecycle( str_repeat( 'e', 64 ), 'every_15_minutes', $now + 900 ); alynt_lifecycle_check( is_array( $s ), 'Owned closed lifecycle seed failed.' );
	$closed = new Alynt_Drime_Backups_Uploader_Closed_Consent_Provenance_Store( $backend, '99999999-9999-4999-8999-999999999999', function () use ( $now ) { return $now; }, true );
	$s = $closed->prepare_closed( str_repeat( 'e', 64 ), $s['lifecycle']['permission_generation'], function () { return true; } ); alynt_lifecycle_check( is_array( $s ), 'Owned checkpoint seed failed.' );
	$s = alynt_writer_model( $db )->prepare_closed( str_repeat( 'e', 64 ), $s['lifecycle']['permission_generation'], function () { return true; } ); alynt_lifecycle_check( is_array( $s ), 'Owned schema-six seed failed.' ); return $s;
}
