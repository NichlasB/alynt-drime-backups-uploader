// Replays one monitor-local RELATIVE epoch; never compares PHP and Node clocks.
import { createWaitWitnessWindow } from './wait-witness-window.mjs';
import { waitDomain } from './wait-witness-contract.mjs';
export function replayCacheExpiry(context,signal,metrics) {
    if(arguments.length!==3||!signal||!metrics)return false;
    const p=createWaitWitnessWindow(context,0n),line=(kind,more={})=>Buffer.from(JSON.stringify({domain:waitDomain,v:2,kind,context,...more})+'\n');
    if(!p.feed('worker',line('hello',{thread:context.roles.monitor}),0n)||!p.feed('parent',line('arm'),0n)||!p.feed('worker',line('armed',{zero_dml:true}),0n)||!p.feed('parent',line('execute',{statement:signal.statement}),BigInt(metrics.trigger_ns),signal.receipt))return false;
    for(const q of metrics.queries)if(!p.beginQuery(BigInt(q.start_ns))||!p.endQuery(null,BigInt(q.end_ns)))return false;
    // The expected no-match refusal must NOT turn into the positive WAITING/CLOSED path.
    return metrics.queries.length>0&&p.beginQuery(BigInt(metrics.elapsed_ns))===false&&p.finish().synthetic_match===false;
}
