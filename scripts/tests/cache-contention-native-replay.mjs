// Replay actual monitor-local relative offsets, never Node/PHP absolute clock comparisons.
import { createWaitWitnessWindow } from './wait-witness-window.mjs';
import { waitDomain,exactWaitTuple } from './wait-witness-contract.mjs';
import { validContentionMetrics } from './cache-contention-native-protocol.mjs';
export function createContentionReplay(context,signal,metrics,wait){
    if(arguments.length!==4||!signal||!validContentionMetrics(metrics)||!exactWaitTuple(wait,context))return false;
    const window=createWaitWitnessWindow(context,0n),line=(kind,more={})=>Buffer.from(JSON.stringify({domain:waitDomain,v:2,kind,context,...more})+'\n');
    if(!window.feed('worker',line('hello',{thread:context.roles.monitor}),0n)||!window.feed('parent',line('arm'),0n)||!window.feed('worker',line('armed',{zero_dml:true}),0n)||!window.feed('parent',line('execute',{statement:signal.statement}),BigInt(metrics.trigger_ns),signal.receipt))return false;
    for(const q of metrics.queries)if(!window.beginQuery(BigInt(q.start_ns))||!window.endQuery(q.matched?wait:null,BigInt(q.end_ns)))return false;
    const matched=BigInt(metrics.queries.at(-1).end_ns);
    if(!window.feed('worker',line('waiting',{...wait,queries:metrics.queries.length}),matched))return false;
    let used=false;
    return Object.freeze({finish(zeroDml,closedNs){
        if(arguments.length!==2||used)return false;used=true;
        if(zeroDml!==true||typeof closedNs!=='string'||!/^(?:0|[1-9][0-9]{0,10})$/.test(closedNs)||BigInt(closedNs)<matched||BigInt(closedNs)>28000000000n)return false;
        return window.feed('parent',line('finish'),matched)&&window.feed('worker',line('closed',{zero_dml:true}),BigInt(closedNs))&&window.finish().synthetic_match===true;
    }});
}
