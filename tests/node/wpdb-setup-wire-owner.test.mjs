import assert from 'node:assert/strict';
import test from 'node:test';
import { createWpdbSetupWireOwner, WPDB_SETUP_WIRE_PROFILE as profile } from '../../scripts/tests/wpdb-setup-wire.mjs';
import { packet, exchange } from './support/wpdb-setup-wire-fixtures.mjs';

const keys = ['attempt', 'pair', 'generation', 'request', 'session', 'command'];
function fixture(kind = 'mode-read', value = '', factory = createWpdbSetupWireOwner) {
	const identity = Object.fromEntries(keys.map((key) => [key, Symbol(key)]));
	const config = { ...identity, profile, kind, startedNs: 100n }, owner = factory(config), f = exchange(kind, value);
	const frames = [f.request, ...f.responses];
	return { identity, config, owner, frames, send: (index, frame = frames[index], now = 101n) => owner.capture(index ? 'server' : 'client', frame, identity, now),
		finish: (now = 102n) => owner.finish(identity, now) };
}
for (const [kind, value, expected] of [['mode-read', '', 'one-empty-string'], ['mode-read', 'STRICT_TRANS_TABLES', 'one-filterable-nonempty'],
	['schema-select', '', 'schema-selected']]) test(`owned ${kind}/${value} completes and releases snapshots only`, () => {
	const f = fixture(kind, value); let total = 0;
	for (const [index, frame] of f.frames.entries()) { total += frame.length; assert.equal(f.send(index), true); frame.fill(0); }
	const result = f.finish(); assert.equal(result.synthetic_match, true); assert.equal(result.outcome, expected);
	assert.deepEqual(result.capture_accounting, { copied_bytes: total, released_bytes: total, pending_bytes: 0, peak_bytes: total });
	for (const [key, value] of Object.entries(result)) if (key.endsWith('_proven') || key.endsWith('_supported')) assert.equal(value, false, key);
	assert.equal(f.finish(), false); assert.equal(f.send(0), false);
});
for (const key of keys) test(`foreign ${key} refuses before byte reads and releases previous snapshots`, () => {
	const f = fixture(); assert.equal(f.send(0), true); let calls = 0;
	const poison = new Proxy(Buffer.alloc(1), { get() { calls++; throw Error('protected bytes'); } });
	assert.equal(f.owner.capture('server', poison, { ...f.identity, [key]: Symbol() }, 101n), false);
	assert.equal(calls, 0); assert.equal(f.send(1), false);
	const result = f.finish(); assert.equal(result.synthetic_match, false); assert.equal(result.refused, true);
	assert.equal(result.capture_accounting.pending_bytes, 0); assert.equal(result.capture_accounting.copied_bytes, result.capture_accounting.released_bytes);
});
test('original half-open deadline, monotonic ticks and one-shot identities do not renew', () => {
	for (const now of [99n, 20000000100n, 101, null]) {
		const f = fixture(); assert.equal(f.send(0, f.frames[0], now), false); assert.equal(f.finish().synthetic_match, false);
	}
	const f = fixture(); assert.equal(f.send(0, f.frames[0], 200n), true); assert.equal(f.send(1, f.frames[1], 199n), false);
	const expired = fixture(); for (const [i] of expired.frames.entries()) assert.equal(expired.send(i), true);
	assert.equal(expired.finish(20000000100n).synthetic_match, false);
	const edge = fixture(); for (const [i] of edge.frames.entries()) assert.equal(edge.send(i), true);
	assert.equal(edge.finish(20000000099n).synthetic_match, true);
	assert.throws(() => createWpdbSetupWireOwner({ ...edge.config, startedNs: 999n }), TypeError);
});
test('direction, duplicate, incomplete and post-match extra packet cannot succeed', () => {
	const wrong = fixture(); assert.equal(wrong.owner.capture('server', wrong.frames[0], wrong.identity, 101n), false);
	const duplicate = fixture(); duplicate.send(0); assert.equal(duplicate.send(0), false);
	for (let length = 0; length < 6; length++) {
		const f = fixture(); for (let i = 0; i < length; i++) assert.equal(f.send(i), true);
		assert.equal(f.finish().synthetic_match, false);
	}
	const f = fixture(); for (const [i] of f.frames.entries()) assert.equal(f.send(i), true);
	assert.equal(f.send(6, packet([1], 6)), false); assert.equal(f.finish().synthetic_match, false);
});
test('cumulative copied-byte ceiling refuses and clears bounded frames, without new credit after refusal', () => {
	const f = fixture(); const body = Buffer.alloc(4096);
	for (let i = 0; i < 3; i++) assert.equal(f.send(i, packet(body, i)), true);
	assert.equal(f.send(3, packet(body, 3)), false); assert.equal(f.send(0), false);
	assert.deepEqual(f.finish().capture_accounting, { copied_bytes: 12300, released_bytes: 12300, pending_bytes: 0, peak_bytes: 12300 });
});
test('unsupported profiles/kinds, getters, registered/duplicate identities never construct', () => {
	let calls = 0; const make = (patch) => ({ ...Object.fromEntries(keys.map((key) => [key, Symbol()])), profile, kind: 'mode-read', startedNs: 100n, ...patch });
	for (const patch of [{ profile: 'wpdb-setup-wire.native.v1' }, { kind: 'mode-write' }, { startedNs: -1n }, { attempt: Symbol.for('x') }]) {
		assert.throws(() => createWpdbSetupWireOwner(make(patch)), TypeError);
	}
	const getter = make({}); Object.defineProperty(getter, 'profile', { get() { calls++; } });
	assert.throws(() => createWpdbSetupWireOwner(getter), TypeError); assert.equal(calls, 0);
	const same = Symbol(); assert.throws(() => createWpdbSetupWireOwner(make({ attempt: same, pair: same })), TypeError);
});
