import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createProjectionShutdownTranscript as create, parseProjectionCandidate as parse, projectionProfile as profile } from '../../scripts/tests/projection-shutdown-transcript.mjs';
const root = 'a'.repeat(24), mode = 'autoload';
const frame = () => ['alynt.projection.candidate', 1, root, mode, profile, 'core_shutdown_returned', [16,16,1,29,7,7,140,0,0,0,1,1], 'modeled_only'];
const line = () => JSON.stringify(frame()) + '\n';
function ready(factory = create) {
	const p = factory(root, mode, 0);
	assert.equal(p.event('fenced', profile, 0), true);
	assert.equal(p.event('core_returned', true, 1), true);
	assert.equal(p.event('core_shutdown_returned', [1,1], 2), true);
	return p;
}
function end(p, order = ['close', 'stdout_eof', 'stderr_eof']) {
	for (const event of order) assert.equal(p.event(event, event === 'close' ? [0,null] : null, 10), true);
	return p.finish(11);
}
const authorityFree = (result, good) => {
	assert.equal(result.synthetic_match, good);
	for (const [key, value] of Object.entries(result)) if (key !== 'synthetic_match') assert.equal(value, false, key);
};
const orders = [['close','stdout_eof','stderr_eof'],['close','stderr_eof','stdout_eof'],['stdout_eof','close','stderr_eof'],['stdout_eof','stderr_eof','close'],['stderr_eof','close','stdout_eof'],['stderr_eof','stdout_eof','close']];
for (const caseMode of ['autoload','fallback']) for (const order of orders) for (const width of [1,7,4096]) test(`modeled positive ${caseMode} ${order.join('/')} fragment${width}`, () => {
	const p = create(root, caseMode, 0), f = frame(); f[3] = caseMode;
	assert.equal(p.event('fenced', profile, 0), true); assert.equal(p.event('core_returned', true, 1), true);
	assert.equal(p.event('core_shutdown_returned', [1,1], 2), true);
	const bytes = JSON.stringify(f) + '\n';
	for (let at = 0; at < bytes.length; at += width) assert.equal(p.event('stdout', bytes.slice(at, at + width), 3), true);
	authorityFree(end(p, order), true); assert.equal(p.finish(12), false); assert.equal(p.event('close', [0,null], 12), false);
});
test('fixed profile is shared with PHP catalog, not a supplied source certificate', () => {
	assert.ok(readFileSync(new URL('../../scripts/tests/fixed-projection-catalog.php', import.meta.url), 'utf8').includes(`const PROFILE = '${profile}'`));
	for (const args of [[], [root,mode], [root,mode,0,'extra'], [root+'\n',mode,0], [root,'unsafe',0], [root,mode,-1], [root,mode,Infinity]]) assert.equal(create(...args), false);
});
test('every frame slot, counter and canonical wire rule matters', () => {
	const f = frame(); assert.deepEqual(parse(line(),root,mode), f);
	const bad = [];
	for (let i = 0; i < f.length; i++) { const m = structuredClone(f); m.splice(i,1); bad.push(JSON.stringify(m)+'\n'); const n = structuredClone(f); n[i] = null; bad.push(JSON.stringify(n)+'\n'); }
	for (const [i,v] of [[0,33],[1,17],[2,2],[3,28],[4,17],[5,6],[6,0],[6,1901],[7,1],[8,1],[9,1],[10,0],[10,2],[11,0],[11,2]]) { const m = structuredClone(f); m[6][i] = v; bad.push(JSON.stringify(m)+'\n'); }
	for (let i = 0; i < 12; i++) for (const v of [-1,0.1,'1',null]) { const m = structuredClone(f); m[6][i] = v; bad.push(JSON.stringify(m)+'\n'); }
	for (const [i,v] of [[0,'other'],[1,2],[2,'b'.repeat(24)],[3,'fallback'],[4,'0'.repeat(64)],[5,'shutdown_entered'],[7,'native_proven']]) { const m = structuredClone(f); m[i] = v; bad.push(JSON.stringify(m)+'\n'); }
	const raw = line(); bad.push(raw+'x', raw+raw, raw.slice(0,-1), ' '+raw, raw.replace(',1,',',1.0,'), raw.replace(',1,',',1e0,'), raw.replace(',1,',',01,'), raw.replace('modeled_only','modeled_\\u006fnly'), raw.replace('modeled_only','é'), '['.repeat(4097), raw.replace('candidate','candidate\u0000'), raw.replace(',0,0,0,',',-0,0,0,'));
	for (const bytes of bad) { assert.equal(parse(bytes,root,mode), false, bytes.slice(0,100)); const p = ready(); p.event('stdout',bytes,3); authorityFree(p.finish(11),false); }
	for (let at = 0; at < raw.length; at++) { const p = ready(); if (at) assert.equal(p.event('stdout',raw.slice(0,at),3), true); authorityFree(p.finish(11), false); }
});
test('registration is not execution: each missing/repeated/early lifecycle guard refuses', () => {
	for (const events of [[], [['fenced','0'.repeat(64)]], [['core_returned',true]], [['fenced',profile],['fenced',profile]], [['fenced',profile],['core_shutdown_returned',[1,1]]], [['fenced',profile],['core_returned',false]], [['fenced',profile],['core_returned',true],['core_shutdown_returned',[1,0]]], [['fenced',profile],['core_returned',true],['core_shutdown_returned',[2,1]]]]) {
		const p = create(root,mode,0); let tick = 0;
		for (const [kind,value] of events) p.event(kind,value,tick++);
		assert.equal(p.event('stdout',line(),tick), false); authorityFree(p.finish(11), false);
	}
	for (const kind of ['close','stdout_eof','stderr_eof']) { const p = ready(); assert.equal(p.event(kind,kind==='close'?[0,null]:null,3),false); authorityFree(p.finish(11),false); }
	const extra=ready(); assert.equal(extra.event('stdout',line(),3,'extra'),false); authorityFree(extra.finish(11),false);
	const extraFinish=ready(); extraFinish.event('stdout',line(),3); for(const kind of orders[0])extraFinish.event(kind,kind==='close'?[0,null]:null,4); authorityFree(extraFinish.finish(5,'extra'),false);
});
test('post-candidate activity is sticky, including caught guard exceptions and empty stderr protocol misuse', () => {
	for (const [kind,value] of [['query','allowed P SELECT'],['http',null],['diagnostic',null],['stdout',' '],['stdout',line()],['stderr',''],['stderr','x'],['fenced',profile],['core_returned',true],['core_shutdown_returned',[1,1]],['close',[1,null]],['close',[0,'SIGTERM']],['timeout',null]]) {
		const p = ready(); assert.equal(p.event('stdout',line(),3),true); assert.equal(p.event(kind,value,4),false); authorityFree(p.finish(11),false);
	}
	const p = ready(); assert.equal(p.event('stdout',line(),3),true);
	assert.throws(() => p.violation('secret detail deliberately discarded'), {message:'projection_refused'});
	assert.equal(p.event('close',[0,null],4),false); authorityFree(p.finish(11),false);
});
test('no exit0/EOF alone, duplicate closure, clock rollback or deadline refresh can accept', () => {
	for (const omit of ['close','stdout_eof','stderr_eof']) { const p=ready(); p.event('stdout',line(),3); for (const kind of ['close','stdout_eof','stderr_eof'].filter((k)=>k!==omit)) assert.equal(p.event(kind,kind==='close'?[0,null]:null,4),true); authorityFree(p.finish(5),false); }
	for (const kind of ['close','stdout_eof','stderr_eof']) { const p=ready(); p.event('stdout',line(),3); const value=kind==='close'?[0,null]:null; assert.equal(p.event(kind,value,4),true); assert.equal(p.event(kind,value,5),false); authorityFree(p.finish(6),false); }
	for (const tick of [1,-1,20000,Number.MAX_SAFE_INTEGER,NaN,Infinity,'3']) { const p=ready(); assert.equal(p.event('stdout',line(),tick),false); authorityFree(p.finish(19999),false); }
	const p=ready(); p.event('stdout',line(),19999); for(const kind of ['close','stdout_eof','stderr_eof']) assert.equal(p.event(kind,kind==='close'?[0,null]:null,19999),true); authorityFree(p.finish(20000),false);
	const near=create(root,mode,Number.MAX_SAFE_INTEGER-100); assert.equal(near.event('fenced',profile,Number.MAX_SAFE_INTEGER-100),true); authorityFree(near.finish(Number.MAX_SAFE_INTEGER),false);
});
test('source-backed semantic controls: removing real guards admits the targeted bad trace', async () => {
	const source=readFileSync(new URL('../../scripts/tests/projection-shutdown-transcript.mjs',import.meta.url),'utf8');
	const controls=[
		['m[4] !== projectionProfile','false', (m)=>{ const f=frame();f[4]='0'.repeat(64);return m.parseProjectionCandidate(JSON.stringify(f)+'\n',root,mode)!==false; }],
		['c[5] === c[4]','true',(m)=>{const f=frame();f[6][5]=6;return m.parseProjectionCandidate(JSON.stringify(f)+'\n',root,mode)!==false;}],
		['stage === 4 && closed && stdoutEof && stderrEof','stage === 4 && stdoutEof && stderrEof',(m)=>{const p=ready(m.createProjectionShutdownTranscript);p.event('stdout',line(),3);p.event('stdout_eof',null,4);p.event('stderr_eof',null,4);return p.finish(5).synthetic_match;}],
		['stage === 4 && closed && stdoutEof && stderrEof','stage === 4 && closed',(m)=>{const p=ready(m.createProjectionShutdownTranscript);p.event('stdout',line(),3);p.event('close',[0,null],4);return p.finish(5).synthetic_match;}],
		['tick - start < 20000','true',(m)=>{const p=ready(m.createProjectionShutdownTranscript);p.event('stdout',line(),20000);for(const kind of ['close','stdout_eof','stderr_eof'])p.event(kind,kind==='close'?[0,null]:null,20001);return p.finish(20002).synthetic_match;}],
		['violation() { refuse();','violation() {',(m)=>{const p=ready(m.createProjectionShutdownTranscript);p.event('stdout',line(),3);assert.throws(()=>p.violation());return end(p).synthetic_match;}],
	];
	for (const [predicate,slot,value] of [ ['m[0] !== \'alynt.projection.candidate\'',0,'other'],['m[1] !== 1',1,2],['m[2] !== root',2,'b'.repeat(24)],['m[3] !== mode',3,'fallback'],['m[5] !== \'core_shutdown_returned\'',5,'shutdown_entered'],['m[7] !== \'modeled_only\'',7,'native_proven'] ]) {
		controls.push([predicate,'false',(m)=>{const f=frame();f[slot]=value;return m.parseProjectionCandidate(JSON.stringify(f)+'\n',root,mode)!==false;}]);
	}
	for (const [predicate,slot,value] of [['c[0] <= 32',0,33],['c[1] <= c[0]',1,17],['c[2] <= 1',2,2],['c[3] === 29',3,28],['c[4] <= c[0]',4,17],['c[6] >= 1',6,0],['c[6] <= 1900',6,1901],['c[7] === 0',7,1],['c[8] === 0',8,1],['c[9] === 0',9,1],['c[10] === 1',10,2],['c[11] === 1',11,0]]) {
		controls.push([predicate,'true',(m)=>{const f=frame();f[6][slot]=value;if(slot===4)f[6][5]=value;return m.parseProjectionCandidate(JSON.stringify(f)+'\n',root,mode)!==false;}]);
	}
	controls.push(['JSON.stringify(m) + \'\\n\' !== line','false',(m)=>m.parseProjectionCandidate(line().replace(',1,',',1.0,'),root,mode)!==false]);
	controls.push(['stage === 1 && value === true','value === true',(m)=>{const p=m.createProjectionShutdownTranscript(root,mode,0);p.event('core_returned',true,1);p.event('core_shutdown_returned',[1,1],2);p.event('stdout',line(),3);return end(p).synthetic_match;}]);
	controls.push(['value[0] === 0 && value[1] === null','value[1] === null',(m)=>{const p=ready(m.createProjectionShutdownTranscript);p.event('stdout',line(),3);p.event('close',[1,null],4);p.event('stdout_eof',null,5);p.event('stderr_eof',null,5);return p.finish(6).synthetic_match;}]);
	for (let n=0;n<controls.length;n++) { const [from,to,exposes]=controls[n]; assert.equal(source.split(from).length,2,from); const mutant=await import('data:text/javascript;base64,'+Buffer.from(source.replace(from,to)+`\n// isolated control ${n}`).toString('base64')); assert.equal(exposes(mutant),true,from); }
});
