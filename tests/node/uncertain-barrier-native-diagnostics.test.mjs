// Actual parser/orchestrator source with owned pipe doubles; no native processes or networking.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { EventEmitter } from 'node:events';
import { validDirectFrame, validHelperFailure } from '../../scripts/tests/uncertain-barrier-native-direct.mjs';
import { nativeSourceDigest } from '../../scripts/tests/uncertain-barrier-native-identity.mjs';
const policy=JSON.parse(readFileSync(new URL('../../scripts/tests/uncertain-barrier-helper-diagnostics.json',import.meta.url),'utf8'));
const context={attempt:'a'.repeat(64),root:'b'.repeat(64),source:nativeSourceDigest,roles:{auth:101,capture:102,writer:103,barrier:104,blocker:105,monitor:106}};
test('failure projection has exact role stages, fixed reason/code vocabulary, and no arbitrary fields',()=>{
    for(const role of ['blocker','monitor'])for(const stage of [...policy.common,...policy[role]])assert.equal(validHelperFailure({stage,reason:'unknown',code:0},role),true);
    const f={stage:'watch_execute',reason:'database',code:1205};assert.equal(validHelperFailure(f,'monitor'),true);
    for(const bad of [{...f,stage:'lock_execute'},{...f,stage:'arbitrary'},{...f,reason:'private-query'},{...f,code:98765},{...f,code:'1205'},{...f,reason:'helper_input'},{...f,sql:'secret'},null])assert.equal(validHelperFailure(bad,'monitor'),false);
});
test('refusal frame is diagnostic-only and rejects old domain, foreign/replayed context, role confusion and handle fields',()=>{
    const f={domain:'alynt.uncertain-barrier.direct.v1',kind:'refused',role:'monitor',failure:{stage:'watch_identity',reason:'helper_wait_identity',code:0},context};
    assert.equal(validDirectFrame(f,'monitor',context),true);
    for(const m of [{...f,domain:'old'},{...f,role:'blocker'},{...f,handle:{}},{...f,context:{...context,root:'c'.repeat(64)}},{...f,failure:{...f.failure,rows:['private']}}])assert.equal(validDirectFrame(m,'monitor',context),false);
    const unbound={domain:f.domain,kind:'refused',role:'monitor',failure:{stage:'connect',reason:'database',code:2002}};
    assert.equal(validDirectFrame(unbound,'monitor',null),true);assert.equal(validDirectFrame(unbound,'monitor',context),false);
});
async function owner(fault='',omitDiagnostic=false) {
    const url=new URL('../../scripts/tests/uncertain-barrier-native-helpers.mjs',import.meta.url),key='__alyntDiagnosticSpawn';
    globalThis[key]=(_php,file)=>{
        const role=file.includes('blocker')?'blocker':'monitor',child=new EventEmitter();child.stdout=new EventEmitter();child.kill=()=>{};
        const emit=m=>queueMicrotask(()=>child.stdout.emit('data',Buffer.from(JSON.stringify(m)+'\n')));
        child.stdin={end(){},destroy(){},write(line,_encoding,callback){const c=JSON.parse(line),base={domain:c.domain};
            if(fault===role&&['lock','watch'].includes(c.command))emit({...base,kind:'refused',role,failure:{stage:role==='blocker'?'lock_prepare':'watch_identity',reason:role==='blocker'?'database':'helper_wait_identity',code:role==='blocker'?1205:0},context:c.context});
            else if(c.command==='lock')emit({...base,kind:'ready',blocker:105,barrier:104,zero_dml:true,context:c.context});
            else if(c.command==='watch')emit({...base,kind:'waiting',requester:104,blocker:105,monitor:106,owned_table:true,role:'safety',queries:1,context:c.context});
            else if(c.command==='rollback'){emit({...base,kind:'released',rollback_acknowledged:true,zero_dml:true,context:c.context});emit({...base,kind:'closed',role,zero_dml:true,context:c.context});}
            else if(c.command==='finish')emit({...base,kind:'closed',role,zero_dml:true,context:c.context});callback();}};
        emit({domain:'alynt.uncertain-barrier.direct.v1',kind:'hello',role,thread:role==='blocker'?105:106});
        return {child,async join(){return {closed:true,exited:true,cleanup_resolved:true,pipes_destroyed:true,timed_out:false,code:fault===role?1:0,stderr_bytes:0};}};
    };
    let raw=readFileSync(url,'utf8').replace("import { realpathSync } from 'node:fs';",`const realpathSync=()=> 'C:/Users/Captain/Local Sites/plugin-tester/app/public';`).replace("import { startNativeQualificationPhp } from './uncertain-barrier-native-process.mjs';",`const startNativeQualificationPhp=(...args)=>globalThis.${key}(...args);`).replace(/from '(\.\/[^']+)'/g,(_,p)=>`from '${new URL(p,url).href}'`);
    if(omitDiagnostic)raw=raw.replace('firstFailure??=Object.freeze({role,...m.failure});','void 0;');
    const mod=await import('data:text/javascript;base64,'+Buffer.from(raw).toString('base64')+'#diagnostic-'+fault+'-'+omitDiagnostic);
    return {value:mod.startUncertainNativeHelpers('unused',['C:/Users/Captain/Local Sites/plugin-tester/app/public','10004','wp_alynt_core_probe_aaaaaaaaaaaa_','b'.repeat(24)],'released'),clear(){delete globalThis[key];}};
}
test('actual orchestration non-vacuously retains success without diagnostic frames or changed message budgets',async()=>{
    const h=await owner();try{await h.value.start();await h.value.bind({auth:101,capture:102,writer:103,barrier:104},context.attempt,context.root);const r=await h.value.finish();assert.equal(r.passed,true);assert.equal(r.first_failure,null);assert.equal(r.actual_wait,true);assert.equal(r.rollback_acknowledged,true);}finally{h.clear();}
});
for(const fault of ['blocker','monitor'])test('actual orchestration latches '+fault+' failure without promoting it or obscuring cleanup',async()=>{
    const h=await owner(fault);try{await h.value.start();if(fault==='blocker')await assert.rejects(h.value.bind({auth:101,capture:102,writer:103,barrier:104},context.attempt,context.root),/direct_frame/);else await h.value.bind({auth:101,capture:102,writer:103,barrier:104},context.attempt,context.root);
        const r=await h.value.finish();assert.equal(r.passed,false);assert.equal(r.cleanup_resolved,true);assert.equal(r.first_failure.role,fault);assert.equal(r.first_failure.stage,fault==='blocker'?'lock_prepare':'watch_identity');assert.deepEqual(Object.keys(r.first_failure),['role','stage','reason','code']);assert.equal(r.blocker_zero_dml,false);assert.equal(r.monitor_zero_dml,false);
    }finally{h.clear();}
});
test('body diagnostic stage assignments are fixed, role-correct and include every native query/execute boundary',()=>{
    for(const role of ['blocker','monitor']){const s=readFileSync(new URL('../../scripts/tests/uncertain-barrier-native-'+role+'-body.php',import.meta.url),'utf8');for(const m of s.matchAll(/\$helper_stage = '([^']+)'/g))assert.ok(policy[role].includes(m[1]));assert.match(s,role==='blocker'?/lock_prepare'; \$stmt = \$db->prepare/:/watch_prepare'; \$stmt = \$db->prepare/);}
    const s=readFileSync(new URL('../../scripts/tests/uncertain-barrier-native-helpers.mjs',import.meta.url),'utf8');assert.match(s,/m.kind==='refused'\)\{bad=true;failed=true;firstFailure\?\?=/);assert.match(s,/\+\+count>4/);assert.match(s,/traffic\+=bytes.length\)>32768/);
});
test('actual diagnostic capture removal is assertion-killed without disk or native mutation',async()=>{
    const h=await owner('monitor',true);try{await h.value.start();await h.value.bind({auth:101,capture:102,writer:103,barrier:104},context.attempt,context.root);const r=await h.value.finish();assert.equal(r.passed,false);assert.throws(()=>assert.deepEqual(r.first_failure,{role:'monitor',stage:'watch_identity',reason:'helper_wait_identity',code:0}),assert.AssertionError);}finally{h.clear();}
});
