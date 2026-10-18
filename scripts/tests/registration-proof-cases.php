<?php
/** Genuine core positive and domain controls, not an artificial public success. */
function alynt_registration_cases() {
    foreach ( array( 'wp_generate_uuid4', 'wp_create_nonce', 'wp_rand', 'wp_verify_nonce' ) as $fn ) {
        alynt_core_assert( 0 === strpos( realpath( ( new ReflectionFunction( $fn ) )->getFileName() ), realpath( ABSPATH . 'wp-includes' ) . DIRECTORY_SEPARATOR ), 'Registration API is not genuine core.' );
    }
    $values = alynt_registration_values(); alynt_scan_seed( $values ); alynt_core_login(); $db = alynt_scan_db();
    try {
        $frame = alynt_registration_frame( $db ); $form = alynt_registration_form();
        $GLOBALS['alynt_core_proof']['events'] = array();
        $observe = function ( $life, $action ) { $GLOBALS['alynt_core_proof']['events'][] = 'nonce:' . ( 0 === strpos( $action, 'alynt_drime_backups_scan_registration_command_v2:' ) ? 'command' : 'issue' ); return $life; };
        add_filter( 'nonce_life', $observe, 10, 2 );
        try { $r = alynt_registration_construct( $frame, $form, true ); } finally { remove_filter( 'nonce_life', $observe, 10 ); }
        $guard = array( 'authenticated', 'capability', 'nonce:issue', 'authenticated', 'capability' );
        // Check this only after the behavioral revocation cases, so a removed
        // guard is killed by an exported blueprint, not merely an event count.
        $GLOBALS['alynt_registration_guard_order'] = array_merge( $guard, $guard, array( 'nonce:command' ), $guard ) === $GLOBALS['alynt_core_proof']['events'];
        $p = $r['proposal']; $e = $p['command'];
        alynt_core_assert( 90000001 === $p['actor_id'] && $e['site_uuid'] === json_decode( $values['ledger'], true )['site_uuid'] && 300 === $e['expires_at'] - $e['issued_at'], 'Fixed server identity/window missing.' );
        $ids = array_merge( array( $e['command_id'], $p['operation_id'] ), array_values( $p['effect_ids'] ), array_values( $p['reservation_command_ids'] ), array( $p['terminal_id'], $p['resolution_id'] ) );
        alynt_core_assert( 10 === count( array_unique( $ids ) ), 'Core UUID roles collided.' );
        $eligible = alynt_registration_eligible( $frame ); alynt_core_assert( is_array( $eligible ), 'Positive complete eligible frame required.' );
        foreach ( $ids as $id ) alynt_core_assert( 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $id ) && ! isset( $eligible['used'][$id] ), 'Core UUID invalid or reused retained history.' );
        alynt_core_assert( $p['dependency_digest'] === $eligible['dependency_digest'] && in_array( wp_verify_nonce( $r['nonce'], $r['action'] ), array( 1, 2 ), true ), 'Real full-envelope command nonce missing.' );
        $GLOBALS['alynt_registration_baseline'] = array( $frame, $form, $r ); // Private memory, never receipt output.
        alynt_core_done( 'genuine_core_private_positive_ten_uuid_roles_three_original_guards_no_authority' );

        $domains = array( $r['action'], Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( get_current_blog_id(), 'register_closed' ), Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( get_current_blog_id(), 'reserve_wpvivid_intent' ), Alynt_Drime_Backups_Uploader_Registration_Request_Contract::issue_action( get_current_blog_id() + 1, 'register_closed' ) );
        foreach ( array( 'reserve_wpvivid_intent', 'observe_registration', 'observe_reservation' ) as $purpose ) {
            $other = array_map( 'strval', $e ); $other['purpose'] = $purpose;
            if ( 'reserve_wpvivid_intent' === $purpose ) { $other['operation_id'] = $p['operation_id']; $other['confirm'] = 'reserve'; }
            else { $other = array_intersect_key( $other, array_flip( array( 'command_id', 'site_uuid', 'issued_at', 'expires_at', 'purpose' ) ) ); $other['contract'] = 'scan_registration_observation_v2'; $other['observed_command_id'] = $e['command_id']; $other['confirm'] = 'observe'; }
            $other['nonce'] = 'pending'; $parsed = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::parse( $other ); alynt_core_assert( is_array( $parsed ), 'Typed domain fixture must parse.' ); $domains[] = $parsed['action'];
        }
        foreach ( $domains as $expected ) { $nonce = wp_create_nonce( $expected ); foreach ( $domains as $actual ) alynt_core_assert( ( $expected === $actual ) === in_array( wp_verify_nonce( $nonce, $actual ), array( 1, 2 ), true ), 'Genuine nonce domains crossed.' ); }
        foreach ( $e as $key => $value ) {
            if ( in_array( $key, array( 'contract', 'purpose', 'confirm' ), true ) ) continue;
            $changed = $e; $changed[$key] = is_int( $value ) ? $value + ( 'expires_at' === $key ? -1 : 1 ) : ( 'site_uuid' === $key || 'command_id' === $key ? wp_generate_uuid4() : str_repeat( 'f', 64 ) );
            $body = array_map( 'strval', $changed ); $body['nonce'] = $r['nonce']; $parsed = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::parse( $body );
            alynt_core_assert( is_array( $parsed ) && false === wp_verify_nonce( $r['nonce'], $parsed['action'] ), 'Changed intent invalid or reused genuine command nonce: ' . $key );
        }
        alynt_core_done( 'genuine_issue_reserve_receipt_blog_domains_and_each_typed_field_bound' );

        foreach ( array_keys( $frame['rows'] ) as $role ) {
            alynt_scan_seed( array( $role => $values[$role] ), 'off' ); $changed = alynt_registration_frame( $db );
            $data = alynt_registration_eligible( $changed ); alynt_core_assert( is_array( $data ) && $data['dependency_digest'] !== $eligible['dependency_digest'], 'Owned tuple omitted from clock-independent digest.' );
            alynt_registration_construct( $changed, $form, true ); alynt_scan_seed( $values );
        }
        alynt_core_assert( $frame === alynt_registration_frame( $db ), 'Private construction changed captured storage.' );
        alynt_core_done( 'all_ten_actual_owned_tuple_metadata_changes_bound_without_writes' );

        $o = Alynt_Drime_Backups_Uploader_Registration_Request_Owner::create(); $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
        try {
            $issued = $o->issue( 'POST', $form ); $body = array_map( 'strval', $e ); $body['nonce'] = $r['nonce']; $consumed = $o->consume( 'POST', $body );
        } finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = false; }
        $unavailable = Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::disposition() + array( 'outcome' => 'unavailable', 'source' => 'registration_construction_v2' );
        alynt_core_assert( $unavailable === $issued && $unavailable === $consumed && false === alynt_core_property( $o, 'consumed' ), 'Public call exported or reached private construction.' );
        $thrown = false; try { serialize( $o ); } catch ( LogicException $error ) { $thrown = true; }
        alynt_core_assert( $thrown && ! isset( $o->__debugInfo()['scope'] ) && array() === headers_list(), 'Owner or browser session escaped.' );
        alynt_core_done( 'public_unavailable_no_blueprint_handle_storage_or_session_export' );
    } finally { $db->close(); }
}
