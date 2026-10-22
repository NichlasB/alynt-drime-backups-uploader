import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { sourceFixture,metadataSql,rowSql,rowDefinitions,table } from './uncertain-barrier-native-source-fixture.mjs';
import { createBarrierStatementRoles,metadataQueries,rowQuery,rowCaps } from '../../scripts/tests/uncertain-barrier-native-statements.mjs';
for(const profile of ['selected','released'])test(`source-backed ${profile} pre-BEGIN and post-safety metadata executions remain distinct from safety`,()=>{
    const f=sourceFixture(profile);assert.equal(f.ready(),true);assert.equal(f.loss(),true);assert.equal(f.api.close(f.ids.writer),true);assert.equal(f.barrier(),true);assert.equal(f.finish().passed,true);
});
test('fixed classifier matches production metadata queries, ten row caps and original locking SELECT exactly',()=>{
    assert.deepEqual(metadataQueries,metadataSql);assert.deepEqual(rowCaps,rowDefinitions.map(r=>r.cap));assert.equal(rowDefinitions[0].role,'safety');
    for(let n=0;n<10;n++)assert.equal(rowQuery(table,n),rowSql(n));
    for(const name of ['wp_options','other_options',table+'`',null])assert.equal(rowQuery(name,0),false);
    for(const n of [-1,10,0.5,null])assert.equal(rowQuery(table,n),false);
});
function rolesFixture(factory=createBarrierStatementRoles) {
    const p=factory();let id=10;
    const prepare=(sql,n=++id)=>{assert.equal(p.accept('client',0,Buffer.concat([Buffer.from([22]),Buffer.from(sql)])),true);const b=Buffer.alloc(12);b.writeUInt32LE(n,1);assert.equal(p.accept('server',1,b),true);return n;};
    const execute=n=>{const b=Buffer.alloc(10);b[0]=23;b.writeUInt32LE(n,1);return p.accept('client',0,b);};
    const close=n=>{const b=Buffer.alloc(5);b[0]=25;b.writeUInt32LE(n,1);return p.accept('client',0,b);};
    const cycle=sql=>{const n=prepare(sql);assert.equal(execute(n),true);assert.equal(close(n),true);};
    const pre=()=>{for(const sql of metadataQueries)cycle(sql);assert.equal(p.ready(),true);assert.equal(p.bind(),true);};
    return {p,prepare,execute,close,cycle,pre};
}
test('first bound safety execute alone qualifies timeout; metadata and later rows cannot masquerade',()=>{
    const f=rolesFixture();f.pre();assert.equal(f.p.timeoutStatement(),false);const n=f.prepare(rowSql(0));assert.equal(f.p.timeoutStatement(),false);assert.equal(f.execute(n),true);assert.equal(f.p.timeoutStatement(),n);assert.equal(f.close(n),true);
    for(const sql of metadataQueries){const n=f.prepare(sql);assert.equal(f.execute(n),true);assert.equal(f.p.timeoutStatement(),false);assert.equal(f.close(n),true);}
    for(let i=1;i<10;i++){const n=f.prepare(rowSql(i));assert.equal(f.execute(n),true);assert.equal(f.p.timeoutStatement(),false);assert.equal(f.close(n),true);}
    assert.equal(f.p.complete(),true);
});
for(const fault of ['unknown-sql','wrong-metadata-order','bind-before-metadata','row-before-bind','unknown-execute','wrong-close','replay-execute','duplicate-prepare','post-safety-skip'])test('prepared role contract refuses '+fault,()=>{
    const f=rolesFixture();
    if(fault==='bind-before-metadata')assert.equal(f.p.bind(),false);
    else if(fault==='unknown-sql'||fault==='wrong-metadata-order'||fault==='row-before-bind')assert.equal(f.p.accept('client',0,Buffer.concat([Buffer.from([22]),Buffer.from(fault==='unknown-sql'?'SELECT 1':fault==='row-before-bind'?rowSql(0):metadataQueries[1])])),false);
    else {f.pre();const n=f.prepare(rowSql(0));
        if(fault==='unknown-execute')assert.equal(f.execute(n+1),false);
        else if(fault==='duplicate-prepare')assert.equal(f.p.accept('client',0,Buffer.concat([Buffer.from([22]),Buffer.from(rowSql(0))])),false);
        else {assert.equal(f.execute(n),true);if(fault==='replay-execute')assert.equal(f.execute(n),false);else if(fault==='wrong-close')assert.equal(f.close(n+1),false);else{assert.equal(f.close(n),true);assert.equal(f.p.accept('client',0,Buffer.concat([Buffer.from([22]),Buffer.from(rowSql(1))])),false);}}
    }
    assert.equal(f.p.complete(),false);assert.equal(f.p.timeoutStatement(),false);
});
test('known statement ID and exact metadata order are independently assertion-killed without native/source mutation',async()=>{
    const url=new URL('../../scripts/tests/uncertain-barrier-native-statements.mjs',import.meta.url),raw=readFileSync(url,'utf8');
    for(const [from,to,check]of [
        ['b.readUInt32LE(1)!==live.id','false',factory=>{const f=rolesFixture(factory);f.pre();const n=f.prepare(rowSql(0));assert.equal(f.execute(n+1),false);}],
        ['sql!==metadataQueries[metadata]','false',factory=>{const f=rolesFixture(factory);assert.equal(f.p.accept('client',0,Buffer.concat([Buffer.from([22]),Buffer.from(metadataQueries[1])])),false);}]
    ]){assert.equal(raw.split(from).length,2);const mod=await import('data:text/javascript;base64,'+Buffer.from(raw.replace(from,to)).toString('base64'));assert.throws(()=>check(mod.createBarrierStatementRoles),assert.AssertionError);assert.equal(readFileSync(url,'utf8'),raw);}
});
