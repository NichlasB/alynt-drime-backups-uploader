import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { parseQualificationPrelude, parseQualificationLine, qualificationPreludeCases } from '../../scripts/tests/qualification-native-grammar.mjs';
import { qualificationIdentities } from '../../scripts/tests/qualification-native-identity.mjs';
// Synthetic receipt/source contracts only. No WordPress, sockets, fixtures or process starts.
const positive = { passed: true, checks: 266, cases: [...qualificationPreludeCases], auth_thread: 123, protected_option_api_reads: 0, public_storage_dispatch_execution: false, browser_headers_emitted: false, qualification_attempted: false };
test('independent prelude positive requires every genuine guard group and cannot be a qualification FINAL', () => {
	const line = JSON.stringify(positive); assert.deepEqual(parseQualificationPrelude(line), positive);
	assert.equal(parseQualificationLine(line, qualificationIdentities()), false);
	for (let n = 0; n < positive.cases.length; n++) {
		const missing = positive.cases.filter((_, at) => at !== n), changed = [...positive.cases]; changed[n] = 'unavailable';
		for (const cases of [missing, changed, [...positive.cases, positive.cases[n]]]) assert.equal(parseQualificationPrelude(JSON.stringify({ ...positive, cases })), false);
	}
	assert.equal(parseQualificationPrelude(JSON.stringify({ ...positive, cases: [...positive.cases].reverse() })), false);
});
test('prelude rejects missing, extra, stale-shape, authority and malformed output rather than normalizing it', () => {
	for (const key of Object.keys(positive)) {
		const missing = { ...positive }; delete missing[key];
		assert.equal(parseQualificationPrelude(JSON.stringify(missing)), false, key);
		assert.equal(parseQualificationPrelude(JSON.stringify({ ...positive, [key]: null })), false, key);
	}
	for (const value of [0, -1, 1.5, '266', 10001]) assert.equal(parseQualificationPrelude(JSON.stringify({ ...positive, checks: value })), false);
	for (const value of [0, -1, 4294967296, '123']) assert.equal(parseQualificationPrelude(JSON.stringify({ ...positive, auth_thread: value })), false);
	for (const key of ['public_storage_dispatch_execution', 'browser_headers_emitted', 'qualification_attempted']) assert.equal(parseQualificationPrelude(JSON.stringify({ ...positive, [key]: true })), false);
	for (const line of ['', null, 'x'.repeat(4096), JSON.stringify(positive) + '\n', JSON.stringify(positive) + '{}', JSON.stringify({ ...positive, source: 'trusted' })]) assert.equal(parseQualificationPrelude(line), false);
});
test('failed prelude may supply only bounded private cleanup correlation, never success', () => {
	const failed = { passed: false, auth_thread: 123 };
	assert.deepEqual(parseQualificationPrelude(JSON.stringify(failed)), failed);
	assert.equal(parseQualificationLine(JSON.stringify(failed), qualificationIdentities()), false);
	for (const value of [{ ...failed, passed: true }, { ...failed, auth_thread: null }, { ...failed, raw_error: 'private' }, { ...failed, qualification_attempted: true }]) assert.equal(parseQualificationPrelude(JSON.stringify(value)), false);
});
test('fixed source wiring requires independent prelude before any qualification relay and preserves root controls', () => {
	const source = (file) => readFileSync(new URL('../../scripts/tests/' + file, import.meta.url), 'utf8');
	const supervisor = source('qualification-native-proof.mjs'), worker = source('qualification-native-worker.php'), prelude = source('qualification-native-prelude.php');
	assert.equal((supervisor.match(/await guardPrelude\(\);/g) ?? []).length, 1);
	assert.ok(supervisor.indexOf('await guardPrelude();') < supervisor.indexOf("initial = await fixture('create')"));
	assert.ok(supervisor.indexOf('await guardPrelude();') < supervisor.indexOf('await openQualificationRelay('));
	assert.match(supervisor, /result\.passed = result\.guard_prelude\?\.passed === true && result\.native_passed === true/);
	assert.match(supervisor, /child\.join\(10000\)/); assert.match(supervisor, /worker\.join\(\)/); // Separate prerequisite bound, original native bound unchanged.
	for (const call of ['alynt_core_cases();', 'alynt_core_denials();']) { assert.ok(prelude.includes(call)); assert.ok(!worker.includes(call)); }
	assert.ok(worker.includes('alynt_qualification_cases();')); // Own fresh genuine denials + the unchanged retained-root entry remain.
	assert.ok(!prelude.includes('alynt_qualification_cases();')); assert.ok(!prelude.includes('qualification-native-channel.php'));
	assert.match(prelude, /\$qualification_bootstrap_port = 10004;/); assert.match(worker, /\$qualification_bootstrap_port = \(int\) \$argv\[5\];/);
	assert.match(source('qualification-native-relay.mjs'), /\+\+row\.frames > 4096/);
	assert.match(source('qualification-native-control.mjs'), /bindAt - rootAt > 4000000000n/);
	assert.match(source('qualification-native-control.mjs'), /process\.hrtime\.bigint\(\) - rootAt < 10000000000n/);
});
