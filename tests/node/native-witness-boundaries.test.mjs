import test from 'node:test';
import assert from 'node:assert/strict';
import { createNativeProfileWitness } from '../../scripts/tests/native-profile-witness.mjs';
import { createNativeControlTranscript } from '../../scripts/tests/native-control-transcript.mjs';
import { nativeGreeting } from '../../scripts/tests/native-witness-profile.mjs';
import { witness, control, greeting, hello, ok, commit, frame } from './support/native-witness-fixtures.mjs';

test('role property order cannot reinterpret synthetic identity ownership', () => {
	const f = witness();
	const api = createNativeProfileWitness({ ...f.config, lifetimes: { writer: f.ids.writer, auth: f.ids.auth, capture: f.ids.capture } });
	for (const [i, role] of ['auth', 'capture', 'writer'].entries()) for (const [direction, body, seq] of [['server', greeting(101 + i), 0], ['client', hello(role === 'auth' ? 0x8a200 : 0x8a208), 1], ['server', ok(), 2]]) {
		assert.equal(api.feed(f.ids[role], direction, frame(body, seq), 0n), true); assert.equal(api.account(f.ids[role], direction, 1, 0n), true);
	}
	assert.equal(api.close(f.ids.capture, 0n), true); assert.equal(api.bind(f.ids.writer, 0n), true);
});
test('high-bit version aliases are not ASCII native-profile versions', () => {
	const b = greeting(); b[1] |= 128; assert.equal(nativeGreeting(b), false);
});
test('context does not invoke getters or coercion hooks', () => {
	const f = witness(); let calls = 0;
	const config = { ...f.config, username: { toString() { calls++; return 'synthetic'; } } };
	assert.throws(() => createNativeProfileWitness(config), TypeError); assert.equal(calls, 0);
	Object.defineProperty(config, 'username', { get() { calls++; return 'synthetic'; } });
	assert.throws(() => createNativeProfileWitness(config), TypeError); assert.equal(calls, 0);
});
test('pair/attempt byte and frame limits are bounded with reused representative buffer', () => {
	for (const mode of ['frames', 'bytes']) {
		const f = witness(); assert.equal(f.ready(), true);
		const b = frame(Buffer.alloc(mode === 'bytes' ? 2097152 : 1, 1), 1);
		let rejected = false;
		for (let i = 0; i < (mode === 'bytes' ? 33 : 4097); i++) {
			if (!f.api.feed(f.ids.writer, 'server', b, 1n)) { rejected = true; break; }
			assert.equal(f.api.account(f.ids.writer, 'server', 1, 1n), true);
		}
		assert.equal(rejected, true); assert.equal(f.api.finish(1n, true).synthetic_match, false);
	}
});
test('handshake every split point preserves both profile and sequence validation', () => {
	const frames = [frame(greeting(), 0), frame(hello(), 1), frame(ok(), 2)];
	for (let which = 0; which < frames.length; which++) for (let split = 1; split < frames[which].length; split++) {
		const f = witness();
		for (const [i, b] of frames.entries()) {
			for (const part of i === which ? [b.subarray(0, split), b.subarray(split)] : [b]) assert.equal(f.api.feed(f.ids.writer, i === 1 ? 'client' : 'server', part, 0n), true);
			assert.equal(f.api.account(f.ids.writer, i === 1 ? 'client' : 'server', 1, 0n), true);
		}
	}
});
test('closed pair, fourth lifetime, numeric thread reuse and role-alias greeting cannot rebind', () => {
	const f = witness(); assert.equal(f.ready(), true);
	assert.equal(f.api.feed(f.ids.capture, 'client', frame(commit()), 1n), false);
	const g = witness(); assert.equal(g.send('auth', 'server', greeting(101)), true); assert.equal(g.send('writer', 'server', greeting(101)), false);
	const h = witness(); assert.equal(h.ready(1n, false), true); assert.equal(h.api.bind(Symbol('fourth'), 1n), false);
});
test('exact six-second binding boundary and no pending/unacknowledged terminal success', () => {
	const boundary = witness(); assert.equal(boundary.ready(4000000000n), true);
	const late = witness(); assert.equal(late.ready(4000000001n), false);
	const f = witness(); assert.equal(f.ready(), true);
	assert.equal(f.send('writer', 'client', commit()), true); assert.equal(f.send('writer', 'server', ok(), 1, 1n, false), true);
	assert.equal(f.api.close(f.ids.writer, 1n), false); assert.equal(f.api.finish(1n, true).synthetic_match, false);
});
test('live capture and mismatching writer profile cannot bind even with valid numeric identities', () => {
	for (const mode of ['live', 'profile']) {
		const f = witness();
		for (const [i, role] of ['auth', 'capture', 'writer'].entries()) {
			assert.equal(f.send(role, 'server', greeting(101 + i)), true);
			assert.equal(f.send(role, 'client', hello(role === 'writer' && mode === 'profile' ? 0x8a20c : 0x8a208), 1), true);
			assert.equal(f.send(role, 'server', ok(), 2), true);
		}
		if (mode === 'profile') assert.equal(f.api.close(f.ids.capture, 1n), true);
		assert.equal(f.api.bind(f.ids.writer, 1n), false);
	}
});
test('borrowed authentication lifetime cannot disappear before selected writer completion', () => {
	const f = witness(); assert.equal(f.ready(), true); assert.equal(f.api.close(f.ids.auth, 1n), false);
	assert.equal(f.api.finish(1n, true).synthetic_match, false);
});
test('shared partial-frame reservations reject the fifth 2MiB body before further allocation', () => {
	const f = witness();
	for (const [i, role] of ['auth', 'capture', 'writer'].entries()) {
		assert.equal(f.send(role, 'server', greeting(101 + i)), true); assert.equal(f.send(role, 'client', hello(), 1), true); assert.equal(f.send(role, 'server', ok(), 2), true);
	}
	let count = 0;
	for (const role of ['auth', 'capture', 'writer']) for (const direction of ['client', 'server']) {
		const header = Buffer.from([0, 0, 32, 0]);
		assert.equal(f.api.feed(f.ids[role], direction, header, 1n), ++count <= 4);
	}
	assert.equal(f.api.finish(1n, true).synthetic_match, false);
});
test('handshake truncation, sequence and auth status cannot qualify', () => {
	for (const mode of ['direction', 'sequence', 'auth-status', 'truncated']) {
		const f = witness();
		if (mode === 'direction') assert.equal(f.send('writer', 'client', greeting()), false);
		else if (mode === 'sequence') assert.equal(f.send('writer', 'server', greeting(), 1), false);
		else {
			assert.equal(f.send('writer', 'server', greeting()), true); assert.equal(f.send('writer', 'client', hello(), 1), true);
			const bytes = ok(); if (mode === 'auth-status') { bytes[3] = 3; assert.equal(f.send('writer', 'server', bytes, 2), false); }
			else assert.equal(f.api.feed(f.ids.writer, 'server', frame(bytes, 2).subarray(0, -1), 1n), true);
		}
		assert.equal(f.api.finish(1n, true).synthetic_match, false);
	}
});
test('control context rejects token reuse, registry aliases/accessors and exposes no authority', () => {
	const f = control(); const config = { attempt: 'a'.repeat(64), root: 'b'.repeat(64), pair: 'c'.repeat(64), roles: f.roles };
	assert.throws(() => createNativeControlTranscript({ ...config, root: config.attempt }), TypeError);
	assert.throws(() => createNativeControlTranscript({ ...config, roles: { ...f.roles, capture: f.roles.writer } }), TypeError);
	let calls = 0; Object.defineProperty(config, 'root', { get() { calls++; return 'b'.repeat(64); } });
	assert.throws(() => createNativeControlTranscript(config), TypeError); assert.equal(calls, 0);
	for (let i = 0; i < 6; i++) assert.equal(f.send(i), true);
	const result = JSON.stringify(f.finish()); assert.ok(!result.includes('a'.repeat(64))); assert.ok(!result.includes('103'));
});
