// Source-backed orchestration only: fake owned pipes, no WordPress/SQL/network.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { EventEmitter } from 'node:events';
const url=new URL('../../scripts/tests/uncertain-barrier-native-helpers.mjs',import.meta.url),raw=readFileSync(url,'utf8');
const canonicalRoot='C:\\Users\\Captain\\Local Sites\\plugin-tester\\app\\public';
async function harness(fault='') {
    const rows=[],key='__alyntOwnedStartup';
    globalThis[key]=(php,file,args)=>{
        const role=file.includes('blocker')?'blocker':'monitor',child=new EventEmitter();
        child.stdin={end(){},destroy(){},write(line,encoding,callback){callback();}};child.stdout=new EventEmitter();child.kill=()=>{};
        const row={args,role,joined:false};rows.push(row);
        queueMicrotask(()=>{if(fault===role){child.emit('close',1);return;}child.stdout.emit('data',Buffer.from(JSON.stringify({domain:'alynt.uncertain-barrier.direct.v1',kind:'hello',role,thread:role==='blocker'?105:106})+'\n'));});
        return {child,async join(){row.joined=true;return {closed:fault!=='unresolved',exited:true,cleanup_resolved:fault!=='unresolved',pipes_destroyed:true,timed_out:false,code:fault?1:0,stderr_bytes:0};}};
    };
    const source=raw.replace("import { realpathSync } from 'node:fs';",`const realpathSync=p=>{if(p!=='C:/Users/Captain/Local Sites/plugin-tester/app/public')throw Error('wrong_path');return ${JSON.stringify(canonicalRoot)};};`).replace("import { startNativeQualificationPhp } from './uncertain-barrier-native-process.mjs';",`const startNativeQualificationPhp=(...args)=>globalThis.${key}(...args);`).replace(/from '(\.\/[^']+)'/g,(_,p)=>`from '${new URL(p,url).href}'`);
    const mod=await import('data:text/javascript;base64,'+Buffer.from(source).toString('base64')+'#'+fault);
    return {rows,mod,clear(){delete globalThis[key];}};
}
test('fixed helper caller canonicalizes the exact target before spawn and retains owner before asynchronous startup',async()=>{
    const h=await harness();try{
        const owner=h.mod.startUncertainNativeHelpers('unused',['C:/Users/Captain/Local Sites/plugin-tester/app/public','10004','wp_alynt_core_probe_0123456789ab_','a'.repeat(24)],'released');
        assert.equal(typeof owner.start,'function');assert.equal(h.rows.length,0);await owner.start();
        assert.equal(h.rows.length,2);for(const r of h.rows)assert.equal(r.args[0],canonicalRoot);
        const end=await owner.finish();assert.equal(end.cleanup_resolved,true);assert.equal(end.passed,false);assert.ok(h.rows.every(r=>r.joined));
    }finally{h.clear();}
});
for(const fault of ['blocker','monitor'])test('startup failure '+fault+' retains and joins every constructed child without native success',async()=>{
    const h=await harness(fault);try{
        const owner=h.mod.startUncertainNativeHelpers('unused',['C:/Users/Captain/Local Sites/plugin-tester/app/public','10004','wp_alynt_core_probe_0123456789ab_','a'.repeat(24)],'held');
        await assert.rejects(owner.start(),/direct_closed/);const end=await owner.finish();
        assert.equal(end.cleanup_resolved,true);assert.equal(end.passed,false);assert.equal(end.actual_wait,false);assert.ok(h.rows.every(r=>r.joined));assert.equal(h.rows.length,fault==='blocker'?1:2);
    }finally{h.clear();}
});
test('case records attempted resources before start and uses separate initial cleanup, never fabricated transport success',()=>{
    const s=readFileSync(new URL('../../scripts/tests/uncertain-barrier-native-case.mjs',import.meta.url),'utf8');
    assert.match(s,/helpers=startUncertainNativeHelpers[\s\S]*await helpers\.start\(\)/);
    assert.match(s,/relayStarted=true;\s*relay = await/);assert.match(s,/workerStarted=true;\s*worker =/);
    assert.match(s,/!relayStarted && !workerStarted/);assert.match(s,/fixture\('cleanup_initial'/);
    const p=readFileSync(new URL('../../scripts/tests/uncertain-barrier-native-fixtures.php',import.meta.url),'utf8');
    assert.match(p,/initial_shape/);assert.match(p,/relay_never_started/);assert.match(p,/worker_never_started/);assert.match(p,/INNODB_TRX/);
    assert.match(p,/'recover_initial' === \$argv\[1\] && 'wp_alynt_core_probe_7f3e5973b625_' !== \$argv\[4\]/);assert.match(p,/'baseline_scope' => \$recovery \? 'current_recovery' : 'current_fixture_lifetime'/);
});
test('unresolved child lifetime cannot be promoted to cleanup resolution',async()=>{
    const h=await harness('unresolved');try{const owner=h.mod.startUncertainNativeHelpers('unused',['C:/Users/Captain/Local Sites/plugin-tester/app/public','10004','wp_alynt_core_probe_0123456789ab_','a'.repeat(24)],'held');await owner.start();const end=await owner.finish();assert.equal(end.cleanup_resolved,false);assert.equal(end.passed,false);}finally{h.clear();}
});
test('source pre-worker cleanup predicate refuses attempted resources and unresolved helpers; each exclusion is assertion-killed',()=>{
    const s=readFileSync(new URL('../../scripts/tests/uncertain-barrier-native-case.mjs',import.meta.url),'utf8'),condition=s.match(/else if \((created && !relayStarted && !workerStarted && \(!helpers\|\|helperReceipt\?\.cleanup_resolved===true\))\)/)[1];
    const compile=x=>new Function('created','relayStarted','workerStarted','helpers','helperReceipt','return '+x);
    const p=compile(condition);assert.equal(p(true,false,false,null,null),true);assert.equal(p(true,false,false,{}, {cleanup_resolved:true}),true);
    for(const args of [[false,false,false,null,null],[true,true,false,null,null],[true,false,true,null,null],[true,false,false,{},null],[true,false,false,{}, {cleanup_resolved:false}]])assert.equal(p(...args),false);
    for(const [from,args]of [['!relayStarted',[true,true,false,null,null]],['!workerStarted',[true,false,true,null,null]],['helperReceipt?.cleanup_resolved===true',[true,false,false,{},null]]])assert.throws(()=>assert.equal(compile(condition.replace(from,'true'))(...args),false),assert.AssertionError);
});
