// Invented packets / actual passive adapter. No WordPress, sockets or database.
import test from 'node:test';
import assert from 'node:assert/strict';
import { createObservationRegistry } from '../../scripts/tests/observation-native-registry.mjs';
import { passiveObservation } from '../../scripts/tests/observation-passive-sink.mjs';
import { frameStream } from '../../scripts/tests/relay-no-fault-frames.mjs';
import { frame, greeting, ok, commit } from './support/native-witness-fixtures.mjs';
import { drainPackets } from './support/fixed-drain-fixtures.mjs';
import { readFile } from 'node:fs/promises';

function fixture(factory = createObservationRegistry) {
	const api = factory(), ids = { auth: Symbol(), capture: Symbol(), writer: Symbol() }, threads = { auth: 101, capture: 102, writer: 103 };
	const send = (role, dir, bytes, account = true) => {
		const credit = Symbol(); assert.equal(api.header(ids[role], dir, bytes.length - 4), true);
		assert.equal(api.frame(ids[role], dir, bytes, credit), true);
		if (account) assert.equal(api.account(ids[role], dir, credit), true);
		return credit;
	};
	const ready = () => {
		assert.equal(api.root(process.hrtime.bigint()), true);
		for (const role of Object.keys(ids)) {
			assert.equal(api.open(ids[role]), true); assert.equal(api.boundary(ids[role], () => true), true);
			send(role, 'server', frame(greeting(threads[role]), 0));
			const h = Buffer.alloc(32); h.writeUInt32LE(role === 'auth' ? 0x8a200 : 0x8a208); h.writeUInt32LE(2097152, 4); h[8] = 45;
			send(role, 'client', frame(Buffer.concat([h, Buffer.from('root\0'), Buffer.from([20]), Buffer.alloc(20, 67), role === 'auth' ? Buffer.alloc(0) : Buffer.from('local\0'), Buffer.from('mysql_native_password\0')]), 1));
			send(role, 'server', frame(ok(), 2));
		}
		assert.equal(api.close(ids.capture), true); assert.equal(api.bind(threads), true);
	};
	const selected = () => { send('writer', 'client', frame(commit())); send('writer', 'server', frame(ok(), 1)); };
	const drain = () => { for (const packet of drainPackets()) send('writer', packet[3] === 0 ? 'client' : 'server', packet); };
	const complete = () => { assert.equal(api.close(ids.writer), true); assert.equal(api.claim(), true); send('auth', 'client', frame(Buffer.from([1]))); assert.equal(api.close(ids.auth), true); return api.finish(true); };
	return { api, ids, threads, send, ready, selected, drain, complete };
}

