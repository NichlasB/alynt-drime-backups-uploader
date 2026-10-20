import test from 'node:test';
import assert from 'node:assert/strict';
import { createDrainWitness } from '../../scripts/tests/fixed-drain-witness.mjs';
import { drainWitness, frame, ok, commit, drainPackets } from './support/fixed-drain-fixtures.mjs';
import { greeting, hello } from './support/native-witness-fixtures.mjs';

test('positive entire successor and redacted immutable synthetic-only result', () => {
	const f = drainWitness(); assert.equal(f.ready(), true); assert.equal(f.selected(), true); assert.equal(f.drain(), true);
	const result = f.finish(); assert.equal(result.synthetic_match, true); assert.equal(result.selected_commit, true); assert.equal(result.ok_egress, true); assert.ok(Object.isFrozen(result));
	for (const k of ['native_proven', 'authentication_proven', 'forwarding_proven', 'storage_supported', 'dispatch_supported', 'execution_supported']) assert.equal(result[k], false);
	assert.equal(f.api.finish(2n, f.receipt), false);
});
test('response may precede request completion; next query may precede previous response completion', () => {
	const f = drainWitness(); assert.equal(f.ready(), true);
	const request = f.api.observe(f.ids.writer, 'client', frame(commit()), 2n);
	const response = f.api.observe(f.ids.writer, 'server', frame(ok(), 1), 2n);
	assert.equal(f.api.complete(f.ids.writer, 'client', request, 2n), true);
	const packets = drainPackets(); const first = f.api.observe(f.ids.writer, 'client', packets.shift(), 2n);
	assert.equal(f.api.complete(f.ids.writer, 'server', response, 2n), true); assert.equal(f.api.complete(f.ids.writer, 'client', first, 2n), true);
	for (const p of packets) assert.equal(f.send('writer', p[3] ? 'server' : 'client', p, 2n), true);
	assert.equal(f.finish().synthetic_match, true);
});
for (const mode of ['unbound', 'wrong-pair', 'wrong-direction', 'wrong-credit', 'duplicate-credit', 'missing-credit', 'early-final', 'wrong-root', 'zero-checks', 'false-receipt', 'extra-receipt', 'auth-first', 'writer-early', 'deadline', 'regression', 'late-bind', 'no-drain', 'extra-query']) test(`successor fails closed ${mode}`, () => {
	const f = drainWitness(); assert.equal(f.ready(mode !== 'unbound'), true);
	if (mode === 'unbound') assert.equal(f.send('writer', 'client', frame(commit())), false);
	else if (['wrong-pair', 'wrong-direction', 'wrong-credit', 'duplicate-credit', 'missing-credit'].includes(mode)) {
		const c = f.api.observe(f.ids.writer, 'client', frame(commit()), 2n);
		if (mode === 'missing-credit') { assert.equal(f.send('writer', 'server', frame(ok(), 1), 2n), true); assert.equal(f.drain(), false); }
		else if (mode === 'duplicate-credit') { assert.equal(f.api.complete(f.ids.writer, 'client', c, 2n), true); assert.equal(f.api.complete(f.ids.writer, 'client', c, 2n), false); }
		else assert.equal(f.api.complete(mode === 'wrong-pair' ? f.ids.auth : f.ids.writer, mode === 'wrong-direction' ? 'server' : 'client', mode === 'wrong-credit' ? Symbol() : c, 2n), false);
	} else if (mode === 'early-final') assert.equal(f.claim(), false);
	else if (mode === 'auth-first') assert.equal(f.api.close(f.ids.auth, 2n), false);
	else if (mode === 'writer-early') assert.equal(f.api.close(f.ids.writer, 2n), false);
	else if (mode === 'deadline') assert.equal(f.send('writer', 'client', frame(commit()), 10000000000n), false);
	else if (mode === 'regression') assert.equal(f.send('writer', 'client', frame(commit()), 0n), false);
	else if (mode === 'late-bind') assert.equal(f.api.bind(f.ids.writer, 4000000001n), false);
	else {
		assert.equal(f.selected(), true);
		if (mode === 'no-drain') { assert.equal(f.claim(), false); }
		else {
			assert.equal(f.drain(), true);
			if (mode === 'wrong-root') assert.equal(f.api.claim(Symbol(), f.ids.writer, { passed: true, checks: 1, groups: 1 }, 2n), false);
			else if (mode === 'zero-checks') assert.equal(f.api.claim(f.root, f.ids.writer, { passed: true, checks: 0, groups: 1 }, 2n), false);
			else if (mode === 'extra-query') assert.equal(f.send('writer', 'client', frame(Buffer.from('\x03SELECT 1')), 2n), false);
			else { f.receipt[mode === 'false-receipt' ? 'guards_passed' : 'foreign'] = false; assert.equal(f.finish().synthetic_match, false); return; }
		}
	}
	assert.equal(f.api.finish(2n, f.receipt).synthetic_match, false);
});
test('original deadline cannot refresh; timely logical completion permits bounded later cleanup', () => {
	const f = drainWitness(); assert.equal(f.ready(), true); assert.equal(f.selected(), true); assert.equal(f.drain(9999999999n), true); assert.equal(f.claim(9999999999n), true);
	assert.equal(f.api.close(f.ids.writer, 10000000001n), true); assert.equal(f.send('auth', 'client', frame(Buffer.from([1])), 10000000001n), true); assert.equal(f.api.close(f.ids.auth, 10000000001n), true);
	assert.equal(f.api.finish(10000000002n, f.receipt).synthetic_match, true);
	const g = drainWitness(); assert.equal(g.ready(), true); assert.equal(g.selected(), true); assert.equal(g.drain(), true); assert.equal(g.claim(10000000000n), false);
});
test('constructor refuses duplicate lifetime/root and invalid clocks', () => {
	for (const patch of [{ startedNs: -1n }, { root: Symbol.for('public') }]) { const f = drainWitness(); assert.throws(() => createDrainWitness({ ...f.config, ...patch }), TypeError); }
	const f = drainWitness(); f.config.lifetimes.capture = f.ids.writer; assert.throws(() => createDrainWitness(f.config), TypeError);
});
for (const width of [1, 3, 4, 13, 65536]) test(`successor streaming drain width ${width} preserves borrowed input`, () => {
	const f = drainWitness(); assert.equal(f.ready(), true); assert.equal(f.selected(), true);
	const packets = drainPackets();
	// Coalesce each server result while retaining protocol direction transitions.
	for (const block of [packets[0], Buffer.concat(packets.slice(1, 15)), packets[15], Buffer.concat(packets.slice(16, 25)), packets[25]]) {
		const original = Buffer.from(block), dir = block[3] === 0 ? 'client' : 'server';
		for (let i = 0; i < block.length; i += width) {
			const credits = f.api.feed(f.ids.writer, dir, block.subarray(i, i + width), 2n, (credit) => assert.equal(f.api.complete(f.ids.writer, dir, credit, 2n), true));
			assert.ok(Array.isArray(credits));
		}
		assert.deepEqual(block, original);
	}
	assert.equal(f.finish().synthetic_match, true);
});
test('partial opposite stream prevents selection and incomplete streams prevent final/close', () => {
	const f = drainWitness(); assert.equal(f.ready(), true);
	assert.deepEqual(f.api.feed(f.ids.writer, 'server', frame(ok(), 1).subarray(0, 2), 2n), []);
	assert.equal(f.send('writer', 'client', frame(commit()), 2n), false);
	const g = drainWitness(); assert.equal(g.ready(), true); assert.equal(g.selected(), true); assert.equal(g.drain(), true);
	assert.deepEqual(g.api.feed(g.ids.auth, 'server', Buffer.from([1]), 2n), []); assert.equal(g.claim(), false);
});
test('pinned root/profile are copied and forged reconfiguration cannot replace the original', () => {
	const f = drainWitness(); f.config.root = Symbol('replacement'); f.config.username = 'foreign'; f.config.database = 'foreign'; f.config.startedNs = 10000000000n;
	assert.equal(f.ready(), true); assert.equal(f.selected(), true); assert.equal(f.drain(), true);
	assert.equal(f.api.claim(f.config.root, f.ids.writer, { passed: true, checks: 1, groups: 1 }, 2n), false);
	const g = drainWitness(); g.config.root = Symbol(); assert.equal(g.ready(), true); assert.equal(g.selected(), true); assert.equal(g.drain(), true); assert.equal(g.finish().synthetic_match, true);
});
test('throwing/reentrant synthetic sink poisons proof without modifying caller bytes', () => {
	for (const reentrant of [false, true]) {
		const f = drainWitness(); assert.equal(f.ready(), true); assert.equal(f.selected(), true);
		const bytes = drainPackets()[0], original = Buffer.from(bytes);
		const output = f.api.feed(f.ids.writer, 'client', bytes, 2n, () => { if (reentrant) f.api.feed(f.ids.writer, 'client', bytes, 2n); else throw Error('invented sink'); });
		assert.equal(output, false); assert.deepEqual(bytes, original); assert.equal(f.api.finish(2n, f.receipt).synthetic_match, false);
	}
});
test('all terminal evidence is conjunctive, with cleanup bounded and final only provisional', () => {
	for (const key of Object.keys(drainWitness().receipt)) {
		const f = drainWitness(); assert.equal(f.ready(), true); assert.equal(f.selected(), true); assert.equal(f.drain(), true); f.receipt[key] = false;
		assert.equal(f.finish().synthetic_match, false, key);
	}
	const f = drainWitness(); assert.equal(f.ready(), true); assert.equal(f.selected(), true); assert.equal(f.drain(), true); assert.equal(f.claim(), true);
	assert.equal(f.api.finish(2n, f.receipt).synthetic_match, false);
	const g = drainWitness(); assert.equal(g.ready(), true); assert.equal(g.selected(), true); assert.equal(g.drain(), true); assert.equal(g.claim(), true);
	assert.equal(g.api.close(g.ids.writer, 28000000000n), false);
});
test('bounded whole-attempt byte/frame limits and shared reservations need no real load', () => {
	const f = drainWitness(); assert.equal(f.ready(), true);
	const large = frame(Buffer.concat([Buffer.from([3]), Buffer.alloc(2097151, 65)]));
	for (let i = 0; i < 31; i++) assert.equal(f.send('writer', 'client', large), true);
	assert.equal(f.send('writer', 'client', large), false);
	const g = drainWitness(); assert.equal(g.ready(), true); const small = frame(Buffer.from([25]));
	for (let i = 0; i < 4093; i++) assert.equal(g.send('writer', 'client', small), true);
	assert.equal(g.send('writer', 'client', small), false);
	const h = drainWitness(); assert.equal(h.ready(), true); const header = Buffer.from([0, 0, 32, 0]);
	for (const [role, dir] of [['auth', 'server'], ['auth', 'client'], ['writer', 'client']]) assert.deepEqual(h.api.feed(h.ids[role], dir, header, 2n), []);
	assert.equal(h.api.feed(h.ids.writer, 'server', header, 2n), false);
	assert.equal(h.api.finish(2n, h.receipt).synthetic_match, false);
});
test('streaming clean auth QUIT may finish after the timely provisional claim', () => {
	const f = drainWitness(); assert.equal(f.ready(), true); assert.equal(f.selected(), true); assert.equal(f.drain(), true); assert.equal(f.claim(), true);
	assert.equal(f.api.close(f.ids.writer, 10000000001n), true);
	assert.ok(Array.isArray(f.api.feed(f.ids.auth, 'client', frame(Buffer.from([1])), 10000000001n, (credit) => assert.equal(f.api.complete(f.ids.auth, 'client', credit, 10000000001n), true))));
	assert.equal(f.api.close(f.ids.auth, 10000000001n), true); assert.equal(f.api.finish(10000000002n, f.receipt).synthetic_match, true);
});
test('initial profiles, wrong role, exact six-second boundary and duplicate binding', () => {
	const f = drainWitness(); assert.equal(f.api.bind(f.ids.writer, 1n), false);
	const g = drainWitness(); assert.equal(g.ready(false), true); assert.equal(g.api.bind(g.ids.auth, 1n), false);
	const h = drainWitness(); assert.equal(h.ready(false), true); assert.equal(h.api.bind(h.ids.writer, 4000000000n), true);
	assert.equal(h.api.bind(h.ids.writer, 4000000000n), false);
});
test('live capture or mismatched writer/capture profile cannot bind', () => {
	for (const live of [true, false]) {
		const f = drainWitness();
		for (const [i, role] of ['auth', 'capture', 'writer'].entries()) {
			assert.equal(f.send(role, 'server', frame(greeting(101 + i), 0)), true);
			assert.equal(f.send(role, 'client', frame(hello(role === 'writer' && !live ? 0x8a209 : 0x8a208), 1)), true);
			assert.equal(f.send(role, 'server', frame(ok(), 2)), true);
		}
		if (!live) assert.equal(f.api.close(f.ids.capture, 1n), true);
		assert.equal(f.api.bind(f.ids.writer, 1n), false);
	}
});
