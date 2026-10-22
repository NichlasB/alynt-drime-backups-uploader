<?php
/** Lock only the compiled first fixture row, after the original writer has been erased. */
$c = alynt_uncertain_helper_receive( 'lock' );
if ( $c['roles']['blocker'] !== $db->thread_id ) throw new RuntimeException( 'helper_owner' );
$db->query( 'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED' );
$db->query( 'SET SESSION innodb_lock_wait_timeout=2' ); $db->query( 'SET SESSION lock_wait_timeout=3' );
alynt_uncertain_helper_zero( $db );
$db->query( 'START TRANSACTION READ WRITE' ); $owned_transaction = true;
$name = Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows()['safety'][0];
$stmt = $db->prepare( "SELECT option_id FROM `{$argv[3]}options` FORCE INDEX (option_name) WHERE option_name=? LIMIT 2 FOR UPDATE NOWAIT" );
$stmt->bind_param( 's', $name ); $stmt->execute(); $rows = $stmt->get_result();
if ( 1 !== $rows->num_rows ) throw new RuntimeException( 'helper_safety_row' ); $rows->free(); $stmt->close();
alynt_uncertain_helper_zero( $db );
alynt_uncertain_helper_send( 'ready', array( 'blocker' => $db->thread_id, 'barrier' => $c['roles']['barrier'], 'zero_dml' => true ) );
alynt_uncertain_helper_receive( 'rollback' );
if ( true !== $db->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ) || array( '1','0' ) !== $db->query( 'SELECT CAST(@@autocommit AS CHAR),CAST(@@in_transaction AS CHAR)' )->fetch_row() ) throw new RuntimeException( 'helper_rollback' );
$owned_transaction = false; alynt_uncertain_helper_zero( $db );
alynt_uncertain_helper_send( 'released', array( 'rollback_acknowledged' => true, 'zero_dml' => true ) );
