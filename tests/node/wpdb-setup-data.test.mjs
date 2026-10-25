import assert from 'node:assert/strict';
import test from 'node:test';
import { WPDB_SETUP_DATA_PROFILE as profile, wpdbSetupCatalog, createWpdbSetupData } from '../../scripts/tests/wpdb-setup-data.mjs';

test('fixed catalog separates seven public references from installed/native evidence', () => {
	const c = wpdbSetupCatalog(profile);
	assert.equal(c.references.length, 7);
	assert.equal(new Set(c.references.map((r) => r.member)).size, 7);
	for (const r of c.references) {
		assert.match(r.sha256, /^[a-f0-9]{64}$/); assert.ok(r.bytes > 20000 && r.bytes < 130000);
		assert.equal(r.evidence, 'public-reference-bytes'); assert.equal(Object.isFrozen(r), true);
	}
	assert.equal(c.references.filter((r) => r.prior_installed_member_match).length, 1);
	assert.equal(c.references[0].sha256, 'e15403e90032dd0508811301505e491d4212de5152581a98f51b744a1a3903bd');
	for (const key of ['old_wire_profile_compatible', 'installed_build_proven', 'observed_session_proven',
		'immutable_ownership_proven', 'transitive_source_closure_proven', 'native_supported']) assert.equal(c[key], false, key);
	assert.equal(Object.isFrozen(c), true); assert.equal(Object.isFrozen(c.references), true);
	assert.equal(new Set(['source_catalog', 'session', 'setup', 'result', 'lifetime', 'resource'].map((k) => c[k])).size, 6);
});
for (const invalid of [undefined, null, {}, new String(profile), 'projection-text41-ordinary-eof-v1', 'wpdb-postconnect-setup.v1', profile + ' ']) {
	test(`catalog refuses unsupported profile ${String(invalid)}`, () => assert.throws(() => wpdbSetupCatalog(invalid), TypeError));
}

const keys = ['attempt', 'pair', 'generation', 'request', 'session'];
export function fixture(factory = createWpdbSetupData) {
	const identity = Object.fromEntries(keys.map((k) => [k, Symbol(k)]));
	const config = { ...identity, profile, schema: 'owned-synthetic', startedNs: 100n };
	const owner = factory(config);
	return { owner, identity, config, send: (kind, value, now = 101n) => owner.accept({ kind, value }, identity, now),
		finish: (now = 102n) => owner.finish(identity, now) };
}
function prelude(f, wire = 'ok-zero-wire') {
	assert.equal(f.send('connect', 'fresh-nonpersistent-no-database'), true);
	assert.equal(f.send('auth-result', 'synthetic-ok'), true);
	assert.equal(f.send('charset-api', wire), true);
	if (wire === 'ok-one-wire') assert.equal(f.send('charset-statement-result', 'ok'), true);
}
for (const wire of ['ok-zero-wire', 'ok-one-wire']) test(`connect/charset checkpoint ${wire} is reachable without native claims`, () => {
	const f = fixture(); prelude(f, wire); const result = f.finish();
	assert.equal(result.stage, 'mode-read'); assert.equal(result.refused, false); assert.equal(result.synthetic_match, false);
	for (const [key, value] of Object.entries(result)) if (key.endsWith('_proven') || key.endsWith('_supported')) assert.equal(value, false);
});
for (const [kind, value] of [['auth-result', 'synthetic-ok'], ['connect', 'schema-in-hello'], ['connect', 'persistent'],
	['charset-api', 'ok-zero-wire'], ['connect', 'reused']]) test(`setup rejects invalid first stage ${kind}/${value}`, () => {
	const f = fixture(); assert.equal(f.send(kind, value), false);
	assert.equal(f.send('connect', 'fresh-nonpersistent-no-database'), false); assert.equal(f.finish().refused, true);
});
test('unknown API/wire mapping and failed charset result are not zero work', () => {
	for (const value of ['unknown', 'error', 'ok-many-wire']) {
		const f = fixture(); f.send('connect', 'fresh-nonpersistent-no-database'); f.send('auth-result', 'synthetic-ok');
		assert.equal(f.send('charset-api', value), false);
	}
	const f = fixture(); f.send('connect', 'fresh-nonpersistent-no-database'); f.send('auth-result', 'synthetic-ok'); f.send('charset-api', 'ok-one-wire');
	assert.equal(f.send('charset-statement-result', 'error'), false);
});

