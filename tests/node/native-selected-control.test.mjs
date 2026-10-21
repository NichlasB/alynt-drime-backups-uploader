import test from 'node:test';
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { ownNativeQualificationControl } from '../../scripts/tests/native-qualification-selected-control.mjs';
import { nativeSelectedIdentities } from '../../scripts/tests/native-qualification-selected-identity.mjs';
import { qualificationFixture } from './support/native-qualification-fixture.mjs';
async function run(mode) {
	const fixture=qualificationFixture(false), {domain,v,kind,seq,attempt,root,profile,source,...facts}=fixture.frames[7];
	// Original source order, independently protected by the PHP DATA-contract test.
	facts.native_result=Object.fromEntries(['candidate_only','storage_supported','dispatch_supported','execution_supported','retry_supported','authenticated','classification','barrier_verified','closure_proven'].map(k=>[k,facts.native_result[k]]));
	if(mode==='missing-guard') facts.post_guards=0;
	if(mode==='false-root') facts.native_result.classification='unavailable';
	if(mode==='false-observation') facts.observation.valid=false;
	if(mode==='extra-handle') facts.unexpected={};
	if(mode==='swapped-roles') facts.barrier=103;
	const calls=[], registry={root:()=>{calls.push('root');return true;},readyWriter:()=>true,readyBarrier:()=>true,bindWriter:()=>{calls.push('writer');return mode!=='writer-refused';},bindBarrier:()=>{calls.push('barrier');return mode!=='barrier-refused';},claim:()=>{calls.push('final');return mode!=='final-refused';}};
	const php=process.platform==='win32'?'C:/php/php.exe':'php';
	const child=spawn(php,['-n','-d','display_errors=0','-d','log_errors=0','-d','disable_functions=exec,shell_exec,system,passthru,proc_open,popen,fsockopen,pfsockopen,stream_socket_client,stream_socket_server',fileURLToPath(new URL('../Support/NativeSelectedChannelWorker.php',import.meta.url)),Buffer.from(JSON.stringify(facts)).toString('base64')],{shell:false,windowsHide:true,stdio:['pipe','pipe','pipe']});
	let stderr=0; child.stderr.on('data',(b)=>{stderr+=b.length;}); child.on('error',()=>{});
	const closed=new Promise((resolve)=>child.once('close',(code)=>resolve(code))), control=ownNativeQualificationControl(child,registry,nativeSelectedIdentities());
	const timeout=setTimeout(()=>{control.abort();child.kill();},3000);
	try { const code=await closed, result=await control.finish(); return {code,result,calls,stderr}; }
	finally { clearTimeout(timeout); child.stdin.destroy();child.stdout.destroy();child.stderr.destroy(); }
}
test('isolated real PHP pipes complete the new eight-frame channel without a native owner',async()=>{
	const r=await run('positive'); assert.equal(r.code,0); assert.equal(r.stderr,0); assert.equal(r.result.passed,true,JSON.stringify(r)); assert.equal(r.result.guard_count,24); assert.equal(r.result.historical_outcome,'uncertain'); assert.deepEqual(r.calls,['root','writer','barrier','final']);
});
for(const mode of ['missing-guard','false-root','false-observation','extra-handle','swapped-roles','writer-refused','barrier-refused']) test(`isolated private channel refuses ${mode}`,async()=>{
	const r=await run(mode); assert.equal(r.result.passed,false); assert.ok(!r.calls.includes('final'));
});
test('valid FINAL cannot promote a refused passive registry claim to success',async()=>{
	const r=await run('final-refused'); assert.deepEqual(r.calls,['root','writer','barrier','final']);
	assert.equal(r.result.passed,false); assert.equal(r.result.reason,'final_registry');
	assert.equal(r.result.protocol_stage,3);assert.equal(r.result.guard_count,0);assert.equal(r.result.historical_outcome,null);
});
