// Fixed source-matched prepared-statement roles. No SQL execution, sockets or supplied policy.
export const metadataQueries=Object.freeze([
    'SELECT ENGINE,TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 2',
    'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,EXTRA,GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION LIMIT 5',
    'SELECT CAST(COUNT(*) AS CHAR) FROM information_schema.TRIGGERS WHERE EVENT_OBJECT_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE=?',
    'SELECT CONSTRAINT_NAME,CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY CONSTRAINT_NAME LIMIT 4',
    'SELECT CAST(COUNT(*) AS CHAR) FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_NAME IS NOT NULL AND ((TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?) OR (REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME=?))',
]);
export const rowCaps=Object.freeze([131072,1048576,131072,131072,524288,524288,1048576,262144,1048576,1048576]);
export function rowQuery(table,index) {
    if(arguments.length!==2||typeof table!=='string'||!/^wp_alynt_core_probe_[a-f0-9]{12}_options$/.test(table)||!Number.isInteger(index)||index<0||index>=10)return false;
    return `SELECT CASE WHEN OCTET_LENGTH(CAST(option_id AS CHAR))<=20 THEN CAST(option_id AS CHAR) ELSE NULL END AS option_id, CASE WHEN OCTET_LENGTH(option_name)<=64 THEN BINARY option_name ELSE NULL END AS option_name, CASE WHEN OCTET_LENGTH(autoload)<=20 THEN BINARY autoload ELSE NULL END AS autoload, CAST(OCTET_LENGTH(option_value) AS CHAR) AS octets, CASE WHEN OCTET_LENGTH(option_value) BETWEEN 1 AND ${rowCaps[index]} THEN BINARY option_value ELSE NULL END AS value FROM \`${table}\` FORCE INDEX (option_name) WHERE option_name=? LIMIT 2 FOR UPDATE`;
}
export function createBarrierStatementRoles() {
    if(arguments.length)throw Error('fixed_statement_roles');
    let failed=false,bound=false,metadata=0,rows=0,post=false,pending=null,live=null,table=null;
    const fail=()=>{failed=true;return false;};
    const ready=()=>!failed&&!bound&&metadata===5&&!pending&&!live;
    return Object.freeze({
        accept(direction,seq,b) {
            if(arguments.length!==3||failed||!['client','server'].includes(direction)||!Number.isInteger(seq)||!Buffer.isBuffer(b)||!b.length||b.length>2097152)return fail();
            if(direction==='client'&&[22,23,25].includes(b[0])) {
                if(seq!==0)return fail();
                if(b[0]===22) {
                    if(pending||live)return fail();
                    const sql=b.subarray(1).toString('ascii');if(!b.subarray(1).every(n=>n>=32&&n<=126))return fail();
                    const meta=!bound||rows===1&&!post;
                    if(meta){if(metadata>=5||sql!==metadataQueries[metadata])return fail();pending='metadata';}
                    else {
                        if(!table){const match=sql.match(/FROM `([^`]+)` FORCE INDEX/);table=match?.[1]??null;}
                        if(rows>=10||sql!==rowQuery(table,rows))return fail();pending='row';
                    }
                } else {
                    if(pending||!live||b.length<(b[0]===25?5:10)||b.readUInt32LE(1)!==live.id)return fail();
                    if(b[0]===23){if(live.executed)return fail();live.executed=true;}
                    else {if(b.length!==5||!live.executed)return fail();if(live.kind==='metadata'){metadata++;if(bound&&metadata===5)post=true;}else{rows++;if(rows===1)metadata=0;}live=null;}
                }
            } else if(direction==='server'&&pending) {
                if(seq!==1||b.length!==12||b[0]!==0||b.readUInt32LE(1)===0)return fail();
                live={id:b.readUInt32LE(1),kind:pending,executed:false};pending=null;
            }
            return true;
        },
        ready,
        diagnostic(){return Object.freeze({bound,failed,metadata_closed:metadata,rows_closed:rows,pending_kind:pending??'none',live_kind:live?.kind??'none',live_executed:live?.executed??false});},
        bind(){if(arguments.length||!ready())return fail();bound=true;return true;},
        timeoutStatement(){return !failed&&bound&&rows===0&&!pending&&live?.kind==='row'&&live.executed ? live.id:false;},
        complete(){return !failed&&bound&&rows===10&&post&&metadata===5&&!pending&&!live;}
    });
}
