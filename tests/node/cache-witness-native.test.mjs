// Synthetic protocol and original-registry API tests, NOT genuine authentication/SQL proof.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { context,creditFixture,packet,command } from './wait-witness-fixture.mjs';
import { nativeNoFaultFixture,barrierPackets } from './support/native-no-fault-fixture.mjs';
import { metadataQueries,rowQuery } from '../../scripts/tests/uncertain-barrier-native-statements.mjs';
import { createCacheWitnessRegistry } from '../../scripts/tests/cache-witness-native-registry.mjs';
import { parseCacheHelper,cacheCommand,cacheNativeDomain,cacheSetupDomain } from '../../scripts/tests/cache-witness-native-protocol.mjs';
import { replayCacheExpiry } from '../../scripts/tests/cache-witness-native-replay.mjs';
import { verifyCacheWitnessIdentity } from '../../scripts/tests/cache-witness-native-identity.mjs';
const metrics=()=>({trigger_ns:'1000000',elapsed_ns:'2500000000',queries:[{start_ns:'126000000',end_ns:'127000000'},{start_ns:'252000000',end_ns:'253000000'}],zero_dml:true,statement_closed:true,session_closed:true});
const refused=(f=metrics(),c=context())=>({domain:cacheNativeDomain,kind:'refused',role:'monitor',reason:'expired',metrics:f,context:c});
function fixture(factory=createCacheWitnessRegistry){
    const f=nativeNoFaultFixture(factory);let n=10;
    const prepare=sql=>{const id=++n;assert.equal(f.send('barrier','client',packet(Buffer.concat([Buffer.from([22]),Buffer.from(sql)]))),true);const b=Buffer.alloc(12);b.writeUInt32LE(id,1);assert.equal(f.send('barrier','server',packet(b,1)),true);return id;};
    const execute=id=>f.send('barrier','client',command(23,id,10)),close=id=>f.send('barrier','client',command(25,id,5));
    const cycle=sql=>{const id=prepare(sql);assert.equal(execute(id),true);assert.equal(close(id),true);};
    const pre=()=>{assert.equal(f.ready(),true);assert.equal(f.writer(),true);assert.equal(f.handshake('barrier'),true);for(const sql of metadataQueries)cycle(sql);assert.equal(f.api.bindBarrier(f.roles),true);};
    return {...f,pre,prepare,execute,close,cycle};
}
test('successor and historical source closures verify, no native execution',()=>{assert.equal(verifyCacheWitnessIdentity(),true);assert.equal(verifyCacheWitnessIdentity('override'),false);});
test('real original registry API accounts first exact safety execute once; full original committed drain still passes',async()=>{
    const f=fixture();f.pre();assert.equal(f.api.bindContext(context()),true);
    const id=f.prepare(rowQuery(context().table,0)),credit=Symbol('actual-original-api-credit'),p=command(23,id,10);let signalled=false;
    const promised=f.api.execution().then(r=>{signalled=true;return r;});
    assert.equal(f.api.frame(f.ids.barrier,'client',p,credit),true);await Promise.resolve();assert.equal(signalled,false);
    assert.equal(f.api.account(f.ids.barrier,'client',credit),true);const signal=await promised;assert.equal(signal.statement,id);
    assert.equal(replayCacheExpiry(context(),signal,metrics()),true);assert.equal(replayCacheExpiry(context(),signal,metrics()),false);
    assert.equal(f.close(id),true);for(const sql of metadataQueries)f.cycle(sql);for(let n=1;n<10;n++)f.cycle(rowQuery(context().table,n));
    assert.equal(f.committed('barrier'),true);assert.equal(f.sendPackets('barrier',barrierPackets()),true);assert.equal(f.api.close(f.ids.barrier),true);
    const result=f.finish();assert.equal(result.passed,true);assert.equal(result.cache_credit_verified,true);assert.equal(result.suppression_supported,false);
});
for(const fault of ['missing-metadata','wrong-roles','rebind','foreign-table','foreign-credit','unavailable'])test('original registry tap refuses '+fault,async()=>{
    const f=fixture();if(fault==='missing-metadata'){assert.equal(f.api.bindContext(context()),false);return;}
    f.pre();const c=context();if(fault==='wrong-roles'){c.roles.auth=999;assert.equal(f.api.bindContext(c),false);return;}
    assert.equal(f.api.bindContext(c),true);
    if(fault==='rebind')assert.equal(f.api.bindContext(c),false);
    else if(fault==='unavailable')assert.equal(f.api.unavailable(),false);
    else if(fault==='foreign-table'){f.prepare(rowQuery('wp_alynt_core_probe_abcdef012345_options',0));}
    else {const id=f.prepare(rowQuery(c.table,0));assert.equal(f.api.frame(f.ids.barrier,'client',command(23,id,10),Symbol()),true);assert.equal(f.api.account(f.ids.barrier,'client',Symbol()),false);}
    assert.equal((await f.api.execution()),false);assert.equal(f.api.finish(false).cache_credit_verified,false);
});
test('canonical no-match refusal accepts valid metrics but never WAITING/positive CLOSED',()=>{
    const c=context(),good=refused();assert.equal(parseCacheHelper(JSON.stringify(good),'monitor',c,'x').reason,'expired');
    for(const k of Object.keys(good)){const m=structuredClone(good);delete m[k];assert.equal(parseCacheHelper(JSON.stringify(m),'monitor',c,'x'),false);}
    for(const kind of ['waiting','closed','ready'])assert.equal(parseCacheHelper(JSON.stringify({...good,kind}),'monitor',c,'x'),false);
    for(const key of ['zero_dml','statement_closed','session_closed']){const m=metrics();m[key]=false;assert.equal(parseCacheHelper(JSON.stringify(refused(m)),'monitor',c,'x'),false);}
    const bad=[{...metrics(),trigger_ns:'2500000000'},{...metrics(),elapsed_ns:'2499999999'},{...metrics(),elapsed_ns:'3250000001'},{...metrics(),queries:[]}, {...metrics(),queries:[{start_ns:'125999999',end_ns:'127000000'}]}, {...metrics(),queries:[{start_ns:'126000000',end_ns:'125000000'}]}, {...metrics(),queries:[{start_ns:'126000000',end_ns:'127000000'},{start_ns:'251999999',end_ns:'253000000'}]}, {...metrics(),queries:[{start_ns:'2500000000',end_ns:'2500000000'}]}];
    for(const m of bad)assert.equal(parseCacheHelper(JSON.stringify(refused(m)),'monitor',c,'x'),false);
    for(const text of [JSON.stringify(good)+' ',JSON.stringify(good).replace('"expired"','"expi\\u0072ed"'),JSON.stringify(good).replace('"kind":"refused"','"kind":"refused","kind":"refused"'),'x'.repeat(4096)])assert.equal(parseCacheHelper(text,'monitor',c,'x'),false);
    assert.equal(parseCacheHelper(JSON.stringify(refused(metrics(),{...c,root:'c'.repeat(64)})),'monitor',c,'x'),false);
});
test('setup source and command domains are separate, roles fixed and commands have no SQL or lock entry',()=>{
    const m={domain:cacheSetupDomain,kind:'hello',role:'idle',thread:105,source:'c'.repeat(64)};
    assert.equal(parseCacheHelper(JSON.stringify(m),'idle',null,'c'.repeat(64)).thread,105);
    assert.equal(parseCacheHelper(JSON.stringify(m),'monitor',null,'c'.repeat(64)),false);assert.equal(parseCacheHelper(JSON.stringify(m),'idle',null,'d'.repeat(64)),false);
    for(const kind of ['bind','arm','finish'])assert.equal(JSON.parse(cacheCommand(kind,context())).kind,kind);
    assert.equal(JSON.parse(cacheCommand('execute',context(),7)).statement,7);
    for(const kind of ['lock','rollback','watch','sql'])assert.throws(()=>cacheCommand(kind,context()),/cache_command/);
    assert.throws(()=>cacheCommand('execute',context(),0));assert.throws(()=>cacheCommand('arm',context(),7));
});
test('replay cannot accept forged, early or wrong-context receipt and never creates actual wait authority',()=>{
    assert.equal(replayCacheExpiry(context(),{statement:11,receipt:{}},metrics()),false);
    const r=creditFixture().receipt(),c={...context(),root:'d'.repeat(64)};assert.equal(replayCacheExpiry(c,r,metrics()),false);
    assert.equal(replayCacheExpiry(context(),r,metrics()),false);
    const early=metrics();early.queries[0].start_ns='125999999';assert.equal(replayCacheExpiry(context(),creditFixture().receipt(),early),false);
});
test('native helper source keeps exact target, real monotonic clock, zero-DML and no-fault-only behavior',()=>{
    const src=readFileSync(new URL('../../scripts/tests/cache-witness-native-helper.php',import.meta.url),'utf8');
    assert.match(src,/125000000/);assert.match(src,/\$previous = hrtime\( true \)/);assert.match(src,/\$deadline = \$prepared \+ 2500000000/);
    assert.doesNotMatch(src,/usleep\( 20000 \)|START TRANSACTION|FOR UPDATE|COMMIT|ROLLBACK|WAITING'/);
    assert.match(src,/\$rows \|\| \$previous >= \$deadline/);assert.match(src,/Alynt_Test_Cache_Witness_Native_Manifest::verify\(\)/);
    const control=readFileSync(new URL('../../scripts/tests/cache-witness-native-control.mjs',import.meta.url),'utf8');
    assert.ok(control.indexOf('helpers.bind(ids,attempt,root,registry)')<control.indexOf("send('bound_barrier'"));
    const proof=readFileSync(new URL('../../scripts/tests/cache-witness-native-proof.mjs',import.meta.url),'utf8');assert.match(proof,/runUncertainNativeCase\('no_fault'\)/);assert.doesNotMatch(proof,/runUncertainNativeCase\('(selected|released|held)'\)/);
    const ci=readFileSync(new URL('../../.github/workflows/quality.yml',import.meta.url),'utf8');assert.match(ci,/cache-witness-native-\*\.php; do php -l/);assert.match(ci,/cache-witness-native-\*\.mjs; do node --check/);assert.doesNotMatch(ci,/node scripts\/tests\/cache-witness-native-proof/);
});
test('required native metric guards are assertion-killed after successful import',async()=>{
    const url=new URL('../../scripts/tests/cache-witness-native-protocol.mjs',import.meta.url),raw=readFileSync(url,'utf8');
    for(const [from,m]of [['BigInt(q.start_ns)-end<125000000n',{...metrics(),queries:[{start_ns:'20000000',end_ns:'21000000'}]}],['BigInt(f.elapsed_ns)<2500000000n',{...metrics(),elapsed_ns:'2499999999'}],['BigInt(q.end_ns)<BigInt(q.start_ns)',{...metrics(),queries:[{start_ns:'200000000',end_ns:'199999999'}]}]]){
        assert.equal(raw.split(from).length,2);const source=raw.replace(from,'false').replace(/from '(\.\/[^']+)'/g,(_,p)=>`from '${new URL(p,url).href}'`),mod=await import('data:text/javascript;base64,'+Buffer.from(source).toString('base64'));
        assert.throws(()=>assert.equal(mod.parseCacheHelper(JSON.stringify(refused(m)),'monitor',context(),'x'),false),assert.AssertionError);assert.equal(readFileSync(url,'utf8'),raw);
    }
});
