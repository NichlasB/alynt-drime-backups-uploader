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