test('actual adapter has a non-vacuous positive fixed-drain path', () => {
	const f = fixture(); f.ready(); f.selected(); f.drain(); const r = f.complete();
	assert.equal(r.passed, true); assert.equal(r.fixed_drain_complete, true); assert.equal(r.suppression_supported, false);
	assert.equal(f.api.finish(true).passed, false);
});
for (const kind of ['pair', 'direction', 'token', 'replay']) test(`exact forwarding credit refuses ${kind}`, () => {
	const f = fixture(); f.ready(); const credit = f.send('writer', 'client', frame(commit()), false);
	if (kind === 'replay') assert.equal(f.api.account(f.ids.writer, 'client', credit), true);
	assert.equal(f.api.account(kind === 'pair' ? f.ids.auth : f.ids.writer, kind === 'direction' ? 'server' : 'client', kind === 'token' ? Symbol() : credit), false);
	assert.equal(f.api.finish(true).passed, false);
});
test('both response/request completion races retain exact selected credits', () => {
	const f = fixture(); f.ready(); const c = f.send('writer', 'client', frame(commit()), false);
	const s = f.send('writer', 'server', frame(ok(), 1), false);
	assert.equal(f.api.account(f.ids.writer, 'client', c), true);
	f.send('writer', 'client', drainPackets()[0]); assert.equal(f.api.account(f.ids.writer, 'server', s), true);
	for (const packet of drainPackets().slice(1)) f.send('writer', packet[3] === 0 ? 'client' : 'server', packet);
	assert.equal(f.complete().passed, true);
});
test('early final and unavailable observer cannot pass', () => {
	const f = fixture(); f.ready(); assert.equal(f.api.claim(), false); assert.equal(f.api.finish(true).passed, false);
	const g = fixture(); g.ready(); g.api.unavailable(); assert.equal(g.api.finish(true).passed, false);
});
test('actual adapter rejects malformed fixed drain rather than arbitrary SELECT', () => {
	const f = fixture(); f.ready(); f.selected();
	assert.equal(f.api.frame(f.ids.writer, 'client', frame(Buffer.from('\x03SELECT 1')), Symbol()), false);
});
test('header refusal is latched before transport allocation and never throws into egress', async () => {
	const f = fixture(); f.ready(); f.selected(); let allocated = false, seen = false;
	const sink = passiveObservation(f.api), h = Buffer.alloc(4); h.writeUIntLE(4097, 0, 3);
	const parser = frameStream(async () => {}, () => { allocated = true; assert.equal(seen, true); }, () => {}, (size) => { sink.call('header', f.ids.writer, 'server', size); seen = sink.failed(); assert.equal(allocated, false); });
	await parser.feed(h); assert.equal(seen, true); assert.equal(allocated, true); assert.equal(parser.finish().complete, false);
	assert.equal(f.api.finish(true).passed, false);
});
for (const mode of ['throw', 'false']) test(`passive ${mode} and failing reporter do not stop forwarding`, async () => {
	let calls = 0, reports = 0, sent = 0;
	const sink = passiveObservation({ frame() { calls++; if (mode === 'throw') throw Error('private'); return false; }, unavailable() { reports++; throw Error('private'); } });
	const bytes = frame(Buffer.from([1])), copy = Buffer.from(bytes);
	const parser = frameStream(async (b) => { sink.call('frame', b); sent += b.length; });
	await parser.feed(Buffer.concat([bytes, bytes])); const r = parser.finish();
	assert.equal(r.equal, true); assert.equal(sent, bytes.length * 2); assert.equal(calls, 1); assert.equal(reports, 1); assert.equal(sink.failed(), true); assert.deepEqual(bytes, copy);
});
test('original expired root cannot bind or finalize without resetting its clock', () => {
	const api = createObservationRegistry(); assert.equal(api.root(process.hrtime.bigint() - 10000000000n), true);
	assert.equal(api.bind({ auth: 101, capture: 102, writer: 103 }), false); assert.equal(api.finish(true).passed, false);
});
test('opposite partial response forbids selected COMMIT', () => {
	const f = fixture(); f.ready(); f.api.boundary(f.ids.writer, () => false);
	assert.equal(f.api.frame(f.ids.writer, 'client', frame(commit()), Symbol()), false);
});
test('transport receipt is conjunctive, not replaced by the private final claim', () => {
	const f = fixture(); f.ready(); f.selected(); f.drain(); assert.equal(f.api.close(f.ids.writer), true); assert.equal(f.api.claim(), true);
	f.send('auth', 'client', frame(Buffer.from([1]))); assert.equal(f.api.close(f.ids.auth), true); assert.equal(f.api.finish(false).passed, false);
});

// In-memory semantic controls import actual adapter source with exactly one guard
// disabled. No source file, authentication, native session or SQL is mutated.
const sourceUrl = new URL('../../scripts/tests/observation-native-registry.mjs', import.meta.url);
const original = await readFile(sourceUrl, 'utf8');
async function altered(needle, replacement) {
	assert.equal(original.split(needle).length, 2, 'Control must have one exact source target.');
	const source = original.replace(needle, replacement).replace(/from '(\.\/[^']+)'/g, (_, path) => `from '${new URL(path, sourceUrl).href}'`);
	return (await import('data:text/javascript;base64,' + Buffer.from(source).toString('base64'))).createObservationRegistry;
}
for (const [name, needle, replacement, exercise] of [
	['positive owner', 'if (failed || finished || typeof id', 'if (true || failed || finished || typeof id', (f) => { f.ready(); f.selected(); f.drain(); assert.equal(f.complete().passed, true); }],
	['exact credit', 'row.pending[direction].credit !== credit', 'false', (f) => { f.ready(); f.send('writer', 'client', frame(commit()), false); assert.equal(f.api.account(f.ids.writer, 'client', Symbol()), false); }],
	['fixed query', 'if (!drain.accept(direction, seq, bytes))', 'if (false)', (f) => { f.ready(); f.selected(); assert.equal(f.api.frame(f.ids.writer, 'client', frame(Buffer.from('\x03SELECT 1')), Symbol()), false); }],
	['final claim', 'claimed && budget(true)', 'true && budget(true)', (f) => { f.ready(); f.selected(); f.drain(); f.api.close(f.ids.writer); f.send('auth', 'client', frame(Buffer.from([1]))); f.api.close(f.ids.auth); assert.equal(f.api.finish(true).passed, false); }],
	['transport receipt', 'transportPassed === true', 'true', (f) => { f.ready(); f.selected(); f.drain(); f.api.close(f.ids.writer); f.api.claim(); f.send('auth', 'client', frame(Buffer.from([1]))); f.api.close(f.ids.auth); assert.equal(f.api.finish(false).passed, false); }],
	['partial boundary', "direction === 'client' && !row.boundary('server')", 'false', (f) => { f.ready(); f.api.boundary(f.ids.writer, () => false); assert.equal(f.api.frame(f.ids.writer, 'client', frame(commit()), Symbol()), false); }],
]) test(`assertion-killed semantic control: ${name}`, async () => {
	const factory = await altered(needle, replacement); assert.equal(typeof factory, 'function');
	assert.throws(() => exercise(fixture(factory)), { code: 'ERR_ASSERTION' });
});
