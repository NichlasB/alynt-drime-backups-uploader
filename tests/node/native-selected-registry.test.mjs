// Invented packets and clocks/credits only. No WordPress/native/transport proof.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createNativeSelectedRegistry } from '../../scripts/tests/native-qualification-selected-registry.mjs';
import { nativeNoFaultFixture, frame, commit, ok } from './support/native-no-fault-fixture.mjs';
function selected(factory=createNativeSelectedRegistry){
	// The synthetic fixture has complete individual frames and no coalesced tail.
	const f=nativeNoFaultFixture(()=>{const api=factory();return Object.freeze({...api,frame:(id,d,bytes,key,tail=0)=>api.frame(id,d,bytes,key,tail)});});
	f.loss=(tail=0,credit=true,body=ok(),seq=1)=>{
		assert.equal(f.send('writer','client',frame(commit()),credit),true);
		const key=Symbol('selected'); const value=f.api.frame(f.ids.writer,'server',frame(body,seq),key,tail);
		return value==='response' && f.api.cut(f.ids.writer,key);
	};
	return f;
}
test('positive four-role selected trace withholds only writer OK and retains exact barrier drain',()=>{
	const f=selected();assert.equal(f.ready(),true);assert.equal(f.loss(),true);assert.equal(f.api.close(f.ids.writer),true);
	assert.equal(f.barrier(),true);const r=f.finish();assert.equal(r.passed,true);
	assert.equal(r.selected_response_discarded,true);assert.equal(r.original_request_completed,true);assert.equal(r.writer_commit_delivered,false);
	assert.equal(r.exact_writer_drain,false);assert.equal(r.exact_barrier_drain,true);assert.equal(r.barrier_commit_delivered,true);assert.equal(r.private_qualification_proven,false);
	assert.equal(f.api.finish(true).passed,false);
});
for(const mode of ['tail','early-credit','wrong-sequence','noncanonical','oversized-header','late','opposite-boundary','wrong-role','wrong-credit','forwarded-response','duplicate-cut','fifth-role','missing-barrier-credit','wrong-final','early-barrier'])test('selected registry rejects '+mode,()=>{
	const f=selected();assert.equal(f.ready(),true);
	if(mode==='tail')assert.equal(f.loss(1),false);
	else if(mode==='early-credit')assert.equal(f.loss(0,false),false);
	else if(mode==='wrong-sequence')assert.equal(f.loss(0,true,ok(),2),false);
	else if(mode==='noncanonical')assert.equal(f.loss(0,true,Buffer.from([0,0,0,3,0,0,0])),false);
	else if(mode==='oversized-header'){assert.equal(f.send('writer','client',frame(commit())),true);assert.equal(f.api.cap(f.ids.writer,'server',8),2097152);const key=Symbol('oversize');assert.equal(f.api.frame(f.ids.writer,'server',frame(ok(),1),key,0),'response');assert.equal(f.api.cut(f.ids.writer,key),false);}
	else if(mode==='late'){const late=selected();assert.equal(late.handshake('auth'),true);assert.equal(late.api.root(process.hrtime.bigint()-4000000001n),true);assert.equal(late.handshake('capture'),true);assert.equal(late.quit('capture'),true);assert.equal(late.handshake('writer'),true);assert.equal(late.api.bindWriter({auth:101,capture:102,writer:103}),false);assert.equal(late.api.finish(true).passed,false);}
	else if(mode==='opposite-boundary'){f.api.boundary(f.ids.auth,()=>false);assert.equal(f.loss(),false);}
	else if(mode==='early-barrier')assert.equal(f.api.open(f.ids.barrier),false);
	else if(mode==='wrong-role'||mode==='wrong-credit'||mode==='forwarded-response'){
		assert.equal(f.send('writer','client',frame(commit())),true);const key=Symbol('target');assert.equal(f.api.frame(f.ids.writer,'server',frame(ok(),1),key,0),'response');
		if(mode==='forwarded-response')assert.equal(f.api.account(f.ids.writer,'server',key),true);
		assert.equal(f.api.cut(mode==='wrong-role'?f.ids.auth:f.ids.writer,mode==='wrong-credit'?Symbol('wrong'):key),false);
	}else{
		assert.equal(f.loss(),true);if(mode==='duplicate-cut')assert.equal(f.api.cut(f.ids.writer,Symbol('again')),false);
		assert.equal(f.api.close(f.ids.writer),true);
		if(mode==='missing-barrier-credit'){assert.equal(f.handshake('barrier'),true);assert.equal(f.api.bindBarrier(f.roles),true);assert.equal(f.send('barrier','client',frame(commit()),false),true);assert.equal(f.send('barrier','server',frame(ok(),1)),false);}
		else{assert.equal(f.barrier(),true);if(mode==='fifth-role')assert.equal(f.api.open(Symbol('fifth')),false);if(mode==='wrong-final')assert.equal(f.api.claim({...f.roles,barrier:105}),false);}
	}
	assert.notEqual(f.api.finish(true).passed,true);
});
test('barrier response can never be a loss target',()=>{
	const f=selected();assert.equal(f.ready(),true);assert.equal(f.loss(),true);assert.equal(f.api.close(f.ids.writer),true);assert.equal(f.handshake('barrier'),true);assert.equal(f.api.bindBarrier(f.roles),true);
	assert.equal(f.send('barrier','client',frame(commit())),true);const key=Symbol('barrier');assert.equal(f.api.frame(f.ids.barrier,'server',frame(ok(),1),key,0),true);assert.equal(f.api.cut(f.ids.barrier,key),false);assert.equal(f.api.account(f.ids.barrier,'server',key),true);
});
test('in-memory semantic controls assertion-kill credit, tail and writer-role selection bypasses',async()=>{
	const url=new URL('../../scripts/tests/native-qualification-selected-registry.mjs',import.meta.url), original=readFileSync(url,'utf8');
	const source=original.replace(/from '(\.\/[^']+)'/g,(_,path)=>`from '${new URL(path,url).href}'`);
	for(const [from,to,contract]of[
		['!r.requestDone || seq !== 1','seq !== 1',factory=>{const f=selected(factory);assert.equal(f.ready(),true);assert.equal(f.send('writer','client',frame(commit()),false),true);assert.equal(f.api.frame(f.ids.writer,'server',frame(ok(),1),Symbol('early'),0),false);}],
		["tail !== 0 || ![...rows.values()].every((x) => empty(x) && ['client','server'].every((d) => x === r && d === 'server' || x.boundary(d)))","![...rows.values()].every((x) => empty(x) && ['client','server'].every((d) => x === r && d === 'server' || x.boundary(d)))",factory=>{const f=selected(factory);assert.equal(f.ready(),true);assert.equal(f.loss(1),false);}],
		["r.role === 'writer' && selected === 'ok' ? 'response' : true","selected === 'ok' ? 'response' : true",factory=>{const f=selected(factory);assert.equal(f.ready(),true);assert.equal(f.loss(),true);assert.equal(f.api.close(f.ids.writer),true);assert.equal(f.handshake('barrier'),true);assert.equal(f.api.bindBarrier(f.roles),true);assert.equal(f.send('barrier','client',frame(commit())),true);assert.equal(f.api.frame(f.ids.barrier,'server',frame(ok(),1),Symbol('barrier'),0),true);}],
	]){assert.ok(source.includes(from));const altered=source.replace(from,to),module=await import('data:text/javascript;base64,'+Buffer.from(altered).toString('base64'));assert.equal(typeof module.createNativeSelectedRegistry,'function');assert.throws(()=>contract(module.createNativeSelectedRegistry),assert.AssertionError);}
	assert.equal(readFileSync(url,'utf8'),original);
});
