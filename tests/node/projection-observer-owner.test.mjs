import assert from 'node:assert/strict';
import test from 'node:test';
import { createProjectionObserverOwner } from '../../scripts/tests/projection-observer-owner.mjs';
import { config, fixture, packets, identityKeys } from './support/projection-observer-fixtures.mjs';

function result(receipt, match = false) {
	assert.equal(receipt.synthetic_match, match); assert.equal(receipt.synthetic_only, true); assert.equal(Object.isFrozen(receipt), true);
	for (const [key, value] of Object.entries(receipt)) if (!['synthetic_match', 'synthetic_only'].includes(key)) assert.equal(value, false, key);
}
test('fixed owner composes the actual decoder with detached bytes and opaque credits', () => {
	const f = fixture();
	for (let i = 0; i < 5; i++) {
		const input = packets()[i], before = Buffer.from(input), credit = f.capture(i, input);
		assert.equal(typeof credit, 'symbol'); assert.equal(Symbol.keyFor(credit), undefined); assert.deepEqual(input, before);
		input.fill(0); assert.equal(f.owner.observe(credit, f.identity, 101n), true);
	}
	result(f.finish(), true); assert.equal(f.finish(), false); assert.equal(f.send(0), false);
	assert.equal(Object.isFrozen(f.owner), true); assert.deepEqual(Object.keys(f.owner), ['capture', 'observe', 'finish']);
});
test('fragmented copied bytes remain ordered and preserve the selected exchange', () => {
	const f = fixture(); for (const [i, packet] of packets().entries()) for (const byte of packet) assert.equal(f.send(i, Buffer.from([byte])), true);
	result(f.finish(), true);
});
test('extra/foreign/reused/out-of-order credits poison and never become result credit', () => {
	for (const mode of ['foreign', 'replay', 'reorder']) {
		const f = fixture(), first = f.capture(0);
		if (mode === 'replay') assert.equal(f.owner.observe(first, f.identity, 101n), true);
		const credit = mode === 'foreign' ? Symbol('foreign') : mode === 'reorder' ? f.capture(1) : first;
		assert.equal(f.owner.observe(credit, f.identity, 101n), false); result(f.finish()); assert.equal(f.send(0), false);
	}
});
test('pending data, malformed result and incomplete transcript cannot finish as matched', () => {
	const f = fixture(); f.capture(0); result(f.finish());
	const g = fixture(); assert.equal(g.send(0), true); result(g.finish());
	const h = fixture(); assert.equal(h.send(0, Buffer.from([0, 0, 0, 0])), false); result(h.finish());
});
test('bad input/getters/proxies/shared bytes refuse without invoking caller code', () => {
	for (const input of [Buffer.alloc(0), Buffer.alloc(16385), new Uint8Array(2), Buffer.from(new SharedArrayBuffer(4)), null]) {
		const f = fixture(); assert.equal(f.capture(0, input), false); result(f.finish());
	}
	for (const key of ['length', 'buffer']) {
		const f = fixture(), b = packets()[0]; let calls = 0;
		Object.defineProperty(b, key, { get() { calls++; throw Error('must not execute'); } });
		assert.equal(f.capture(0, b), false); assert.equal(calls, 0); result(f.finish());
	}
	const c = config(); Object.defineProperty(c, 'pair', { get() { throw Error('must not execute'); } });
	assert.throws(() => createProjectionObserverOwner(c), TypeError);
});
for (const key of identityKeys) test(`foreign ${key} before copying permanently refuses`, () => {
	const f = fixture(); assert.equal(f.owner.capture('client', packets()[0], { ...f.identity, [key]: Symbol() }, 101n), false); result(f.finish());
});
test('original clock and cumulative copy ceilings are not refreshed by disposal', () => {
	const f = fixture(); assert.equal(f.capture(0, undefined, 20000000100n), false); result(f.finish(20000000100n));
	const g = fixture(); const token = g.capture(0, undefined, 200n); assert.equal(g.owner.observe(token, g.identity, 199n), false); result(g.finish(200n));
	const h = fixture(); assert.equal(typeof h.capture(0, Buffer.alloc(16384)), 'symbol'); assert.equal(h.capture(0, Buffer.from([1])), false); result(h.finish());
});
