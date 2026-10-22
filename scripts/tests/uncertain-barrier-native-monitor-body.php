<?php
/** Bounded actual InnoDB wait witness; no sleeps-as-proof or arbitrary query input. */
$c = alynt_uncertain_helper_receive( 'watch' );
if ( $c['roles']['monitor'] !== $db->thread_id ) throw new RuntimeException( 'helper_owner' );
$db->query( 'SET SESSION TRANSACTION READ ONLY' );
$requester = $c['roles']['barrier']; $blocker = $c['roles']['blocker']; $table = '`local`.`' . $argv[3] . 'options`';
$sql = 'SELECT r.trx_mysql_thread_id,b.trx_mysql_thread_id,l.lock_table,l.lock_index FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX r ON r.trx_id=w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id=w.blocking_trx_id JOIN information_schema.INNODB_LOCKS l ON l.lock_id=w.requested_lock_id WHERE r.trx_mysql_thread_id=? AND b.trx_mysql_thread_id=?';
$stmt = $db->prepare( $sql ); $stmt->bind_param( 'ii', $requester, $blocker ); $end = hrtime( true ) + 2500000000; $found = false; $queries = 0;
do {
    if ( ++$queries > 125 ) throw new RuntimeException( 'helper_query_cap' );
    $stmt->execute(); $rows = $stmt->get_result(); $values = $rows->fetch_all( MYSQLI_NUM ); $rows->free();
    if ( $values ) {
        if ( array( array( (string) $requester, (string) $blocker, $table, 'option_name' ) ) !== array_map( function ( $row ) { return array_map( 'strval', $row ); }, $values ) ) throw new RuntimeException( 'helper_wait_identity' );
        $found = true; break;
    }
    usleep( 20000 );
} while ( hrtime( true ) < $end );
$stmt->close(); if ( ! $found ) throw new RuntimeException( 'helper_wait_missing' );
alynt_uncertain_helper_zero( $db );
alynt_uncertain_helper_send( 'waiting', array( 'requester' => $requester, 'blocker' => $blocker, 'monitor' => $db->thread_id, 'owned_table' => true, 'role' => 'safety', 'queries' => $queries ) );
alynt_uncertain_helper_receive( 'finish' );
