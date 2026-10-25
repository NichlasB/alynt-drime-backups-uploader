import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync, readdirSync } from 'node:fs';
import { fixture, packets } from './support/projection-observer-fixtures.mjs';

const root = new URL('../../', import.meta.url);
test('observer dependencies remain fixed and socket-free; no arbitrary provider/transport import', () => {
	for (const [file, expected] of [
		['projection-observer-owner.mjs', ['node:util', './projection-zero-row-decoder.mjs', './projection-session-profile.mjs']],
		['projection-session-profile.mjs', ['node:util']],
	]) {
		const source = readFileSync(new URL(`scripts/tests/${file}`, root), 'utf8');
		assert.deepEqual([...source.matchAll(/from '([^']+)'/g)].map((m) => m[1]), expected);
		const active = /\b(?:import\s*\(|eval\s*\(|require\s*\(|fetch\s*\(|process\.|child_process\b|createServer\b|createConnection\b)/;
		assert.equal(active.test(source), false);
		for (const control of ["import('foreign')", 'fetch(url)', 'process.argv']) assert.equal(active.test(control), true);
	}
});
test('static runtime source guard finds no observer wiring; not an installed runtime proof', () => {
	const files = ['alynt-drime-backups-uploader.php', 'uninstall.php'];
	function collect(path) {
		for (const entry of readdirSync(new URL(path + '/', root), { withFileTypes: true })) {
			if (entry.isDirectory()) collect(path + '/' + entry.name);
			else if (entry.isFile() && /\.(?:php|mjs|js)$/.test(entry.name)) files.push(path + '/' + entry.name);
		}
	}
	collect('includes'); collect('server-runner/src');
	assert.ok(files.length > 200 && files.length < 1000);
	for (const file of files) assert.equal(/projection-observer-owner|projection-session-profile|createProjectionObserverOwner/.test(readFileSync(new URL(file, root), 'utf8')), false, file);
});
test('positive receipt never promotes synthetic session/credit/EOF/accounting to physical authority', () => {
	const f = fixture(); for (const [index] of packets().entries()) assert.equal(f.send(index), true);
	const result = f.finish(); assert.equal(result.synthetic_match, true);
	assert.deepEqual(Object.keys(result).sort(), ['synthetic_match', 'synthetic_only', 'authentication_proven', 'native_proven',
		'forwarding_proven', 'delivery_proven', 'backing_absence_proven', 'resource_closure_proven', 'storage_supported',
		'wiring_supported', 'execution_supported', 'capture_accounting'].sort());
	for (const [key, value] of Object.entries(result)) {
		if (key === 'capture_accounting') { assert.equal(Object.isFrozen(value), true); assert.ok(Object.values(value).every(Number.isSafeInteger)); }
		else assert.equal(value, ['synthetic_match', 'synthetic_only'].includes(key), key);
	}
	assert.equal(Object.isFrozen(result), true); assert.equal(JSON.stringify(result).includes('012345abcdef'), false);
});
