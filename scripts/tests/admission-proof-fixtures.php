<?php
/** Fixed developer-only model loading and seed; no real settings or provenance. */
if ( ! defined( 'SHORTINIT' ) || ! SHORTINIT ) exit( 1 );
require_once __DIR__ . '/closed-writer-proof-fixtures.php'; require_once __DIR__ . '/closed-writer-proof-storage.php';
foreach ( array( 'class-closed-writer-admission-contract', 'class-closed-writer-admission-snapshot', 'class-closed-writer-admission-projection', 'trait-closed-writer-admission-records', 'trait-closed-writer-admission-validation', 'class-closed-writer-admission-model', 'class-closed-writer-admission-continuation', 'trait-closed-writer-admission-request', 'class-closed-writer-admission-boundary' ) as $part ) require_once dirname( __DIR__, 2 ) . '/includes/' . $part . '.php';
function alynt_admission_seed( $db ) {
	// Only these two fixture rows reset; never remove fixture roles, users or sessions.
	foreach ( array( alynt_writer_state_name(), 'probe_settings' ) as $name ) $db->exec( 'DELETE FROM ' . $db->table . ' WHERE option_name=?', array( $name ) );
	$raw = wp_json_encode( array( 'site_uuid' => Alynt_Auth_Proof_Ports::SITE, 'auto_scan_enabled' => true, 'polled_at' => 0, 'unrelated' => 'preserved' ) );
	$db->exec( 'INSERT INTO ' . $db->table . ' (option_name,option_value,autoload) VALUES (?,?,?)', array( 'probe_settings', $raw, 'no' ) );
	$backend = new Alynt_Closed_Writer_Proof_CAS( $db ); $now = time();
	$old = new Alynt_Drime_Backups_Uploader_Cadence_Authority_Lifecycle_Store( $backend, function () use ( $now ) { return $now; } );
	$s = $old->prepare_lifecycle( str_repeat( 'e', 64 ), 'every_15_minutes', $now + 900 ); alynt_lifecycle_check( is_array( $s ), 'Admission lifecycle seed failed.' );
	$closed = new Alynt_Drime_Backups_Uploader_Closed_Consent_Provenance_Store( $backend, Alynt_Auth_Proof_Ports::SITE, function () use ( $now ) { return $now; }, true );
	$s = $closed->prepare_closed( str_repeat( 'e', 64 ), $s['lifecycle']['permission_generation'], function () { return true; } ); alynt_lifecycle_check( is_array( $s ), 'Admission checkpoint seed failed.' );
	$s = alynt_writer_model( $db )->prepare_closed( str_repeat( 'e', 64 ), $s['lifecycle']['permission_generation'], function () { return true; } ); alynt_lifecycle_check( is_array( $s ), 'Admission schema-six seed failed.' ); return $s;
}
function alynt_admission_state( $db ) { return json_decode( $db->value( alynt_writer_state_name() ), true, 16 ); }
function alynt_admission_closed( $db, $pending ) {
	$s = alynt_admission_state( $db );
	alynt_lifecycle_check( Alynt_Drime_Backups_Uploader_Closed_Writer_Admission_Model::valid( $s, Alynt_Auth_Proof_Ports::SITE ) && 'closed_writer_admission_fixture_v1' === $s['writer']['source'] && null === $s['writer']['authenticated_grant'] && array() === $s['writer']['authenticated_receipts'] && array() === $s['writer']['internal_receipts'] && false === $s['authority']['enabled'] && false === $s['lifecycle']['consent'] && $pending === ( null !== $s['writer']['pending'] ), 'Admission closed fixture invariants failed.' ); return $s;
}
function alynt_admission_upgrade( $factory ) {
	// Start deterministic success scenarios early in a real clock second. Never
	// freeze/rewind the clock or retry a command whose acknowledgement is unknown.
	$limit = microtime( true ) + 1.1; while ( microtime( true ) - floor( microtime( true ) ) > 0.08 && microtime( true ) < $limit ) usleep( 10000 );
	$old = alynt_admission_seed( $factory->db ); $b = $factory->boundary(); $form = $factory->issue( $b, 'upgrade_closed' ); $r = $b->consume( 'POST', $form );
	alynt_lifecycle_check( is_array( $r ) && 'observed_current' === $r['outcome'], 'Native admission upgrade failed at ' . $factory->phase . '.' );
	$s = alynt_admission_closed( $factory->db, false ); alynt_lifecycle_check( $old['barrier'] === $s['legacy_writer'], 'Admission promoted or lost frozen evidence.' ); return $b;
}
function alynt_admission_target( $db, $enabled ) {
	$r = json_decode( $db->value( 'probe_settings' ), true );
	alynt_lifecycle_check( is_array( $r ) && $enabled === $r['auto_scan_enabled'] && 0 === $r['polled_at'] && 'preserved' === $r['unrelated'] && Alynt_Auth_Proof_Ports::SITE === $r['site_uuid'], 'Fixed target or unrelated bytes changed.' );
}