for (const mode of ['one-empty-string', 'one-filterable-nonempty']) test(`successful typed mode branch ${mode} reaches schema checkpoint`, () => {
	const f = fixture(); prelude(f); assert.equal(f.send('mode-read-result', mode), true);
	if (mode === 'one-filterable-nonempty') assert.equal(f.send('mode-write-result', 'ok-fixed-filtered'), true);
	assert.equal(f.finish().stage, 'select-schema');
});
for (const value of ['error', 'no-result', 'zero-rows', 'null', 'zero-string', 'multi-rows', 'unknown', '', 'one-nonempty-dynamic-filter']) {
	test(`mode result ${value} cannot masquerade as successful empty`, () => {
		const f = fixture(); prelude(f); assert.equal(f.send('mode-read-result', value), false);
		assert.equal(f.send('mode-read-result', 'one-empty-string'), false); assert.equal(f.finish().refused, true);
	});
}
test('empty branch cannot accept unnecessary write; nonempty cannot skip or accept generic write', () => {
	const empty = fixture(); prelude(empty); empty.send('mode-read-result', 'one-empty-string');
	assert.equal(empty.send('mode-write-result', 'ok-fixed-filtered'), false);
	for (const [kind, value] of [['select-schema-result', 'owned-synthetic'], ['mode-write-result', 'ok'], ['mode-write-result', 'error']]) {
		const f = fixture(); prelude(f); f.send('mode-read-result', 'one-filterable-nonempty');
		assert.equal(f.send(kind, value), false);
	}
});
test('mode read or write replay refuses rather than overwriting branch history', () => {
	for (const mode of ['one-empty-string', 'one-filterable-nonempty']) {
		const f = fixture(); prelude(f); f.send('mode-read-result', mode);
		assert.equal(f.send('mode-read-result', mode), false);
	}
	const f = fixture(); prelude(f); f.send('mode-read-result', 'one-filterable-nonempty'); f.send('mode-write-result', 'ok-fixed-filtered');
	assert.equal(f.send('mode-write-result', 'ok-fixed-filtered'), false);
});

function selected(f, mode = 'one-empty-string', wire = 'ok-zero-wire') {
	prelude(f, wire); assert.equal(f.send('mode-read-result', mode), true);
	if (mode === 'one-filterable-nonempty') assert.equal(f.send('mode-write-result', 'ok-fixed-filtered'), true);
	assert.equal(f.send('schema-intent', 'owned-synthetic'), true);
	assert.equal(f.send('schema-result', 'ok'), true);
	assert.equal(f.send('data-profile', 'synthetic-separate-result-grammar'), true);
}
for (const mode of ['one-empty-string', 'one-filterable-nonempty']) for (const wire of ['ok-zero-wire', 'ok-one-wire']) {
	test(`non-vacuous selected setup ${mode}/${wire} reaches DATA match only`, () => {
		const f = fixture(); selected(f, mode, wire); const result = f.finish();
		assert.equal(result.synthetic_match, true); assert.equal(result.refused, false); assert.equal(Object.isFrozen(result), true);
		for (const [key, value] of Object.entries(result)) if (key.endsWith('_proven') || key.endsWith('_supported')) assert.equal(value, false);
	});
}
for (const [kind, value] of [['schema-result', 'ok'], ['data-profile', 'synthetic-separate-result-grammar'], ['schema-intent', 'foreign']]) {
	test(`later schema selection refuses ${kind}/${value}`, () => {
		const f = fixture(); prelude(f); f.send('mode-read-result', 'one-empty-string'); assert.equal(f.send(kind, value), false);
		assert.equal(f.finish().synthetic_match, false);
	});
}
test('schema error and old data grammar never obtain selected-data admission', () => {
	for (const result of ['error', 'partial', 'unknown']) {
		const f = fixture(); prelude(f); f.send('mode-read-result', 'one-empty-string'); f.send('schema-intent', 'owned-synthetic');
		assert.equal(f.send('schema-result', result), false); assert.equal(f.finish().synthetic_match, false);
	}
	const f = fixture(); prelude(f); f.send('mode-read-result', 'one-empty-string'); f.send('schema-intent', 'owned-synthetic'); f.send('schema-result', 'ok');
	assert.equal(f.send('data-profile', 'projection-text41-ordinary-eof-v1'), false);
});
test('every selected-setup prefix remains incomplete and post-completion extra event refuses', () => {
	const events = [['connect', 'fresh-nonpersistent-no-database'], ['auth-result', 'synthetic-ok'], ['charset-api', 'ok-zero-wire'],
		['mode-read-result', 'one-empty-string'], ['schema-intent', 'owned-synthetic'], ['schema-result', 'ok'], ['data-profile', 'synthetic-separate-result-grammar']];
	for (let length = 0; length < events.length; length++) {
		const f = fixture(); for (const [kind, value] of events.slice(0, length)) assert.equal(f.send(kind, value), true);
		assert.equal(f.finish().synthetic_match, false, `prefix ${length}`);
	}
	const f = fixture(); selected(f); assert.equal(f.send('schema-result', 'ok'), false); assert.equal(f.finish().synthetic_match, false);
});

