import assert from 'node:assert/strict';
import test from 'node:test';
import { CLIENT_PROFILE, greeting, clientHello, idleOk } from '../../scripts/tests/commit-witness-protocol.mjs';
import { createCommitWitnessDecoder as create } from '../../scripts/tests/commit-witness-decoder.mjs';
import { fixture, frame, serverHello, clientResponse, command, ok } from './support/commit-witness-fixtures.mjs';

test('constructor rejects unbounded clocks before deadline arithmetic', () => {
	assert.throws(() => fixture({ startedNs: 2n ** 63n }), /Unsupported synthetic decoder context/);
	const start = 2n ** 63n - 1n - 10000000000n;
	const f = fixture({ startedNs: start });
	for (const [dir, bytes] of [['server', frame(serverHello(), 0)], ['client', frame(clientResponse(), 1)], ['server', frame(ok(), 2)]]) assert.equal(f.send(dir, bytes, start), true);
	assert.equal(f.arm(103, start), true); assert.equal(f.send('client', frame(command(), 0), start), true); assert.equal(f.send('server', frame(ok(), 1), start), true);
	assert.equal(f.finish(start).synthetic_match, true);
});

test('constructor rejects accessors, symbol fields and registered identity aliases without invoking callbacks', () => {
	let calls = 0;
	const f = fixture(); const config = { ...f.config };
	Object.defineProperty(config, 'attempt', { enumerable: true, get() { calls++; return Symbol('accessor'); } });
	// Import fixture's factory directly: spreading an accessor would invoke it in test setup.
	assert.throws(() => create(config), /Unsupported synthetic decoder context/); assert.equal(calls, 0);
	assert.throws(() => create({ ...f.config, [Symbol('hidden')]: true }), /Unsupported synthetic decoder context/);
	assert.throws(() => fixture({ attempt: Symbol.for('public-attempt') }), /Unsupported synthetic decoder context/);
	assert.throws(() => fixture({ lifetime: Symbol.for('public-pair') }), /Unsupported synthetic decoder context/);
});

test('protocol fixtures use an independently specified capability profile', () => {
	assert.equal(CLIENT_PROFILE, 0x0008a208); assert.equal(clientResponse().readUInt32LE(0), 0x0008a208);
	assert.equal(greeting(serverHello(0xffffffff)), 0xffffffff);
	for (const bit of [8, 0x200, 0x2000, 0x8000, 0x80000]) {
		const bytes = clientResponse(); bytes.writeUInt32LE(0x0008a208 ^ bit); assert.equal(clientHello(bytes, 'fixture'), false);
	}
});

test('every truncated payload and appended trailing byte refuses without throwing', () => {
	for (const [bytes, parse] of [[serverHello(), greeting], [clientResponse(), (b) => clientHello(b, 'fixture')], [ok(), idleOk]]) {
		for (let size = 0; size < bytes.length; size++) assert.equal(parse(bytes.subarray(0, size)), false, `truncation ${size}`);
		assert.equal(parse(Buffer.concat([bytes, Buffer.from([0])])), false);
	}
});

test('feed and finish validate identity and clock, not just arm', () => {
	for (const method of ['feed', 'finish']) for (const fault of ['attempt', 'pair', 'clock-type', 'clock-before-start']) {
		const f = fixture({ startedNs: 10n });
		const args = [fault === 'attempt' ? Symbol('attempt') : f.config.attempt, fault === 'pair' ? Symbol('pair') : f.config.lifetime, fault === 'clock-type' ? 10 : fault === 'clock-before-start' ? 9n : 10n];
		const result = method === 'feed' ? f.d.feed('server', frame(serverHello(), 0), ...args) : f.d.finish(...args);
		if (method === 'feed') assert.equal(result, false); else assert.equal(result.synthetic_match, false);
		assert.equal(f.send('server', frame(serverHello(), 0), 10n), false);
	}
});

test('authentication-shaped OK before arming and late extra frames never match', () => {
	const ready = fixture(); assert.equal(ready.handshake(), true); assert.equal(ready.send('server', frame(ok(), 1)), false); assert.equal(ready.finish().synthetic_match, false);
	const late = fixture(); assert.equal(late.transcript(), true); assert.equal(late.send('server', frame(ok(), 1)), false); assert.equal(late.finish().synthetic_match, false);
	const partial = fixture(); assert.equal(partial.send('server', frame(serverHello(), 0).subarray(0, 3)), true); assert.equal(partial.arm(), false); assert.equal(partial.finish().synthetic_match, false);
});

test('opaque auth bytes and unnegotiated advertised TLS do not pretend to be authentication', () => {
	const bytes = serverHello(); const at = bytes.indexOf(0, 1) + 1;
	bytes.writeUInt16LE(bytes.readUInt16LE(at + 13) | 0x800, at + 13);
	assert.equal(greeting(bytes), 103); // Advertisement alone is not negotiation.
	const hello = clientResponse(); const userEnd = hello.indexOf(0, 32); hello.fill(0xff, userEnd + 2, userEnd + 22);
	assert.equal(clientHello(hello, 'fixture'), true); // Opaque invented auth bytes; never a password/session proof.
});
