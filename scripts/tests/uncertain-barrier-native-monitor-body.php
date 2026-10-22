<?php
/** Bounded actual InnoDB wait witness; no sleeps-as-proof or arbitrary query input. */
$helper_stage = 'watch_request'; $c = alynt_uncertain_helper_receive( 'watch' );
$helper_stage = 'watch_owner';
if ( $c['roles']['monitor'] !== $db->thread_id ) throw new RuntimeException( 'helper_owner' );
$helper_stage = 'watch_policy'; $db->query( 'SET SESSION TRANSACTION READ ONLY' );
$requester = $c['roles']['barrier']; $blocker = $c['roles']['blocker']; $table = '`local`.`' . $argv[3] . 'options`';
$sql = 'SELECT r.trx_mysql_thread_id,b.trx_mysql_thread_id,l.lock_table,l.lock_index FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX r ON r.trx_id=w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id=w.blocking_trx_id JOIN information_schema.INNODB_LOCKS l ON l.lock_id=w.requested_lock_id WHERE r.trx_mysql_thread_id=? AND b.trx_mysql_thread_id=?';
$helper_stage = 'watch_prepare'; $stmt = $db->prepare( $sql ); $stmt->bind_param( 'ii', $requester, $blocker ); $end = hrtime( true ) + 2500000000; $found = false; $queries = 0;
do {
    if ( ++$queries > 125 ) throw new RuntimeException( 'helper_query_cap' );
    $helper_stage = 'watch_execute'; $stmt->execute(); $rows = $stmt->get_result(); $values = $rows->fetch_all( MYSQLI_NUM ); $rows->free();
    if ( $values ) {
        $helper_stage = 'watch_identity';
        if ( array( array( (string) $requester, (string) $blocker, $table, 'option_name' ) ) !== array_map( function ( $row ) { return array_map( 'strval', $row ); }, $values ) ) throw new RuntimeException( 'helper_wait_identity' );
        $found = true; break;
    }
    usleep( 20000 );
} while ( hrtime( true ) < $end );
$helper_stage = 'watch_missing'; $stmt->close(); if ( ! $found ) throw new RuntimeException( 'helper_wait_missing' );
$helper_stage = 'watch_counters'; alynt_uncertain_helper_zero( $db );
$helper_stage = 'watch_waiting';
alynt_uncertain_helper_send( 'waiting', array( 'requester' => $requester, 'blocker' => $blocker, 'monitor' => $db->thread_id, 'owned_table' => true, 'role' => 'safety', 'queries' => $queries ) );
$helper_stage = 'finish_request'; alynt_uncertain_helper_receive( 'finish' );
