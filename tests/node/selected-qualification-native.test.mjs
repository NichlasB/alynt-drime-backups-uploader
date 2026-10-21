import test from 'node:test';
import assert from 'node:assert/strict';
import { PassThrough, Writable } from 'node:stream';
import { readFileSync } from 'node:fs';
import { ownSelectedQualificationControl } from '../../scripts/tests/selected-qualification-native-control.mjs';
import { selectedIdentities, verifySelectedIdentity } from '../../scripts/tests/selected-qualification-identity.mjs';
import { verifyQualificationIdentity } from '../../scripts/tests/qualification-native-identity.mjs';
import { selectedFixture } from './support/selected-qualification-fixture.mjs';
const source = selectedIdentities(), final = selectedFixture().frames[5];
// Synthetic private streams and fabricated claims only; never WordPress or a native receipt.
async function simulated(fault = '', release = true) {
	let line = '', done; const finished = new Promise((r) => { done = r; }), seen = [];
	const child = { stdout: new PassThrough(), stdin: null };
	const deliver = (m) => {
		if (fault === 'source' && m.kind === 'root_request') m.source = { ...source, native_worker: 'd'.repeat(64) };
		child.stdout.write(Buffer.from(JSON.stringify(m) + '\n'));
	};
	child.stdin = new Writable({ write(bytes, encoding, cb) {
		line += bytes.toString('ascii');
		if (line.endsWith('\n')) {
			const m = JSON.parse(line); line = '';
			const base = { domain: m.domain, v: 1, kind: '', seq: 1, attempt: m.attempt };
			if (m.kind === 'boot') deliver({ ...base, kind: 'root_request', source });
			if (m.kind === 'root') deliver({ ...base, kind: 'bind', seq: 2, root: m.root, auth: 1, capture: 2, writer: 3, remaining_ns: '8000000000' });
			if (m.kind === 'bound') {
				const f = structuredClone({ ...final, attempt: m.attempt, root: m.root, pair: m.pair });
				f.observation.result.client_release_claimed = release;
				if (fault === 'unavailable') f.observation = null;
				if (fault === 'pair') f.pair = 'd'.repeat(64);
				if (fault === 'root_true') f.root_returned = true;
				if (fault === 'post_guard') f.post_guards = 1;
				deliver(f); if (fault === 'trailing') deliver(f);
			}
		}
		cb();
	} });
	child.stdin.on('finish', () => { if (fault !== 'eof') child.stdout.end(); done(); });
	child.stdin.on('close', done);
	const registry = { root() { seen.push('root'); return fault !== 'root'; }, bind() { seen.push('bind'); return fault !== 'bind'; }, claim() { seen.push('claim'); return fault !== 'claim'; } };
	const control = ownSelectedQualificationControl(child, registry, source);
	await finished; await new Promise(setImmediate);
	const result = await control.finish(), replay = await control.finish();
	child.stdout.destroy(); child.stdin.destroy(); return { result, replay, seen };
}
test('selected native manifest pins old eight identities and distinct fixed native files', () => {
	assert.equal(verifySelectedIdentity(), true); assert.equal(verifyQualificationIdentity(), true);
	assert.equal(verifySelectedIdentity('provider'), false); assert.throws(() => selectedIdentities('provider'));
	const groups = JSON.parse(readFileSync(new URL('../../scripts/tests/selected-qualification-groups.json', import.meta.url)));
	for (const f of ['tests/Support/SelectedQualificationNativeWorker.php', 'scripts/tests/selected-qualification-native-bootstrap.php']) assert.ok(groups.selected_worker.includes(f));
	for (const f of ['scripts/tests/selected-qualification-native-proof.ps1', 'scripts/tests/selected-qualification-prerequisite.mjs']) assert.ok(groups.selected_supervisor.includes(f));
});
for (const release of [true, false]) test('selected private control copies actual release=' + release + ' without promoting authority', async () => {
	const p = await simulated('', release); assert.equal(p.result.passed, true);
	assert.equal(p.result.root_returned, false); assert.equal(p.result.pre_guards, 8); assert.equal(p.result.post_guards, 0);
	assert.equal(p.result.historical_outcome, 'uncertain'); assert.equal(p.result.client_release_claimed, release);
	assert.equal(p.result.current_acceptance_claimed, false); assert.equal(p.result.qualifier_entries, 1);
	assert.equal(p.result.qualifier_returns, 1); assert.equal(p.replay.passed, false);
	assert.deepEqual(p.seen, ['root', 'bind', 'claim']); assert.equal(p.result.private_tokens_exported, false);
});
for (const fault of ['source', 'unavailable', 'pair', 'root_true', 'post_guard', 'trailing', 'eof', 'root', 'bind', 'claim']) test('selected private control independently refuses ' + fault, async () => {
	assert.equal((await simulated(fault)).result.passed, false);
});
