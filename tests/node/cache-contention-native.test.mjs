// Isolated original-registry and synthetic-byte tests, NOT native wait/authentication proof.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { context,creditFixture,command } from './wait-witness-fixture.mjs';
import { sourceFixture,rowSql,metadataSql } from './uncertain-barrier-native-source-fixture.mjs';
import { frame,barrierPackets } from './support/native-no-fault-fixture.mjs';
import { nativeTimeoutFixture } from './uncertain-barrier-native-fixtures.mjs';
import { createCacheContentionRegistry } from '../../scripts/tests/cache-contention-native-registry.mjs';
import { parseContentionHelper,contentionDomain,contentionSetup,validContentionMetrics,contentionCommand } from '../../scripts/tests/cache-contention-native-protocol.mjs';
import { createContentionReplay } from '../../scripts/tests/cache-contention-native-replay.mjs';
import { verifyCacheContentionIdentity,cacheContentionDigest } from '../../scripts/tests/cache-contention-native-identity.mjs';
const metrics=()=>({trigger_ns:'1000000',queries:[{start_ns:'126000000',end_ns:'127000000',matched:false},{start_ns:'252000000',end_ns:'253000000',matched:true}],zero_dml:true});
const wait=c=>({requester:c.roles.barrier,blocker:c.roles.blocker,monitor:c.roles.monitor,table:c.table,index:'option_name',role:'safety'});
const message=(profile='released',c=context())=>({domain:contentionDomain,kind:'waiting',role:'monitor',profile,wait:wait(c),metrics:metrics(),context:c});
function fixture(profile,factory=createCacheContentionRegistry){
    const f=sourceFixture(profile,factory);assert.equal(f.ready(),true);assert.equal(f.loss(),true);assert.equal(f.api.close(f.ids.writer),true);f.first();return f;
}
test('new and unchanged predecessor seals verify source-only, no caller override',()=>{assert.equal(verifyCacheContentionIdentity(),true);assert.equal(verifyCacheContentionIdentity('provider'),false);});
for(const profile of ['released','held'])test(`non-vacuous ${profile} original selected registry, actual API credit and exact terminal suffix`,async()=>{
    const f=fixture(profile);assert.equal(f.api.bindContext(context()),true);const id=f.prepare(rowSql(0),7),credit=Symbol('original-api-credit');let signalled=false;
    const pending=f.api.execution().then(r=>{signalled=true;return r;});assert.equal(f.api.frame(f.ids.barrier,'client',command(23,id,10),credit,0),true);await Promise.resolve();assert.equal(signalled,false);
    assert.equal(f.api.account(f.ids.barrier,'client',credit),true);const signal=await pending;assert.equal(signal.statement,7);
    const replay=createContentionReplay(context(),signal,metrics(),wait(context()));assert.notEqual(replay,false);assert.equal(replay.finish(true,'2100000000'),true);assert.equal(replay.finish(true,'2100000000'),false);
    if(profile==='released'){
        assert.equal(f.closeId(id),true);for(const sql of metadataSql)f.cycle(sql);for(let n=1;n<10;n++)f.cycle(rowSql(n));
        assert.equal(f.committed('barrier'),true);assert.equal(f.sendPackets('barrier',barrierPackets()),true);
    }else for(const [direction,seq,body] of nativeTimeoutFixture())assert.equal(f.send('barrier',direction,frame(body,seq)),true);
    assert.equal(f.api.close(f.ids.barrier),true);const result=f.finish();assert.equal(result.passed,true);assert.equal(result.cache_credit_verified,true);assert.equal(result.selected_response_discarded,true);assert.equal(result.writer_commit_delivered,false);
    assert.equal(result.barrier_commit_delivered,profile==='released');assert.equal(result.native_timeout_suffix,profile==='held');
});
for(const fault of ['unavailable','wrong-roles','unbound','foreign-credit','foreign-table'])test('selected tap fails closed for '+fault,async()=>{
    const f=fixture('released');if(fault==='unbound'){assert.equal(f.api.bindContext({...context(),roles:{...context().roles,auth:999}}),false);return;}
    if(fault==='wrong-roles'){const c=context();c.roles.monitor=c.roles.blocker;assert.equal(f.api.bindContext(c),false);assert.equal(f.api.bindContext(context()),false);assert.equal(f.api.finish(false).cache_credit_verified,false);return;}
    assert.equal(f.api.bindContext(context()),true);
    if(fault==='unavailable')assert.equal(f.api.unavailable(),false);
    else if(fault==='foreign-table')f.prepare(rowSql(0).replace(context().table,'wp_alynt_core_probe_abcdef012345_options'));
    else {const id=f.prepare(rowSql(0));assert.equal(f.api.frame(f.ids.barrier,'client',command(23,id,10),Symbol(),0),true);assert.equal(f.api.account(f.ids.barrier,'client',Symbol()),false);}
    assert.equal(await f.api.execution(),false);assert.equal(f.api.finish(false).cache_credit_verified,false);
});
test('context getters are never invoked and an invalid candidate cannot bind again',async()=>{
    const f=fixture('released'),c=context();let reads=0;
    Object.defineProperty(c,'roles',{enumerable:true,get(){reads++;throw Error('forbidden_context_read');}});
    assert.equal(f.api.bindContext(c),false);assert.equal(reads,0);assert.equal(f.api.bindContext(context()),false);assert.equal(await f.api.execution(),false);
});
test('matching native frame preserves exact tuple, current profile and canonical source/root',()=>{
    const good=message();assert.equal(parseContentionHelper(JSON.stringify(good),'monitor','released',context(),cacheContentionDigest).kind,'waiting');
    for(const key of ['requester','blocker','monitor','table','index','role']){const m=structuredClone(good);m.wait[key]=typeof m.wait[key]==='number'?999:'foreign';assert.equal(parseContentionHelper(JSON.stringify(m),'monitor','released',context(),cacheContentionDigest),false);}
    for(const [key,value]of [['profile','held'],['domain','alynt.uncertain-barrier.wait-native.v1'],['kind','refused']])assert.equal(parseContentionHelper(JSON.stringify({...good,[key]:value}),'monitor','released',context(),cacheContentionDigest),false);
    const stale=message('released',{...context(),root:'d'.repeat(64)});assert.equal(parseContentionHelper(JSON.stringify(stale),'monitor','released',context(),cacheContentionDigest),false);
    const hello={domain:contentionSetup,kind:'hello',role:'monitor',profile:'held',thread:106,source:cacheContentionDigest};
    assert.equal(parseContentionHelper(JSON.stringify(hello),'monitor','held',null,cacheContentionDigest).thread,106);assert.equal(parseContentionHelper(JSON.stringify({...hello,source:'c'.repeat(64)}),'monitor','held',null,cacheContentionDigest),false);
});
test('query timing requires actual final match, strict125ms after END and no deadline reset',()=>{
    assert.equal(validContentionMetrics(metrics()),true);
    const bad=[];for(const change of [q=>q[0].start_ns='125999999',q=>q[1].start_ns='251999999',q=>q[1].end_ns='2500000000',q=>q[0].end_ns='125999999',q=>q[0].matched=true,q=>q[1].matched=false]){const m=metrics();change(m.queries);bad.push(m);}
    bad.push({...metrics(),trigger_ns:'2500000000'},{...metrics(),queries:[]},{...metrics(),zero_dml:false},{...metrics(),queries:Array(126).fill(metrics().queries[0])});
    for(const m of bad){assert.equal(validContentionMetrics(m),false);assert.equal(parseContentionHelper(JSON.stringify({...message(),metrics:m}),'monitor','released',context(),cacheContentionDigest),false);}
});
test('garbage, escaped/duplicate/deep/oversized bytes and unbounded failure text cannot become helper receipts',()=>{
    const good=JSON.stringify(message());for(const line of [good+' ',good.replace('"waiting"','"wait\\u0069ng"'),good.replace('"role":"monitor"','"role":"monitor","role":"monitor"'),'['.repeat(9)+']'.repeat(9),'x'.repeat(4096),'{}\n{}'])assert.equal(parseContentionHelper(line,'monitor','released',context(),cacheContentionDigest),false);
    const f={domain:contentionDomain,kind:'refused',role:'monitor',profile:'released',failure:{stage:'query',reason:'expired',code:0},context:context()};assert.equal(parseContentionHelper(JSON.stringify(f),'monitor','released',context(),cacheContentionDigest).failure.reason,'expired');
    for(const change of [{...f,failure:{...f.failure,reason:'private SQL text'}},{...f,failure:{...f.failure,code:123456}},{...f,failure:{...f.failure,stage:'private path'}}])assert.equal(parseContentionHelper(JSON.stringify(change),'monitor','released',context(),cacheContentionDigest),false);
});
test('no-match, forged/stale/replayed execute evidence or false/early closure never proves a wait',()=>{
    assert.equal(createContentionReplay(context(),{statement:7,receipt:{}},metrics(),wait(context())),false);
    const r=creditFixture().receipt(),foreign={...context(),root:'d'.repeat(64)};assert.equal(createContentionReplay(foreign,r,metrics(),wait(foreign)),false);assert.equal(createContentionReplay(context(),r,metrics(),wait(context())),false);
    for(const [zero,closed]of [[false,'2100000000'],[true,'1'],[true,'28000000001']]){const p=createContentionReplay(context(),creditFixture().receipt(),metrics(),wait(context()));assert.notEqual(p,false);assert.equal(p.finish(zero,closed),false);}
    const p=createContentionReplay(context(),creditFixture().receipt(),{...metrics(),queries:metrics().queries.map(q=>({...q,matched:false}))},wait(context()));assert.equal(p,false);
});
test('commands are fixed, profile-bound and cannot admit SQL/providers',()=>{
    assert.equal(JSON.parse(contentionCommand('execute','held',context(),7)).statement,7);
    for(const kind of ['sql','apply','grant'])assert.throws(()=>contentionCommand(kind,'released',context()),/contention_command/);
    assert.throws(()=>contentionCommand('arm','selected',context()));assert.throws(()=>contentionCommand('execute','held',context(),0));assert.throws(()=>contentionCommand('finish','held',context(),7));
    assert.throws(()=>createCacheContentionRegistry('no_fault'));assert.throws(()=>createCacheContentionRegistry('held','handle'));
});
test('native/CI source preserves sole original relay and exact no-retry ordered prerequisites',()=>{
    const root=new URL('../../',import.meta.url),proof=readFileSync(new URL('scripts/tests/cache-contention-native-proof.mjs',root),'utf8'),helper=readFileSync(new URL('scripts/tests/cache-contention-native-helper.php',root),'utf8'),runner=readFileSync(new URL('scripts/tests/cache-contention-native-case.mjs',root),'utf8');
    assert.ok(proof.indexOf("runUncertainNativeCase('no_fault')")<proof.indexOf('runCacheWitnessCase()'));assert.ok(proof.indexOf('runCacheWitnessCase()')<proof.indexOf("runUncertainNativeCase('selected')"));assert.match(proof,/\['released','held'\]/);assert.match(proof,/90000000000n/);assert.equal(proof.split('runCacheContentionCase(profile)').length,2);
    assert.match(runner,/openNativeSelectedRelay\(10004, registry\)/);assert.match(runner,/ownNativeQualificationControl/);assert.match(runner,/fixture\('cleanup_initial'/);assert.match(runner,/relay_resolved: false/);
    assert.match(helper,/\$due = \$previous \+ 125000000/);assert.match(helper,/\$deadline = \$epoch \+ 2500000000/);assert.match(helper,/\$previous >= \$deadline/);assert.match(helper,/array_map\( 'strval', \$row \)/);assert.match(helper,/FOR UPDATE NOWAIT/);assert.doesNotMatch(helper,/usleep\( 20000 \)|\bKILL\b|wp-load\.php/);
    const ci=readFileSync(new URL('.github/workflows/quality.yml',root),'utf8');assert.match(ci,/cache-contention-native-\*\.php; do php -l/);assert.match(ci,/cache-contention-native-\*\.mjs; do node --check/);assert.doesNotMatch(ci,/node scripts\/tests\/cache-contention-native-proof/);
});
test('quiet-gap, tuple and cross-profile guards are assertion-killed after successful imports',async()=>{
    const url=new URL('../../scripts/tests/cache-contention-native-protocol.mjs',import.meta.url),raw=readFileSync(url,'utf8');
    const early=message();early.metrics.queries[0].start_ns='20000000';early.metrics.queries[0].end_ns='21000000';
    const foreign=message();foreign.wait.index='foreign';
    for(const [from,to,m]of [['BigInt(q.start_ns)-previous<125000000n','false',early],['exactWaitTuple(m.wait,context)','true',foreign],['m.profile!==profile','false',{...message(),profile:'held'}]]){
        assert.equal(raw.split(from).length,2);const code=raw.replace(from,to).replace(/from '(\.\/[^']+)'/g,(_,p)=>`from '${new URL(p,url).href}'`),mod=await import('data:text/javascript;base64,'+Buffer.from(code).toString('base64'));
        assert.throws(()=>assert.equal(mod.parseContentionHelper(JSON.stringify(m),'monitor','released',context(),cacheContentionDigest),false),assert.AssertionError);assert.equal(readFileSync(url,'utf8'),raw);
    }
});
