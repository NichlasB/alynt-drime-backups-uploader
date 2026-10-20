import assert from 'node:assert/strict';
import test from 'node:test';
import { CLIENT_PROFILE, greeting, clientHello, idleOk } from '../../scripts/tests/commit-witness-protocol.mjs';
import { fixture, frame, serverHello, clientResponse, command, ok } from './support/commit-witness-fixtures.mjs';

function closed(result, match = false) {
	assert.equal(result.synthetic_match, match);
	assert.equal(result.synthetic_only, true);
	for (const [name, value] of Object.entries(result)) if (!['synthetic_match', 'synthetic_only'].includes(name)) assert.equal(value, false, name);
	assert.equal(Object.isFrozen(result), true);
	assert.equal(JSON.stringify(result).includes('103'), false);
}

test('positive selected transcript is non-authorizing, private and one-shot', () => {
	const f = fixture(); assert.equal(f.transcript(), true); closed(f.finish(), true);
	assert.equal(f.finish(), false); assert.equal(f.arm(), false); assert.equal(f.send('server', frame(ok(), 1)), false);
	assert.equal(Object.isFrozen(f.d), true); assert.deepEqual(Object.keys(f.d).sort(), ['arm', 'feed', 'finish']);
});

const steps = [['server', frame(serverHello(), 0)], ['client', frame(clientResponse(), 1)], ['server', frame(ok(), 2)], ['client', frame(command(), 0)], ['server', frame(ok(), 1)]];
for (const size of [1, 2, 3, 4, 5, 7, 13, 64, 1024]) test(`fragmentation accepts fixed transcript in chunks of ${size}`, () => {
	const f = fixture();
	for (const [index, [dir, bytes]] of steps.entries()) {
		if (index === 3) assert.equal(f.arm(), true);
		for (let at = 0; at < bytes.length; at += size) assert.equal(f.send(dir, bytes.subarray(at, at + size)), true);
	}
	closed(f.finish(), true);
});

for (const [index, [dir, bytes]] of steps.entries()) {
	for (const fault of ['wrong-direction', 'wrong-sequence', 'truncated', 'zero-length', 'oversize', 'continuation', 'duplicate']) test(`stage ${index} ${fault} permanently refuses`, () => {
		const f = fixture();
		for (let prior = 0; prior < index; prior++) { if (prior === 3) assert.equal(f.arm(), true); assert.equal(f.send(...steps[prior]), true); }
		if (index === 3) assert.equal(f.arm(), true);
		let changed = Buffer.from(bytes);
		if (fault === 'wrong-sequence') changed[3] ^= 1;
		if (fault === 'truncated') changed = changed.subarray(0, changed.length - 1);
		if (fault === 'zero-length') changed.fill(0, 0, 3);
		if (fault === 'oversize') changed.writeUIntLE(257, 0, 3);
		if (fault === 'continuation') changed.fill(255, 0, 3);
		if (fault === 'duplicate') changed = Buffer.concat([changed, changed]);
		const accepted = f.send(fault === 'wrong-direction' ? (dir === 'server' ? 'client' : 'server') : dir, changed);
		assert.equal(accepted, fault === 'truncated'); closed(f.finish()); assert.equal(f.send(dir, bytes), false);
	});
}

for (const fault of ['wrong-attempt', 'wrong-pair', 'wrong-writer', 'borrowed-auth', 'capture', 'early-arm', 'double-arm', 'expired', 'regression', 'insufficient-window']) test(`identity/lifetime guard: ${fault}`, () => {
	const f = fixture();
	if (fault !== 'early-arm') assert.equal(f.handshake(), true);
	if (fault === 'wrong-attempt') assert.equal(f.d.arm(Symbol('attempt'), f.config.lifetime, 103, 1n), false);
	else if (fault === 'wrong-pair') assert.equal(f.d.arm(f.config.attempt, Symbol('pair'), 103, 1n), false);
	else if (fault === 'regression') { assert.equal(f.arm(103, 2n), true); assert.equal(f.send('client', frame(command(), 0), 1n), false); }
	else if (fault === 'double-arm') { assert.equal(f.arm(), true); assert.equal(f.arm(), false); }
	else assert.equal(f.arm(fault === 'wrong-writer' ? 104 : fault === 'borrowed-auth' ? 101 : fault === 'capture' ? 102 : 103, fault === 'expired' ? 10000000000n : fault === 'insufficient-window' ? 4000000001n : 1n), false);
	closed(f.finish(10000000001n));
});

test('half-open clock and six-second arming boundary are exact', () => {
	const f = fixture(); assert.equal(f.handshake(), true); assert.equal(f.arm(103, 4000000000n), true);
	assert.equal(f.send('client', frame(command(), 0), 9999999999n), true); assert.equal(f.send('server', frame(ok(), 1), 9999999999n), true);
	closed(f.finish(9999999999n), true);
	const expired = fixture(); assert.equal(expired.transcript(), true); closed(expired.finish(10000000000n));
});

