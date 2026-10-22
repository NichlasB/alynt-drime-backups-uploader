// Passive bounded projection only. No delivery, acceptance, query or retry authority.
import { rowQuery } from './uncertain-barrier-native-statements.mjs';
const session = 'SELECT CAST(CONNECTION_ID() AS CHAR),DATABASE(),@@hostname,CAST(@@port AS CHAR),CAST(@@server_id AS CHAR),VERSION(),CAST(@@autocommit+0 AS CHAR),CAST(@@in_transaction+0 AS CHAR),CAST(@@read_only+0 AS CHAR),CAST(@@tx_read_only+0 AS CHAR)';
const policy = 'SELECT @@tx_isolation,CAST(@@innodb_lock_wait_timeout AS CHAR),CAST(@@lock_wait_timeout AS CHAR),CURRENT_ROLE(),CAST(@@innodb_flush_log_at_trx_commit AS CHAR)';
const zero = "SHOW SESSION STATUS WHERE Variable_name IN ('Com_insert','Com_insert_select','Com_update','Com_update_multi','Com_delete','Com_delete_multi','Com_replace','Com_replace_select','Com_create_table','Com_alter_table','Com_drop_table','Com_truncate','Handler_write','Handler_update','Handler_delete')";
const queries = new Map([[session,'session'],[policy,'policy'],[zero,'zero_dml'],['START TRANSACTION READ WRITE','begin'],['COMMIT AND NO CHAIN NO RELEASE','commit'],['ROLLBACK AND NO CHAIN NO RELEASE','rollback']]);
export function validBarrierWorkerProgress(v) {
    return v !== null && typeof v === 'object' && Object.keys(v).join(',') === 'phase,reads,metadata,zero_dml' &&
        ['unopened','idle','begin_sent','transaction_owned','commit_sent','barrier_released','rollback_sent','rolled_back','poisoned','closed'].includes(v.phase) &&
        Number.isInteger(v.reads) && v.reads>=0 && v.reads<=10 && Number.isInteger(v.metadata) && v.metadata>=0 && v.metadata<=2 && Number.isInteger(v.zero_dml) && v.zero_dml>=0 && v.zero_dml<=2;
}
export function createBarrierProgress() {
    if(arguments.length)throw Error('fixed_barrier_progress');
    let last='none',count=0,begin=false,safety=false,execute=false,error=false,code=0,inconclusive=false;
    return Object.freeze({
        frame(direction,seq,b,statement) {
            try {
                if(!['client','server'].includes(direction)||!Number.isInteger(seq)||!Buffer.isBuffer(b)||!b.length||b.length>2097152||++count>4096){inconclusive=true;return;}
                if(direction==='server') {
                    if(seq===1&&b[0]===255){error=true;const n=b.length>=3?b.readUInt16LE(1):0;code=[1044,1045,1054,1064,1146,1205,1213,2002,2006,2013,3572].includes(n)?n:0;}
                    return;
                }
                if(seq!==0)return;
                if([3,22].includes(b[0])&&b.length<=4097&&b.subarray(1).every(n=>n>=32&&n<=126)) {
                    const sql=b.subarray(1).toString('ascii');
                    if(b[0]===3&&queries.has(sql)){last=queries.get(sql);if(last==='begin')begin=true;}
                    else if(b[0]===22){const table=sql.match(/FROM `([^`]+)` FORCE INDEX/)?.[1];if(table&&sql===rowQuery(table,0)){last='safety_prepare';safety=true;}}
                } else if(b[0]===23&&statement?.live_kind==='row'&&statement.rows_closed===0){last='safety_execute';execute=true;}
            } catch { inconclusive=true; } // Diagnostics cannot change the original observer's result.
        },
        summary(){return Object.freeze({diagnostic_only:true,last_command:last,frames_seen:count,begin_seen:begin,safety_prepare_seen:safety,safety_execute_seen:execute,server_error_seen:error,server_error_code:code,inconclusive});}
    });
}
