import assert from 'node:assert/strict';
import test from 'node:test';
import { createSupervisorJournalData as create, parseSupervisorIntentData as parse, SUPERVISOR_DATA_PROFILE as profile } from '../../scripts/tests/supervisor-journal-data.mjs';
import { fixture, recover, run, intent, event, trace, prefix, cleanup, ids, objects, probes, terminals } from './support/supervisor-journal-fixtures.mjs';

function allTrue(values) { assert.ok(values.length > 0); assert.ok(values.every((v) => v === true)); }
function noAuthority(result) {
	for (const [key, value] of Object.entries(result)) if (key.endsWith('_supported') || key.endsWith('_proven')) assert.equal(value, false, key);
	assert.equal(Object.isFrozen(result), true); assert.equal(result.synthetic_only, true);
}
test('canonical proof and cleanup intents are model-only and frozen', () => {
	for (const mode of ['proof', 'cleanup-only']) { const result = parse(intent(mode)); assert.equal(result.mode, mode); assert.equal(result.version, profile); noAuthority(result); }
});
for (const field of ['version', 'mode', 'target', 'artifact', 'policy', 'parent']) test(`intent rejects unsupported ${field}`, () => {
	assert.equal(parse(intent('proof', { [field]: 'foreign' })), false);
});
for (const value of [undefined, null, {}, [], new String(intent()), '', 'null', '[]', intent() + ' ', ' ' + intent(), intent().replace('"mode":"proof"', '"mode":"proof","mode":"proof"'),
	intent().replace('"proof"', '"pr\\u006fof"'), intent('proof', { account: 'forbidden' }), intent('proof', { password: 'forbidden' }), 'x'.repeat(513)]) {
	test(`noncanonical or nonprimitive intent refused ${String(value).slice(0, 35)}`, () => assert.equal(parse(value), false));
}
test('positive model completes full intent/result/probe/terminal/cleanup trace', () => {
	const f = fixture(); allTrue(run(f)); const result = f.finish();
	assert.equal(result.proof_model_match, true); assert.equal(result.cleanup_model_match, false); assert.equal(result.model_accepted, 23);
	assert.equal(result.outcome, 'model-complete'); assert.equal(result.reason, 'none'); assert.equal(result.refused, false);
	assert.equal(result.retained_control_records, 1); assert.equal(result.model_account, false); assert.equal(result.model_root, false); assert.equal(result.model_process, false);
	assert.equal(result.model_bytes, intent().length + trace.reduce((n, [k, v], i) => n + event(i, k, v).length, 0)); noAuthority(result);
	assert.equal(f.finish(), false); assert.equal(f.send('journal', 'removed-control-record'), false);
});
test('explicit modeled journal removal reports zero retained control records, not real cleanup', () => {
	const f = fixture(); allTrue(run(f, [...trace.slice(0, -1), ['journal', 'removed-control-record']]));
	const result = f.finish(); assert.equal(result.proof_model_match, true); assert.equal(result.retained_control_records, 0); noAuthority(result);
});
test('configured inputs are snapshotted rather than retained caller objects', () => {
	const f = fixture(); f.config.account = Symbol(); f.config.startedNs = 999999n;
	allTrue(run(f)); assert.equal(f.finish().proof_model_match, true);
});
for (const key of [...ids, ...objects, 'startedNs']) test(`configuration accessor ${key} is not invoked`, () => {
	const f = fixture(); let reads = 0; const config = { ...f.config };
	Object.defineProperty(config, key, { get() { reads++; throw Error('must not read'); } });
	assert.throws(() => create(config, intent()), TypeError); assert.equal(reads, 0);
});
test('proxies including revoked proxies are rejected before prototype access', () => {
	const f = fixture(), revoked = Proxy.revocable({}, {}); revoked.revoke();
	for (const config of [new Proxy({}, { getPrototypeOf() { throw Error('trap'); } }), revoked.proxy]) assert.throws(() => create(config, intent()), TypeError);
	assert.equal(parse(revoked.proxy), false);
	assert.equal(f.owner.accept(event(0, 'handoff', 'protected-model'), revoked.proxy, f.owned, 101n), false);
});
test('identity constructors reject reuse/global/well-known/aliased IDs and bad epochs', () => {
	for (const patch of [{ attempt: Symbol.for('global') }, { attempt: Symbol.iterator }, { startedNs: -1n }, { startedNs: 0 }, { startedNs: undefined }]) {
		const f = fixture(); assert.throws(() => create({ ...f.config, ...patch }, intent()), TypeError);
	}
	const f = fixture(); assert.throws(() => create(f.config, intent()), TypeError);
	assert.throws(() => create({ ...f.config, consumer: f.config.supervisor }, intent()), TypeError);
	assert.throws(() => create(Object.assign(Object.create(null), f.config), intent()), TypeError);
});
for (const key of ids) test(`changed context ${key} refuses before charging/parsing event`, () => {
	const f = fixture(); assert.equal(f.owner.accept('x'.repeat(3000), { ...f.identity, [key]: Symbol() }, f.owned, 101n), false);
	const r = f.finish(); assert.equal(r.reason, 'identity'); assert.equal(r.model_events, 0); assert.equal(r.model_bytes, intent().length);
});
for (const key of objects) test(`owned-object drift ${key} refuses`, () => {
	const f = fixture(); assert.equal(f.owner.accept(event(0, 'handoff', 'protected-model'), f.identity, { ...f.owned, [key]: Symbol() }, 101n), false);
	assert.equal(f.finish().reason, 'identity');
});
for (const now of [99n, 20000000100n, 20000000101n, 101, NaN, undefined]) test(`invalid original clock ${String(now)}`, () => {
	const f = fixture(); assert.equal(f.owner.accept(event(0, ...prefix[0]), f.identity, f.owned, now), false); assert.equal(f.finish().reason, 'clock');
});
test('monotonic half-open epoch accepts last instant and refuses rollback', () => {
	const f = fixture(); allTrue(trace.map(([k, v]) => f.send(k, v, 20000000099n))); assert.equal(f.finish(20000000099n).proof_model_match, true);
	const g = fixture(); assert.equal(g.send(...prefix[0], 103n), true); assert.equal(g.send(...prefix[1], 102n), false); assert.equal(g.finish(103n).reason, 'clock');
});
test('sequence/noncanonical/duplicate/unknown records refuse sticky without replay', () => {
	for (const text of [event(1, ...prefix[0]), event(0, ...prefix[0]) + ' ', event(0, ...prefix[0]).replace('"seq":0', '"seq":0,"seq":0'),
		JSON.stringify({ seq: 0, kind: 'handoff', value: 'protected-model', command: 'forbidden' }), event(0, 'handoff', 'unavailable'), event(0, 'unknown', 'unknown')]) {
		const f = fixture(); assert.equal(f.owner.accept(text, f.identity, f.owned, 101n), false); assert.equal(f.send(...prefix[0]), false);
		assert.equal(f.finish().proof_model_match, false);
	}
});
test('every required trace omission or adjacent swap prevents proof-model completion', () => {
	for (let i = 0; i < trace.length; i++) { const f = fixture(); run(f, trace.filter((_, j) => j !== i)); assert.equal(f.finish().proof_model_match, false, `omitted ${i}`); }
	for (let i = 0; i < trace.length - 1; i++) {
		if (i >= prefix.length + probes.length && i < prefix.length + probes.length + terminals.length - 1) continue;
		const f = fixture(), changed = trace.map((e) => [...e]); [changed[i], changed[i + 1]] = [changed[i + 1], changed[i]];
		run(f, changed); assert.equal(f.finish().proof_model_match, false, `swapped ${i}`);
	}
});
test('all terminal enums are required once; order alone is not authority', () => {
	const f = fixture(); allTrue(run(f, [...prefix, ...probes.map((v) => ['probe', v]), ...terminals.toReversed().map((v) => ['terminal', v]), ...cleanup]));
	assert.equal(f.finish().proof_model_match, true);
	const g = fixture(); allTrue(run(g, [...prefix, ...probes.map((v) => ['probe', v]), ['terminal', terminals[0]]]));
	assert.equal(g.send('terminal', terminals[0]), false); assert.equal(g.finish().proof_model_match, false);
});
test('record/byte bounds precede parsing and failed work remains charged', () => {
	const f = fixture(); assert.equal(f.owner.accept('x'.repeat(257), f.identity, f.owned, 101n), false);
	const r = f.finish(); assert.equal(r.reason, 'record-limit'); assert.equal(r.model_events, 1); assert.equal(r.model_bytes, intent().length + 257);
	const g = fixture(); assert.equal(g.owner.accept('x'.repeat(3000), g.identity, g.owned, 101n), false);
	const q = g.finish(); assert.equal(q.reason, 'byte-limit'); assert.equal(q.model_bytes, 2049);
	const h = fixture(); assert.equal(h.owner.accept('é', h.identity, h.owned, 101n), false); assert.equal(h.finish().model_bytes, intent().length + 2);
});
test('pre-handoff absence and ambiguous create/delete intents cannot mint recovery', () => {
	for (const length of [0, 2, 4, 6, 19, 21]) {
		const f = fixture(); allTrue(length ? run(f, trace.slice(0, length)) : [true]); const r = f.finish();
		assert.equal(r.outcome, 'quarantine'); assert.throws(() => recover(f, r), TypeError, `unowned/ambiguous ${length}`);
	}
});
test('known owned account/root gets one sealed cleanup-only continuation with original costs', () => {
	const f = fixture(); allTrue(run(f, prefix.slice(0, 5))); const before = f.finish(), g = recover(f, before);
	allTrue(run(g, cleanup)); const after = g.finish(); assert.equal(after.cleanup_model_match, true); assert.equal(after.proof_model_match, false);
	assert.equal(after.model_events, before.model_events + cleanup.length); assert.equal(after.model_accepted, before.model_accepted + cleanup.length);
	assert.ok(after.model_bytes > before.model_bytes + intent('cleanup-only').length); noAuthority(after);
	assert.throws(() => recover(f, before), TypeError); assert.throws(() => recover(g, after), TypeError);
});
test('live process recovery requires modeled containment before object removal', () => {
	const f = fixture(); allTrue(run(f, prefix)); const before = f.finish(), g = recover(f, before);
	assert.equal(g.send('containment', 'owned-processes-and-eofs-closed'), true); allTrue(run(g, cleanup));
	const after = g.finish(); assert.equal(after.cleanup_model_match, true); assert.equal(after.proof_model_match, false); assert.equal(after.model_process, false);
	const a = fixture(); run(a, prefix); const b = recover(a, a.finish()); assert.equal(b.send('intent', 'remove-account'), false);
});
test('forged/cloned/wrong-context/fresh-epoch recovery and creation in cleanup-only refuse', () => {
	const f = fixture(); run(f, prefix.slice(0, 3)); const r = f.finish();
	for (const value of [{ ...r }, Object.freeze({}), undefined]) assert.throws(() => create(f.config, intent('cleanup-only'), value), TypeError);
	for (const patch of [...[...ids, ...objects].map((k) => ({ [k]: Symbol() })), { startedNs: 1000n }]) assert.throws(() => create({ ...f.config, ...patch }, intent('cleanup-only'), r), TypeError);
	const g = recover(f, r); assert.equal(g.send('handoff', 'protected-model'), false); assert.equal(g.finish().cleanup_model_match, false);
});
test('recovery cannot revive expiry, reset event ceilings or hide retained records', () => {
	const f = fixture(); run(f, prefix.slice(0, 3)); const g = recover(f, f.finish());
	assert.equal(g.send('intent', 'remove-root', 20000000100n), false); assert.equal(g.finish().cleanup_model_match, false);
	const h = fixture(); allTrue(run(h)); assert.equal(h.send('journal', 'removed-control-record'), false);
	const prior = h.finish(), j = recover(h, prior); assert.equal(j.send('journal', 'removed-control-record'), false);
	const result = j.finish(); assert.equal(result.reason, 'event-limit'); assert.equal(result.retained_control_records, 1);
});
test('a rejected original clock cannot become a fresh recovery lease after a smaller finish stamp', () => {
	const f = fixture(); run(f, prefix.slice(0, 3)); assert.equal(f.send('intent', 'create-account', 20000000100n), false);
	const r = f.finish(); assert.equal(r.reason, 'clock'); assert.throws(() => recover(f, r), TypeError);
});
test('a recovery receipt cannot seed a new proof even with fresh private identities', () => {
	const f = fixture(); run(f, prefix.slice(0, 3)); const r = f.finish();
	const config = { ...Object.fromEntries([...ids, ...objects].map((k) => [k, Symbol()])), startedNs: 100n };
	assert.throws(() => create(config, intent(), r), TypeError);
});
test('event input never invokes accessors/coercion; malformed numeric encodings are noncanonical', () => {
	for (const input of [{ toString() { throw Error('coercion'); } }, Object.create(null), event(0, ...prefix[0]).replace('"seq":0', '"seq":-0'),
		event(0, ...prefix[0]).replace('"seq":0', '"seq":0e0'), event(0, ...prefix[0]).replace('"seq":0', '"seq":0.0')]) {
		const f = fixture(); assert.doesNotThrow(() => assert.equal(f.owner.accept(input, f.identity, f.owned, 101n), false));
		assert.equal(f.finish().reason, 'event-format');
	}
	for (const key of ['attempt', 'account']) {
		const f = fixture(), identity = { ...f.identity }, owned = { ...f.owned }; let reads = 0;
		Object.defineProperty(key === 'attempt' ? identity : owned, key, { get() { reads++; throw Error('protected accessor'); } });
		assert.equal(f.owner.accept(event(0, ...prefix[0]), identity, owned, 101n), false); assert.equal(reads, 0);
	}
});
