import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { parseNativeQualificationLine,profiles } from '../../scripts/tests/uncertain-barrier-native-grammar.mjs';
import { createNativeQualificationNativeTranscript } from '../../scripts/tests/uncertain-barrier-native-transcript.mjs';
import { verifyUncertainNativeIdentity } from '../../scripts/tests/uncertain-barrier-native-identity.mjs';
import { validDirectFrame } from '../../scripts/tests/uncertain-barrier-native-direct.mjs';
import { createNativeTimeoutDrain } from '../../scripts/tests/uncertain-barrier-native-drain.mjs';
import { nativeTerminalFixture,nativeTimeoutFixture } from './uncertain-barrier-native-fixtures.mjs';
test('new source closure and immutable predecessors verify without native execution',()=>{assert.equal(verifyUncertainNativeIdentity(),true);assert.equal(verifyUncertainNativeIdentity('provider'),false);});
for(const profile of profiles) for(const width of [1,3,4096])test(`exact ${profile} original terminal/qualification transcript width${width}`,()=>{
    const {source,lines}=nativeTerminalFixture(profile),p=createNativeQualificationNativeTranscript(source,profile);
    for(let n=0;n<8;n++)for(let i=0;i<lines[n].length;i+=width)assert.equal(p.feed(n%2?'worker':'parent',lines[n].slice(i,i+width),BigInt(n)),true);
    const result=p.finish();assert.equal(result.synthetic_match,true);for(const [k,v]of Object.entries(result))if(k!=='synthetic_match')assert.equal(v,false);assert.equal(p.finish(),false);
});
for(const profile of profiles)test(`${profile} cannot pass always-unavailable, missing terminal/qualifier/guard, changed source or opposite branch`,()=>{
    const {source,frames}=nativeTerminalFixture(profile),m=frames[7],bad=[];
    for(const k of Object.keys(m.terminal)){const x=structuredClone(m);delete x.terminal[k];bad.push(x);}
    for(let n=0;n<m.guard_order.length;n++){const x=structuredClone(m);x.guard_order.splice(n,1);bad.push(x);}
    for(const k of ['original_slots_empty','writer_erased_before_read'])bad.push({...m,[k]:false});
    bad.push({...m,native_result:false},{...m,terminal:false},{...m,terminal:{...m.terminal,phase:'poisoned'}});
    const q=structuredClone(m);q.observation.entries=0;bad.push(q);
    const history=structuredClone(m);history.observation.result.historical_outcome=profile==='no_fault'?'uncertain':'committed';bad.push(history);
    for(const x of bad)assert.equal(parseNativeQualificationLine(JSON.stringify(x),source),false);
    const p=createNativeQualificationNativeTranscript(source,profile);for(let n=0;n<7;n++)assert.equal(p.feed(n%2?'worker':'parent',JSON.stringify(frames[n])+'\n',BigInt(n)),true);
    assert.equal(p.feed('worker',JSON.stringify(m)+'\nx',7n),false);assert.equal(p.finish().synthetic_match,false);
});
test('direct witnesses require six distinct identities, current source/root, exact ownership and actual-wait fields',()=>{
    const context={attempt:'a'.repeat(64),root:'b'.repeat(64),source:'c'.repeat(64),roles:{auth:101,capture:102,writer:103,barrier:104,blocker:105,monitor:106}};
    const frames=[['blocker',{domain:'alynt.uncertain-barrier.direct.v1',kind:'ready',blocker:105,barrier:104,zero_dml:true,context}],['monitor',{domain:'alynt.uncertain-barrier.direct.v1',kind:'waiting',requester:104,blocker:105,monitor:106,owned_table:true,role:'safety',queries:1,context}],['blocker',{domain:'alynt.uncertain-barrier.direct.v1',kind:'released',rollback_acknowledged:true,zero_dml:true,context}]];
    for(const [role,m]of frames){assert.equal(validDirectFrame(m,role,context),true);for(const k of Object.keys(m)){const x=structuredClone(m);delete x[k];assert.equal(validDirectFrame(x,role,context),false);}const stale=structuredClone(m);stale.context.root='d'.repeat(64);assert.equal(validDirectFrame(stale,role,context),false);}
    const m=frames[1][1];for(const [k,v]of [['requester',103],['blocker',104],['monitor',105],['owned_table',false],['role','ledger'],['queries',0],['queries',126]])assert.equal(validDirectFrame({...m,[k]:v},'monitor',context),false);
    const duplicate=structuredClone(context);duplicate.roles.monitor=105;assert.equal(validDirectFrame({...m,context:duplicate},'monitor',duplicate),false);
});
test('native timeout suffix requires exact error, statement ID, transaction/policy, acknowledged rollback and same physical session',()=>{
    const rows=nativeTimeoutFixture(),p=createNativeTimeoutDrain(7,104);for(const r of rows)assert.equal(p.accept(...r),true);assert.equal(p.done(),true);
    for(let n=0;n<rows.length;n++){const p=createNativeTimeoutDrain(7,104);let accepted=true;for(let i=0;i<rows.length;i++)if(i!==n)accepted=p.accept(...rows[i])&&accepted;assert.equal(accepted&&p.done(),false,`omitted frame ${n}`);}
    for(const thread of [103,105]){const p=createNativeTimeoutDrain(7,thread);for(const r of rows)p.accept(...r);assert.equal(p.done(),false);}
    const p2=createNativeTimeoutDrain(8,104);for(const r of rows)p2.accept(...r);assert.equal(p2.done(),false);
});
test('guard, qualifier, original result and direct wait predicates are assertion-killed without source/native mutation',async()=>{
    for(const [file,from,to,run]of [
        ['uncertain-barrier-native-grammar.mjs','m.post_guards===12','true',mod=>{const {source,frames}=nativeTerminalFixture('held');frames[7].post_guards=14;assert.equal(mod.parseNativeQualificationLine(JSON.stringify(frames[7]),source),false);}],
        ['uncertain-barrier-native-grammar.mjs','r.entries !== 1','false',mod=>{const {source,frames}=nativeTerminalFixture('selected');frames[7].observation.entries=0;assert.equal(mod.parseNativeQualificationLine(JSON.stringify(frames[7]),source),false);}],
        ['uncertain-barrier-native-grammar.mjs','r===null','true',mod=>{const {source,frames}=nativeTerminalFixture('held');frames[7].native_result=false;assert.equal(mod.parseNativeQualificationLine(JSON.stringify(frames[7]),source),false);}],
        ['uncertain-barrier-native-direct.mjs','m.owned_table===true','true',mod=>{const context={attempt:'a'.repeat(64),root:'b'.repeat(64),source:'c'.repeat(64),roles:{auth:1,capture:2,writer:3,barrier:4,blocker:5,monitor:6}};assert.equal(mod.validDirectFrame({domain:'alynt.uncertain-barrier.direct.v1',kind:'waiting',requester:4,blocker:5,monitor:6,owned_table:false,role:'safety',queries:1,context},'monitor',context),false);}]
    ]){const url=new URL('../../scripts/tests/'+file,import.meta.url),raw=readFileSync(url,'utf8');assert.equal(raw.split(from).length,2);const code=raw.replace(from,to).replaceAll('import.meta.url',JSON.stringify(url.href)).replace(/from '(\.\/[^']+)'/g,(_,p)=>`from '${new URL(p,url).href}'`),mod=await import('data:text/javascript;base64,'+Buffer.from(code).toString('base64'));assert.throws(()=>run(mod),assert.AssertionError);assert.equal(readFileSync(url,'utf8'),raw);}
});
