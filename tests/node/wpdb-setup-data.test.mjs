import assert from 'node:assert/strict';
import test from 'node:test';
import { WPDB_SETUP_DATA_PROFILE as profile, wpdbSetupCatalog } from '../../scripts/tests/wpdb-setup-data.mjs';

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
