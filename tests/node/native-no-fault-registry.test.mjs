import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createNativeBarrierDrain } from '../../scripts/tests/native-qualification-no-fault-barrier-drain.mjs';
import { verifyNativeNoFaultIdentity, nativeNoFaultIdentities } from '../../scripts/tests/native-qualification-no-fault-identity.mjs';
import { nativeNoFaultFixture, barrierPackets, zeroQuery, frame, commit, ok } from './support/native-no-fault-fixture.mjs';
test('positive four-role no-fault trace needs both credited commits, exact drains and all closes',()=>{
	const f=nativeNoFaultFixture(); assert.equal(f.ready(),true); assert.equal(f.writer(),true); assert.equal(f.barrier(),true); const r=f.finish();
	assert.equal(r.passed,true); for(const k of ['four_roles_bound','writer_commit_delivered','barrier_commit_delivered','exact_writer_drain','exact_barrier_drain']) assert.equal(r[k],true);
	assert.equal(r.suppression_supported,false); assert.equal(f.api.finish(true).passed,false);
	assert.equal(Object.hasOwn(f.api,'cut'),false); assert.equal(Object.hasOwn(f.api,'decide'),false);
});
test('barrier drain literals remain exactly tied to the native zero-DML source',()=>{
	const source=readFileSync(new URL('../../includes/trait-registration-observation-barrier-rows.php',import.meta.url),'utf8'); assert.ok(source.includes(zeroQuery));
	const d=createNativeBarrierDrain(); for(const p of barrierPackets()) assert.equal(d.accept(p[3]===0?'client':'server',p[3],p.subarray(4)),true); assert.equal(d.done(),true);
});
test('native SHOW metadata differs from final/SELECT EOFs without weakening the fixed trace',()=>{
	const packets=barrierPackets(), d=createNativeBarrierDrain();
	assert.deepEqual(packets[29].subarray(4),Buffer.from([254,0,0,34,0]));
	for(const at of [12,14,22,24,45]) assert.deepEqual(packets[at].subarray(4),Buffer.from([254,0,0,2,0]));
	for(const p of packets) assert.equal(d.accept(p[3]===0?'client':'server',p[3],p.subarray(4)),true);
	assert.equal(d.done(),true); assert.deepEqual(d.diagnostic(),{reason:'',query:3,sequence:20,counters:15,quit:true});
});
for(const [name,at,status] of [
	['physical-metadata',12,34],['physical-final',14,34],['policy-metadata',22,34],['policy-final',24,34],
	['show-metadata-missing-scan',29,2],['show-metadata-transaction',29,35],['show-metadata-more-results',29,42],
	['show-metadata-index-alternative',29,18],['show-metadata-cursor',29,98],['show-metadata-readonly-transaction',29,8226],
	['show-metadata-session-change',29,16418],['show-final-scan',45,34],
]) test(`position-specific EOF refuses ${name}`,()=>{
	const packets=barrierPackets(), d=createNativeBarrierDrain(); packets[at].writeUInt16LE(status,7);
	assert.equal(packets.every((p)=>d.accept(p[3]===0?'client':'server',p[3],p.subarray(4))),false);
	assert.equal(d.done(),false); assert.equal(d.diagnostic().reason,'eof');
	assert.equal(d.accept('client',0,Buffer.from([1])),false); assert.equal(d.diagnostic().reason,'eof');
});
for(const at of [12,14,22,24,29,45]) test(`EOF warning remains forbidden at packet ${at}`,()=>{
	const packets=barrierPackets(),d=createNativeBarrierDrain();packets[at].writeUInt16LE(1,5);
	assert.equal(packets.every((p)=>d.accept(p[3]===0?'client':'server',p[3],p.subarray(4))),false);
	assert.equal(d.done(),false);assert.equal(d.diagnostic().reason,'eof');
});
for(const mode of ['missing','extra-query','nonzero','duplicate-counter','bad-sequence','metadata','early-quit','no-quit','extra-row','unknown-counter']) test(`exact barrier drain rejects ${mode}`,()=>{
	const packets=barrierPackets();
	if(mode==='missing') packets.splice(0,1);
	if(mode==='extra-query') packets.splice(-1,0,frame(Buffer.from('\x03SELECT 1')));
	if(mode==='nonzero') packets[packets.length-3][packets[packets.length-3].length-1]=49;
	if(mode==='duplicate-counter') packets.splice(-3,1,packets[packets.length-4]);
	if(mode==='bad-sequence') packets[3][3]++;
	if(mode==='metadata') packets[2][5]=88;
	if(mode==='early-quit') packets.splice(2,0,frame(Buffer.from([1])));
	if(mode==='no-quit') packets.pop();
	if(mode==='extra-row') packets.splice(-2,0,packets[packets.length-3]);
	if(mode==='unknown-counter') packets[packets.length-3][6]=88;
	const d=createNativeBarrierDrain(); const accepted=packets.every((p)=>d.accept(p[3]===0?'client':'server',p[3],p.subarray(4))); assert.equal(accepted && d.done(),false);
});
for(const mode of ['early-barrier','fifth-role','reused-thread','swapped-roles','unbound-commit','early-ok','missing-credit','early-claim','writer-open','barrier-open','wrong-final-roles','transport-failure','extra-post-final','read-prepare','observer-error']) test(`native no-fault registry rejects ${mode}`,()=>{
	const f=nativeNoFaultFixture(); assert.equal(f.ready(),true);
	if(mode==='early-barrier') assert.equal(f.api.open(f.ids.barrier),false);
	else if(mode==='unbound-commit') { assert.equal(f.writer(),true); assert.equal(f.handshake('barrier'),true); assert.equal(f.send('barrier','client',frame(commit())),false); }
	else if(mode==='early-ok') { assert.equal(f.send('writer','client',frame(commit()),false),true); assert.equal(f.send('writer','server',frame(ok(),1)),false); }
	else if(mode==='missing-credit') { assert.equal(f.send('writer','client',frame(commit()),false),true); assert.equal(f.api.close(f.ids.writer),false); }
	else if(mode==='early-claim'||mode==='writer-open') assert.equal(f.api.claim(f.roles),false);
	else if(mode==='read-prepare') { assert.equal(f.writer(),true); assert.equal(f.handshake('barrier'),true); assert.equal(f.send('barrier','client',frame(Buffer.from('\x16UPDATE owned SET value=1'))),false); }
	else if(mode==='observer-error') assert.equal(f.api.unavailable(),false);
	else {
		assert.equal(f.writer(),true);
		if(mode==='swapped-roles'||mode==='reused-thread') { assert.equal(f.handshake('barrier'),true); assert.equal(f.api.bindBarrier({...f.roles,barrier:mode==='reused-thread'?103:101,auth:mode==='swapped-roles'?104:101}),false); }
		else { assert.equal(f.barrier(),true);
			if(mode==='fifth-role') assert.equal(f.api.open(Symbol('fifth')),false);
			if(mode==='barrier-open') assert.equal(f.api.open(f.ids.barrier),false);
			if(mode==='wrong-final-roles') assert.equal(f.api.claim({...f.roles,barrier:105}),false);
			if(mode==='transport-failure') { assert.equal(f.api.claim(f.roles),true); assert.equal(f.quit('auth'),true); assert.equal(f.api.finish(false).passed,false); }
			if(mode==='extra-post-final') { assert.equal(f.api.claim(f.roles),true); assert.equal(f.send('auth','client',frame(Buffer.from('\x03SELECT 1'))),false); }
		}
	}
	assert.notEqual(f.api.finish(true).passed,true);
});
test('native source closure is separate, complete, bounded and immutable at the unit layer',()=>{
	assert.equal(verifyNativeNoFaultIdentity(),true); assert.equal(verifyNativeNoFaultIdentity('override'),false);
	assert.equal(Object.keys(nativeNoFaultIdentities()).length,7); assert.throws(()=>nativeNoFaultIdentities('supplied'));
});
test('independent EOF contract assertion-kills obsolete, bypassed and position-widened guards in memory',async()=>{
	const url=new URL('../../scripts/tests/native-qualification-no-fault-barrier-drain.mjs',import.meta.url);
	const source=readFileSync(url,'utf8').replace("'./fixed-drain-grammar.mjs'",JSON.stringify(new URL('./fixed-drain-grammar.mjs',url).href));
	const predicate="query === 2 && seq === count + 2 ? showMetadataEof : eof";
	const contract=(factory)=>{
		const accept=(packets)=>{const d=factory();return packets.every(p=>d.accept(p[3]===0?'client':'server',p[3],p.subarray(4)))&&d.done();};
		assert.equal(accept(barrierPackets()),true);
		for(const [at,status,warnings] of [[12,34,0],[29,35,0],[45,34,0],[29,34,1]]){
			const p=barrierPackets();p[at].writeUInt16LE(status,7);p[at].writeUInt16LE(warnings,5);assert.equal(accept(p),false);
		}
	};
	contract(createNativeBarrierDrain);
	for(const [from,to] of [[predicate,'eof'],["if (!bytes.equals("+predicate+")) return fail('eof');","if (false) return fail('eof');"],[predicate,'query === 2 ? showMetadataEof : eof']]){
		assert.ok(source.includes(from));const altered=source.replace(from,to);assert.notEqual(altered,source);
		const m=await import('data:text/javascript;base64,'+Buffer.from(altered).toString('base64'));
		assert.throws(()=>contract(m.createNativeBarrierDrain),assert.AssertionError);
	}
});
