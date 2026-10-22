// Fake private pipes/source-backed parent orchestration only; never genuine SQL/contention proof.
import test from 'node:test';
import assert from 'node:assert/strict';
import { EventEmitter } from 'node:events';
import { readFileSync } from 'node:fs';
import { context,creditFixture } from './wait-witness-fixture.mjs';
import { cacheContentionDigest } from '../../scripts/tests/cache-contention-native-identity.mjs';
const url=new URL('../../scripts/tests/cache-contention-native-helpers.mjs',import.meta.url),raw=readFileSync(url,'utf8');
async function harness(fault='',replacement=null){
    const rows=[],events=[],key='__alyntContentionOwnedChild';
    globalThis[key]=(php,role,args)=>{
        const child=new EventEmitter(),row={role,args,joined:false};rows.push(row);let closed=false;
        const close=()=>{if(!closed){closed=true;queueMicrotask(()=>child.emit('close',0));}};
        const send=m=>queueMicrotask(()=>child.stdout.emit('data',Buffer.from(JSON.stringify(m)+'\n')));
        child.stdout=new EventEmitter();child.kill=close;
        child.stdin={destroy:close,end:close,write(line,encoding,done){const m=JSON.parse(line);events.push(role+':'+m.kind);done();
            const frame=(kind,facts)=>send({domain:m.domain,kind,role,profile:m.profile,...facts,context:m.context});
            if(role==='blocker'&&m.kind==='lock')frame('ready',{zero_dml:true});
            else if(role==='blocker'&&m.kind==='rollback'){frame('released',{rollback_acknowledged:true,zero_dml:true});frame('closed',{zero_dml:true,closed_ns:'2100000000'});}
            else if(m.kind==='arm')frame('armed',{zero_dml:true});
            else if(m.kind==='execute'){
                const c=m.context,w={requester:c.roles.barrier,blocker:c.roles.blocker,monitor:c.roles.monitor,table:c.table,index:fault==='foreign-wait'?'foreign':'option_name',role:'safety'};
                frame('waiting',{wait:w,metrics:{trigger_ns:'1000000',queries:[{start_ns:'126000000',end_ns:'127000000',matched:true}],zero_dml:true}});
            }else if(m.kind==='finish')frame('closed',{zero_dml:true,closed_ns:'2100000000'});
        }};
        queueMicrotask(()=>{if(fault===role){close();return;}send({domain:'alynt.uncertain-barrier.wait-contended-setup.v1',kind:'hello',role,profile:args[4],thread:role==='blocker'?105:106,source:cacheContentionDigest});});
        return {child,async join(){row.joined=true;if(fault==='join-throw')throw Error('fake_join');return {closed:fault!=='unresolved',exited:true,code:fault?1:0,timed_out:false,stderr_bytes:0,overflow:false,cleanup_resolved:fault!=='unresolved',pipes_destroyed:true};}};
    };
    let source=raw.replace("import { startContentionHelper } from './cache-contention-native-process.mjs';",`const startContentionHelper=(...args)=>globalThis.${key}(...args);`).replace(/from '(\.\/[^']+)'/g,(_,p)=>`from '${new URL(p,url).href}'`);
    if(replacement){assert.equal(source.split(replacement[0]).length,2);source=source.replace(...replacement);}
    const mod=await import('data:text/javascript;base64,'+Buffer.from(source).toString('base64')+'#'+fault+(replacement?'mutant':''));
    return {rows,events,mod,clear(){delete globalThis[key];}};
}
const args=['C:/Users/Captain/Local Sites/plugin-tester/app/public','10004','wp_alynt_core_probe_0123456789ab_','a'.repeat(24)];
async function bound(h,profile,pending=null){
    const c=context(),signal=creditFixture().receipt();let resolve;const none=new Promise(r=>{resolve=r;});
    const api={bindContext(actual){assert.deepEqual(actual,c);return true;},execution:()=>pending?none:Promise.resolve(signal),cancelCredit(){resolve(false);}};
    const owner=h.mod.startCacheContentionHelpers('fake',args,profile,api);await owner.start();await owner.bind({auth:101,capture:102,writer:103,barrier:104},c.attempt,c.root);
    await new Promise(r=>setImmediate(r));return owner;
}
for(const profile of ['released','held'])test(`positive ${profile} orchestration requires physical-shaped tuple before release and idempotent cleanup`,async()=>{
    const h=await harness();try{
        const owner=await bound(h,profile);assert.equal(h.events.includes('blocker:rollback'),profile==='released');
        assert.ok(h.events.indexOf('blocker:lock')<h.events.indexOf('monitor:arm'));assert.ok(h.events.indexOf('monitor:arm')<h.events.indexOf('monitor:execute'));
        const closing=owner.finish();assert.equal(owner.finish(),closing);const r=await closing;
        assert.equal(r.passed,true);assert.equal(r.actual_wait,true);assert.equal(r.rollback_acknowledged,true);assert.equal(r.monitor_closed_verified,true);assert.equal(r.queries,1);assert.equal(r.cleanup_resolved,true);assert.ok(h.rows.every(r=>r.joined));assert.equal(h.events.filter(x=>x==='blocker:rollback').length,1);
    }finally{h.clear();}
});
for(const fault of ['blocker','monitor'])test('startup EOF '+fault+' retains and settles every owned helper',async()=>{
    const h=await harness(fault);try{
        const api={bindContext:()=>true,execution:()=>Promise.resolve(false),cancelCredit:()=>true},owner=h.mod.startCacheContentionHelpers('fake',args,'held',api);
        await assert.rejects(owner.start(),/contention_frame/);const r=await owner.finish();assert.equal(r.passed,false);assert.equal(r.cleanup_resolved,true);assert.equal(r.actual_wait,false);assert.ok(h.rows.every(r=>r.joined));
    }finally{h.clear();}
});
for(const fault of ['unresolved','join-throw'])test(fault+' never fabricates cleanup eligibility',async()=>{
    const h=await harness(fault);try{const owner=await bound(h,'held');const r=await owner.finish();assert.equal(r.passed,false);assert.equal(r.cleanup_resolved,false);assert.ok(h.rows.every(r=>r.joined));}finally{h.clear();}
});
test('unissued private credit cancels and settles without an actual-wait claim',async()=>{
    const h=await harness();try{const owner=await bound(h,'held',true);const r=await owner.finish();assert.equal(r.passed,false);assert.equal(r.actual_wait,false);assert.equal(r.cleanup_resolved,true);}finally{h.clear();}
});
test('foreign physical-shaped wait cannot release as success or earn verified closure',async()=>{
    const h=await harness('foreign-wait');try{const owner=await bound(h,'held');assert.equal(h.events.includes('blocker:rollback'),false);const r=await owner.finish();assert.equal(r.passed,false);assert.equal(r.actual_wait,false);assert.equal(r.monitor_closed_verified,false);assert.equal(r.cleanup_resolved,true);}finally{h.clear();}
});
test('held early-release guard removal is assertion-killed with valid fake-pipe transcript',async()=>{
    const h=await harness('',["if(profile==='released')await release()",'if(true)await release()']);try{const owner=await bound(h,'held');assert.throws(()=>assert.equal(h.events.includes('blocker:rollback'),false),assert.AssertionError);await owner.finish();}finally{h.clear();}
});
test('parent physical-wait predicate requires waiting, rollback and monitor closure independently',()=>{
    const expression=raw.match(/const passed=(started&&bound&&waiting&&released&&monitorClosed&&!failed&&watchJoin.settled&&!watchJoin.rejected)/)[1];
    const fn=s=>new Function('started','bound','waiting','released','monitorClosed','failed','watchJoin','return '+s);
    assert.equal(fn(expression)(true,true,true,true,true,false,{settled:true,rejected:false}),true);
    for(const [guard,args]of [['waiting',[true,true,false,true,true,false,{settled:true,rejected:false}]],['released',[true,true,true,false,true,false,{settled:true,rejected:false}]],['monitorClosed',[true,true,true,true,false,false,{settled:true,rejected:false}]]]){
        assert.equal(fn(expression)(...args),false);assert.throws(()=>assert.equal(fn(expression.replace(guard,'true'))(...args),false),assert.AssertionError);
    }
});
