import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync, readdirSync } from 'node:fs';
import { createWpdbSetupData, WPDB_SETUP_DATA_PROFILE as profile } from '../../scripts/tests/wpdb-setup-data.mjs';

const root = new URL('../../', import.meta.url);
test('setup DATA module has only fixed socket-free dependency and no driver/observer bridge', () => {
	const source = readFileSync(new URL('scripts/tests/wpdb-setup-data.mjs', root), 'utf8');
	assert.deepEqual([...source.matchAll(/from '([^']+)'/g)].map((m) => m[1]), ['node:util']);
	const active = /\b(?:import\s*\(|eval\s*\(|require\s*\(|fetch\s*\(|process\.|child_process\b|createServer\b|createConnection\b|projectionSessionProfile\b|createProjectionObserverOwner\b)/;
	assert.equal(active.test(source), false);
	for (const candidate of ["import('foreign')", 'process.argv', 'fetch(url)', 'createProjectionObserverOwner(c)']) assert.equal(active.test(candidate), true);
});
test('static source scan finds no setup DATA hook in runtime PHP/runner entry points', () => {
	const files = ['alynt-drime-backups-uploader.php', 'uninstall.php'];
	function collect(path) {
		for (const entry of readdirSync(new URL(path + '/', root), { withFileTypes: true })) {
			if (entry.isDirectory()) collect(path + '/' + entry.name);
			else if (entry.isFile() && /\.(?:php|mjs|js)$/.test(entry.name)) files.push(path + '/' + entry.name);
		}
	}
	collect('includes'); collect('server-runner/src');
	assert.ok(files.length > 200 && files.length < 1000);
	for (const file of files) assert.equal(/wpdb-setup-data|createWpdbSetupData|wpdbSetupCatalog|WPDB_SETUP_DATA_PROFILE/.test(readFileSync(new URL(file, root), 'utf8')), false, file);
});
test('successful receipt exposes only frozen primitive model facts, never write-capable handles or true physical claims', () => {
	const identity = Object.fromEntries(['attempt', 'pair', 'generation', 'request', 'session'].map((key) => [key, Symbol()]));
	const owner = createWpdbSetupData({ ...identity, startedNs: 0n, profile, schema: 'owned-synthetic' });
	assert.deepEqual(Object.keys(owner).sort(), ['accept', 'finish']); assert.equal(Object.isFrozen(owner), true);
	const events = [['connect', 'fresh-nonpersistent-no-database'], ['auth-result', 'synthetic-ok'], ['charset-api', 'ok-one-wire'],
		['charset-statement-result', 'ok'], ['mode-read-result', 'one-filterable-nonempty'], ['mode-write-result', 'ok-fixed-filtered'],
		['schema-intent', 'owned-synthetic'], ['schema-result', 'ok'], ['data-profile', 'synthetic-separate-result-grammar'], ...
		['natural-shutdown', 'engine-complete', 'process-exit-zero', 'client-eof', 'server-eof'].map((value) => ['terminal', value])];
	for (const [kind, value] of events) assert.equal(owner.accept({ kind, value }, identity, 1n), true);
	const result = owner.finish(identity, 2n);
	assert.deepEqual(Object.keys(result).sort(), ['synthetic_match', 'synthetic_only', 'stage', 'refused', 'model_accounting',
		'source_proven', 'authentication_proven', 'native_proven', 'forwarding_proven', 'backing_absence_proven',
		'resource_closure_proven', 'storage_supported', 'wiring_supported', 'execution_supported'].sort());
	assert.equal(result.synthetic_match, true); assert.equal(result.stage, 'complete'); assert.equal(result.refused, false);
	for (const [key, value] of Object.entries(result)) {
		if (key === 'model_accounting') { assert.equal(Object.isFrozen(value), true); assert.ok(Object.values(value).every(Number.isSafeInteger)); }
		else if (key !== 'stage') assert.equal(value, ['synthetic_match', 'synthetic_only'].includes(key), key);
	}
	assert.equal(Object.isFrozen(result), true); assert.equal(JSON.stringify(result).includes('owned-synthetic'), false);
});