test('private context cannot be rebound to a replacement owner or renewed epoch', () => {
	const f = fixture(); assert.throws(() => createWpdbSetupData({ ...f.config, startedNs: 500n }), TypeError);
	for (const key of keys) {
		const other = fixture(); const mixed = { ...other.config, [key]: f.identity[key] };
		assert.throws(() => createWpdbSetupData(mixed), TypeError);
	}
});
for (const key of keys) test(`foreign ${key} poisons original owner without replacement context`, () => {
	const f = fixture(); const foreign = { ...f.identity, [key]: Symbol(key) };
	assert.equal(f.owner.accept({ kind: 'connect', value: 'fresh-nonpersistent-no-database' }, foreign, 101n), false);
	assert.equal(f.send('connect', 'fresh-nonpersistent-no-database'), false); assert.equal(f.finish().synthetic_match, false);
});
for (const now of [99n, 20000000100n, 20000000101n, 101, null]) test(`original half-open epoch refuses ${String(now)}`, () => {
	const f = fixture(); assert.equal(f.send('connect', 'fresh-nonpersistent-no-database', now), false); assert.equal(f.finish().refused, true);
});
test('last tick before expiry remains valid, but regression and expired finish refuse', () => {
	const edge = fixture(); selected(edge); assert.equal(edge.finish(20000000099n).synthetic_match, true);
	const expired = fixture(); selected(expired); assert.equal(expired.finish(20000000100n).synthetic_match, false);
	const f = fixture(); f.send('connect', 'fresh-nonpersistent-no-database', 300n);
	assert.equal(f.send('auth-result', 'synthetic-ok', 299n), false);
});
test('input snapshots never invoke getters/proxy traps and reject extra/prototype fields', () => {
	let calls = 0;
	const f = fixture(); const getter = { kind: 'connect', get value() { calls++; throw Error('must not read'); } };
	assert.equal(f.owner.accept(getter, f.identity, 101n), false); assert.equal(calls, 0);
	for (const event of [new Proxy({}, { ownKeys() { calls++; throw Error('trap'); } }),
		Object.assign(Object.create(null), { kind: 'connect', value: 'fresh-nonpersistent-no-database' }),
		{ kind: 'connect', value: 'fresh-nonpersistent-no-database', [Symbol()]: true }]) {
		const other = fixture(); assert.equal(other.owner.accept(event, other.identity, 101n), false);
	}
	assert.equal(calls, 0);
	const other = fixture(); const identity = { ...other.identity }; Object.defineProperty(identity, 'attempt', { get() { calls++; } });
	assert.equal(other.owner.accept({ kind: 'connect', value: 'fresh-nonpersistent-no-database' }, identity, 101n), false); assert.equal(calls, 0);
});
test('configuration snapshot is detached; malformed/untrusted configuration never constructs', () => {
	const f = fixture(); f.config.startedNs = 999n; f.config.attempt = Symbol(); selected(f); assert.equal(f.finish().synthetic_match, true);
	for (const patch of [{ startedNs: -1n }, { startedNs: 100 }, { profile: 'old' }, { schema: 'foreign' },
		{ attempt: Symbol.for('registered') }, { attempt: 'label' }]) {
		const own = Object.fromEntries(keys.map((k) => [k, Symbol(k)]));
		assert.throws(() => createWpdbSetupData({ ...own, startedNs: 100n, profile, schema: 'owned-synthetic', ...patch }), TypeError);
	}
});
test('finish is one-shot and no accepted event can revive a closed owner', () => {
	const f = fixture(); selected(f); assert.equal(f.finish().synthetic_match, true);
	assert.equal(f.finish(), false); assert.equal(f.send('connect', 'fresh-nonpersistent-no-database'), false);
});
