import test from 'node:test';
import assert from 'node:assert/strict';
import { lossFixture, frame, commit, ok, receipt } from './support/selected-loss-fixtures.mjs';
for (const split of [1, 2, 3, 4, 7, 10]) test(`selected response framing fragmented by ${split}`, () => {
	const f = lossFixture(); f.ready(); f.send('writer', 'client', commit()); const bytes = frame(ok(), 1); let keys = [];
	for (let at = 0; at < bytes.length; at += split) { const out = f.api.feed(f.ids.writer, 'server', bytes.subarray(at, at + split), 1n); assert.notEqual(out, false); keys.push(...out); }
	assert.equal(keys.length, 1); assert.equal(f.api.decide(f.root, f.ids.writer, keys[0], 1n), true); assert.equal(f.complete().synthetic_match, true);
});
test('selected response cap rejects eight-byte header before payload arrives', () => {
	const f = lossFixture(); f.ready(); f.send('writer', 'client', commit());
	assert.equal(f.api.feed(f.ids.writer, 'server', Buffer.from([8, 0, 0, 1]), 1n), false);
	assert.equal(f.api.finish(2n, receipt()).synthetic_match, false);
});
test('frame, shared reservation and aggregate byte ceilings use bounded representative buffers', () => {
	const bytes = frame(Buffer.alloc(2097152, 65)), f = lossFixture(); f.ready();
	let accepted = 0;
	for (; accepted < 33; accepted++) {
		const keys = f.api.feed(f.ids.writer, 'client', bytes, 1n); if (keys === false) break;
		assert.equal(f.forward('writer', 'client', bytes, keys[0]), true);
	}
	assert.equal(accepted, 31); // 32 * (2 MiB + four framing bytes), plus the profiles, exceeds 64 MiB.
	assert.equal(f.api.finish(2n, receipt()).synthetic_match, false);
	const g = lossFixture(); g.ready();
	assert.notEqual(g.api.feed(g.ids.auth, 'client', bytes, 1n), false);
	assert.notEqual(g.api.feed(g.ids.writer, 'client', bytes, 1n), false);
	assert.equal(g.api.feed(g.ids.auth, 'server', bytes, 1n), false); // Scratch + queued owned frame would exceed shared 8 MiB.
	const h = lossFixture(); h.ready(); assert.equal(h.api.feed(h.ids.writer, 'client', Buffer.from([1, 0, 32, 0]), 1n), false);
});
test('exact 4096 frame-per-pair ceiling does not grow an unbounded completed-credit set', () => {
	const f = lossFixture(); f.ready(); const bytes = frame(Buffer.from([2]));
	for (let i = 0; i < 4093; i++) { const keys = f.api.feed(f.ids.writer, 'client', bytes, 1n); assert.notEqual(keys, false); assert.equal(f.forward('writer', 'client', bytes, keys[0]), true); }
	assert.equal(f.api.feed(f.ids.writer, 'client', bytes, 1n), false);
});
test('wrong bytes and replayed/completion-first/error callbacks cannot balance accounting', () => {
	for (const mode of ['wrong', 'replay', 'early', 'error', 'pending']) {
		const f = lossFixture(); f.ready(); const bytes = frame(commit()), key = f.api.feed(f.ids.writer, 'client', bytes, 1n)[0];
		if (mode === 'wrong') assert.equal(f.api.attempt(f.ids.writer, 'client', key, Buffer.from([1]), 1n), false);
		if (mode === 'early') assert.equal(f.api.complete(f.ids.writer, 'client', key, 1n), false);
		if (mode === 'replay') { assert.equal(f.forward('writer', 'client', bytes, key), true); assert.equal(f.api.complete(f.ids.writer, 'client', key, 1n), false); }
		if (mode === 'error') { assert.equal(f.api.attempt(f.ids.writer, 'client', key, bytes, 1n), true); assert.equal(f.api.complete(f.ids.writer, 'client', key, 1n, false), false); }
		const result = f.api.finish(2n, receipt()); assert.equal(result.synthetic_match, false); if (mode !== 'replay') assert.equal(result.accounting_balanced, false);
	}
});
