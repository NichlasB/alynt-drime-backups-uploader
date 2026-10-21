import test from 'node:test';
import assert from 'node:assert/strict';
import { witnessFixture, cleanup, frame, greeting, hello, ok, commit } from './support/native-qualification-fixture.mjs';
for (const noFault of [false, true]) test(`four-role/eight-stream ${noFault ? 'no-fault' : 'selected'} accounting is positive but never native authority`, () => {
	const f = witnessFixture(noFault); assert.equal(f.ready(), true); assert.equal(f.select(), true); assert.equal(f.observe(), true);
	const r = f.finish(); assert.equal(r.synthetic_match, true); assert.equal(r.simulated_cut, !noFault); assert.equal(r.withheld_bytes, noFault ? 0 : 11); assert.equal(r.target_write_attempts, noFault ? 1 : 0); assert.equal(r.accounting_balanced, true);
	for (const [k, v] of Object.entries(r)) if (!['synthetic_match', 'simulated_cut', 'withheld_bytes', 'target_write_attempts', 'accounting_balanced'].includes(k)) assert.equal(v, false, k);
	assert.equal(f.api.finish(5n, cleanup()), false);
});
test('selected response accepts fragmentation but cut cannot occur at a partial/coalesced boundary', () => {
	for (const width of [1, 2, 7, 11]) {
		const f = witnessFixture(); assert.equal(f.ready(), true); assert.equal(f.send('writer', 'client', commit(), 0, 2n), true);
		const bytes = frame(ok(), 1); let key;
		for (let at = 0; at < bytes.length; at += width) {
			const keys = f.api.feed(f.ids.writer, 'server', bytes.subarray(at, at + width), 2n); assert.ok(keys);
			if (at + width < bytes.length) assert.equal(keys.length, 0); else { assert.equal(keys.length, 1); key = keys[0]; }
		}
		assert.equal(f.api.decide(f.root, f.ids.writer, key, 2n), true); assert.equal(f.observe(), true); assert.equal(f.finish().synthetic_match, true);
	}
	const f = witnessFixture(); assert.equal(f.ready(), true); assert.equal(f.send('writer', 'client', commit(), 0, 2n), true);
	assert.equal(f.api.feed(f.ids.writer, 'server', Buffer.concat([frame(ok(), 1), frame(ok(), 1)]), 2n), false); assert.equal(f.api.finish(3n, cleanup()).synthetic_match, false);
	const tail = witnessFixture(); assert.equal(tail.ready(),true); assert.equal(tail.send('writer','client',commit(),0,2n),true);
	const keys = tail.api.feed(tail.ids.writer,'server',Buffer.concat([frame(ok(),1),Buffer.from([1])]),2n); assert.equal(keys.length,1);
	assert.equal(tail.api.decide(tail.root,tail.ids.writer,keys[0],2n),false); assert.equal(tail.api.finish(3n,cleanup()).synthetic_match,false);
});
test('first-four-second eligibility is separate from original20s root and10s read deadlines', () => {
	for (const tick of [4000000000n, 4000000001n]) {
		const f = witnessFixture(); assert.equal(f.ready(), true); assert.equal(f.select(tick), tick === 4000000000n);
		if (tick === 4000000000n) { assert.equal(f.observe(tick + 1n), true); assert.equal(f.finish(tick + 2n).synthetic_match, true); }
		else assert.equal(f.api.finish(tick + 1n, cleanup()).synthetic_match, false);
	}
	for (const tick of [10000000003n, 20000000000n]) {
		const f = witnessFixture(); assert.equal(f.ready(), true); assert.equal(f.select(), true); assert.equal(f.observe(), true);
		assert.equal(f.api.claim(f.root, JSON.stringify(f.frames[7]), tick), false); assert.equal(f.api.finish(tick, cleanup()).synthetic_match, false);
	}
});
test('both borrowed/owned identity and role lifetimes are required; no fifth or recycled barrier', () => {
	for (const fault of ['premature', 'fifth', 'writer-role', 'root', 'capture-live', 'recycle-thread']) {
		const f = witnessFixture();
		if (fault === 'capture-live') {
			for (const [role, id] of [['auth',101], ['capture',102], ['writer',103]]) assert.equal(f.handshake(role,id), true);
			assert.equal(f.api.bindWriter(f.root, f.ids.writer, 1n), false);
		} else {
			assert.equal(f.ready(), true);
			if (fault === 'premature') assert.equal(f.api.openBarrier(f.root, f.ids.barrier, 2n), false);
			if (fault === 'fifth') assert.equal(f.api.feed(Symbol('fifth'), 'server', frame(greeting(105)), 2n), false);
			if (fault === 'writer-role') assert.equal(f.api.bindWriter(f.root, f.ids.auth, 2n), false);
			if (fault === 'root') assert.equal(f.api.bindWriter(Symbol('other'), f.ids.writer, 2n), false);
			if (fault === 'recycle-thread') {
				assert.equal(f.select(), true); assert.equal(f.api.close(f.ids.writer, 3n), true); assert.equal(f.api.beginRead(f.root, 3n), true); assert.equal(f.api.openBarrier(f.root, f.ids.barrier, 3n), true);
				assert.equal(f.api.feed(f.ids.barrier, 'server', frame(greeting(103)), 3n), false);
			}
		}
		assert.equal(f.api.finish(4n, cleanup()).synthetic_match, false);
	}
});
test('request credit must complete before OK, and any attempted target write permanently disqualifies selection', () => {
	for (const fault of ['early-ok', 'attempt', 'partial-attempt', 'wrong-credit', 'failed-credit', 'barrier-target']) {
		const f = witnessFixture(); assert.equal(f.ready(), true);
		const req = frame(commit()), [request] = f.api.feed(f.ids.writer, 'client', req, 2n);
		if (fault !== 'early-ok') {
			assert.equal(f.api.attempt(f.ids.writer, 'client', request, req, 2n), true);
			if (fault === 'failed-credit') { assert.equal(f.api.complete(f.ids.writer, 'client', request, 2n, false), false); assert.equal(f.api.finish(3n, cleanup()).synthetic_match, false); continue; }
			assert.equal(f.api.complete(f.ids.writer, 'client', request, 2n), true);
		}
		const reply = frame(ok(), 1), [response] = f.api.feed(f.ids.writer, 'server', reply, 2n);
		if (fault === 'attempt') assert.equal(f.api.attempt(f.ids.writer, 'server', response, reply, 2n), true);
		if (fault === 'partial-attempt') assert.equal(f.api.attempt(f.ids.writer, 'server', response, reply.subarray(0, 1), 2n), false);
		assert.equal(f.api.decide(f.root, fault === 'barrier-target' ? f.ids.barrier : f.ids.writer, fault === 'wrong-credit' ? Symbol('wrong') : response, 2n), false);
		assert.equal(f.api.finish(3n, cleanup()).synthetic_match, false);
	}
});
test('late/ineligible bytes can still be transparently accounted, but never regain selected eligibility', () => {
	const f = witnessFixture(); assert.equal(f.ready(), true); assert.equal(f.send('writer', 'client', commit(), 0, 4000000001n), true);
	const bytes = frame(ok(), 1), [key] = f.api.feed(f.ids.writer, 'server', bytes, 4000000001n);
	assert.equal(f.forward('writer', 'server', bytes, key, 4000000001n), true);
	assert.equal(f.api.decide(f.root, f.ids.writer, key, 4000000001n), false); const r = f.api.finish(4000000002n, cleanup());
	assert.equal(r.synthetic_match, false); assert.equal(r.simulated_cut, false); assert.equal(r.accounting_balanced, true);
});
test('post-cut failure cannot rearm or forward the withheld response', () => {
	const f = witnessFixture(); assert.equal(f.ready(), true); assert.equal(f.select(), true);
	assert.equal(f.api.feed(f.ids.writer, 'client', frame(commit()), 3n), false); assert.equal(f.observe(), false);
	const r = f.api.finish(4n, cleanup()); assert.equal(r.synthetic_match, false); assert.equal(r.simulated_cut, true); assert.equal(r.withheld_bytes, 11);
});
test('request selection cannot cross a partial opposite-stream boundary, even if it drains later', () => {
	const f = witnessFixture(); assert.equal(f.ready(), true);
	const authBytes = frame(ok(), 1); assert.deepEqual(f.api.feed(f.ids.auth, 'server', authBytes.subarray(0,1), 2n), []);
	assert.equal(f.send('writer', 'client', commit(), 0, 2n), true);
	const [authCredit] = f.api.feed(f.ids.auth, 'server', authBytes.subarray(1), 2n); assert.equal(f.forward('auth','server',authBytes,authCredit,2n),true);
	const [reply] = f.api.feed(f.ids.writer, 'server', frame(ok(),1), 2n);
	assert.equal(f.api.decide(f.root,f.ids.writer,reply,2n),false); assert.equal(f.api.finish(3n,cleanup()).synthetic_match,false);
});
test('no general post-cut traffic exemption or inferred native SQL-profile support', () => {
	for (const role of ['auth','barrier']) {
		const f = witnessFixture(); assert.equal(f.ready(),true); assert.equal(f.select(),true);
		if (role === 'barrier') { assert.equal(f.api.close(f.ids.writer,3n),true); assert.equal(f.api.beginRead(f.root,3n),true); assert.equal(f.api.openBarrier(f.root,f.ids.barrier,3n),true); assert.equal(f.handshake('barrier',104,3n),true); assert.equal(f.api.bindBarrier(f.root,f.ids.barrier,3n),true); }
		assert.equal(f.api.feed(f.ids[role],'client',frame(Buffer.from('\x03SELECT arbitrary_not_a_supported_trace')),3n),false);
		assert.equal(f.api.finish(4n,cleanup()).synthetic_match,false);
	}
});
test('each cleanup conjunction, private FINAL predicate and all four greeting identities are required', () => {
	for (const key of Object.keys(cleanup())) {
		const f = witnessFixture(); assert.equal(f.ready(), true); assert.equal(f.select(), true); assert.equal(f.observe(), true);
		assert.equal(f.finish(4n, { ...cleanup(), [key]: false }).synthetic_match, false, key);
	}
	for (const fault of ['root-false', 'old-guards', 'unreleased', 'wrong-barrier', 'authority', 'cross-root', 'cross-attempt']) {
		const f = witnessFixture(); assert.equal(f.ready(), true); assert.equal(f.select(), true); assert.equal(f.observe(), true);
		const m = structuredClone(f.frames[7]);
		if (fault === 'root-false') m.native_result = false;
		if (fault === 'old-guards') { m.pre_guards = 8; m.post_guards = 0; }
		if (fault === 'unreleased') m.observation.result.client_release_claimed = false;
		if (fault === 'wrong-barrier') m.barrier = 105;
		if (fault === 'authority') m.native_result.execution_supported = true;
		if (fault === 'cross-root') m.root = 'e'.repeat(64);
		if (fault === 'cross-attempt') m.attempt = 'e'.repeat(64);
		assert.equal(f.finish(4n, cleanup(), m), false); assert.equal(f.api.finish(5n, cleanup()).synthetic_match, false);
	}
});
test('framing/aggregate caps reject before oversized allocation; closed/replayed/foreign credits fail', () => {
	for (const fault of ['frame', 'bytes', 'partial-close', 'credit-replay', 'extra-arg', 'backward']) {
		const f = witnessFixture(); assert.equal(f.ready(), true);
		if (fault === 'frame') { const b = Buffer.alloc(4); b.writeUIntLE(2097153,0,3); assert.equal(f.api.feed(f.ids.writer, 'client', b, 2n), false); }
		if (fault === 'bytes') assert.equal(f.api.feed(f.ids.writer, 'client', Buffer.alloc(67108865), 2n), false);
		if (fault === 'partial-close') { assert.deepEqual(f.api.feed(f.ids.writer, 'client', frame(commit()).subarray(0, 1), 2n), []); assert.equal(f.api.close(f.ids.writer, 2n), false); }
		if (fault === 'credit-replay') { const bytes = frame(commit()), [k] = f.api.feed(f.ids.writer, 'client', bytes, 2n); assert.equal(f.forward('writer','client',bytes,k,2n),true); assert.equal(f.api.complete(f.ids.writer,'client',k,2n),false); }
		if (fault === 'extra-arg') assert.equal(f.api.bindWriter(f.root, f.ids.writer, 2n, 'extra'), false);
		if (fault === 'backward') assert.equal(f.api.close(f.ids.writer, 0n), false);
		assert.equal(f.api.finish(3n, cleanup()).synthetic_match, false);
	}
});
