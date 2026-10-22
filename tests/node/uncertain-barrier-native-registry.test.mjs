// Synthetic complete packets/credits only, not a native wait or timeout proof.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createUncertainNativeRegistry } from '../../scripts/tests/uncertain-barrier-native-registry.mjs';
import { frame,commit,ok } from './support/native-no-fault-fixture.mjs';
import { sourceFixture as fixture, rowSql } from './uncertain-barrier-native-source-fixture.mjs';
import { nativeTimeoutFixture } from './uncertain-barrier-native-fixtures.mjs';

for(const profile of ['selected','released'])test(`${profile} positive selected trace retains four roles and exact successful drain`,()=>{
    const f=fixture(profile);assert.equal(f.ready(),true);assert.equal(f.loss(),true);assert.equal(f.api.close(f.ids.writer),true);assert.equal(f.barrier(),true);
    const r=f.finish();assert.equal(r.passed,true);assert.equal(r.selected_response_discarded,true);assert.equal(r.writer_commit_delivered,false);assert.equal(r.barrier_commit_delivered,true);assert.equal(r.native_timeout_suffix,false);
    assert.equal(r.barrier_progress.diagnostic_only,true);assert.equal(r.barrier_progress.safety_execute_seen,true);
    assert.equal(r.barrier_statements.rows_closed,10);assert.equal(r.barrier_statements.failed,false);
});
test('held trace accepts only actual error-shaped first execute plus complete rollback suffix; no barrier COMMIT',()=>{
    const f=fixture('held');assert.equal(f.ready(),true);assert.equal(f.loss(),true);assert.equal(f.api.close(f.ids.writer),true);f.first();f.execute();
    for(const [d,n,b]of nativeTimeoutFixture())assert.equal(f.send('barrier',d,frame(b,n)),true);
    assert.equal(f.api.close(f.ids.barrier),true);const r=f.finish();assert.equal(r.passed,true);assert.equal(r.native_timeout_suffix,true);assert.equal(r.barrier_commit_delivered,false);assert.equal(r.exact_writer_drain,false);
    assert.equal(r.barrier_progress.safety_execute_seen,true);assert.equal(r.barrier_progress.server_error_code,1205);assert.equal(r.barrier_statements.rows_closed,0);
});
for(const fault of ['early-error','missing-credit','coalesced-tail','fifth-role','wrong-statement','wrong-error','missing-suffix','successful-barrier'])test('new native registry refuses '+fault,()=>{
    const f=fixture('held');assert.equal(f.ready(),true);
    if(fault==='missing-credit'){assert.equal(f.loss(false),false);return;}
    if(fault==='coalesced-tail'){assert.equal(f.loss(true,1),false);return;}
    assert.equal(f.loss(),true);assert.equal(f.api.close(f.ids.writer),true);f.first();
    if(fault==='fifth-role')assert.equal(f.api.open(Symbol('direct-helper-must-not-relay')),false);
    else if(fault==='early-error')assert.equal(f.send('barrier','server',frame(nativeTimeoutFixture()[0][2],1)),false);
    else if(fault==='successful-barrier'){assert.equal(f.committed('barrier'),false);assert.equal(f.api.close(f.ids.barrier),false);}
    else {f.execute();const rows=nativeTimeoutFixture();if(fault==='wrong-error')rows[0][2]=Buffer.from([255,182,4,35]);if(fault==='wrong-statement')rows[1][2][1]=8;for(const [d,n,b]of (fault==='missing-suffix'?rows.slice(0,-1):rows))f.send('barrier',d,frame(b,n));assert.equal(f.api.close(f.ids.barrier),false);}
    assert.notEqual(f.api.finish(true).passed,true);
});
test('timeout execute and original upstream credit checks are assertion-killed without changing native source',async()=>{
    const url=new URL('../../scripts/tests/uncertain-barrier-native-registry.mjs',import.meta.url),raw=readFileSync(url,'utf8');
    for(const [from,to,run]of [
        ['const statement=r.statements.timeoutStatement();','const statement=7;',factory=>{const f=fixture('held',factory);assert.equal(f.ready(),true);assert.equal(f.loss(),true);assert.equal(f.api.close(f.ids.writer),true);f.first();f.prepare(rowSql(0),7);assert.equal(f.send('barrier','server',frame(nativeTimeoutFixture()[0][2],1)),false);}],
        ['!r.requestDone || seq !== 1','seq !== 1',factory=>{const f=fixture('selected',factory);assert.equal(f.ready(),true);assert.equal(f.send('writer','client',frame(commit()),false),true);assert.equal(f.api.frame(f.ids.writer,'server',frame(ok(),1),Symbol('uncredited'),0),false);}]
    ]){assert.equal(raw.split(from).length,2);const code=raw.replace(from,to).replace(/from '(\.\/[^']+)'/g,(_,p)=>`from '${new URL(p,url).href}'`),mod=await import('data:text/javascript;base64,'+Buffer.from(code).toString('base64'));assert.throws(()=>run(mod.createUncertainNativeRegistry),assert.AssertionError);assert.equal(readFileSync(url,'utf8'),raw);}
});
