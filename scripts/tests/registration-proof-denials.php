<?php
/** Genuine role/session loss and deliberate invalid captured data. No apply. */
function alynt_registration_denials() {
    list( $frame ) = $GLOBALS['alynt_registration_baseline'];
    alynt_core_login(); $form = alynt_registration_form();
    foreach ( array( array(), array_merge( $form, array( 'actor_id' => '90000001' ) ), array_merge( $form, array( 'provider' => 'caller' ) ) ) as $bad ) {
        $queries = $GLOBALS['alynt_core_proof']['queries']; alynt_registration_construct( $frame, $bad );
        alynt_core_assert( $queries === $GLOBALS['alynt_core_proof']['queries'], 'Malformed original issuance reached core reads.' );
    }
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $o = Alynt_Drime_Backups_Uploader_Registration_Request_Owner::create(); $queries = $GLOBALS['alynt_core_proof']['queries'];
    alynt_core_assert( false === alynt_core_private( $o, 'construct_registration', array( $frame, $form ) ) && $queries === $GLOBALS['alynt_core_proof']['queries'], 'GET reached private inspection.' ); $_SERVER['REQUEST_METHOD'] = 'POST';
    $bad = $form; $bad['nonce'] = 'bad'; alynt_registration_construct( $frame, $bad );
    foreach ( array( 'session', 'capability', 'role', 'user' ) as $change ) {
        alynt_core_login(); $form = alynt_registration_form(); $restore = alynt_core_change( $change );
        try { alynt_registration_construct( $frame, $form ); }
        finally { $restore(); wp_cache_flush(); $GLOBALS['wp_roles'] = new WP_Roles(); }
    }
    alynt_core_login( 90000002 ); alynt_registration_construct( $frame, alynt_registration_form() );
    alynt_core_login(); $form = alynt_registration_form(); alynt_core_login(); alynt_registration_construct( $frame, $form );
    alynt_core_done( 'malformed_method_nonce_subscriber_and_real_warm_cache_revocation_denied' );

    foreach ( array( 1, 2 ) as $phase ) {
        $token = alynt_core_login(); $form = alynt_registration_form(); $issue_calls = 0; $command_calls = 0;
        $early_loss = function ( $life, $action ) use ( $phase, $token, &$issue_calls, &$command_calls ) {
            if ( 0 === strpos( $action, 'alynt_drime_backups_scan_registration_command_v2:' ) ) ++$command_calls;
            else if ( $phase === ++$issue_calls ) alynt_core_mutate( function () use ( $token ) { WP_Session_Tokens::get_instance( 90000001 )->destroy( $token ); } );
            return $life;
        };
        add_filter( 'nonce_life', $early_loss, 10, 2 ); $o = Alynt_Drime_Backups_Uploader_Registration_Request_Owner::create(); $writes = $GLOBALS['alynt_core_proof']['fixture_writes']; $roles = $GLOBALS['wp_roles'];
        $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
        try { $r = alynt_core_private( $o, 'construct_registration', array( $frame, $form ) ); }
        finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = false; remove_filter( 'nonce_life', $early_loss, 10 ); }
        // Exactly one fixture-session deletion is the deliberate hook injection,
        // not a constructor write. All other WordPress writes remain fenced.
        alynt_core_assert( false === $r && $phase === $issue_calls && 0 === $command_calls && $writes + 1 === $GLOBALS['alynt_core_proof']['fixture_writes'], 'Revoked initial/renewed session reached issuance or wrote beyond the owned hook.' );
        alynt_core_assert( $roles === $GLOBALS['wp_roles'] && ! alynt_core_property( $o, 'active' ) && alynt_core_property( $o, 'consumed' ) && false === alynt_core_private( $o, 'construct_registration', array( $frame, $form ) ), 'Early refusal lifetime or roles escaped.' );
    }
    alynt_core_done( 'real_session_loss_at_initial_and_renewed_issuance_prevents_command_nonce_creation' );

    // These are core hooks inside the actual creation call, not stubbed APIs.
    foreach ( array( 'session', 'capability', 'actor', 'cookie', 'method', 'throw', 'reenter' ) as $change ) {
        alynt_core_login(); $form = alynt_registration_form(); $restore = null; $fired = 0; $nested = null;
        $o = Alynt_Drime_Backups_Uploader_Registration_Request_Owner::create();
        $during_creation = function ( $life, $action ) use ( $change, &$restore, &$fired, &$nested, $o, $frame, $form ) {
            if ( 0 !== strpos( $action, 'alynt_drime_backups_scan_registration_command_v2:' ) ) return $life;
            ++$fired;
            if ( 'reenter' === $change ) { $nested = alynt_core_private( $o, 'construct_registration', array( $frame, $form ) ); throw new RuntimeException( 'Owned reentry control.' ); }
            if ( 'throw' === $change ) throw new RuntimeException( 'Owned nonce creation failure.' );
            $restore = alynt_core_change( $change ); return $life;
        };
        add_filter( 'nonce_life', $during_creation, 10, 2 ); $roles = $GLOBALS['wp_roles']; $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
        try { $r = alynt_core_private( $o, 'construct_registration', array( $frame, $form ) ); }
        finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = false; remove_filter( 'nonce_life', $during_creation, 10 ); if ( $restore ) $restore(); wp_cache_flush(); $GLOBALS['wp_roles'] = $roles; }
        alynt_core_assert( false === $r && 1 === $fired && false === alynt_core_property( $o, 'active' ) && alynt_core_property( $o, 'consumed' ), 'Nonce creation loss or exception exported a blueprint.' );
        if ( 'reenter' === $change ) alynt_core_assert( false === $nested, 'Reentrant private construction admitted.' );
        alynt_core_assert( false === alynt_core_private( $o, 'construct_registration', array( $frame, $form ) ), 'Failed owner reminted identities.' );
    }
    alynt_core_done( 'real_nonce_creation_revocation_throw_and_reentry_consumed_without_blueprint' );

    alynt_core_login(); $form = alynt_registration_form();
    foreach ( array( 'grant', 'chain', 'schema', 'enabled', 'connection', 'genesis', 'generation', 'blog', 'prefix', 'database' ) as $bad ) {
        $changed = $frame;
        if ( in_array( $bad, array( 'grant', 'chain', 'schema' ), true ) ) {
            $s = json_decode( $frame['rows']['safety']['value'], true );
            if ( 'grant' === $bad ) $s['writer']['authenticated_grant'] = array( 'active' => true );
            if ( 'chain' === $bad ) $s['writer']['upgrade']['request_digest'] = str_repeat( 'f', 64 );
            if ( 'schema' === $bad ) $s['schema_version'] = 6;
            $changed = alynt_registration_row( $changed, 'safety', json_encode( $s ) );
        }
        if ( 'enabled' === $bad ) $changed = alynt_registration_row( $changed, 'settings', serialize( array( 'site_uuid' => '11111111-1111-4111-8111-111111111111', 'auto_scan_enabled' => true ) ) );
        if ( 'connection' === $bad ) $changed = alynt_registration_row( $changed, 'connection', 'O:8:"stdClass":0:{}' );
        if ( in_array( $bad, array( 'genesis', 'generation' ), true ) ) { $s = json_decode( $frame['rows']['ledger']['value'], true ); if ( 'genesis' === $bad ) $s['origin']['ledger_id'] = 'not-a-genesis'; else ++$s['authority_generation']; $changed = alynt_registration_row( $changed, 'ledger', json_encode( $s ) ); }
        if ( 'blog' === $bad ) $changed['blog_id']++;
        if ( 'prefix' === $bad ) $changed['prefix'] = 'other_';
        if ( 'database' === $bad ) $changed['database'] = 'other';
        alynt_registration_construct( $changed, $form );
    }
    alynt_core_done( 'complete_retained_chain_grant_settings_schema_genesis_scope_required' );

    $s = json_decode( $frame['rows']['safety']['value'], true ); $site = json_decode( $frame['rows']['ledger']['value'], true )['site_uuid']; $raw = serialize( array( 'site_uuid' => $site, 'auto_scan_enabled' => true ) ); $now = time();
    $c = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::context( $s, $raw, $site, get_current_blog_id(), 'no' );
    alynt_core_assert( is_array( $c ), 'Pending-state control context invalid.' );
    $pending_command = array( 'contract' => 'closed_writer_request_v1', 'purpose' => 'begin_disable', 'command_id' => wp_generate_uuid4(), 'site_uuid' => $site, 'enrollment_binding' => $s['enrollment_binding'], 'expected_generation' => $s['lifecycle']['permission_generation'], 'issued_at' => $now, 'expires_at' => $now + 300, 'confirm' => 'disable', 'context_digest' => $c['context_digest'], 'before_hash' => $c['before_hash'], 'after_hash' => $c['after_hash'] );
    $pending = Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::plan( $s, $pending_command, $raw, $now, get_current_blog_id(), 'no', wp_generate_uuid4(), true );
    alynt_core_assert( is_array( $pending ) && Alynt_Drime_Backups_Uploader_Real_Closed_Writer_Model::valid( $pending['state'], $site ) && null !== $pending['state']['writer']['pending'], 'Pending control must be genuinely valid schema eight.' );
    $changed = alynt_registration_row( $frame, 'safety', json_encode( $pending['state'] ) ); $root = json_decode( $frame['rows']['ledger']['value'], true ); $root['authority_generation'] = $pending['state']['lifecycle']['permission_generation'];
    alynt_registration_construct( alynt_registration_row( $changed, 'ledger', json_encode( $root ) ), $form );
    $eligible = alynt_registration_eligible( $frame ); $r = alynt_registration_construct( $frame, $form, true );
    $after = Alynt_Drime_Backups_Uploader_Closed_Registration_Contract::register( $eligible['before'], $eligible['scope'], $r['proposal'], time() );
    alynt_core_assert( is_string( $after ) && 1 === Alynt_Drime_Backups_Uploader_Closed_Registration_Contract::inspect( $after, $eligible['scope'] )['pending_count'], 'Registered pending control must be valid schema two.' );
    alynt_registration_construct( alynt_registration_row( $frame, 'ledger', $after ), $form );
    alynt_core_done( 'valid_pending_schema8_and_schema2_candidates_denied_not_just_malformed_shapes' );

    $r = alynt_registration_construct( $frame, $form, true ); $e = $r['proposal']['command']; $e['issued_at'] = time() - 400; $e['expires_at'] = $e['issued_at'] + 300;
    $body = array_map( 'strval', $e ); $body['nonce'] = 'pending'; $parsed = Alynt_Drime_Backups_Uploader_Registration_Request_Contract::parse( $body );
    alynt_core_assert( is_array( $parsed ), 'Stale typed control invalid.' ); $nonce = wp_create_nonce( $parsed['action'] );
    alynt_core_assert( in_array( wp_verify_nonce( $nonce, $parsed['action'] ), array( 1, 2 ), true ), 'Stale original intent needs live genuine nonce.' );
    $o = Alynt_Drime_Backups_Uploader_Registration_Request_Owner::create(); $core = alynt_core_private( $o, 'frame' ); $queries = $GLOBALS['alynt_core_proof']['queries'];
    alynt_core_assert( false === alynt_core_private( $o, 'guard', array( $core, $nonce, $parsed['action'], $e ) ) && $queries === $GLOBALS['alynt_core_proof']['queries'], 'Stale original deadline bypassed by live nonce.' );
    $db = alynt_scan_db(); try { alynt_core_assert( $frame === alynt_registration_frame( $db ), 'Denials modified owned protected rows.' ); } finally { $db->close(); }
    unset( $GLOBALS['alynt_registration_baseline'] );
    alynt_core_assert( true === $GLOBALS['alynt_registration_guard_order'], 'Original guards must surround genuine nonce creation.' ); unset( $GLOBALS['alynt_registration_guard_order'] );
    alynt_core_done( 'stale_original_deadline_before_auth_reads_and_all_ten_fixture_rows_unchanged' );
}
