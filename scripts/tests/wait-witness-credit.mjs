// Synthetic write/account model only. Never a registry adapter or transport handle.
import { copyWaitContext } from './wait-witness-contract.mjs';
import { createBarrierStatementRoles, rowQuery } from './uncertain-barrier-native-statements.mjs';
const receipts = new WeakMap();
export function consumeWaitExecuteReceipt(receipt,context) {
    const row = receipt && typeof receipt==='object' ? receipts.get(receipt):null;
    if(row)receipts.delete(receipt); // Wrong context also consumes the one-shot candidate.
    return row?.active===true && row.context===JSON.stringify(context) ? row.statement:false;
}
export function createWaitExecuteCredit(context) {
    if(arguments.length!==1)throw Error('fixed_wait_credit_arguments');
    const c=copyWaitContext(context),roles=createBarrierStatementRoles(),used=new Set(),pending=Object.assign(Object.create(null),{client:null,server:null});
    let failed=false,bound=false,issued=null,frames=0,total=0;
    const fail=()=>{failed=true;if(issued)issued.active=false;return false;};
    return Object.freeze({
        frame(role,direction,packet,credit) {
            if(arguments.length!==4 || failed || role!=='barrier' || !['client','server'].includes(direction) || typeof credit!=='symbol' || used.has(credit) ||
                pending[direction] || !Buffer.isBuffer(packet) || packet.length<5 || packet.length>2097156 || packet.readUIntLE(0,3)!==packet.length-4 ||
                ++frames>4096 || (total+=packet.length)>67108864) return fail();
            used.add(credit);const bytes=packet.subarray(4),before=roles.diagnostic();
            // This projection admits only compiled prepare/execute/close events, not a full native conversation.
            if(direction==='client' ? ![22,23,25].includes(bytes[0]) : before.pending_kind==='none')return fail();
            if(bound && before.rows_closed===0 && direction==='client' && bytes[0]===22 && bytes.subarray(1).toString('ascii')!==rowQuery(c.table,0))return fail();
            if(!roles.accept(direction,packet[3],bytes))return fail();
            const statement=roles.timeoutStatement();
            const candidate=bound && direction==='client' && bytes[0]===23 && before.rows_closed===0 && before.live_kind==='row' && !before.live_executed && statement;
            pending[direction]={credit,size:packet.length,forwarded:false,statement:candidate||false};return true;
        },
        bind() { if(arguments.length || failed || bound || pending.client || pending.server || !roles.bind())return fail();bound=true;return true; },
        forwarded(direction,credit,bytes) {
            const p=['client','server'].includes(direction) ? pending[direction]:null;
            if(arguments.length!==3 || failed || !['client','server'].includes(direction) || typeof credit!=='symbol' || !p || p.credit!==credit || p.forwarded || bytes!==p.size)return fail();
            p.forwarded=true;return true;
        },
        account(direction,credit) {
            const p=['client','server'].includes(direction) ? pending[direction]:null;
            if(arguments.length!==2 || failed || !['client','server'].includes(direction) || typeof credit!=='symbol' || !p || p.credit!==credit || !p.forwarded)return fail();
            pending[direction]=null;
            if(!p.statement)return true;
            if(issued)return fail();
            const receipt=Object.freeze({});issued={context:JSON.stringify(c),statement:p.statement,active:true};receipts.set(receipt,issued);return receipt;
        },
    });
}
