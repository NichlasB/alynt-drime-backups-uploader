import assert from 'node:assert/strict';
import test from 'node:test';
import { copyWpdbSetupPayload, decodeWpdbSetupOK } from '../../scripts/tests/wpdb-setup-wire.mjs';
import { packet } from './support/wpdb-setup-wire-fixtures.mjs';

test('packet copy is detached and header/sequence exact', () => {
	const frame = packet([1, 2, 3], 1), copy = copyWpdbSetupPayload(frame, 1);
	assert.deepEqual(copy, Buffer.from([1, 2, 3])); frame.fill(0); assert.deepEqual(copy, Buffer.from([1, 2, 3]));
	copy.fill(9); assert.equal(frame.every((v) => v === 0), true);
});
for (const length of [1, 4096]) test(`bounded payload ${length} succeeds`, () => {
	const body = Buffer.alloc(length, 67); assert.deepEqual(copyWpdbSetupPayload(packet(body, 1), 1), body);
});
test('truncation at every boundary and concatenated/suffixed packets refuse', () => {
	const frame = packet([0, 0, 0, 2, 0, 0, 0], 1);
	for (let cut = 0; cut < frame.length; cut++) assert.equal(copyWpdbSetupPayload(frame.subarray(0, cut), 1), false);
	assert.equal(copyWpdbSetupPayload(Buffer.concat([frame, Buffer.from([0])]), 1), false);
	assert.equal(copyWpdbSetupPayload(Buffer.concat([frame, frame]), 1), false);
});
for (const length of [0, 4097, 0xffffff]) test(`unsupported length ${length} refuses before payload allocation`, () => {
	const header = Buffer.alloc(5); header.writeUIntLE(length, 0, 3); header[3] = 1;
	assert.equal(copyWpdbSetupPayload(header, 1), false);
	if (length < 5000) assert.equal(copyWpdbSetupPayload(packet(Buffer.alloc(length), 1), 1), false);
});
for (const sequence of [-1, 1.5, 9, '1', NaN, null]) test(`invalid sequence ${String(sequence)} refuses`, () => {
	assert.equal(copyWpdbSetupPayload(packet([1], 1), sequence), false);
});
test('wrong sequence and exotic/shared inputs cannot cross byte boundary or invoke getters', () => {
	let calls = 0; const frame = packet([1], 1);
	const getter = packet([1], 1); Object.defineProperty(getter, 'length', { get() { calls++; throw Error('getter'); } });
	const bufferGetter = packet([1], 1); Object.defineProperty(bufferGetter, 'buffer', { get() { calls++; throw Error('getter'); } });
	const proxy = new Proxy(frame, { get() { calls++; throw Error('proxy'); } });
	for (const value of [getter, bufferGetter, proxy, Buffer.from(new SharedArrayBuffer(5)), new Uint8Array(frame), {}, null]) {
		assert.equal(copyWpdbSetupPayload(value, 1), false);
	}
	assert.equal(copyWpdbSetupPayload(frame, 2), false); assert.equal(calls, 0);
});

test('exact ordinary protocol41 OK fixture succeeds without altering caller bytes', () => {
	const frame = packet([0, 0, 0, 2, 0, 0, 0], 1), before = Buffer.from(frame);
	assert.equal(decodeWpdbSetupOK(frame), true); assert.deepEqual(frame, before);
});
test('all affected/insert/status/warning/header mutations refuse, not merely first-byte check', () => {
	const body = Buffer.from([0, 0, 0, 2, 0, 0, 0]);
	for (let index = 0; index < body.length; index++) for (let bit = 0; bit < 8; bit++) {
		const changed = Buffer.from(body); changed[index] ^= 1 << bit;
		assert.equal(decodeWpdbSetupOK(packet(changed, 1)), false, `${index}/${bit}`);
	}
});
for (const body of [[0], [0, 0, 0, 2, 0, 0], [0, 0, 0, 2, 0, 0, 0, 0], [254, 0, 0, 2, 0],
	[255, 1, 0, 35, 72, 89, 48, 48, 48], [251], [254, 0, 0, 2, 0, 0, 0]]) {
	test(`non-canonical completion ${Buffer.from(body).toString('hex')} refuses`, () => assert.equal(decodeWpdbSetupOK(packet(body, 1)), false));
}
test('OK cannot hide wrong sequence, trailing frame or length mismatch', () => {
	const frame = packet([0, 0, 0, 2, 0, 0, 0], 1);
	assert.equal(decodeWpdbSetupOK(packet([0, 0, 0, 2, 0, 0, 0], 2)), false);
	assert.equal(decodeWpdbSetupOK(Buffer.concat([frame, frame])), false);
	for (let cut = 0; cut < frame.length; cut++) assert.equal(decodeWpdbSetupOK(frame.subarray(0, cut)), false);
});
