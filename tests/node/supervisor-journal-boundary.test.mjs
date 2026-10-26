import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync, readdirSync } from 'node:fs';
import { fixture, run } from './support/supervisor-journal-fixtures.mjs';
const root = new URL('../../', import.meta.url);
test('DATA helper has only util import and no OS/IO/native adapter', () => {
	const source = readFileSync(new URL('scripts/tests/supervisor-journal-data.mjs', root), 'utf8');
	assert.deepEqual([...source.matchAll(/from '([^']+)'/g)].map((m) => m[1]), ['node:util']);
	const active = /\b(?:import\s*\(|require\s*\(|eval\s*\(|fetch\s*\(|process\s*\.|child_process\b|createServer\b|createConnection\b)/;
	assert.equal(active.test(source), false);
	for (const value of ["import('native')", 'process.argv', 'fetch(url)', 'createConnection()']) assert.equal(active.test(value), true);
});
test('runtime roots contain no supervisor DATA import/hook/entry point', () => {
	const files = ['alynt-drime-backups-uploader.php', 'uninstall.php'];
	function collect(path) {
		for (const e of readdirSync(new URL(path + '/', root), { withFileTypes: true })) {
			if (e.isDirectory()) collect(path + '/' + e.name);
			else if (e.isFile() && /\.(?:php|mjs|js)$/.test(e.name)) files.push(path + '/' + e.name);
		}
	}
	for (const path of ['includes', 'server-runner/src', 'assets']) collect(path);
	assert.ok(files.length > 200 && files.length < 1500);
	for (const file of files) assert.equal(/supervisor-journal-data|createSupervisorJournalData|parseSupervisorIntentData|SUPERVISOR_DATA_PROFILE/.test(readFileSync(new URL(file, root), 'utf8')), false, file);
});
test('positive receipt and owner expose no symbols, buffers, handles or arbitrary callback', () => {
	const f = fixture(); assert.deepEqual(Object.keys(f.owner).sort(), ['accept', 'finish']); assert.equal(Object.isFrozen(f.owner), true);
	assert.ok(run(f).every(Boolean)); const receipt = f.finish(); assert.equal(receipt.proof_model_match, true);
	assert.equal(Object.isFrozen(receipt), true);
	assert.equal(Object.getOwnPropertySymbols(receipt).length, 0);
	for (const value of Object.values(receipt)) assert.ok(['string', 'number', 'boolean'].includes(typeof value));
	assert.equal(JSON.stringify(receipt).includes('fixed-supervisor.fixture.v1'), false);
	assert.equal(JSON.stringify(receipt).includes('password'), false);
	for (const [key, value] of Object.entries(receipt)) if (key.endsWith('_supported') || key.endsWith('_proven')) assert.equal(value, false, key);
});
