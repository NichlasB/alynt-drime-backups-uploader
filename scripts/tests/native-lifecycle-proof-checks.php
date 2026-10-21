<?php
/** Observations only. Never supplies an authorization, frame, handle or result. */
function alynt_native_lifecycle_erased( $o ) {
    foreach ( array( 'native_resource', 'native_handle', 'reader', 'native_outcome_writer', 'physical', 'read_physical', 'barrier_auth', 'barrier_auth_facts', 'origin', 'origin_data', 'scope', 'started', 'lifecycle_clock', 'read_started', 'before', 'proposal', 'context', 'after', 'recorded_at', 'admission_origin', 'admission_clock', 'native_outcome', 'staged', 'inflight' ) as $k ) alynt_core_assert( null === alynt_core_property( $o, $k ), 'native_lifecycle_erasure_' . $k );
    foreach ( array( 'used', 'admission_ids', 'fences' ) as $k ) alynt_core_assert( array() === alynt_core_property( $o, $k ), 'native_lifecycle_empty_' . $k );
    foreach ( array( 'root_attempted', 'attempted', 'claimed', 'finished', 'consumed', 'observation_attempted', 'admission_qualification_spent' ) as $k ) alynt_core_assert( true === alynt_core_property( $o, $k ), 'native_lifecycle_consumed_' . $k );
    foreach ( array( 'busy', 'active', 'admission_join_busy' ) as $k ) alynt_core_assert( false === alynt_core_property( $o, $k ), 'native_lifecycle_inactive_' . $k );
    $public = array_map( function ( $m ) { return $m->name; }, ( new ReflectionClass( $o ) )->getMethods( ReflectionMethod::IS_PUBLIC ) ); sort( $public );
    alynt_core_assert( array( '__debugInfo', '__serialize', '__unserialize', '__wakeup', 'create', 'disposition' ) === $public, 'native_lifecycle_no_public_handle' );
    $serialized = false; try { serialize( $o ); $serialized = true; } catch ( LogicException $error ) { alynt_core_assert( 'Closed contract data cannot be transferred.' === $error->getMessage(), 'native_lifecycle_privacy_refusal' ); }
    alynt_core_assert( ! $serialized, 'native_lifecycle_no_serialization' );
}
function alynt_native_lifecycle_hooks() {
    $hooks = array(); foreach ( array( 'query', 'pre_wp_load_alloptions' ) as $hook ) $hooks[$hook] = isset( $GLOBALS['wp_filter'][$hook] ) ? $GLOBALS['wp_filter'][$hook]->callbacks : array(); return $hooks;
}
function alynt_native_lifecycle_call( $o, $method, $form, $probe = null ) {
    $callback_error = null;
    $filter = function ( $life, $action ) use ( $probe, &$callback_error ) {
        if ( ! $probe || ! in_array( 'wp_verify_nonce', array_column( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 14 ), 'function' ), true ) ) return $life;
        try { return $probe( $life, $action ); } catch ( Throwable $error ) { $callback_error = $error; throw $error; }
    };
    $hooks = alynt_native_lifecycle_hooks(); $roles = $GLOBALS['wp_roles']; $writes = $GLOBALS['alynt_core_proof']['fixture_writes'];
    $handler = static function () { throw new RuntimeException( 'Original proof diagnostic.' ); }; set_error_handler( $handler );
    add_filter( 'nonce_life', $filter, 10, 2 ); $GLOBALS['alynt_core_proof']['guard_readonly'] = true;
    try { $r = alynt_core_private( $o, 'lifecycle_root', array( $method, $form ) ); }
    finally { $GLOBALS['alynt_core_proof']['guard_readonly'] = false; remove_filter( 'nonce_life', $filter, 10 ); $restored = set_error_handler( $handler ); restore_error_handler(); restore_error_handler(); }
    if ( $callback_error ) throw $callback_error;
    alynt_core_assert( $roles === $GLOBALS['wp_roles'] && $hooks === alynt_native_lifecycle_hooks() && $handler === $restored, 'native_lifecycle_exact_hook_role_handler_restoration' );
    alynt_core_assert( $writes === $GLOBALS['alynt_core_proof']['fixture_writes'], 'native_lifecycle_no_wordpress_writes' );
    alynt_native_lifecycle_erased( $o );
    $counts = array( alynt_core_property( $o, 'protected_reads' ), alynt_core_property( $o, 'writes' ), alynt_core_property( $o, 'barrier_reads' ), $GLOBALS['alynt_core_proof']['queries'] );
    alynt_core_assert( false === alynt_core_private( $o, 'lifecycle_root', array( 'POST', $form ) ) && $counts === array( alynt_core_property( $o, 'protected_reads' ), alynt_core_property( $o, 'writes' ), alynt_core_property( $o, 'barrier_reads' ), $GLOBALS['alynt_core_proof']['queries'] ), 'native_lifecycle_no_replay_io' );
    return $r;
}
function alynt_native_lifecycle_thread( mysqli $client ) {
    $id = $client->thread_id; $GLOBALS['alynt_native_lifecycle_threads'][$id] = $id; return $id;
}
