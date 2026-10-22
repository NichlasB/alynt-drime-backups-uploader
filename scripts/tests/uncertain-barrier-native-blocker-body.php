<?php
/** Lock only the compiled first fixture row, after the original writer has been erased. */
$helper_stage = 'lock_request'; $c = alynt_uncertain_helper_receive( 'lock' );
$helper_stage = 'lock_owner';
if ( $c['roles']['blocker'] !== $db->thread_id ) throw new RuntimeException( 'helper_owner' );
$helper_stage = 'lock_policy'; $db->query( 'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED' );
$db->query( 'SET SESSION innodb_lock_wait_timeout=2' ); $db->query( 'SET SESSION lock_wait_timeout=3' );
$helper_stage = 'lock_counters_before'; alynt_uncertain_helper_zero( $db );
$helper_stage = 'lock_begin'; $db->query( 'START TRANSACTION READ WRITE' ); $owned_transaction = true;
$name = Alynt_Drime_Backups_Uploader_Scan_Effect_Manifest::rows()['safety'][0];
$helper_stage = 'lock_prepare'; $stmt = $db->prepare( "SELECT option_id FROM `{$argv[3]}options` FORCE INDEX (option_name) WHERE option_name=? LIMIT 2 FOR UPDATE NOWAIT" );
$helper_stage = 'lock_execute';
$stmt->bind_param( 's', $name ); $stmt->execute(); $rows = $stmt->get_result();
$helper_stage = 'lock_rows'; if ( 1 !== $rows->num_rows ) throw new RuntimeException( 'helper_safety_row' ); $rows->free(); $stmt->close();
$helper_stage = 'lock_counters_after'; alynt_uncertain_helper_zero( $db );
$helper_stage = 'lock_ready';
alynt_uncertain_helper_send( 'ready', array( 'blocker' => $db->thread_id, 'barrier' => $c['roles']['barrier'], 'zero_dml' => true ) );
$helper_stage = 'rollback_request'; alynt_uncertain_helper_receive( 'rollback' );
$helper_stage = 'rollback';
if ( true !== $db->query( 'ROLLBACK AND NO CHAIN NO RELEASE' ) || array( '1','0' ) !== $db->query( 'SELECT CAST(@@autocommit AS CHAR),CAST(@@in_transaction AS CHAR)' )->fetch_row() ) throw new RuntimeException( 'helper_rollback' );
$owned_transaction = false; $helper_stage = 'rollback_counters'; alynt_uncertain_helper_zero( $db );
$helper_stage = 'rollback_ack';
alynt_uncertain_helper_send( 'released', array( 'rollback_acknowledged' => true, 'zero_dml' => true ) );
