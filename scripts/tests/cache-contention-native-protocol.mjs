// Fixed released/held private frames only. No SQL, native handles or success inference.
import { exact } from './selected-qualification-grammar.mjs';
import { validWaitContext,exactWaitTuple } from './wait-witness-contract.mjs';
export const contentionDomain='alynt.uncertain-barrier.wait-contended.v1';
export const contentionSetup='alynt.uncertain-barrier.wait-contended-setup.v1';
export const contentionProfiles=Object.freeze(['released','held']);
export const contentionStages=Object.freeze(['connect','target','hello','command','policy','counters','begin','prepare','lock','ready','arm','execute','query','waiting','finish','rollback','close','closed']);
export const contentionReasons=Object.freeze(['database','protocol','owned','late_execute','expired','query_cap','late_query','wait_identity','safety_row','rollback','close','context','thread','owner','input','target','counters','dml','output']);
const ns=s=>typeof s==='string'&&/^(?:0|[1-9][0-9]{0,10})$/.test(s);
export function validContentionMetrics(m){
    if(!exact(m,['trigger_ns','queries','zero_dml'])||!ns(m.trigger_ns)||BigInt(m.trigger_ns)>=2500000000n||m.zero_dml!==true||!Array.isArray(m.queries)||!m.queries.length||m.queries.length>125)return false;
    let previous=BigInt(m.trigger_ns);
    for(let i=0;i<m.queries.length;i++){
        const q=m.queries[i];
        if(!exact(q,['start_ns','end_ns','matched'])||!ns(q.start_ns)||!ns(q.end_ns)||BigInt(q.start_ns)-previous<125000000n||BigInt(q.end_ns)<BigInt(q.start_ns)||BigInt(q.end_ns)>=2500000000n||q.matched!==(i===m.queries.length-1))return false;
        previous=BigInt(q.end_ns);
    }
    return true;
}
export function parseContentionHelper(line,role,profile,context,source){
    if(arguments.length!==5||!['blocker','monitor'].includes(role)||!contentionProfiles.includes(profile)||typeof line!=='string'||!line.length||line.length>4095||!/^[\x20-\x5b\x5d-\x7e]+$/.test(line))return false;
    let depth=0,quoted=false;for(const ch of line){if(ch==='"')quoted=!quoted;else if(!quoted&&(ch==='{'||ch==='[')&&++depth>8)return false;else if(!quoted&&(ch==='}'||ch===']'))depth--;}
    let m;try{m=JSON.parse(line);}catch{return false;}
    if(!m||JSON.stringify(m)!==line||m.role!==role||m.profile!==profile)return false;
    if(m.kind==='refused'){
        const f=m.failure;
        return exact(m,['domain','kind','role','profile','failure',...(context===null?[]:['context'])])&&m.domain===(context===null?contentionSetup:contentionDomain)&&(context===null||validWaitContext(context)&&JSON.stringify(m.context)===JSON.stringify(context))&&exact(f,['stage','reason','code'])&&contentionStages.includes(f.stage)&&contentionReasons.includes(f.reason)&&[0,1205,1213,2006,2013].includes(f.code)&&(f.reason==='database'||f.code===0)?m:false;
    }
    if(context===null)return exact(m,['domain','kind','role','profile','thread','source'])&&m.domain===contentionSetup&&m.kind==='hello'&&typeof source==='string'&&/^[a-f0-9]{64}$/.test(source)&&m.source===source&&Number.isInteger(m.thread)&&m.thread>0&&m.thread<=4294967295?m:false;
    if(!validWaitContext(context)||m.domain!==contentionDomain||JSON.stringify(m.context)!==JSON.stringify(context))return false;
    if(m.kind==='closed')return exact(m,['domain','kind','role','profile','zero_dml','closed_ns','context'])&&m.zero_dml===true&&ns(m.closed_ns)&&BigInt(m.closed_ns)<=28000000000n?m:false;
    if(role==='blocker')return m.kind==='ready'?exact(m,['domain','kind','role','profile','zero_dml','context'])&&m.zero_dml===true?m:false:m.kind==='released'&&exact(m,['domain','kind','role','profile','rollback_acknowledged','zero_dml','context'])&&m.rollback_acknowledged===true&&m.zero_dml===true?m:false;
    if(m.kind==='armed')return exact(m,['domain','kind','role','profile','zero_dml','context'])&&m.zero_dml===true?m:false;
    return m.kind==='waiting'&&exact(m,['domain','kind','role','profile','wait','metrics','context'])&&exactWaitTuple(m.wait,context)&&validContentionMetrics(m.metrics)?m:false;
}
export function contentionCommand(kind,profile,context,statement){
    if(!contentionProfiles.includes(profile)||!validWaitContext(context)||!['lock','arm','execute','rollback','finish'].includes(kind)||kind==='execute'&&(!Number.isInteger(statement)||statement<1||statement>4294967295)||kind!=='execute'&&statement!==undefined)throw Error('contention_command');
    return JSON.stringify({domain:contentionDomain,kind,profile,context,...(kind==='execute'?{statement}:{})})+'\n';
}
