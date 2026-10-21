// Synthetic protocol checks only: never imports the supervisor, process or relay.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { qualificationFixture } from './support/native-qualification-fixture.mjs';
import { parseNativeQualificationLine, domain, noFaultProfile } from '../../scripts/tests/uncertain-barrier-no-fault-grammar.mjs';
import { createNativeQualificationNoFaultTranscript } from '../../scripts/tests/uncertain-barrier-no-fault-transcript.mjs';
import { uncertainNoFaultIdentities, verifyUncertainNoFaultIdentity } from '../../scripts/tests/uncertain-barrier-no-fault-identity.mjs';
function fixture() {
    const f=qualificationFixture(true), source=uncertainNoFaultIdentities();
    for(const m of f.frames) { m.domain=domain; if(Object.hasOwn(m,'profile')) m.profile=noFaultProfile; if(Object.hasOwn(m,'source')) m.source=source; }
    const m=f.frames[7], r=m.native_result;
    m.terminal={outcome:'observed',root_kind:'array',phase:'barrier_released',barrier_receipt:true,admission_reads:40,barrier_reads:10,writes:1,metadata_checks:2,zero_dml_checks:2,root_result:Object.fromEntries(['candidate_only','storage_supported','dispatch_supported','execution_supported','retry_supported','authenticated','classification','barrier_verified','closure_proven'].map(k=>[k,r[k]]))};
    m.genuine_denials=10; m.checks=100;
    return { source,frames:f.frames,lines:f.frames.map(m=>JSON.stringify(m)+'\n') };
}
test('new fixed source closure verifies all immutable predecessors without native execution',()=>{
    assert.equal(verifyUncertainNoFaultIdentity(),true); assert.equal(verifyUncertainNoFaultIdentity('path'),false);
    assert.equal(Object.keys(uncertainNoFaultIdentities()).length,7); assert.throws(()=>uncertainNoFaultIdentities('provider'));
});
for(const width of [1,3,4096]) test(`new committed terminal no-fault transcript width${width}`,()=>{
    const {source,lines}=fixture(),p=createNativeQualificationNoFaultTranscript(source);
    assert.ok(lines.every(l=>l.length<=4096));
    for(let n=0;n<8;n++) for(let i=0;i<lines[n].length;i+=width) assert.equal(p.feed(n%2?'worker':'parent',lines[n].slice(i,i+width),BigInt(n)),true);
    const r=p.finish(); assert.equal(r.synthetic_match,true); for(const [k,v] of Object.entries(r)) if(k!=='synthetic_match') assert.equal(v,false);
    assert.equal(p.finish(),false);
});
test('missing or fabricated terminal, every field, guard and committed provenance independently refuse',()=>{
    const {source,frames}=fixture(),m=frames[7],bad=[];
    for(const k of Object.keys(m.terminal)) { const x=structuredClone(m); delete x.terminal[k]; bad.push(x); }
    for(const k of Object.keys(m.terminal.root_result)) { const x=structuredClone(m); x.terminal.root_result[k]=null; bad.push(x); }
    for(const k of ['outcome','root_kind','phase','barrier_receipt','admission_reads','barrier_reads','writes','metadata_checks','zero_dml_checks']) { const x=structuredClone(m); x.terminal[k]=null; bad.push(x); }
    for(let n=0;n<24;n++){const x=structuredClone(m);x.guard_order.splice(n,1);bad.push(x);}
    const x=structuredClone(m);x.observation.result.historical_outcome='uncertain';bad.push(x);
    bad.push({...m,terminal:false},{...m,domain:'alynt.native-lifecycle-observation.selected.v1'},{...m,profile:'native_lifecycle_no_fault'},{...m,terminal:{...m.terminal,provider:true}});
    for(const x of bad) assert.equal(parseNativeQualificationLine(JSON.stringify(x),source),false);
});
test('wrong direction/source/attempt/root, extra tail and expired first-fragment clocks consume transcript',()=>{
    for(const fault of ['source','attempt','root','direction','tail','clock']) {
        const {source,frames,lines}=fixture(),p=createNativeQualificationNoFaultTranscript(source);
        for(let n=0;n<7;n++)assert.equal(p.feed(n%2?'worker':'parent',lines[n],BigInt(n)),true);
        const m=structuredClone(frames[7]); if(fault==='source')m.source.production='0'.repeat(64); if(fault==='attempt')m.attempt='e'.repeat(64); if(fault==='root')m.root='f'.repeat(64);
        assert.equal(p.feed(fault==='direction'?'parent':'worker',JSON.stringify(m)+'\n'+(fault==='tail'?'x':''),fault==='clock'?20000000000n:7n),false);
        assert.equal(p.finish().synthetic_match,false);
    }
});
test('required terminal validation and guards are assertion-killed in memory, not source or native mutations',async()=>{
    const url=new URL('../../scripts/tests/uncertain-barrier-no-fault-grammar.mjs',import.meta.url),raw=await readFile(url,'utf8'),sha=s=>createHash('sha256').update(s).digest('hex');
    for(const [from,change]of [['terminal(m.terminal, m)',m=>{m.terminal=false;}],['JSON.stringify(m.guard_order) === JSON.stringify(guardOrder)',m=>{m.guard_order.pop();}],['r.entries !== 1',m=>{m.observation.entries=0;}]]) {
        assert.equal(raw.split(from).length,2);
        const code=raw.replace(from,from==='r.entries !== 1'?'false':'true').replace(/from '(\.\/[^']+)'/g,(_,p)=>`from '${new URL(p,url).href}'`);
        const parse=(await import('data:text/javascript;base64,'+Buffer.from(code).toString('base64'))).parseNativeQualificationLine,{source,frames}=fixture(),m=structuredClone(frames[7]);change(m);
        assert.throws(()=>assert.equal(parse(JSON.stringify(m),source),false),{code:'ERR_ASSERTION'});
    }
    assert.equal(sha(await readFile(url,'utf8')),sha(raw));
});
