// Isolated API tests: inherited-stream shapes only, not OS pipe/authentication proof.
import test from 'node:test';
import assert from 'node:assert/strict';
import { PassThrough, Writable } from 'node:stream';
import { ownObservationControl } from '../../scripts/tests/observation-control-owner.mjs';

async function exchange(mode = 'positive') {
	let buffer = '', rootCalls = 0, binds = 0, claims = 0;
	const stdout = new PassThrough();
	const incoming = (m) => { for (const b of Buffer.from(JSON.stringify(m) + '\n')) stdout.write(Buffer.from([b])); };
	const stdin = new Writable({ write(chunk, encoding, callback) {
		buffer += chunk.toString('ascii');
		if (buffer.endsWith('\n')) {
			const m = JSON.parse(buffer); buffer = '';
			queueMicrotask(() => {
				if (m.kind === 'boot') incoming({ v: 1, kind: 'root_request', seq: 1, attempt: m.attempt });
				if (m.kind === 'root') incoming({ v: 1, kind: 'bind', seq: 2, attempt: m.attempt, root: m.root, auth: 101, capture: 102, writer: 103, remaining_ns: '9999999999' });
				if (m.kind === 'bound') {
					incoming({ v: 1, kind: 'final', seq: 3, attempt: m.attempt, root: mode === 'wrong-root' ? 'f'.repeat(64) : m.root, pair: m.pair, passed: true, checks: mode === 'zero' ? 0 : 242, groups: 2 });
					if (mode === 'trailing') stdout.write(Buffer.from('tail'));
				}
			});
		}
		callback();
	} });
	const done = new Promise((resolve) => stdin.once('finish', () => { stdout.end(); resolve(); }));
	const registry = {
		root(tick) { rootCalls++; assert.equal(typeof tick, 'bigint'); return true; },
		bind(ids) { binds++; assert.deepEqual(ids, { auth: 101, capture: 102, writer: 103 }); return true; },
		claim() { claims++; return mode !== 'unavailable'; },
	};
	const owner = ownObservationControl({ stdin, stdout }, registry);
	await done; const result = await owner.finish(); stdin.destroy(); stdout.destroy();
	return { result, rootCalls, binds, claims };
}
test('actual private owner non-vacuously accepts fragmented original-root final as provisional', async () => {
	const r = await exchange(); assert.equal(r.result.passed, true); assert.equal(r.rootCalls, 1); assert.equal(r.binds, 1); assert.equal(r.claims, 1);
	assert.equal(r.result.checks, 242); assert.equal(r.result.private_tokens_exported, false);
});
for (const mode of ['unavailable', 'wrong-root', 'zero', 'trailing']) test(`actual private owner refuses ${mode}`, async () => {
	const r = await exchange(mode); assert.equal(r.result.passed, false); assert.equal(r.rootCalls, 1); assert.equal(r.binds, 1);
	assert.equal(r.claims, ['wrong-root', 'zero'].includes(mode) ? 0 : 1);
});
