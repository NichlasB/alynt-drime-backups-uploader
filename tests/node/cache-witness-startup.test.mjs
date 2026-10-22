// Source-backed owned-child API tests; fake pipes only, no processes or SQL.
import test from 'node:test';
import assert from 'node:assert/strict';
import { EventEmitter } from 'node:events';
import { readFileSync } from 'node:fs';
import { context,creditFixture } from './wait-witness-fixture.mjs';
import { cacheWitnessDigest } from '../../scripts/tests/cache-witness-native-identity.mjs';
const url=new URL('../../scripts/tests/cache-witness-native-helpers.mjs',import.meta.url),raw=readFileSync(url,'utf8');
async function harness(fault=''){
    const rows=[],key='__alyntCacheOwnedChild';
    globalThis[key]=(php,role,args)=>{
        const child=new EventEmitter(),row={role,args,joined:false};rows.push(row);
        const send=m=>queueMicrotask(()=>child.stdout.emit('data',Buffer.from(JSON.stringify(m)+'\n')));
        child.stdout=new EventEmitter();child.kill=()=>{};
        child.stdin={destroy(){},end(){},write(line,encoding,done){const m=JSON.parse(line);done();
            if(role==='idle')send({domain:m.domain,kind:m.kind==='bind'?'ready':'closed',role,zero_dml:true,context:m.context});
            else if(m.kind==='arm')send({domain:m.domain,kind:'armed',role,zero_dml:true,context:m.context});
            else if(m.kind==='execute')send({domain:m.domain,kind:'refused',role,reason:'expired',metrics:{trigger_ns:'1000000',elapsed_ns:'2500000000',queries:[{start_ns:'126000000',end_ns:'127000000'}],zero_dml:true,statement_closed:true,session_closed:true},context:m.context});
        }};
        queueMicrotask(()=>{if(fault===role){child.emit('close',1);return;}send({domain:'alynt.uncertain-barrier.wait-setup.v1',kind:'hello',role,thread:role==='idle'?105:106,source:cacheWitnessDigest});});
        return {child,async join(){row.joined=true;return {closed:fault!=='unresolved',exited:true,code:role==='monitor'?1:0,timed_out:false,stderr_bytes:0,overflow:false,cleanup_resolved:fault!=='unresolved',pipes_destroyed:true};}};
    };
    const source=raw.replace("import { startCacheWitnessHelper } from './cache-witness-native-process.mjs';",`const startCacheWitnessHelper=(...args)=>globalThis.${key}(...args);`).replace(/from '(\.\/[^']+)'/g,(_,p)=>`from '${new URL(p,url).href}'`);
    const mod=await import('data:text/javascript;base64,'+Buffer.from(source).toString('base64')+'#'+fault);
    return {rows,mod,clear(){delete globalThis[key];}};
}
const args=['C:/Users/Captain/Local Sites/plugin-tester/app/public','10004','wp_alynt_core_probe_0123456789ab_','a'.repeat(24)];
test('positive private helper orchestration verifies expected no-match, never a wait; every child joined',async()=>{
    const h=await harness();try{
        const owner=h.mod.startCacheWitnessHelpers('fake',args);assert.equal(h.rows.length,0);await owner.start();
        const c=context(),r=creditFixture().receipt();let bound=false;
        const api={bindContext(actual){assert.deepEqual(actual,c);bound=true;return true;},execution(){assert.equal(bound,true);return Promise.resolve(r);},cancelCredit(){return true;}};
        await owner.bind({auth:101,capture:102,writer:103,barrier:104},c.attempt,c.root,api);
        const result=await owner.finish();assert.equal(result.passed,true);assert.equal(result.actual_wait,false);assert.equal(result.no_match_refusal,true);assert.equal(result.queries,1);assert.equal(result.cleanup_resolved,true);assert.ok(h.rows.every(r=>r.joined));
    }finally{h.clear();}
});
for(const fault of ['idle','monitor'])test('startup EOF '+fault+' retains and closes all children without fake success',async()=>{
    const h=await harness(fault);try{const owner=h.mod.startCacheWitnessHelpers('fake',args);await assert.rejects(owner.start(),/cache_frame/);const result=await owner.finish();assert.equal(result.passed,false);assert.equal(result.cleanup_resolved,true);assert.equal(result.actual_wait,false);assert.ok(h.rows.every(r=>r.joined));assert.equal(h.rows.length,fault==='idle'?1:2);}finally{h.clear();}
});
test('unresolved owned child never earns cleanup eligibility',async()=>{
    const h=await harness('unresolved');try{const owner=h.mod.startCacheWitnessHelpers('fake',args);await owner.start();const result=await owner.finish();assert.equal(result.cleanup_resolved,false);assert.equal(result.passed,false);}finally{h.clear();}
});
test('cancelled private execute waiter settles without altering original native state',async()=>{
    const h=await harness();try{const owner=h.mod.startCacheWitnessHelpers('fake',args);await owner.start();let resolve;const pending=new Promise(r=>{resolve=r;});const c=context();await owner.bind({auth:101,capture:102,writer:103,barrier:104},c.attempt,c.root,{bindContext:()=>true,execution:()=>pending,cancelCredit(){resolve(false);}});const result=await owner.finish();assert.equal(result.passed,false);assert.equal(result.cleanup_resolved,true);assert.equal(result.first_failure,'monitor_expiry');}finally{h.clear();}
});
test('fixed launcher normalizes Windows root and forced close receipt has explicit closed flag',()=>{
    const p=readFileSync(new URL('../../scripts/tests/cache-witness-native-process.mjs',import.meta.url),'utf8');assert.match(p,/args=\[realpathSync\(args\[0\]\)/);assert.match(p,/resolve\(\{code,closed:true\}\)/);assert.match(p,/windowsHide:true/);assert.match(p,/shell:false/);
});
