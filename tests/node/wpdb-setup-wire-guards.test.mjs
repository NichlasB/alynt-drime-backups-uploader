import assert from 'node:assert/strict';
import { AssertionError } from 'node:assert';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import * as actual from '../../scripts/tests/wpdb-setup-wire.mjs';
import { packet, modeFrames, exchange } from './support/wpdb-setup-wire-fixtures.mjs';

const source = readFileSync(new URL('../../scripts/tests/wpdb-setup-wire.mjs', import.meta.url), 'utf8');
function fixture(module) {
	const identity = Object.fromEntries(['attempt', 'pair', 'generation', 'request', 'session', 'command'].map((key) => [key, Symbol()]));
	const owner = module.createWpdbSetupWireOwner({ ...identity, profile: actual.WPDB_SETUP_WIRE_PROFILE, kind: 'mode-read', startedNs: 100n });
	const f = exchange(), frames = [f.request, ...f.responses];
	return { owner, identity, frames, send: (i, frame = frames[i], now = 101n) => owner.capture(i ? 'server' : 'client', frame, identity, now) };
}
const controls = [
	['declared length', 'frame.length === length + 4', 'true', (m) => {
		const frame = packet([1], 1); frame[0] = 2; assert.equal(m.copyWpdbSetupPayload(frame, 1), false);
	}],
	['sequence', 'frame[3] === sequence', 'true', (m) => assert.equal(m.copyWpdbSetupPayload(packet([1], 2), 1), false)],
	['complete OK', 'payload.equals(setupOK)', 'payload[0] === 0', (m) => assert.equal(m.decodeWpdbSetupOK(packet([0, 0, 0, 2, 0, 1, 0], 1)), false)],
	['metadata', '!bodies[1].equals(modeColumn)', 'false', (m) => {
		const frames = modeFrames(); frames[1][4] ^= 1; assert.equal(m.decodeWpdbModeResult(frames), false);
	}],
	['typed row', 'bodies[3].equals(nonemptyMode)', 'true', (m) => {
		const frames = modeFrames(); frames[3] = packet([251], 4); assert.equal(m.decodeWpdbModeResult(frames), false);
	}],
	['terminal EOF', '!bodies[4].equals(ordinaryEOF)', 'false', (m) => {
		const frames = modeFrames(); frames[4] = packet([254, 0, 0, 10, 0], 5); assert.equal(m.decodeWpdbModeResult(frames), false);
	}],
	['request binding', "!payload.equals(kind === 'mode-read' ? modeRequest : schemaRequest)", 'false', (m) => {
		const f = exchange('schema-select'); assert.equal(m.decodeWpdbSetupExchange('schema-select', packet(Buffer.from('\x02foreign'), 0), f.responses), false);
	}],
	['profile', 'config.profile !== WPDB_SETUP_WIRE_PROFILE', 'false', (m) => {
		const f = fixture(m); assert.throws(() => m.createWpdbSetupWireOwner({ ...Object.fromEntries(Object.keys(f.identity).map((key) => [key, Symbol()])),
			profile: 'native-claimed-profile', kind: 'mode-read', startedNs: 100n }), TypeError);
	}],
	['context', '!identities.every((k) => fields[k] === config[k])', 'false', (m) => {
		const f = fixture(m); assert.equal(f.owner.capture('client', f.frames[0], { ...f.identity, command: Symbol() }, 101n), false);
	}],
	['original deadline', 'now >= config.startedNs + 20000000000n', 'false', (m) => {
		const f = fixture(m); assert.equal(f.send(0, f.frames[0], 20000000100n), false);
	}],
	['sticky refusal', 'if (closed || refused) return false;', 'if (closed) return false;', (m) => {
		const f = fixture(m); assert.equal(f.owner.capture('server', f.frames[0], f.identity, 101n), false); assert.equal(f.send(0), false);
	}],
	['cumulative byte ceiling', 'copied + frame.length > 16384', 'false', (m) => {
		const f = fixture(m); for (let i = 0; i < 3; i++) assert.equal(f.send(i, packet(Buffer.alloc(4096), i)), true);
		assert.equal(f.send(3, packet(Buffer.alloc(4096), 3)), false);
	}],
	['snapshot release', 'finally { clear(); closed = true; }', 'finally { closed = true; }', (m) => {
		const f = fixture(m); for (const [i] of f.frames.entries()) assert.equal(f.send(i), true);
		const result = f.owner.finish(f.identity, 102n); assert.equal(result.synthetic_match, true); assert.equal(result.capture_accounting.pending_bytes, 0);
	}],
	['non-vacuous success', 'synthetic_match: outcome !== false', 'synthetic_match: false', (m) => {
		const f = fixture(m); for (const [i] of f.frames.entries()) assert.equal(f.send(i), true);
		assert.equal(f.owner.finish(f.identity, 102n).synthetic_match, true);
	}],
];
for (const [name, before, after, exercise] of controls) test(`assertion-killed wire semantic control: ${name}`, async () => {
	exercise(actual); assert.equal(source.split(before).length - 1, 1, 'unique source anchor required');
	const mutant = await import(`data:text/javascript;base64,${Buffer.from(source.replace(before, after) + `\n// ${name}`).toString('base64')}`);
	assert.throws(() => exercise(mutant), AssertionError, 'assertion failure required, not syntax/import failure');
});
test('every single-byte mutation in a positive mode exchange is rejected', () => {
	const f = exchange('mode-read', 'STRICT_TRANS_TABLES'); const originals = [f.request, ...f.responses];
	assert.equal(actual.decodeWpdbSetupExchange('mode-read', originals[0], originals.slice(1)), 'one-filterable-nonempty');
	for (let index = 0; index < originals.length; index++) for (let byte = 0; byte < originals[index].length; byte++) {
		const frames = originals.map((frame) => Buffer.from(frame)); frames[index][byte] ^= 1;
		assert.equal(actual.decodeWpdbSetupExchange('mode-read', frames[0], frames.slice(1)), false, `${index}/${byte}`);
	}
});
