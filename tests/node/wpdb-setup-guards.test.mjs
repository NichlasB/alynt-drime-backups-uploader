import assert from 'node:assert/strict';
import { AssertionError } from 'node:assert';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createWpdbSetupData, WPDB_SETUP_DATA_PROFILE as profile } from '../../scripts/tests/wpdb-setup-data.mjs';

const source = readFileSync(new URL('../../scripts/tests/wpdb-setup-data.mjs', import.meta.url), 'utf8');
const keys = ['attempt', 'pair', 'generation', 'request', 'session'];
const trace = [['connect', 'fresh-nonpersistent-no-database'], ['auth-result', 'synthetic-ok'], ['charset-api', 'ok-zero-wire'],
	['mode-read-result', 'one-filterable-nonempty'], ['mode-write-result', 'ok-fixed-filtered'], ['schema-intent', 'owned-synthetic'],
	['schema-result', 'ok'], ['data-profile', 'synthetic-separate-result-grammar'], ...
	['natural-shutdown', 'engine-complete', 'process-exit-zero', 'client-eof', 'server-eof'].map((value) => ['terminal', value])];
function fixture(factory) {
	const identity = Object.fromEntries(keys.map((key) => [key, Symbol(key)]));
	const config = { ...identity, startedNs: 100n, profile, schema: 'owned-synthetic' };
	const owner = factory(config);
	return { owner, config, identity, send: ([kind, value], now = 101n) => owner.accept({ kind, value }, identity, now) };
}
const controls = [
	['source profile', 'config.profile !== WPDB_SETUP_DATA_PROFILE', 'false', (factory) => {
		const f = fixture(factory); const fresh = Object.fromEntries(keys.map((k) => [k, Symbol(k)]));
		assert.throws(() => factory({ ...f.config, ...fresh, profile: 'old-profile' }), TypeError);
	}],
	['mode branch', "value === 'one-filterable-nonempty') stage = 'mode-write'", "value === 'one-filterable-nonempty') stage = 'select-schema'", (factory) => {
		const f = fixture(factory); for (const event of trace) assert.equal(f.send(event), true);
		assert.equal(f.owner.finish(f.identity, 102n).synthetic_match, true);
	}],
	['order', "stage === 'fresh' && kind === 'connect'", "['fresh', 'authentication'].includes(stage) && kind === 'connect'", (factory) => {
		const f = fixture(factory); assert.equal(f.send(trace[0]), true); assert.equal(f.send(trace[0]), false);
	}],
	['schema result', "stage === 'schema-result' && kind === 'schema-result' && value === 'ok'", "stage === 'schema-result' && kind === 'schema-result'", (factory) => {
		const f = fixture(factory); for (const event of trace.slice(0, 6)) assert.equal(f.send(event), true);
		assert.equal(f.send(['schema-result', 'error']), false);
	}],
	['epoch', 'now >= config.startedNs + 20000000000n', 'false', (factory) => {
		const f = fixture(factory); assert.equal(f.send(trace[0], 20000000100n), false);
	}],
	['sticky refusal', 'if (closed || refused) return false;', 'if (closed) return false;', (factory) => {
		const f = fixture(factory); assert.equal(f.send(['unknown', 'unknown']), false); assert.equal(f.send(trace[0]), false);
	}],
	['context', '!identityKeys.every((k) => fields[k] === config[k])', 'false', (factory) => {
		const f = fixture(factory); assert.equal(f.owner.accept({ kind: trace[0][0], value: trace[0][1] }, { ...f.identity, pair: Symbol() }, 101n), false);
	}],
	['terminal closure', "stage === 'complete', synthetic_only", "stage === 'data-admitted', synthetic_only", (factory) => {
		const f = fixture(factory); for (const event of trace.slice(0, 8)) assert.equal(f.send(event), true);
		assert.equal(f.owner.finish(f.identity, 102n).synthetic_match, false);
	}],
	['identity reuse', ' && !consumedIdentities.has(config[k])', '', (factory) => {
		const f = fixture(factory); assert.throws(() => factory({ ...f.config, startedNs: 1000n }), TypeError);
	}],
];
for (const [name, before, after, exercise] of controls) test(`assertion-killed source control: ${name}`, async () => {
	exercise(createWpdbSetupData);
	assert.equal(source.split(before).length - 1, 1, 'mutation anchor must be unique');
	const mutant = await import(`data:text/javascript;base64,${Buffer.from(source.replace(before, after) + `\n// ${name}`).toString('base64')}`);
	assert.throws(() => exercise(mutant.createWpdbSetupData), AssertionError, 'must be killed by an assertion, not syntax/import failure');
});

test('every required event removal and adjacent swap fails a nonempty positive trace', () => {
	for (let index = 0; index < trace.length; index++) {
		const f = fixture(createWpdbSetupData); const omitted = trace.filter((_, i) => i !== index);
		for (const event of omitted) f.send(event);
		assert.equal(f.owner.finish(f.identity, 102n).synthetic_match, false, `omitted ${index}`);
	}
	for (let index = 0; index < 7; index++) {
		const f = fixture(createWpdbSetupData); const swapped = trace.map((event) => [...event]);
		[swapped[index], swapped[index + 1]] = [swapped[index + 1], swapped[index]];
		for (const event of swapped) f.send(event);
		assert.equal(f.owner.finish(f.identity, 102n).synthetic_match, false, `swapped ${index}`);
	}
});
