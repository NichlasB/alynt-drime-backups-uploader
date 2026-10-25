import assert from 'node:assert/strict';
import test from 'node:test';
import { createProjectionObserverOwner } from '../../scripts/tests/projection-observer-owner.mjs';
import { config, fixture, packets, identityKeys, sessionFrames } from './support/projection-observer-fixtures.mjs';
import { projectionSessionProfile } from '../../scripts/tests/projection-session-profile.mjs';

function result(receipt, match = false) {
	assert.equal(receipt.synthetic_match, match); assert.equal(receipt.synthetic_only, true); assert.equal(Object.isFrozen(receipt), true);
	for (const [key, value] of Object.entries(receipt)) if (!['synthetic_match', 'synthetic_only'].includes(key)) assert.equal(value, false, key);
}
test('fixed owner composes the actual decoder with detached bytes and opaque credits', () => {
	const f = fixture();
	for (let i = 0; i < 5; i++) {
		const input = packets()[i], before = Buffer.from(input), credit = f.capture(i, input);
		assert.equal(typeof credit, 'symbol'); assert.equal(Symbol.keyFor(credit), undefined); assert.deepEqual(input, before);
		input.fill(0); assert.equal(f.owner.forwarded(credit, before, f.identity, 101n), true); assert.equal(f.owner.observe(credit, f.identity, 101n), true);
	}
	result(f.finish(), true); assert.equal(f.finish(), false); assert.equal(f.send(0), false);
	assert.equal(Object.isFrozen(f.owner), true); assert.deepEqual(Object.keys(f.owner), ['bindSession', 'capture', 'forwarded', 'observe', 'end', 'finish']);
});
test('fragmented copied bytes remain ordered and preserve the selected exchange', () => {
	const f = fixture(); for (const [i, packet] of packets().entries()) for (const byte of packet) assert.equal(f.send(i, Buffer.from([byte])), true);
	result(f.finish(), true);
});
test('extra/foreign/reused/out-of-order credits poison and never become result credit', () => {
	for (const mode of ['foreign', 'replay', 'reorder']) {
		const f = fixture(), first = f.capture(0);
		if (mode === 'replay') { assert.equal(f.owner.forwarded(first, packets()[0], f.identity, 101n), true); assert.equal(f.owner.observe(first, f.identity, 101n), true); }
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

test('session profile is required once, before capture, with genuine module composition', () => {
	const f = fixture(undefined, false); assert.equal(f.capture(0), false); result(f.finish());
	const g = fixture(); assert.equal(g.owner.bindSession(sessionFrames(), g.identity, 101n), false); result(g.finish());
	assert.equal(projectionSessionProfile(sessionFrames(), '012345abcdef'), 103);
});
for (const kind of ['root', 'schema', 'flags', 'plugin', 'max-packet', 'thread', 'server-flags', 'error', 'sequence', 'extra', 'truncated']) test(`session ${kind} refuses before selected bytes`, () => {
	const f = fixture(undefined, false), frames = sessionFrames();
	if (kind === 'root') frames[1].write('root', 36);
	if (kind === 'schema') frames[1][frames[1].indexOf(Buffer.from('local'))] ^= 1;
	if (kind === 'flags') frames[1][4] ^= 0x20;
	if (kind === 'plugin') frames[1][frames[1].length - 2] ^= 1;
	if (kind === 'max-packet') frames[1][8] ^= 1;
	if (kind === 'thread') frames[0].fill(0, 4 + Buffer.from('\x0a5.5.5-10.6.23-MariaDB\0').length, 8 + Buffer.from('\x0a5.5.5-10.6.23-MariaDB\0').length);
	if (kind === 'server-flags') frames[0][4 + Buffer.from('\x0a5.5.5-10.6.23-MariaDB\0').length + 13] ^= 0x20;
	if (kind === 'error') frames[2][4] = 255;
	if (kind === 'sequence') frames[1][3] = 0;
	if (kind === 'extra') frames.push(Buffer.from([1]));
	if (kind === 'truncated') frames[1] = frames[1].subarray(0, -1);
	assert.equal(f.owner.bindSession(frames, f.identity, 101n), false); assert.equal(f.capture(0), false); result(f.finish());
});
test('session array getters/proxies never execute; auth bytes remain opaque and unreported', () => {
	let invoked = false; const frames = sessionFrames(); Object.defineProperty(frames, 1, { get() { invoked = true; throw Error('no'); } });
	assert.equal(projectionSessionProfile(frames, '012345abcdef'), false); assert.equal(invoked, false);
	assert.equal(projectionSessionProfile(new Proxy(sessionFrames(), { ownKeys() { throw Error('no'); } }), '012345abcdef'), false);
	const opaque = sessionFrames(), at = 36 + Buffer.from('alynt_ro_012345abcdef_i\0').length + 1; opaque[1].fill(99, at, at + 20);
	assert.equal(projectionSessionProfile(opaque, '012345abcdef'), 103); // Not a password-validation claim.
});

for (const mode of ['missing', 'duplicate', 'partial', 'changed', 'foreign', 'out-of-order', 'mutated-after-capture']) test(`forwarding ${mode} cannot become observation credit`, () => {
	const f = fixture(), original = packets()[0], token = f.capture(0, original);
	if (mode === 'missing') assert.equal(f.owner.observe(token, f.identity, 101n), false);
	else if (mode === 'duplicate') { assert.equal(f.owner.forwarded(token, original, f.identity, 101n), true); assert.equal(f.owner.forwarded(token, original, f.identity, 101n), false); }
	else {
		const ack = Buffer.from(original); if (mode === 'changed' || mode === 'mutated-after-capture') ack[ack.length - 1] ^= 1;
		if (mode === 'mutated-after-capture') original.fill(0);
		const credit = mode === 'foreign' ? Symbol() : mode === 'out-of-order' ? f.capture(1) : token;
		assert.equal(f.owner.forwarded(credit, mode === 'partial' ? ack.subarray(0, -1) : ack, f.identity, 101n), false);
	}
	result(f.finish()); assert.equal(f.send(0), false);
});
test('acknowledgement is bound to every context field and its original clock', () => {
	for (const key of identityKeys) {
		const f = fixture(), token = f.capture(0); assert.equal(f.owner.forwarded(token, packets()[0], { ...f.identity, [key]: Symbol() }, 101n), false); result(f.finish());
	}
	const f = fixture(), token = f.capture(0); assert.equal(f.owner.forwarded(token, packets()[0], f.identity, 20000000100n), false); result(f.finish(20000000100n));
});

for (const mode of ['none', 'client-only', 'server-only', 'duplicate', 'after-eof', 'pending', 'expired']) test(`terminal ${mode} refuses without a synthetic closure match`, () => {
	const f = fixture(); for (let i = 0; i < 5; i++) assert.equal(f.send(i), true);
	if (mode === 'client-only') assert.equal(f.owner.end('client', f.identity, 101n), true);
	if (mode === 'server-only') assert.equal(f.owner.end('server', f.identity, 101n), true);
	if (mode === 'duplicate') { assert.equal(f.owner.end('client', f.identity, 101n), true); assert.equal(f.owner.end('client', f.identity, 101n), false); }
	if (mode === 'after-eof') { assert.equal(f.owner.end('server', f.identity, 101n), true); assert.equal(f.capture(1), false); }
	if (mode === 'pending') { assert.equal(typeof f.capture(1), 'symbol'); assert.equal(f.owner.end('server', f.identity, 101n), false); }
	if (mode === 'expired') assert.equal(f.owner.end('client', f.identity, 20000000100n), false);
	result(f.owner.finish(f.identity, mode === 'expired' ? 20000000100n : 101n)); assert.equal(f.owner.finish(f.identity, 101n), false);
});
test('either EOF order is valid, but empty/partial exchanges and foreign EOF are not', () => {
	for (const order of [['server', 'client'], ['client', 'server']]) {
		const f = fixture(); for (let i = 0; i < 5; i++) assert.equal(f.send(i), true);
		for (const dir of order) assert.equal(f.owner.end(dir, f.identity, 101n), true);
		result(f.owner.finish(f.identity, 101n), true);
	}
	const g = fixture(); result(g.finish());
	const h = fixture(); assert.equal(h.send(0, packets()[0].subarray(0, 2)), true); result(h.finish());
	const f = fixture(); assert.equal(f.owner.end('client', { ...f.identity, pair: Symbol() }, 101n), false); result(f.finish());
});
