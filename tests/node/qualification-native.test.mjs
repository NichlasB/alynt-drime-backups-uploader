import test from 'node:test';
import assert from 'node:assert/strict';
import { PassThrough, Writable } from 'node:stream';
import { parseQualificationLine, parseQualificationFailure, qualificationDomain as domain, qualificationProfile as profile } from '../../scripts/tests/qualification-native-grammar.mjs';
import { qualificationIdentities, verifyQualificationIdentity } from '../../scripts/tests/qualification-native-identity.mjs';
import { ownQualificationControl } from '../../scripts/tests/qualification-native-control.mjs';
import { createOwnedStdioLines } from '../../scripts/tests/owned-stdio-lines.mjs';
const source = qualificationIdentities(), attempt = 'a'.repeat(64), root = 'b'.repeat(64), pair = 'c'.repeat(64);
// Synthetic bytes only. Native provenance/physical cleanup are NOT claimed by these fixtures.
const observation = { valid: true, entries: 1, returns: 1, kind: 'closed_array', result: { historical_outcome: 'committed', client_release_claimed: true, current_acceptance_claimed: true, request_lifecycle_closed_claimed: true, diagnostic_candidate_only: false, candidate_only: true, authenticated: false, barrier_verified: false, closure_proven: false, retry_supported: false, storage_supported: false, dispatch_supported: false, execution_supported: false }, sealed: true, empty_slots: true, capture_unchanged: true, io_unchanged: true };
const final = { domain, v: 1, kind: 'final', seq: 3, attempt, root, pair, profile, source, root_returned: true, pre_guards: 8, post_guards: 2, protected_reads: 40, writes: 1, original_slots_empty: true, observation };
test('support diagnostics accept bounded failures, never an authority or arbitrary payload', () => {
	const m = { passed: false, redacted_failure: true, code: 'qualification_bind_state', location: ['QualificationNativeWorker.php', 62], progress: null };
	assert.deepEqual(parseQualificationFailure(JSON.stringify(m)), m);
	for (const key of Object.keys(m)) { const v = { ...m }; delete v[key]; assert.equal(parseQualificationFailure(JSON.stringify(v)), false); }
	for (const v of [{ ...m, passed: true }, { ...m, code: 'SELECT cookie=private' }, { ...m, secret: true }, { ...m, location: ['private.php', 1] }, { ...m, location: ['QualificationNativeWorker.php', 0] }, { ...m, location: ['QualificationNativeWorker.php', 10001] }]) assert.equal(parseQualificationFailure(JSON.stringify(v)), false);
	assert.equal(parseQualificationFailure(JSON.stringify(m) + '\n'), false); assert.equal(parseQualificationFailure('x'.repeat(1025)), false);
	assert.equal(parseQualificationLine(JSON.stringify(m), source), false); // Never accepted as a FINAL.
});
test('failure progress independently preserves bounded predicates and never promotes absent FINAL defaults', () => {
	const p = { callback_clean: true, root_returned_true: false, guards_observed: 6, nonce_creations_observed: 1, protected_reads_observed: 20, writes_observed: 0 };
	const m = { passed: false, redacted_failure: true, code: 'qualification_root_return', location: ['QualificationNativeWorker.php', 80], progress: p };
	assert.deepEqual(parseQualificationFailure(JSON.stringify(m)), m);
	for (const key of Object.keys(p)) { assert.equal(parseQualificationFailure(JSON.stringify({ ...m, progress: { ...p, [key]: null } })), false); const bad = { ...p }; delete bad[key]; assert.equal(parseQualificationFailure(JSON.stringify({ ...m, progress: bad })), false); }
	for (const value of [-1, 12, '6', 1.5]) assert.equal(parseQualificationFailure(JSON.stringify({ ...m, progress: { ...p, guards_observed: value } })), false);
	assert.equal(parseQualificationFailure(JSON.stringify({ ...m, progress: { ...p, secret: true } })), false);
	for (const code of ['qualification_callback_state', 'qualification_guard_count', 'qualification_nonce_creation_count']) assert.equal(parseQualificationFailure(JSON.stringify({ ...m, code })).code, code);
	assert.equal(parseQualificationLine(JSON.stringify(m), source), false);
});
test('fixed native source groups match before constructing any owner or fixture', () => {
	assert.equal(verifyQualificationIdentity(), true); assert.equal(verifyQualificationIdentity('provider'), false);
	assert.throws(() => qualificationIdentities('provider')); assert.equal(Object.keys(source).length, 8);
});
test('native grammar positive is non-authorizing and every null/missing/extra field refuses', () => {
	assert.deepEqual(parseQualificationLine(JSON.stringify(final), source), final);
	for (const key of Object.keys(final)) {
		assert.equal(parseQualificationLine(JSON.stringify({ ...final, [key]: null }), source), false, key);
		const m = { ...final }; delete m[key]; assert.equal(parseQualificationLine(JSON.stringify(m), source), false, key);
	}
	for (const field of ['root_returned', 'pre_guards', 'post_guards', 'protected_reads', 'writes', 'original_slots_empty']) assert.equal(parseQualificationLine(JSON.stringify({ ...final, [field]: false }), source), false);
	for (const field of ['authenticated', 'closure_proven', 'barrier_verified', 'storage_supported', 'dispatch_supported', 'execution_supported']) {
		const m = structuredClone(final); m.observation.result[field] = true; assert.equal(parseQualificationLine(JSON.stringify(m), source), false);
	}
	assert.equal(parseQualificationLine(JSON.stringify({ ...final, provider: true }), source), false);
	for (const field of Object.keys(source)) assert.equal(parseQualificationLine(JSON.stringify({ ...final, source: { ...source, [field]: 'd'.repeat(64) } }), source), false);
});
test('every final truncation and trailing byte is rejected by bounded framing', () => {
	const bytes = Buffer.from(JSON.stringify(final) + '\n');
	for (let n = 0; n < bytes.length; n++) {
		let calls = 0; const p = createOwnedStdioLines((s) => { calls++; return !!parseQualificationLine(s, source); });
		if (n) p.feed(bytes.subarray(0, n));
		assert.equal(p.end(), false, String(n)); assert.equal(calls, 0);
	}
	for (const suffix of ['x', '\n', JSON.stringify(final) + '\n']) {
		let seen = 0; const p = createOwnedStdioLines((s) => seen++ === 0 && !!parseQualificationLine(s, source)); p.feed(Buffer.concat([bytes, Buffer.from(suffix)]));
		assert.equal(p.end(), false);
	}
});
test('native grammar bounds roles, remaining budget, canonical JSON and distinct identity', () => {
	const bind = { domain, v: 1, kind: 'bind', seq: 2, attempt, root, auth: 1, capture: 2, writer: 3, remaining_ns: '10000000000' };
	assert.deepEqual(parseQualificationLine(JSON.stringify(bind), source), bind);
	for (const remaining_ns of ['5999999999', '10000000001', '06000000000', '6e9', null, 6000000000]) assert.equal(parseQualificationLine(JSON.stringify({ ...bind, remaining_ns }), source), false);
	for (const writer of [0, -1, 2, 4294967296, '3', null]) assert.equal(parseQualificationLine(JSON.stringify({ ...bind, writer }), source), false);
	for (const bad of [' '.repeat(4096), 'é', JSON.stringify(final).replace('"v":1', '"v":1,"v":1'), JSON.stringify(final).replace('"seq":3', '"seq":3.0'), JSON.stringify(final) + '\r', JSON.stringify({ ...final, pair: root })]) assert.equal(parseQualificationLine(bad, source), false);
});
async function simulatedControl(fault = '') {
	let line = '', done; const finished = new Promise((r) => { done = r; }), seen = [];
	const child = { stdout: new PassThrough(), stdin: null };
	const deliver = (m) => { if (fault === 'source' && m.kind === 'root_request') m.source = { ...source, native_worker: 'd'.repeat(64) }; child.stdout.write(Buffer.from(JSON.stringify(m) + '\n')); };
	child.stdin = new Writable({ write(bytes, encoding, cb) {
		line += bytes.toString('ascii');
		if (line.endsWith('\n')) {
			const m = JSON.parse(line); line = '';
			if (m.kind === 'boot') deliver({ domain, v: 1, kind: 'root_request', seq: 1, attempt: m.attempt, source });
			if (m.kind === 'root') deliver({ domain, v: 1, kind: 'bind', seq: 2, attempt: m.attempt, root: m.root, auth: 1, capture: 2, writer: 3, remaining_ns: '8000000000' });
			if (m.kind === 'bound') { const f = { ...final, attempt: m.attempt, root: m.root, pair: m.pair }; if (fault === 'unavailable') f.observation = null; if (fault === 'pair') f.pair = 'd'.repeat(64); deliver(f); if (fault === 'trailing') deliver(f); }
		}
		cb();
	} });
	child.stdin.on('finish', done); child.stdin.on('close', done);
	const registry = { root() { seen.push('root'); return true; }, bind() { seen.push('bind'); return fault !== 'bind'; }, claim() { seen.push('claim'); return fault !== 'claim'; } };
	const control = ownQualificationControl(child, registry, source); await finished;
	const result = await control.finish(); child.stdout.destroy(); child.stdin.destroy();
	return { result, seen, replay: await control.finish() };
}
test('actual control wrapper has a positive three-stage synthetic handoff and one-shot EOF', async () => {
	const p = await simulatedControl(); assert.equal(p.result.passed, true); assert.equal(p.result.qualifier_entries, 1); assert.equal(p.result.historical_outcome, 'committed'); assert.deepEqual(p.seen, ['root', 'bind', 'claim']); assert.equal(p.replay.passed, false);
});
for (const fault of ['source', 'unavailable', 'pair', 'bind', 'claim', 'trailing']) test(`native control refuses ${fault}, not just malformed parsing`, async () => { assert.equal((await simulatedControl(fault)).result.passed, false); });
