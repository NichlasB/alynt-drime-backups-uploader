// Bounded in-memory counterfactuals: never edit source or mutate a native process.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { lossFixture, lossControl, frame, commit, ok, receipt } from './support/selected-loss-fixtures.mjs';
const witnessUrl = new URL('../../scripts/tests/selected-loss-witness.mjs', import.meta.url);
const controlUrl = new URL('../../scripts/tests/selected-loss-control.mjs', import.meta.url);
async function altered(url, needle, replacement, name) {
	const original = await readFile(url, 'utf8'); assert.equal(original.split(needle).length, 2, 'unique semantic guard');
	const source = original.replace(needle, replacement).replace(/from '(\.\/[^']+)'/g, (_, path) => `from '${new URL(path, url).href}'`);
	const factory = (await import('data:text/javascript;base64,' + Buffer.from(source).toString('base64')))[name];
	assert.equal(typeof factory, 'function'); // Import/syntax/fixture failures cannot count as assertion kills.
	return factory;
}
const digest = (text) => createHash('sha256').update(text).digest('hex');
test('eight assertion-killed actual-source counterfactuals, unchanged on-disk SHA256', async () => {
	const before = [await readFile(witnessUrl), await readFile(controlUrl)].map(digest);
	const cases = [
		['cut = true; entry.withheld = true;', 'cut = false; entry.withheld = true;', (f) => { f.ready(); f.select(); assert.equal(f.complete().synthetic_match, true); }],
		['entry.row.id !== id', 'false', (f) => { f.ready(); const key = f.feed('writer', 'client', commit())[0]; assert.equal(f.api.attempt(f.ids.auth, 'client', key, frame(commit()), 1n), false); }],
		['!requestDone || sequence !== 1', 'false || sequence !== 1', (f) => {
			f.ready(); const key = f.feed('writer', 'client', commit())[0]; f.api.attempt(f.ids.writer, 'client', key, frame(commit()), 1n);
			const reply = f.feed('writer', 'server', ok(), 1)[0]; f.api.complete(f.ids.writer, 'client', key, 1n);
			assert.equal(f.api.decide(f.root, f.ids.writer, reply, 1n), false);
		}],
		['const enough = (now) => now - startedNs <= lossLimits.window - lossLimits.remaining;', 'const enough = () => true;', (f) => {
			f.ready(); f.send('writer', 'client', commit(), 0, 4000000001n); const key = f.feed('writer', 'server', ok(), 1, 4000000001n)[0];
			assert.equal(f.api.decide(f.root, f.ids.writer, key, 4000000001n), false);
		}],
		['if (entry.withheld || entry.attempted || !Buffer.isBuffer(bytes) || !entry.bytes?.equals(bytes)) return fail();',
			'if (entry.attempted || !Buffer.isBuffer(bytes) || (!entry.withheld && !entry.bytes?.equals(bytes))) return fail();', (f) => {
			f.ready(); f.send('writer', 'client', commit()); const key = f.feed('writer', 'server', ok(), 1)[0]; f.api.decide(f.root, f.ids.writer, key, 1n);
			assert.equal(f.api.attempt(f.ids.writer, 'server', key, frame(ok(), 1), 1n), false);
		}],
		['targetAttempts === 0 && lossReceipt(receipt)', 'targetAttempts === 0 && true', (f) => { f.ready(); f.select(); const r = receipt(); r.ledger_matches = false; assert.equal(f.complete(3n, r).synthetic_match, false); }],
	];
	for (const [needle, replacement, exercise] of cases) {
		const factory = await altered(witnessUrl, needle, replacement, 'createSelectedLossWitness');
		assert.throws(() => exercise(lossFixture(factory)), { code: 'ERR_ASSERTION' });
	}
	for (const [needle, replacement, exercise] of [
		['m.v !== 2', 'false', (f) => { f.messages[0].v = 1; assert.equal(f.send(0), false); }],
		['node - bindNode > 1000000000n', 'false', (f) => { for (let i = 0; i < 4; i++) f.send(i); assert.equal(f.send(4, 1000000001n, 900000000000n), false); }],
	]) {
		const factory = await altered(controlUrl, needle, replacement, 'createSelectedLossControl');
		assert.throws(() => exercise(lossControl(factory)), { code: 'ERR_ASSERTION' });
	}
	assert.deepEqual([await readFile(witnessUrl), await readFile(controlUrl)].map(digest), before);
});