for (const changed of ['BEGIN', 'COMMIT', 'COMMIT AND NO CHAIN NO RELEASE ', 'commit and no chain no release', '/* nonce */COMMIT AND NO CHAIN NO RELEASE', 'COMMIT AND NO CHAIN NO RELEASE;SELECT 1']) test(`exact command refuses ${JSON.stringify(changed)}`, () => {
	const f = fixture(); assert.equal(f.handshake(), true); assert.equal(f.arm(), true);
	assert.equal(f.send('client', frame(Buffer.from('\x03' + changed), 0)), false); closed(f.finish());
});

for (const [name, change] of Object.entries({
	err: () => Buffer.from([255, 1, 0]), eof: () => Buffer.from([254, 0, 0, 2, 0]),
	row: () => Buffer.from([0, 4, 0, 2, 0, 0, 0]), noncanonical: () => Buffer.from([0, 252, 0, 0, 0, 2, 0, 0, 0]),
	insert: () => Buffer.from([0, 0, 1, 2, 0, 0, 0]), transaction: () => Buffer.from([0, 0, 0, 3, 0, 0, 0]),
	results: () => Buffer.from([0, 0, 0, 10, 0, 0, 0]), warnings: () => Buffer.from([0, 0, 0, 2, 0, 1, 0]),
	info: () => Buffer.from([0, 0, 0, 2, 0, 0, 0, 0]), session: () => Buffer.from([0, 0, 0, 2, 64, 0, 0]),
})) test(`complete OK grammar rejects ${name} at authentication and COMMIT`, () => {
	assert.equal(idleOk(change()), false);
	for (const auth of [true, false]) {
		const f = fixture(); assert.equal(f.send('server', steps[0][1]), true); assert.equal(f.send('client', steps[1][1]), true);
		if (!auth) { assert.equal(f.send('server', frame(ok(), 2)), true); assert.equal(f.arm(), true); assert.equal(f.send('client', steps[3][1]), true); }
		assert.equal(f.send('server', frame(change(), auth ? 2 : 1)), false); closed(f.finish());
	}
});

for (const flag of [0x800, 0x20, 0x10000, 0x800000, 0x1000000]) test(`unsupported negotiated capability ${flag} refuses without downgrade`, () => {
	const bytes = clientResponse(); bytes.writeUInt32LE(CLIENT_PROFILE | flag);
	assert.equal(clientHello(bytes, 'fixture'), false);
	const f = fixture(); assert.equal(f.send('server', steps[0][1]), true); assert.equal(f.send('client', frame(bytes, 1)), false); closed(f.finish());
});

test('greeting profile, database, reserved fields and auth plugin are strict', () => {
	for (const thread of [0, 101, 102]) { const f = fixture(); assert.equal(f.send('server', frame(serverHello(thread), 0)), false); closed(f.finish()); }
	for (const mutate of [(b) => { b[0] = 9; }, (b) => { b[1] = 0; }, (b) => { b[b.length - 2] ^= 1; }, (b) => { b[b.length - 1] = 1; }]) {
		const bytes = serverHello(); mutate(bytes); assert.equal(greeting(bytes), false);
	}
	for (const mutate of [(b) => { b[9] = 1; }, (b) => { b[8] = 0; }, (b) => { b.writeUInt32LE(0, 4); }, (b) => { b[32] = 0; }, (b) => { b[b.length - 2] ^= 1; }]) {
		const bytes = clientResponse(); mutate(bytes); assert.equal(clientHello(bytes, 'fixture'), false);
	}
	assert.equal(clientHello(clientResponse(), 'other'), false);
});

test('partial buffers copy caller bytes; malformed input poisons without exporting payloads', () => {
	const f = fixture(); const bytes = steps[0][1]; const first = Buffer.from(bytes.subarray(0, 9));
	assert.equal(f.send('server', first), true); first.fill(255); assert.equal(f.send('server', bytes.subarray(9)), true);
	assert.equal(f.send('client', steps[1][1]), true); assert.equal(f.send('server', steps[2][1]), true); assert.equal(f.arm(), true);
	assert.equal(f.send('client', steps[3][1]), true); assert.equal(f.send('server', steps[4][1]), true); closed(f.finish(), true);
	for (const bad of [null, 'secret-like bytes', new Uint8Array(3), Buffer.alloc(0), Buffer.alloc(1025)]) {
		const badFixture = fixture(); assert.equal(badFixture.send('server', bad), false); closed(badFixture.finish());
	}
});

test('context rejects invalid or aliased identities, clocks, fields and thread domains', () => {
	for (const override of [{ attempt: 'public' }, { lifetime: null }, { startedNs: -1n }, { startedNs: 0 }, { authThread: 0 }, { captureThread: 101 }, { captureThread: 2 ** 32 }, { database: '../x' }, { extra: true }]) assert.throws(() => fixture(override), /Unsupported synthetic decoder context/);
	const shared = Symbol('same'); assert.throws(() => fixture({ attempt: shared, lifetime: shared }), /Unsupported synthetic decoder context/);
});
