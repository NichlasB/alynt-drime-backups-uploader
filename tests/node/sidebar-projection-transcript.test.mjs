import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { createSidebarProjectionShutdownTranscript as create, parseSidebarProjectionCandidate as parse,
	sidebarProjectionProfile as profile, projectionProfile as oldProfile, parseProjectionCandidate as oldParse,
	createProjectionShutdownTranscript as oldCreate } from '../../scripts/tests/projection-shutdown-transcript.mjs';

const root = 'a'.repeat(24);
const frame = (mode = 'autoload') => ['alynt.projection.candidate', 1, root, mode, profile, 'core_shutdown_returned', [17,17,1,29,8,8,140,0,0,0,1,1], 'modeled_only'];
const orders = [['close','stdout_eof','stderr_eof'],['close','stderr_eof','stdout_eof'],['stdout_eof','close','stderr_eof'],['stdout_eof','stderr_eof','close'],['stderr_eof','close','stdout_eof'],['stderr_eof','stdout_eof','close']];
function ready(mode = 'autoload', start = 0) {
	const p = create(root, mode, start);
	assert.equal(p.event('fenced', profile, start), true);
	assert.equal(p.event('core_returned', true, start + 1), true);
	assert.equal(p.event('core_shutdown_returned', [1,1], start + 2), true);
	return p;
}
function closed(p, order = orders[0], tick = 10) {
	for (const kind of order) assert.equal(p.event(kind, kind === 'close' ? [0,null] : null, tick), true);
	return p.finish(tick + 1);
}
function authorityFree(result, accepted) {
	assert.equal(result.synthetic_match, accepted);
	assert.equal(Object.keys(result).length, 8);
	for (const [key, value] of Object.entries(result)) if (key !== 'synthetic_match') assert.equal(value, false, key);
}
for (const mode of ['autoload','fallback']) for (const order of orders) for (const width of [1,7,4096]) test(`v2 synthetic ${mode}/${order.join('/')}/${width}`, () => {
	const f = frame(mode), bytes = JSON.stringify(f) + '\n';
	assert.deepEqual(parse(bytes, root, mode), f);
	assert.equal(oldParse(bytes, root, mode), false);
	const p = ready(mode);
	for (let at = 0; at < bytes.length; at += width) assert.equal(p.event('stdout', bytes.slice(at, at + width), 3), true);
	authorityFree(closed(p, order), true);
	assert.equal(p.finish(12), false);
});
test('fixed wrappers cannot select or silently promote another profile', () => {
	const bytes = JSON.stringify(frame()) + '\n';
	const old = frame(); old[4] = oldProfile;
	assert.notEqual(profile, oldProfile);
	assert.equal(parse(JSON.stringify(old) + '\n', root, 'autoload'), false);
	assert.notEqual(oldParse(JSON.stringify(old) + '\n', root, 'autoload'), false);
	for (const args of [[], [root,'autoload'], [root,'autoload',0,profile], [root,'other',0], [root,'autoload',-1]]) {
		assert.equal(create(...args), false);
	}
	assert.equal(parse(bytes, root, 'autoload', profile), false);
	const p = oldCreate(root, 'autoload', 0);
	assert.equal(p.event('fenced', profile, 0), false);
	authorityFree(p.finish(1), false);
});
test('v2 cannot relabel a seven-absence counter trace as eight-absence evidence', () => {
	const f = frame(); f[6][4] = 7; f[6][5] = 7;
	assert.equal(parse(JSON.stringify(f) + '\n', root, 'autoload'), false);
});
test('canonical bytes, every frame/counter slot and extra profile arguments refuse', () => {
	const base = frame(), bytes = JSON.stringify(base) + '\n', bad = [];
	for (let slot = 0; slot < base.length; slot++) {
		const missing = structuredClone(base); missing.splice(slot, 1); bad.push(JSON.stringify(missing) + '\n');
		const changed = structuredClone(base); changed[slot] = null; bad.push(JSON.stringify(changed) + '\n');
	}
	for (let slot = 0; slot < 12; slot++) for (const value of [-1, '1', null, 0.5]) {
		const changed = structuredClone(base); changed[6][slot] = value; bad.push(JSON.stringify(changed) + '\n');
	}
	for (const [slot,value] of [[0,33],[1,18],[2,2],[3,28],[4,7],[4,9],[5,7],[6,0],[6,1901],[7,1],[8,1],[9,1],[10,0],[11,0]]) {
		const changed = structuredClone(base); changed[6][slot] = value; bad.push(JSON.stringify(changed) + '\n');
	}
	bad.push(bytes + bytes, bytes.slice(0,-1), ' ' + bytes, bytes.replace(',1,', ',1.0,'), bytes.replace('modeled_only','native_proven'),
		bytes.replace('candidate','candidaté'), bytes.replace('modeled_only','modeled_\\u006fnly'), '['.repeat(4097));
	for (const line of bad) assert.equal(parse(line, root, 'autoload'), false, line.slice(0,90));
	for (let cut = 0; cut < bytes.length; cut++) {
		const p = ready(); if (cut) assert.equal(p.event('stdout', bytes.slice(0,cut), 3), true);
		authorityFree(p.finish(10), false);
	}
	assert.equal(parse(bytes, root, 'fallback'), false);
	assert.equal(parse(bytes, 'b'.repeat(24), 'autoload'), false);
});
test('v2 lifecycle refusal stays latched across late effects, caught failures and closure gaps', () => {
	const bytes = JSON.stringify(frame()) + '\n';
	for (const [kind,value] of [['query',null],['http',null],['diagnostic',null],['stderr',''],['stdout',' '],['stdout',bytes],
		['fenced',profile],['core_returned',true],['core_shutdown_returned',[1,1]],['close',[1,null]],['close',[0,'SIGTERM']]]) {
		const p = ready(); assert.equal(p.event('stdout', bytes, 3), true);
		assert.equal(p.event(kind, value, 4), false);
		authorityFree(p.finish(11), false);
	}
	for (const omit of ['close','stdout_eof','stderr_eof']) {
		const p = ready(); assert.equal(p.event('stdout', bytes, 3), true);
		for (const kind of orders[0].filter(k => k !== omit)) assert.equal(p.event(kind,kind === 'close' ? [0,null] : null,4),true);
		authorityFree(p.finish(5), false);
	}
	for (const duplicate of orders[0]) {
		const p = ready(); p.event('stdout',bytes,3); const value = duplicate === 'close' ? [0,null] : null;
		assert.equal(p.event(duplicate,value,4),true); assert.equal(p.event(duplicate,value,5),false);
		authorityFree(p.finish(6),false);
	}
	const caught = ready(); caught.event('stdout',bytes,3);
	assert.throws(() => caught.violation('discarded secret'), {message:'projection_refused'});
	assert.equal(caught.event('close',[0,null],4),false); authorityFree(caught.finish(5),false);
	for (const tick of [1,20000,NaN,Infinity,'3',-1]) {
		const p = ready(); assert.equal(p.event('stdout',bytes,tick),false); authorityFree(p.finish(19999),false);
	}
	const expiry = ready('autoload',1000); expiry.event('stdout',bytes,20999);
	for (const kind of orders[0]) assert.equal(expiry.event(kind,kind === 'close' ? [0,null] : null,20999),true);
	authorityFree(expiry.finish(21000),false);
	for (const events of [[], [['core_returned',true]], [['fenced',profile],['core_shutdown_returned',[1,1]]],
		[['fenced',profile],['core_returned',true],['core_shutdown_returned',[1,0]]]]) {
		const p = create(root,'autoload',0); let tick = 0;
		for (const [kind,value] of events) p.event(kind,value,tick++);
		assert.equal(p.event('stdout',bytes,tick),false); authorityFree(p.finish(10),false);
	}
});
test('PHP/JS profile composition is fixed and semantic guard controls are non-vacuous', async () => {
	assert.equal(profile, createHash('sha256').update('alynt-fixed-projection.wp7.1.2.theme.v2.sidebar-absent').digest('hex'));
	const php = readFileSync(new URL('../../scripts/tests/sidebar-projection-catalog.php',import.meta.url),'utf8');
	assert.equal(php.match(/const PROFILE = '([a-f0-9]{64})';/)[1],profile);
	const source = readFileSync(new URL('../../scripts/tests/projection-shutdown-transcript.mjs',import.meta.url),'utf8');
	const controls = [
		['m[4] !== projectionProfile','false', m => { const f=frame(); f[4]=oldProfile; return m.parseSidebarProjectionCandidate(JSON.stringify(f)+'\n',root,'autoload') !== false; }],
		['(projectionProfile === sidebarProjectionProfile && m[6][4] !== 8)','false', m => { const f=frame(); f[6][4]=7; f[6][5]=7; return m.parseSidebarProjectionCandidate(JSON.stringify(f)+'\n',root,'autoload') !== false; }],
		['tick - start < 20000','true', m => {
			const p=m.createSidebarProjectionShutdownTranscript(root,'autoload',0);
			p.event('fenced',profile,0);p.event('core_returned',true,1);p.event('core_shutdown_returned',[1,1],2);
			p.event('stdout',JSON.stringify(frame())+'\n',20000);
			for(const kind of orders[0])p.event(kind,kind==='close'?[0,null]:null,20001);
			return p.finish(20002).synthetic_match;
		}],
		['stage === 4 && closed && stdoutEof && stderrEof','stage === 4 && stdoutEof && stderrEof', m => {
			const p=m.createSidebarProjectionShutdownTranscript(root,'autoload',0);
			p.event('fenced',profile,0);p.event('core_returned',true,1);p.event('core_shutdown_returned',[1,1],2);
			p.event('stdout',JSON.stringify(frame())+'\n',3);p.event('stdout_eof',null,4);p.event('stderr_eof',null,4);
			return p.finish(5).synthetic_match;
		}],
		['violation() { refuse();','violation() {', m => {
			const p=m.createSidebarProjectionShutdownTranscript(root,'autoload',0);
			p.event('fenced',profile,0);p.event('core_returned',true,1);p.event('core_shutdown_returned',[1,1],2);
			p.event('stdout',JSON.stringify(frame())+'\n',3);assert.throws(()=>p.violation());
			return closed(p).synthetic_match;
		}],
	];
	for (let index=0;index<controls.length;index++) {
		const [from,to,exposes]=controls[index]; assert.equal(source.split(from).length,2,from);
		const mutated=await import('data:text/javascript;base64,'+Buffer.from(source.replace(from,to)+`\n// v2 control ${index}`).toString('base64'));
		assert.equal(exposes(mutated),true,from);
	}
});
