import test from 'node:test';
import assert from 'node:assert/strict';
import { frameStream, handshakeGate, FRAME_CAP } from '../../scripts/tests/relay-no-fault-frames.mjs';
import { serverHello, clientResponse, ok } from './support/commit-witness-fixtures.mjs';
import { boundRelayRoles } from '../../scripts/tests/relay-no-fault-receipt.mjs';

const packet = (payload, sequence = 0) => { const h = Buffer.alloc(4); h.writeUIntLE(payload.length, 0, 3); h[3] = sequence; return Buffer.concat([h, payload]); };
for (const split of [1, 2, 3, 4, 7, 13, 64, 257]) test(`opaque pass-through split ${split}`, async () => {
	const original = Buffer.concat([packet(Buffer.alloc(256, 19)), packet(Buffer.from([0, 255, 1]), 1)]);
	const forwarded = []; const parser = frameStream(async (frame) => forwarded.push(Buffer.from(frame)));
	for (let i = 0; i < original.length; i += split) await parser.feed(original.subarray(i, i + split));
	assert.deepEqual(Buffer.concat(forwarded), original);
	assert.deepEqual(parser.finish(), { complete: true, equal: true, bytes: original.length, frames: 2 });
	assert.throws(() => parser.finish()); await assert.rejects(parser.feed(original));
});
test('incomplete frame never becomes a successful stream', async () => {
	for (const length of [1, 3, 4, 5]) {
		const parser = frameStream(async () => assert.fail('partial frame forwarded'));
		await parser.feed(packet(Buffer.alloc(7)).subarray(0, length));
		assert.equal(parser.finish().equal, false);
	}
});
test('frame cap checked from header before body allocation', async () => {
	for (const length of [0, FRAME_CAP + 1, 0xffffff]) {
		const h = Buffer.alloc(4); h.writeUIntLE(length, 0, 3);
		const parser = frameStream(async () => assert.fail('oversize forwarded'));
		await assert.rejects(parser.feed(h), /frame_cap/); assert.equal(parser.finish().equal, false);
	}
});
test('failed egress cannot certify equal delivery', async () => {
	const parser = frameStream(async () => { throw Error('write failed'); });
	await assert.rejects(parser.feed(packet(Buffer.from('opaque')))); assert.equal(parser.finish().equal, false);
});
test('bounded frame count', async () => {
	const parser = frameStream(async () => {}); const one = packet(Buffer.from([1]));
	await parser.feed(Buffer.concat(Array.from({ length: 4096 }, () => one)));
	await assert.rejects(parser.feed(one), /frame_cap/); assert.equal(parser.finish().equal, false);
});
test('handshake positive native-shaped synthetic control preserves thread lifetime', () => {
	const gate = handshakeGate();
	assert.equal(gate.identity(), null);
	gate.inspect('server', packet(serverHello(103)));
	gate.inspect('client', packet(clientResponse(), 1));
	gate.inspect('server', packet(ok(), 2));
	assert.equal(gate.identity(), 103);
	// After authentication the relay frames opaque prepared/query/result traffic, not SQL.
	gate.inspect('client', packet(Buffer.from([0x16, 0, 255])));
	assert.equal(gate.identity(), 103);
});
for (const flag of [0x20, 0x800, 0x10000, 0x400000, 0x4000000]) test(`unsupported negotiation ${flag}`, () => {
	const gate = handshakeGate(); gate.inspect('server', packet(serverHello()));
	const hello = clientResponse(); hello.writeUInt32LE((hello.readUInt32LE(0) | flag) >>> 0);
	assert.throws(() => gate.inspect('client', packet(hello, 1)), /unsupported_negotiation/);
});
test('advertised LOCAL_FILES never permits server local-file payload', () => {
	const gate = handshakeGate(); gate.inspect('server', packet(serverHello()));
	const hello = clientResponse(); hello.writeUInt32LE(hello.readUInt32LE(0) | 0x80);
	gate.inspect('client', packet(hello, 1)); gate.inspect('server', packet(ok(), 2));
	assert.throws(() => gate.inspect('server', packet(Buffer.from([0xfb, 1, 2]), 1)), /unsupported_local_file/);
});
test('wrong handshake direction/sequence and auth switch rejected', () => {
	for (const direction of ['client', 'server']) assert.throws(() => handshakeGate().inspect(direction, packet(serverHello(), direction === 'server' ? 1 : 0)));
	const gate = handshakeGate(); gate.inspect('server', packet(serverHello())); gate.inspect('client', packet(clientResponse(), 1));
	assert.throws(() => gate.inspect('server', packet(Buffer.from([0xfe]), 2)), /unsupported_auth/);
});
test('MariaDB extended greeting and wpdb database-later hello stay opaque', () => {
	const payload = serverHello(321); const at = payload.indexOf(0, 1) + 1;
	payload[at + 15] = 8; payload[at + 27] = 0x1d;
	const hello = clientResponse(); hello.writeUInt32LE(hello.readUInt32LE(0) & ~8);
	const gate = handshakeGate(); gate.inspect('server', packet(payload)); gate.inspect('client', packet(hello, 1)); gate.inspect('server', packet(ok(), 2));
	assert.equal(gate.identity(), 321);
});
test('greeting corruption cannot establish an owned identity', () => {
	for (const offset of [0, 1, serverHello().indexOf(0, 1) + 1 + 21, serverHello().length - 2]) {
		const payload = serverHello(); payload[offset] ^= 1;
		const gate = handshakeGate(); assert.throws(() => gate.inspect('server', packet(payload))); assert.equal(gate.cleanupIdentity(), null);
	}
});
test('exact owned role/lifetime set, never ordinal or subset matching', () => {
	const roles = { auth: 101, capture: 102, writer: 103 };
	assert.equal(boundRelayRoles(roles, [103, 101, 102]), true);
	for (const ids of [[], [101, 102], [101, 102, 102], [101, 102, 104], [101, 102, 103, 104]]) assert.equal(boundRelayRoles(roles, ids), false);
	for (const invalid of [null, { ...roles, writer: 102 }, { ...roles, writer: '103' }, { ...roles, extra: 104 }]) assert.equal(boundRelayRoles(invalid, [101, 102, 103]), false);
});
test('exact frame and aggregate byte cap using one reused two MiB fixture', async () => {
	let forwarded = 0; const parser = frameStream(async () => { forwarded++; }); const one = packet(Buffer.alloc(FRAME_CAP - 4));
	for (let i = 0; i < 32; i++) await parser.feed(one);
	assert.equal(forwarded, 32); await assert.rejects(parser.feed(Buffer.from([1])), /byte_cap/); assert.equal(parser.finish().equal, false);
});
test('reservation is checked before allocation and released after failed egress', async () => {
	let queued = 0;
	const parser = frameStream(async () => { throw Error('egress'); }, (n) => { queued += n; }, (n) => { queued -= n; });
	await assert.rejects(parser.feed(packet(Buffer.alloc(8)))); assert.equal(queued, 12); assert.equal(parser.finish().equal, false); assert.equal(queued, 0);
	const refused = frameStream(async () => assert.fail('unreserved body sent'), () => { throw Error('queue_cap'); });
	await assert.rejects(refused.feed(packet(Buffer.alloc(8))), /queue_cap/); assert.equal(refused.finish().equal, false);
});
