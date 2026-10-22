// Fixed private no-fault diagnostics only, never a wait-success receipt.
import { exact } from './selected-qualification-grammar.mjs';
import { validWaitContext, nativeBase } from './wait-witness-contract.mjs';
export const cacheNativeDomain='alynt.uncertain-barrier.wait-native.v1';
export const cacheSetupDomain='alynt.uncertain-barrier.wait-setup.v1';
export function parseCacheHelper(line,role,context,source) {
    if(arguments.length!==4||!['idle','monitor'].includes(role)||typeof line!=='string'||!line.length||line.length>4095||!/^[\x20-\x5b\x5d-\x7e]+$/.test(line))return false;
    let m;try{m=JSON.parse(line);}catch{return false;}
    if(!m||JSON.stringify(m)!==line)return false;
    if(context===null)return exact(m,['domain','kind','role','thread','source'])&&m.domain===cacheSetupDomain&&m.kind==='hello'&&m.role===role&&m.source===source&&Number.isInteger(m.thread)&&m.thread>0&&m.thread<=4294967295?m:false;
    if(!validWaitContext(context)||m.domain!==cacheNativeDomain||JSON.stringify(m.context)!==JSON.stringify(context))return false;
    if(role==='idle')return exact(m,['domain','kind','role','zero_dml','context'])&&['ready','closed'].includes(m.kind)&&m.role===role&&m.zero_dml===true?m:false;
    if(m.kind==='armed')return exact(m,['domain','kind','role','zero_dml','context'])&&m.role===role&&m.zero_dml===true?m:false;
    if(!exact(m,['domain','kind','role','reason','metrics','context'])||m.kind!=='refused'||m.role!==role||m.reason!=='expired')return false;
    const f=m.metrics,number=s=>typeof s==='string'&&/^(?:0|[1-9][0-9]{0,10})$/.test(s);
    if(!exact(f,['trigger_ns','elapsed_ns','queries','zero_dml','statement_closed','session_closed'])||!number(f.trigger_ns)||!number(f.elapsed_ns)||BigInt(f.trigger_ns)>=2500000000n||BigInt(f.elapsed_ns)<2500000000n||BigInt(f.elapsed_ns)>3250000000n||!Array.isArray(f.queries)||f.queries.length<1||f.queries.length>125||!['zero_dml','statement_closed','session_closed'].every(k=>f[k]===true))return false;
    let end=BigInt(f.trigger_ns);
    for(const q of f.queries){if(!exact(q,['start_ns','end_ns'])||!number(q.start_ns)||!number(q.end_ns)||BigInt(q.start_ns)-end<125000000n||BigInt(q.end_ns)<BigInt(q.start_ns)||BigInt(q.end_ns)>=2500000000n)return false;end=BigInt(q.end_ns);}
    return m;
}
export function cacheCommand(kind,context,statement) {
    if(!validWaitContext(context)||context.source!==nativeBase||!['bind','arm','execute','finish'].includes(kind)||kind==='execute'&&(!Number.isInteger(statement)||statement<1||statement>4294967295)||kind!=='execute'&&statement!==undefined)throw Error('cache_command');
    return JSON.stringify({domain:cacheNativeDomain,kind,context,...(kind==='execute'?{statement}:{})})+'\n';
}
