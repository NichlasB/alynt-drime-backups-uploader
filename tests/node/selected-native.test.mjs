// Synthetic adapter/framing/virtual lifetime tests only; no WordPress, SQL or sockets.
import test from 'node:test';
import assert from 'node:assert/strict';
import { createSelectedNativeRegistry } from '../../scripts/tests/selected-native-registry.mjs';
import { selectedNativeFrames } from '../../scripts/tests/selected-native-frames.mjs';
import { boundedSettlement, closeOwnedChild } from '../../scripts/tests/selected-native-lifetime.mjs';
import { frame, greeting, ok, commit } from './support/native-witness-fixtures.mjs';
import { readFile } from 'node:fs/promises';
function fixture(factory = createSelectedNativeRegistry) {
	const api = factory(), ids = { auth: Symbol(), capture: Symbol(), writer: Symbol() }, threads = { auth: 101, capture: 102, writer: 103 };
	const send = (role, dir, bytes, account = true, tail = 0) => {
		const credit = Symbol(); api.frame(ids[role], dir, bytes, credit, tail); if (account) assert.equal(api.account(ids[role], dir, credit), true); return credit;
	};
	const ready = () => {
		assert.equal(api.root(process.hrtime.bigint()), true);
		for (const role of Object.keys(ids)) {
			assert.equal(api.open(ids[role]), true); assert.equal(api.boundary(ids[role], () => true), true); send(role, 'server', frame(greeting(threads[role])));
			const h = Buffer.alloc(32); h.writeUInt32LE(role === 'auth' ? 0x8a200 : 0x8a208); h[8] = 45;
			send(role, 'client', frame(Buffer.concat([h, Buffer.from('root\0'), Buffer.from([20]), Buffer.alloc(20, 67), role === 'auth' ? Buffer.alloc(0) : Buffer.from('local\0'), Buffer.from('mysql_native_password\0')]), 1));
			send(role, 'server', frame(ok(), 2));
		}
		assert.equal(api.close(ids.capture), true); assert.equal(api.bind(threads), true);
	};
	const selected = () => { send('writer', 'client', frame(commit())); const reply = send('writer', 'server', frame(ok(), 1), false); return api.cut(ids.writer, reply); };
	const complete = () => { assert.equal(api.close(ids.writer), true); assert.equal(api.claim(), true); send('auth', 'client', frame(Buffer.from([1]))); assert.equal(api.close(ids.auth), true); return api.finish(true); };
	return { api, ids, threads, send, ready, selected, complete };
}
test('dedicated real-clock adapter has a non-vacuous synthetic cut/final/closure control', () => {
	const f = fixture(); f.ready(); assert.equal(f.selected(), true); assert.equal(f.complete().passed, true); assert.equal(f.api.finish(true).passed, false);
});
for (const mode of ['pending', 'tail', 'wrong_ok', 'wrong_sequence', 'extra_client', 'partial', 'header', 'wrong_pair', 'wrong_credit']) test(`native candidate refuses ${mode} without a discard`, () => {
	const f = fixture(); f.ready(); const request = f.send('writer', 'client', frame(commit()), mode !== 'pending');
	if (mode === 'extra_client') f.send('writer', 'client', frame(Buffer.from('\x03SELECT 1')));
	if (mode === 'partial') f.api.boundary(f.ids.writer, (d) => d !== 'client');
	if (mode === 'header') assert.equal(f.api.cap(f.ids.writer, 'server', 8), 2097152); // Disqualify candidate, preserve transport cap.
	const bytes = mode === 'wrong_ok' ? frame(Buffer.from([0, 0, 0, 3, 0, 0, 0]), 1) : frame(ok(), mode === 'wrong_sequence' ? 2 : 1);
	const reply = f.send('writer', 'server', bytes, false, mode === 'tail' ? 1 : 0);
	if (mode === 'pending') assert.equal(f.api.account(f.ids.writer, 'client', request), true);
	assert.equal(f.api.cut(mode === 'wrong_pair' ? f.ids.auth : f.ids.writer, mode === 'wrong_credit' ? Symbol() : reply), false);
	assert.equal(f.api.account(f.ids.writer, 'server', reply), true); assert.equal(f.api.finish(true).passed, false);
});
test('after cut no callback, rebind, failure or late attempt can turn into a new match', () => {
	const f = fixture(); f.ready(); assert.equal(f.selected(), true); assert.equal(f.api.bind(f.threads), false);
	assert.equal(f.api.account(f.ids.writer, 'server', Symbol()), false); assert.equal(f.api.finish(true).outcome, 'inconclusive');
	assert.throws(() => f.send('writer', 'client', frame(commit())), /candidate_frame/);
});
test('expired original root and early final refuse', () => {
	const api = createSelectedNativeRegistry(); assert.equal(api.root(process.hrtime.bigint() - 10000000000n), true); assert.equal(api.bind({ auth: 101, capture: 102, writer: 103 }), false);
	const f = fixture(); f.ready(); assert.equal(f.api.claim(), false); assert.equal(f.api.finish(true).passed, false);
});
for (const width of [1, 2, 3, 4, 7, 11]) test(`loss framer conserves prefix plus exact withheld frame at width ${width}`, async () => {
	let queue = 0; const bytes = frame(ok(), 1), copy = Buffer.from(bytes);
	const parser = selectedNativeFrames(async () => 'withheld', (n) => { queue += n; }, (n) => { queue -= n; }, () => 2097152);
	for (let i = 0; i < bytes.length; i += width) await parser.feed(bytes.subarray(i, i + width));
	const r = parser.finish(); assert.equal(r.conserved, true); assert.equal(r.sent, 0); assert.equal(r.withheld, 11); assert.equal(queue, 0); assert.deepEqual(bytes, copy);
});
for (const mode of ['extra', 'partial', 'wrong_disposition', 'callback_error', 'duplicate_cut']) test(`framer never excuses unrelated loss: ${mode}`, async () => {
	const parser = selectedNativeFrames(async () => { if (mode === 'callback_error') throw Error('write'); return mode === 'wrong_disposition' ? true : 'withheld'; }, () => {}, () => {}, () => 2097152);
	const bytes = frame(ok(), 1);
	if (['wrong_disposition', 'callback_error', 'duplicate_cut'].includes(mode)) await assert.rejects(parser.feed(mode === 'duplicate_cut' ? Buffer.concat([bytes, bytes]) : bytes));
	else await parser.feed(Buffer.concat([bytes, mode === 'partial' ? Buffer.from([1]) : frame(Buffer.from([1]))])).catch(() => {});
	assert.equal(parser.finish().conserved, false);
});
test('bounded unresolved/rejected joins and owned child closure do not claim success', async () => {
	assert.equal((await boundedSettlement(new Promise(() => {}), 10)).settled, false); assert.equal((await boundedSettlement(Promise.reject(Error()), 10)).rejected, true);
	const pipe = () => ({ destroyed: false, destroy() { this.destroyed = true; }, resume() {} });
	const child = { exitCode: null, signalCode: null, kill() {}, stdin: pipe(), stdout: pipe(), stderr: pipe() };
	const r = await closeOwnedChild(child, new Promise(() => {}), 10); assert.equal(r.resolved, false); assert.equal(r.pipes_destroyed, true);
});
test('six in-memory native-adapter semantic controls are assertion-killed, no disk edits', async () => {
	const url = new URL('../../scripts/tests/selected-native-registry.mjs', import.meta.url), original = await readFile(url, 'utf8');
	const controls = [
		['cut = true; r.pending.server = null; return true;', 'cut = false; r.pending.server = null; return false;', (f) => { f.ready(); assert.equal(f.selected(), true); }],
		['!requestDone || !enough() || tail !== 0 || ![...rows.values()].every(empty)', 'false || !enough() || tail !== 0 || false', (f) => { f.ready(); const a = f.send('writer', 'client', frame(commit()), false); const b = f.send('writer', 'server', frame(ok(), 1), false); f.api.account(f.ids.writer, 'client', a); assert.equal(f.api.cut(f.ids.writer, b), false); }],
		['tail !== 0', 'false', (f) => { f.ready(); f.send('writer', 'client', frame(commit())); const b = f.send('writer', 'server', frame(ok(), 1), false, 1); assert.equal(f.api.cut(f.ids.writer, b), false); }],
		['p.credit !== credit', 'false', (f) => { f.ready(); f.send('writer', 'client', frame(commit()), false); assert.equal(f.api.account(f.ids.writer, 'client', Symbol()), false); }],
		['transport === true', 'true', (f) => { f.ready(); f.selected(); f.api.close(f.ids.writer); f.api.claim(); f.send('auth', 'client', frame(Buffer.from([1]))); f.api.close(f.ids.auth); assert.equal(f.api.finish(false).passed, false); }],
		['!finished && !failed', '!failed', (f) => { f.ready(); f.selected(); assert.equal(f.complete().passed, true); assert.equal(f.api.finish(true).passed, false); }],
	];
	for (const [needle, replacement, exercise] of controls) {
		assert.equal(original.split(needle).length, 2); const source = original.replace(needle, replacement).replace(/from '(\.\/[^']+)'/g, (_, path) => `from '${new URL(path, url).href}'`);
		const factory = (await import('data:text/javascript;base64,' + Buffer.from(source).toString('base64'))).createSelectedNativeRegistry; assert.equal(typeof factory, 'function');
		assert.throws(() => exercise(fixture(factory)), { code: 'ERR_ASSERTION' }, needle);
	}
	assert.equal(await readFile(url, 'utf8'), original);
});
