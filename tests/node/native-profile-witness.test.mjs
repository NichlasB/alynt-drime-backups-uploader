import test from 'node:test';
import assert from 'node:assert/strict';
import { nativeGreeting, nativeHello } from '../../scripts/tests/native-witness-profile.mjs';
import { createNativeProfileWitness } from '../../scripts/tests/native-profile-witness.mjs';
import { witness, greeting, hello, ok, commit, frame } from './support/native-witness-fixtures.mjs';

test('positive native-shaped observation survives opaque traffic and request-accounting race', () => {
	const f = witness(); assert.equal(f.ready(), true);
	for (const command of [Buffer.from('\x03SELECT 1'), Buffer.from('\x16SELECT ?'), Buffer.from('\x17COMMIT AND NO CHAIN NO RELEASE')]) {
		assert.equal(f.send('writer', 'client', command), true); assert.equal(f.send('writer', 'server', Buffer.from('\x01opaque row COMMIT AND NO CHAIN NO RELEASE'), 1), true);
	}
	assert.equal(f.send('writer', 'client', commit(), 0, 2n, false), true);
	assert.equal(f.send('writer', 'server', ok(), 1, 2n), true);
	assert.equal(f.api.account(f.ids.writer, 'client', 1, 2n), true);
	const result = f.complete(); assert.equal(result.synthetic_match, true);
	assert.ok(Object.isFrozen(result)); assert.ok(Object.entries(result).every(([key, value]) => ['synthetic_match', 'synthetic_only'].includes(key) || value === false));
	assert.equal(f.api.finish(2n, true), false);
});
for (const bit of [1, 4, 128, 0x20000, 0x40000]) test(`candidate optional capability ${bit}`, () => assert.notEqual(nativeHello(hello(0x8a208 | bit), 'synthetic', 'fixture'), false));
for (const bit of [0x20, 0x800, 0x10000, 0x200000, 0x400000, 0x800000, 0x1000000, 0x4000000, 0x80000000]) test(`unsupported capability ${bit}`, () => assert.equal(nativeHello(hello((0x8a208 | bit) >>> 0), 'synthetic', 'fixture'), false));
test('required flags, exact user/database/plugin and opaque auth length', () => {
	for (const bit of [8, 0x200, 0x2000, 0x8000, 0x80000]) assert.equal(nativeHello(hello(0x8a208 & ~bit), 'synthetic', 'fixture'), false);
	assert.notEqual(nativeHello(hello(0x8a200), 'synthetic', 'fixture', false), false);
	for (const [u, d] of [['foreign', 'fixture'], ['synthetic', 'foreign']]) assert.equal(nativeHello(hello(), u, d), false);
	const b = hello(); b[42] = 19; assert.equal(nativeHello(b, 'synthetic', 'fixture'), false);
	assert.equal(nativeHello(Buffer.concat([hello(), Buffer.from([0])]), 'synthetic', 'fixture'), false);
});
test('attribute pairs/1024 byte block and canonical length encoding are bounded', () => {
	const attrs = Buffer.alloc(1024); attrs[0] = 252; attrs.writeUInt16LE(1019, 1); attrs[1022] = 1;
	const block = Buffer.concat([Buffer.from([252, 0, 4]), attrs]);
	assert.notEqual(nativeHello(hello(0x18a208, block), 'synthetic', 'fixture'), false);
	for (const b of [Buffer.from([0]), Buffer.from([4, 1, 65, 1, 66]), Buffer.concat([Buffer.from([32]), Buffer.alloc(32)])]) assert.notEqual(nativeHello(hello(0x18a208, b), 'synthetic', 'fixture'), false);
	for (const b of [Buffer.from([34, ...Buffer.alloc(34)]), Buffer.from([1, 0]), Buffer.from([252, 1, 0, 0]), Buffer.from([251]), Buffer.from([253, 0, 0, 0]), Buffer.from([252, 1, 4]), Buffer.from([0, 0])]) assert.equal(nativeHello(hello(0x18a208, b), 'synthetic', 'fixture'), false);
});
test('greeting reserved bytes differ from extended capabilities; canonical status/plugin', () => {
	assert.equal(nativeGreeting(greeting()), 103); assert.equal(nativeGreeting(greeting(103, 45)), 103);
	const zero = greeting(); zero.writeUInt32LE(0, zero.indexOf(0, 1) + 1); assert.equal(nativeGreeting(zero), false);
	for (const offset of [12, 15, 16, 20, 21, 26, 43, 44]) {
		const b = greeting(), at = b.indexOf(0, 1) + 1; b[at + offset] ^= 0xff;
		assert.equal(nativeGreeting(b), false, `offset ${offset}`);
	}
});
for (const width of [1, 2, 3, 4, 7, 13, 64, 257]) test(`selected frames fragmented at ${width}`, () => {
	const f = witness(); assert.equal(f.ready(), true);
	for (const [direction, b] of [['client', frame(commit())], ['server', frame(ok(), 1)]]) {
		for (let i = 0; i < b.length; i += width) assert.equal(f.api.feed(f.ids.writer, direction, b.subarray(i, i + width), 2n), true);
		assert.equal(f.api.account(f.ids.writer, direction, 1, 2n), true);
	}
	assert.equal(f.complete().synthetic_match, true);
});
for (const body of [Buffer.from('\x03commit AND NO CHAIN NO RELEASE'), Buffer.from('\x03COMMIT AND NO CHAIN NO RELEASE '), Buffer.from('\x03SELECT "COMMIT AND NO CHAIN NO RELEASE"'), Buffer.from('\x17COMMIT AND NO CHAIN NO RELEASE')]) test(`not substring/normalized target ${body.toString('hex').slice(-6)}`, () => {
	const f = witness(); assert.equal(f.ready(), true); assert.equal(f.send('writer', 'client', body), true); assert.equal(f.send('writer', 'server', ok(), 1), true);
	assert.equal(f.api.finish(2n, true).synthetic_match, false);
});
for (const body of [Buffer.from([0xfb]), Buffer.from([0xff]), Buffer.from([0xfe]), Buffer.from([0, 0, 0]), Buffer.from([0, 0, 0, 3, 0, 0, 0]), Buffer.concat([ok(), Buffer.from([0])])]) test(`invalid selected response ${body.toString('hex')}`, () => {
	const f = witness(); assert.equal(f.ready(), true); assert.equal(f.send('writer', 'client', commit()), true); assert.equal(f.send('writer', 'server', body, 1), false); assert.equal(f.api.finish(2n, true).synthetic_match, false);
});
for (const mode of ['unbound', 'foreign', 'duplicate', 'early-partial', 'pending', 'wrong-sequence', 'extra-response', 'truncated', 'false-receipt', 'expired', 'regression', 'late-bind']) test(`fail closed ${mode}`, () => {
	const f = witness(); assert.equal(f.ready(), true);
	if (mode === 'foreign') assert.equal(f.api.feed(Symbol('writer'), 'client', frame(commit()), 2n), false);
	else if (mode === 'duplicate') { assert.equal(f.send('writer', 'client', commit()), true); assert.equal(f.send('writer', 'client', commit()), false); }
	else if (mode === 'early-partial') { assert.equal(f.api.feed(f.ids.writer, 'server', frame(ok(), 1).subarray(0, 2), 1n), true); assert.equal(f.send('writer', 'client', commit()), false); }
	else if (mode === 'pending') { assert.equal(f.send('writer', 'client', Buffer.from('\x03SELECT 1'), 0, 1n, false), true); assert.equal(f.send('writer', 'client', commit()), false); }
	else if (mode === 'wrong-sequence') assert.equal(f.send('writer', 'client', commit(), 1), false);
	else if (mode === 'extra-response') { assert.equal(f.send('writer', 'client', commit()), true); assert.equal(f.api.feed(f.ids.writer, 'server', Buffer.concat([frame(ok(), 1), frame(ok(), 1)]), 1n), false); }
	else if (mode === 'truncated') assert.equal(f.api.feed(f.ids.writer, 'client', frame(commit()).subarray(0, 5), 1n), true);
	else if (mode === 'expired') assert.equal(f.api.feed(f.ids.writer, 'client', frame(commit()), 10000000000n), false);
	else if (mode === 'regression') assert.equal(f.api.feed(f.ids.writer, 'client', frame(commit()), 0n), false);
	else if (mode === 'late-bind') assert.equal(f.api.bind(f.ids.writer, 4000000001n), false);
	else if (mode === 'false-receipt') { assert.equal(f.send('writer', 'client', commit()), true); assert.equal(f.send('writer', 'server', ok(), 1), true); assert.equal(f.complete(2n, false).synthetic_match, false); return; }
	else { const fresh = witness(); assert.equal(fresh.send('writer', 'client', commit()), false); }
	assert.equal(f.api.finish(2n, true).synthetic_match, false);
});
test('oversize header, unknown commands and duplicate lifetimes fail before allocation/selection', () => {
	const f = witness(); assert.equal(f.ready(), true); assert.equal(f.api.feed(f.ids.writer, 'client', Buffer.from([1, 0, 32, 0]), 1n), false);
	for (const command of [17, 27, 31, 255]) { const g = witness(); assert.equal(g.ready(), true); assert.equal(g.send('writer', 'client', Buffer.from([command])), false); }
	const g = witness(); g.config.lifetimes.capture = g.ids.writer; assert.throws(() => createNativeProfileWitness(g.config), TypeError);
});
