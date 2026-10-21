// Fixed in-memory counterfactuals only. No source edits, native runner or transport.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { qualificationFixture, witnessFixture, cleanup, frame, ok, commit } from './support/native-qualification-fixture.mjs';
const grammar = new URL('../../scripts/tests/native-qualification-grammar.mjs', import.meta.url);
const transcript = new URL('../../scripts/tests/native-qualification-transcript.mjs', import.meta.url);
const eligibility = new URL('../../scripts/tests/native-qualification-eligibility.mjs', import.meta.url);
const hash = (s) => createHash('sha256').update(s).digest('hex');
async function mutant(url, from, to, name) {
	const original = await readFile(url, 'utf8'); assert.equal(original.split(from).length, 2, 'one fixed semantic change');
	const code = original.replace(from, to).replace(/from '(\.\/[^']+)'/g, (_, path) => `from '${new URL(path, url).href}'`);
	const fn = (await import('data:text/javascript;base64,' + Buffer.from(code).toString('base64')))[name];
	assert.equal(typeof fn, 'function', 'control compiles/imports, not a bootstrap kill'); return fn;
}
test('nine new FINAL guards are independently assertion-killed without changing disk source', async () => {
	const before = hash(await readFile(grammar));
	for (const [from, to, change] of [
		['m.pre_guards === 10', 'true', (m) => { m.pre_guards = 8; }],
		['m.post_guards === 14', 'true', (m) => { m.post_guards = 0; }],
		['JSON.stringify(m.guard_order) === JSON.stringify(guardOrder)', 'true', (m) => { m.guard_order[9] = 'M'; }],
		['v.client_release_claimed === true', 'true', (m) => { m.observation.result.client_release_claimed = false; }],
		['r.entries !== 1', 'false', (m) => { m.observation.entries = 0; }],
		['r[k] === false', 'true', (m) => { m.native_result.execution_supported = true; }],
		['JSON.stringify(m.source) !== JSON.stringify(source)', 'false', (m) => { m.source.production = '0'.repeat(64); }],
		['m.writer_erased_before_read === true', 'true', (m) => { m.writer_erased_before_read = false; }],
		['m.barrier_closed_before_finish === true', 'true', (m) => { m.barrier_closed_before_finish = false; }],
	]) {
		const parse = await mutant(grammar, from, to, 'parseNativeQualificationLine'), { frames, source } = qualificationFixture(), m = structuredClone(frames[7]); change(m);
		assert.throws(() => assert.equal(parse(JSON.stringify(m), source), false), { code: 'ERR_ASSERTION' });
	}
	assert.equal(hash(await readFile(grammar)), before);
});
test('both ACK/read/root deadline controls are assertion-killed with valid ordered transcripts', async () => {
	const before = hash(await readFile(transcript));
	for (const [from, to, ticks] of [
		['(stage === 4 || stage === 6) && tick - bindAt > 1000000000n', 'false', [0n,0n,0n,1n,2n,3n,1000000004n,1000000005n]],
		['readEnd !== null && tick >= readEnd', 'false', [0n,0n,0n,1n,2n,3n,4n,9000000003n]],
		['rootAt !== null && tick - rootAt >= 20000000000n', 'false', [0n,0n,0n,1n,2n,15000000000n,15000000001n,20000000000n]],
	]) {
		const create = await mutant(transcript, from, to, 'createNativeQualificationTranscript'), { source, lines } = qualificationFixture(), p = create(source);
		for (let n = 0; n < 7; n++) assert.equal(p.feed(n % 2 ? 'worker' : 'parent', lines[n], ticks[n]), true);
		assert.throws(() => assert.equal(p.feed('worker', lines[7], ticks[7]), false), { code: 'ERR_ASSERTION' });
	}
	assert.equal(hash(await readFile(transcript)), before);
});
test('retroactive request credit, cross-root FINAL and barrier acknowledgement controls are assertion-killed', async () => {
	const before = hash(await readFile(eligibility));
	{
		const create = await mutant(eligibility, 'response || !requestDone || seq !== 1', 'response || seq !== 1', 'createNativeQualificationWitness'), f = witnessFixture(false, create);
		assert.equal(f.ready(), true); const req = frame(commit()), [request] = f.api.feed(f.ids.writer, 'client', req, 2n);
		assert.equal(f.api.attempt(f.ids.writer, 'client', request, req, 2n), true);
		const [response] = f.api.feed(f.ids.writer, 'server', frame(ok(), 1), 2n); assert.equal(f.api.complete(f.ids.writer, 'client', request, 2n), true);
		assert.throws(() => assert.equal(f.api.decide(f.root, f.ids.writer, response, 2n), false), { code: 'ERR_ASSERTION' });
	}
	{
		const create = await mutant(eligibility, 'm.root !== context.root ||', '', 'createNativeQualificationWitness'), f = witnessFixture(false, create);
		assert.equal(f.ready(), true); assert.equal(f.select(), true); assert.equal(f.observe(), true);
		const m = structuredClone(f.frames[7]); m.root = 'e'.repeat(64);
		assert.throws(() => assert.equal(f.api.claim(f.root, JSON.stringify(m), 4n), false), { code: 'ERR_ASSERTION' });
	}
	{
		const create = await mutant(eligibility, 'r === barrier && !barrierAckDone ||', '', 'createNativeQualificationWitness'), f = witnessFixture(false, create);
		assert.equal(f.ready(), true); assert.equal(f.select(), true); assert.equal(f.api.close(f.ids.writer, 3n), true); assert.equal(f.api.beginRead(f.root, 3n), true);
		assert.equal(f.api.openBarrier(f.root, f.ids.barrier, 3n), true); assert.equal(f.handshake('barrier', 104, 3n), true); assert.equal(f.api.bindBarrier(f.root, f.ids.barrier, 3n), true);
		assert.throws(() => assert.equal(f.api.close(f.ids.barrier, 3n), false), { code: 'ERR_ASSERTION' });
	}
	assert.equal(hash(await readFile(eligibility)), before);
});
