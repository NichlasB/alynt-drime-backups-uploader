import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { parseUncertainBarrierLine,validTerminal } from '../../scripts/tests/native-qualification-uncertain-grammar.mjs';
import { createUncertainBarrierTranscript } from '../../scripts/tests/native-qualification-uncertain-transcript.mjs';
import { createUncertainBarrierAccounting } from '../../scripts/tests/native-qualification-uncertain-accounting.mjs';
import { createUncertainBarrierDrain } from '../../scripts/tests/native-qualification-uncertain-drain.mjs';
import { source,terminal,messages,frame,commit,ok,failureDrain } from './uncertain-barrier-fixtures.mjs';
const directions=['parent','blocker','monitor','parent','worker','parent'];
function match(rows,fragment=false) {
	const t=createUncertainBarrierTranscript(source);let good=true;
	for(let i=0;i<rows.length;i++) {const s=JSON.stringify(rows[i])+'\n';const tick=BigInt(i)*1000000n;good=t.feed(directions[i],fragment?s.slice(0,1):s,tick)&&good;if(fragment)good=t.feed(directions[i],s.slice(1),tick+1n)&&good;}
	const r=t.finish();assert.equal(t.finish(),false);return good&&r.synthetic_match;
}
test('positive resolved and expected-refusal transcripts are non-vacuous, fragmented, inert and distinct',()=>{
	for(const n of [false,true]) {assert.equal(validTerminal(terminal(n)),true);assert.equal(match(messages(n),true),true);}
	const t=createUncertainBarrierTranscript(source);for(const [i,m] of messages(true).entries())assert.equal(t.feed(directions[i],JSON.stringify(m)+'\n',BigInt(i)),true);
	const r=t.finish();assert.equal(r.synthetic_match,true);for(const k of Object.keys(r).filter((k)=>k!=='synthetic_match'))assert.equal(r[k],false);
});
for(const negative of [false,true]) test(`every terminal field has negative coverage (${negative})`,()=>{
	const base=terminal(negative);
	for(const k of Object.keys(base)) {const bad=structuredClone(base);delete bad[k];assert.equal(validTerminal(bad),false,k);}
	for(const k of Object.keys(base.terminal)) {const bad=structuredClone(base);bad.terminal[k]=typeof bad.terminal[k]==='number'?-1:'wrong';assert.equal(validTerminal(bad),false,k);}
	for(const k of Object.keys(base.qualification)) {const bad=structuredClone(base);bad.qualification[k]=null;assert.equal(validTerminal(bad),false,k);}
	for(const k of Object.keys(base.qualification.result)) {const bad=structuredClone(base);bad.qualification.result[k]=null;assert.equal(validTerminal(bad),false,k);}
	for(let i=0;i<base.guard_order.length;i++){const bad=structuredClone(base);bad.guard_order.splice(i,1);assert.equal(validTerminal(bad),false,`guard${i+1}`);}
});
test('strict parser rejects old domain, extra/missing fields, escape/duplicate keys, floats, bounds and poison',()=>{
	const b=messages(true)[0],s=JSON.stringify(b);assert.ok(parseUncertainBarrierLine(s));
	for(const k of Object.keys(b)){const bad={...b};delete bad[k];assert.equal(parseUncertainBarrierLine(JSON.stringify(bad)),false,k);}
	for(const bad of [s+' ',s.replace('"v":1','"v":1,"v":1'),s.replace('"v":1','"v":1.0'),'x'.repeat(4096),s.replace('synthetic.v1','selected.v1'),JSON.stringify({...b,extra:true}),JSON.stringify({...b,roles:{...b.roles,blocker:14}}),JSON.stringify({...b,read_remaining_ns:'10000000001'}),JSON.stringify({...b,attempt:b.root})])assert.equal(parseUncertainBarrierLine(bad),false);
});
test('cross-root/source, swapped role, false wait, wrong branch and incomplete cleanup fail permanently',()=>{
	const changes=[(r)=>r[1].barrier=13,(r)=>r[2].requester=13,(r)=>r[2].blocker=16,(r)=>r[2].owned_table=false,(r)=>r[3].root='d'.repeat(64),(r)=>r[4].source='e'.repeat(64),(r)=>r[3].case='released',(r)=>r[4].data.terminal.phase='poisoned'];
	for(const k of Object.keys(messages(true)[5]).slice(7))changes.push((r)=>r[5][k]=false);
	for(const change of changes){const r=messages(true);change(r);assert.equal(match(r),false);}
	assert.equal(match(messages(true).slice(0,5)),false);
});
test('first-fragment clocks, ACK budget, regression, direction and trailing bytes are not reset',()=>{
	for(const mode of ['ack','partial','root','read','backwards','direction','tail']) {
		const t=createUncertainBarrierTranscript(source), rows=messages(true);let rejected=false;
		for(let i=0;i<rows.length;i++) {
			let tick=BigInt(i)*1000000n,dir=directions[i],line=JSON.stringify(rows[i])+'\n';
			if(i===1&&mode==='ack')tick=1000000001n;
			if(i===2&&mode==='backwards')tick=0n;
			if(i===2&&mode==='direction')dir='worker';
			if(i===4&&mode==='root')tick=20000000000n;
			if(i===4&&mode==='read')tick=9000000000n;
			if(i===4&&mode==='tail')line+='x';
			if(i===1&&mode==='partial'){assert.equal(t.feed(dir,line[0],0n),true);tick=1000000001n;line=line.slice(1);}
			if(!t.feed(dir,line,tick))rejected=true;
		}
		assert.equal(rejected,true,mode);assert.equal(t.finish().synthetic_match,false,mode);
	}
});
function account(change='') {
	const a=createUncertainBarrierAccounting();let good=true;
	for(const role of ['auth','capture','writer','barrier'])for(const d of ['client','server']) {
		const c=Symbol(); const f=role==='writer'?(d==='client'?commit():ok()):frame(Buffer.from([1]));
		good=a.receive(role,d,f,c,change==='tail'&&role==='writer'&&d==='server'?1:0)&&good;
		if(role==='writer'&&d==='server')good=(change==='delivered'?a.account(role,d,c):a.discardWriterResponse(c))&&good;
		else good=a.account(role,d,change==='credit'?Symbol():c)&&good;
		good=a.close(role,d)&&good;
	}
	const r=a.finish();assert.equal(a.finish(),false);return good&&r.synthetic_match;
}
test('all eight streams conserved except one exact pre-write suffix; no vacuous zero-stream success',()=>{
	assert.equal(account(),true);for(const k of ['tail','credit','delivered'])assert.equal(account(k),false,k);
	const a=createUncertainBarrierAccounting();assert.equal(a.finish().synthetic_match,false);
	const b=createUncertainBarrierAccounting(),c=Symbol();assert.equal(b.receive('blocker','server',ok(),c),false);assert.equal(b.finish().synthetic_match,false);
});
test('request credit, fresh symbols, no pending opposite traffic, no rearm or post-cut writer bytes',()=>{
	const a=createUncertainBarrierAccounting(),c=Symbol();assert.equal(a.receive('writer','server',ok(),c),true);assert.equal(a.discardWriterResponse(c),false);
	const b=createUncertainBarrierAccounting(),x=Symbol(),y=Symbol();assert.equal(b.receive('writer','client',commit(),x),true);assert.equal(b.account('writer','client',x),true);assert.equal(b.receive('writer','server',ok(),y),true);assert.equal(b.discardWriterResponse(y),true);assert.equal(b.discardWriterResponse(y),false);
	const d=createUncertainBarrierAccounting();assert.equal(d.receive('auth','client',frame(Buffer.from([1])),c),true);assert.equal(d.account('auth','client',c),true);assert.equal(d.receive('auth','server',ok(),c),false);
});
test('4096 frames per pair counts both directions; queue and attempt byte caps are independent',()=>{
	const a=createUncertainBarrierAccounting();
	for(let i=0;i<4096;i++){const c=Symbol(),dir=i%2?'server':'client';assert.equal(a.receive('auth',dir,frame(Buffer.from([1])),c),true);assert.equal(a.account('auth',dir,c),true);}
	assert.equal(a.receive('auth','client',frame(Buffer.from([1])),Symbol()),false,'pair cap not global symbol cap');
	const b=createUncertainBarrierAccounting(),large=Buffer.alloc(2097152);large.writeUIntLE(2097148,0,3);
	for(const [r,d] of [['auth','client'],['auth','server'],['capture','client'],['capture','server']])assert.equal(b.receive(r,d,large,Symbol()),true);
	assert.equal(b.receive('writer','client',large,Symbol()),false,'8MiB queue cap');
	const c=createUncertainBarrierAccounting();for(let i=0;i<32;i++){const key=Symbol();assert.equal(c.receive('auth','client',large,key),true);assert.equal(c.account('auth','client',key),true);}
	assert.equal(c.receive('auth','client',frame(Buffer.from([1])),Symbol()),false,'64MiB attempt cap');
});
test('exact candidate timeout, statement close, pre-rollback checks, acknowledged rollback and post-checks',()=>{
	const rows=failureDrain(),d=createUncertainBarrierDrain(7);for(const r of rows)assert.equal(d.accept(...r),true);assert.equal(d.done(),true);
	for(let i=0;i<rows.length;i++){const bad=createUncertainBarrierDrain(7);for(let j=0;j<rows.length;j++){if(i===j)continue;bad.accept(...rows[j]);}assert.equal(bad.done(),false,`missing${i}`);}
	for(const ix of [0,1,rows.length-1]){const bad=createUncertainBarrierDrain(7),copy=failureDrain();copy[ix][2]=Buffer.from([0]);for(const r of copy)bad.accept(...r);assert.equal(bad.done(),false);}
	assert.equal(d.accept('client',0,Buffer.from([1])),false);assert.equal(d.done(),false);
});
test('new closure hashes match exact source and historical pinned files remain separate',()=>{
	const root=new URL('../../',import.meta.url),path=new URL('scripts/tests/native-qualification-uncertain-identities.json',root),raw=readFileSync(path,'utf8').replaceAll('\r\n','\n');
	const manifest=readFileSync(new URL('tests/Support/UncertainBarrierManifest.php',root),'utf8');assert.ok(manifest.includes(createHash('sha256').update(raw).digest('hex')));
	for(const [name,hash] of Object.entries(JSON.parse(raw)))assert.equal(createHash('sha256').update(readFileSync(new URL(name,root),'utf8').replaceAll('\r\n','\n')).digest('hex'),hash,name);
});
