import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync, readdirSync } from 'node:fs';
import { createWpdbSetupWireOwner, WPDB_SETUP_WIRE_PROFILE as profile } from '../../scripts/tests/wpdb-setup-wire.mjs';
import { exchange, packet } from './support/wpdb-setup-wire-fixtures.mjs';

const root = new URL('../../', import.meta.url);
function context() {
	const identity = Object.fromEntries(['attempt', 'pair', 'generation', 'request', 'session', 'command'].map((key) => [key, Symbol()]));
	return { identity, config: { ...identity, profile, kind: 'schema-select', startedNs: 0n } };
}
test('synthetic wire module imports only fixed byte support, never driver/old observer/DATA execution', () => {
	const source = readFileSync(new URL('scripts/tests/wpdb-setup-wire.mjs', root), 'utf8');
	assert.deepEqual([...source.matchAll(/from '([^']+)'/g)].map((m) => m[1]), ['node:util']);
	const active = /\b(?:import\s*\(|eval\s*\(|require\s*\(|fetch\s*\(|process\.|child_process\b|createServer\b|createConnection\b|createProjectionObserverOwner\b|createWpdbSetupData\b)/;
	assert.equal(active.test(source), false);
	for (const value of ["import('foreign')", 'fetch(url)', 'process.argv', 'createWpdbSetupData(c)']) assert.equal(active.test(value), true);
});
test('static runtime source scan has no setup wire import, route or owner hook', () => {
	const files = ['alynt-drime-backups-uploader.php', 'uninstall.php'];
	function collect(path) {
		for (const entry of readdirSync(new URL(path + '/', root), { withFileTypes: true })) {
			if (entry.isDirectory()) collect(path + '/' + entry.name);
			else if (entry.isFile() && /\.(?:php|mjs|js)$/.test(entry.name)) files.push(path + '/' + entry.name);
		}
	}
	collect('includes'); collect('server-runner/src'); assert.ok(files.length > 200 && files.length < 1000);
	for (const file of files) assert.equal(/wpdb-setup-wire|createWpdbSetupWireOwner|decodeWpdbSetupExchange|WPDB_SETUP_WIRE_PROFILE/.test(readFileSync(new URL(file, root), 'utf8')), false, file);
});
test('successful frozen receipt exposes no buffers/handles or physical/native authority', () => {
	const { identity, config } = context(), owner = createWpdbSetupWireOwner(config), f = exchange('schema-select');
	assert.deepEqual(Object.keys(owner).sort(), ['capture', 'finish']); assert.equal(Object.isFrozen(owner), true);
	assert.equal(owner.capture('client', f.request, identity, 1n), true);
	assert.equal(owner.capture('server', f.responses[0], identity, 1n), true);
	const result = owner.finish(identity, 2n); assert.equal(result.synthetic_match, true); assert.equal(result.outcome, 'schema-selected');
	assert.deepEqual(Object.keys(result).sort(), ['synthetic_match', 'synthetic_only', 'outcome', 'refused', 'profile', 'source_proven',
		'authentication_proven', 'native_proven', 'forwarding_proven', 'backing_absence_proven', 'resource_closure_proven',
		'storage_supported', 'wiring_supported', 'execution_supported', 'capture_accounting'].sort());
	for (const [key, value] of Object.entries(result)) {
		if (key === 'capture_accounting') { assert.equal(Object.isFrozen(value), true); assert.ok(Object.values(value).every(Number.isSafeInteger)); }
		else if (!['outcome', 'profile'].includes(key)) assert.equal(value, ['synthetic_match', 'synthetic_only'].includes(key), key);
	}
	assert.equal(Object.isFrozen(result), true); assert.equal(JSON.stringify(result).includes('owned_synthetic'), false);
});
test('guessed capability masks/old profile/handshake cannot launder native compatibility', () => {
	for (const patch of [{ capabilities: 0x88209 }, { profile: 'projection-text41-ordinary-eof-v1' }, { profile: 'wpdb-postconnect-setup.data.v1' }]) {
		const { config } = context(); assert.throws(() => createWpdbSetupWireOwner({ ...config, ...patch }), TypeError);
	}
	const { identity, config } = context(), owner = createWpdbSetupWireOwner(config), f = exchange('schema-select');
	assert.equal(owner.capture('client', packet(Buffer.alloc(32), 0), identity, 1n), true);
	assert.equal(owner.capture('server', f.responses[0], identity, 1n), true);
	assert.equal(owner.finish(identity, 2n).synthetic_match, false);
});
