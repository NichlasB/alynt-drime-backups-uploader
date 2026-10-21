// Bounded test-support counterfactuals only. No production edits, native runner or disk rewrite.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { selectedFixture } from './support/selected-qualification-fixture.mjs';
const grammar = new URL('../../scripts/tests/selected-qualification-grammar.mjs', import.meta.url);
const transcript = new URL('../../scripts/tests/selected-qualification-transcript.mjs', import.meta.url);
const digest = (bytes) => createHash('sha256').update(bytes).digest('hex');
async function altered(url, needle, replacement, name) {
	const original = await readFile(url, 'utf8'); assert.equal(original.split(needle).length, 2, 'unique guard');
	const code = original.replace(needle, replacement).replace(/from '(\.\/[^']+)'/g, (_, path) => `from '${new URL(path, url).href}'`);
	const fn = (await import('data:text/javascript;base64,' + Buffer.from(code).toString('base64')))[name];
	assert.equal(typeof fn, 'function'); // Syntax/import failure is not an assertion kill.
	return fn;
}
test('eight in-memory selected grammar/lifecycle semantic controls are assertion-killed, disk hashes unchanged', async () => {
	const before = [await readFile(grammar), await readFile(transcript)].map(digest);
	for (const [needle, replacement, change] of [
		['m.root_returned === false', 'true', (m) => { m.root_returned = true; }],
		['r.entries !== 1', 'false', (m) => { m.observation.entries = 0; }],
		["v.historical_outcome === 'uncertain'", 'true', (m) => { m.observation.result.historical_outcome = 'committed'; }],
		['JSON.stringify(m.source) !== JSON.stringify(source)', 'false', (m) => { m.source.selected_worker = '0'.repeat(64); }],
		['m.post_guards === 0', 'true', (m) => { m.post_guards = 2; }],
		['v[k] === false', 'true', (m) => { m.observation.result.execution_supported = true; }],
	]) {
		const parse = await altered(grammar, needle, replacement, 'parseSelectedLine');
		const { frames, source } = selectedFixture(), m = structuredClone(frames[5]); change(m);
		assert.throws(() => assert.equal(parse(JSON.stringify(m), source), false), { code: 'ERR_ASSERTION' });
	}
	for (const [needle, replacement, stage, chunk, tick] of [
		['if (tick - rootAt > 4000000000n) return fail();', 'if (false) return fail();', 3, 'whole', 4000000001n],
		['stage === 4 && tick - bindAt > 1000000000n', 'false', 4, 'partial', 1000000004n],
	]) {
		const create = await altered(transcript, needle, replacement, 'createSelectedTranscript');
		const { source, lines } = selectedFixture(), p = create(source);
		for (let n = 0; n < stage; n++) assert.equal(p.feed(n % 2 ? 'worker' : 'parent', lines[n], BigInt(n)), true);
		assert.throws(() => assert.equal(p.feed(stage % 2 ? 'worker' : 'parent', chunk === 'partial' ? lines[stage].slice(0, 1) : lines[stage], tick), false), { code: 'ERR_ASSERTION' });
	}
	assert.deepEqual([await readFile(grammar), await readFile(transcript)].map(digest), before);
});
