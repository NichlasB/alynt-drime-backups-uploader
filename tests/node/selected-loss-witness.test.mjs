import test from 'node:test';
import assert from 'node:assert/strict';
import { createSelectedLossWitness } from '../../scripts/tests/selected-loss-witness.mjs';
import { lossFixture, frame, greeting, hello, ok, commit, summary, receipt } from './support/selected-loss-fixtures.mjs';

test('positive selection accounts exactly eleven bytes and exports no native authority', () => {
	const f = lossFixture(); assert.equal(f.ready(), true); assert.equal(f.select(), true);
	const result = f.complete(); assert.equal(result.synthetic_match, true); assert.equal(result.outcome, 'simulated_match');
	assert.equal(result.withheld_bytes, 11); assert.equal(result.target_write_attempts, 0); assert.equal(result.accounting_balanced, true);
	for (const key of ['native_proven', 'zero_delivery_proven', 'ipc_proven', 'authentication_proven', 'storage_supported', 'dispatch_supported', 'execution_supported', 'suppression_supported']) assert.equal(result[key], false);
	assert.equal(Object.isFrozen(result), true); assert.equal(f.api.finish(4n, receipt()), false);
	assert.equal(f.api.bind(f.root, f.ids.writer, 4n), false); assert.equal(JSON.stringify(result).includes('thread'), false);
});
test('no cut is required for a balanced transparent model; borrowed bytes unchanged', () => {
	const f = lossFixture(); assert.equal(f.ready(), true); assert.equal(f.api.fault(2n), false);
	const bytes = frame(commit()), saved = Buffer.from(bytes), keys = f.api.feed(f.ids.writer, 'client', bytes, 2n);
	assert.equal(f.forward('writer', 'client', bytes, keys[0], 2n), true); assert.deepEqual(bytes, saved);
	assert.equal(f.send('writer', 'server', ok(), 1, 2n), true);
	assert.equal(f.api.close(f.ids.writer, 3n), true); assert.equal(f.api.close(f.ids.auth, 3n), true);
	const r = f.api.finish(3n, receipt()); assert.equal(r.outcome, 'ineligible'); assert.equal(r.accounting_balanced, true); assert.equal(r.withheld_bytes, 0);
});
test('request callback race remains ineligible even after the late callback completes', () => {
	const f = lossFixture(); assert.equal(f.ready(), true);
	const request = f.feed('writer', 'client', commit())[0], bytes = frame(commit());
	assert.equal(f.api.attempt(f.ids.writer, 'client', request, bytes, 1n), true);
	const response = f.feed('writer', 'server', ok(), 1)[0];
	assert.equal(f.api.complete(f.ids.writer, 'client', request, 1n), true);
	assert.equal(f.api.decide(f.root, f.ids.writer, response, 1n), false);
	assert.equal(f.forward('writer', 'server', frame(ok(), 1), response), true);
});
for (const timing of ['request', 'response']) test(`six-second reserve at ${timing} is not refreshed`, () => {
	const f = lossFixture(); assert.equal(f.ready(), true);
	assert.equal(f.send('writer', 'client', commit(), 0, timing === 'request' ? 4000000001n : 2n), true);
	const response = f.feed('writer', 'server', ok(), 1, 4000000001n)[0];
	assert.equal(f.api.decide(f.root, f.ids.writer, response, 4000000001n), false);
});
test('inclusive six-second boundary and bounded cleanup use the original root', () => {
	const f = lossFixture(); assert.equal(f.ready(4000000000n), true); assert.equal(f.select(4000000000n), true);
	assert.equal(f.api.claim(f.root, f.ids.writer, summary(), 9999999999n), true);
	assert.equal(f.api.close(f.ids.writer, 27999999999n), true); assert.equal(f.api.close(f.ids.auth, 27999999999n), true);
	assert.equal(f.api.finish(27999999999n, receipt()).synthetic_match, true);
});
for (const now of [-1n, 1, 10000000000n, 9223372036854775808n]) test(`invalid or expired virtual time ${now}`, () => {
	const f = lossFixture(); assert.equal(f.ready(), true); assert.equal(f.select(), true);
	assert.equal(f.api.claim(f.root, f.ids.writer, summary(), now), false); assert.equal(f.api.finish(3n, receipt()).synthetic_match, false);
});
test('cleanup deadline does not refresh when a modeled final arrives', () => {
	const f = lossFixture(); f.ready(); f.select(); assert.equal(f.api.claim(f.root, f.ids.writer, summary(), 3n), true);
	assert.equal(f.api.close(f.ids.writer, 28000000000n), false); assert.equal(f.api.finish(28000000000n, receipt()).outcome, 'inconclusive');
});
for (const when of ['before', 'after']) test(`any selected egress attempt ${when} cut defeats zero-write match`, () => {
	const f = lossFixture(); f.ready(); f.send('writer', 'client', commit()); const key = f.feed('writer', 'server', ok(), 1)[0];
	if (when === 'after') assert.equal(f.api.decide(f.root, f.ids.writer, key, 1n), true);
	assert.equal(f.api.attempt(f.ids.writer, 'server', key, Buffer.from([7]), 1n), false);
	assert.equal(f.api.decide(f.root, f.ids.writer, key, 1n), false);
	const r = f.api.finish(2n, receipt()); assert.equal(r.synthetic_match, false); assert.equal(r.target_write_attempts, 1); assert.equal(r.simulated_cut, when === 'after');
});
test('one-shot cut cannot replay after external failure, duplicate decision or a late callback', () => {
	const f = lossFixture(); f.ready(); f.send('writer', 'client', commit()); const key = f.feed('writer', 'server', ok(), 1)[0];
	assert.equal(f.api.decide(f.root, f.ids.writer, key, 1n), true); assert.equal(f.api.fault(1n), false);
	assert.equal(f.api.decide(f.root, f.ids.writer, key, 1n), false); assert.equal(f.api.complete(f.ids.writer, 'server', key, 1n), false);
	assert.equal(f.api.finish(2n, receipt()).outcome, 'inconclusive');
});
for (const wrong of ['root', 'pair', 'direction', 'credit', 'bytes', 'callback']) test(`frame credit rejects wrong ${wrong}`, () => {
	const f = lossFixture(); f.ready(); const key = f.feed('writer', 'client', commit())[0];
	let result;
	if (wrong === 'root') result = f.api.bind(Symbol(), f.ids.writer, 1n);
	else if (wrong === 'callback') result = f.api.complete(f.ids.writer, 'client', key, 1n);
	else result = f.api.attempt(wrong === 'pair' ? f.ids.auth : f.ids.writer, wrong === 'direction' ? 'server' : 'client', wrong === 'credit' ? Symbol() : key, wrong === 'bytes' ? frame(ok()) : frame(commit()), 1n);
	assert.equal(result, false); assert.equal(f.api.finish(2n, receipt()).synthetic_match, false);
});
for (const body of [Buffer.from('\x03SELECT "COMMIT AND NO CHAIN NO RELEASE"'), Buffer.from('\x16COMMIT AND NO CHAIN NO RELEASE'), Buffer.from('\x03COMMIT'), Buffer.from('\x03commit and no chain no release')]) test(`opaque non-target command ${body[0]}:${body.length}`, () => {
	const f = lossFixture(); f.ready(); assert.equal(f.send('writer', 'client', body), true);
	const key = f.feed('writer', 'server', ok(), 1)[0]; assert.equal(f.api.decide(f.root, f.ids.writer, key, 1n), false);
});
for (const body of [Buffer.from([0xff, 1]), Buffer.from([0xfe]), Buffer.from([0]), Buffer.from([0, 0, 0, 3, 0, 0, 0]), Buffer.from([0, 0, 0, 2, 0, 1, 0]), Buffer.concat([ok(), Buffer.from([0])])]) test(`noncanonical first reply ${body.toString('hex')}`, () => {
	const f = lossFixture(); f.ready(); f.send('writer', 'client', commit()); const keys = f.feed('writer', 'server', body, 1);
	assert.equal(f.api.decide(f.root, f.ids.writer, keys?.[0], 1n), false);
});
for (const tail of [Buffer.from([1]), frame(ok(), 2)]) test(`coalesced extra or partial response prevents selection ${tail.length}`, () => {
	const f = lossFixture(); f.ready(); f.send('writer', 'client', commit());
	const keys = f.api.feed(f.ids.writer, 'server', Buffer.concat([frame(ok(), 1), tail]), 1n);
	assert.equal(f.api.decide(f.root, f.ids.writer, keys?.[0], 1n), false);
});
test('unexpected client command between COMMIT and OK cannot borrow the OK', () => {
	const f = lossFixture(); f.ready(); f.send('writer', 'client', commit()); f.send('writer', 'client', Buffer.from('\x03SELECT 1'));
	const key = f.feed('writer', 'server', ok(), 1)[0]; assert.equal(f.api.decide(f.root, f.ids.writer, key, 1n), false);
});
for (const role of ['auth', 'writer']) test(`post-cut traffic is not an extra discard allowance: ${role}`, () => {
	const f = lossFixture(); f.ready(); f.select(); if (role === 'auth') f.api.claim(f.root, f.ids.writer, summary(), 2n);
	assert.equal(f.feed(role, 'client', Buffer.from('\x03SELECT 1'), 0, 2n), false); assert.equal(f.api.finish(3n, receipt()).synthetic_match, false);
});
for (const key of Object.keys(receipt())) test(`missing physical-claim candidate ${key} cannot match`, () => {
	const f = lossFixture(); f.ready(); f.select(); const r = receipt(); r[key] = false;
	assert.equal(f.complete(3n, r).synthetic_match, false);
});
for (const key of Object.keys(summary())) test(`malformed final ${key} fails closed`, () => {
	const f = lossFixture(); f.ready(); f.select(); const value = summary(); value[key] = null;
	assert.equal(f.api.claim(f.root, f.ids.writer, value, 3n), false); assert.equal(f.api.finish(3n, receipt()).synthetic_match, false);
});
test('configuration copied, exact shape, no accessors or registered symbols', () => {
	const f = lossFixture(); f.config.username = 'changed'; f.config.root = Symbol(); f.config.startedNs = 999n;
	assert.equal(f.ready(), true); assert.equal(f.select(), true); assert.equal(f.complete().synthetic_match, true);
	const g = lossFixture(); let read = 0; Object.defineProperty(g.config, 'username', { get() { read++; return 'synthetic'; } });
	assert.throws(() => createSelectedLossWitness(g.config), TypeError); assert.equal(read, 0);
	const h = lossFixture(); h.config.root = Symbol.for('root'); assert.throws(() => createSelectedLossWitness(h.config), TypeError);
});
test('receipt and final accessors/extra fields are refused without invoking getters', () => {
	let reads = 0;
	const f = lossFixture(); f.ready(); f.select(); const s = summary(); Object.defineProperty(s, 'pre_guards', { get() { reads++; return 8; } });
	assert.equal(f.api.claim(f.root, f.ids.writer, s, 3n), false); assert.equal(reads, 0);
	const g = lossFixture(); g.ready(); g.select(); const r = receipt(); Object.defineProperty(r, 'ledger_matches', { get() { reads++; return true; } });
	assert.equal(g.complete(3n, r).synthetic_match, false); assert.equal(reads, 0);
	const h = lossFixture(); h.ready(); h.select(); assert.equal(h.complete(3n, { ...receipt(), native_proven: true }).synthetic_match, false);
});
for (const flags of [0x8a208 | 32, 0x8a208 | 2048, 0x8a208 | 0x400000]) test(`unsupported negotiation never reaches bind ${flags}`, () => {
	const f = lossFixture(); assert.notEqual(f.feed('writer', 'server', greeting()), false); assert.equal(f.feed('writer', 'client', hello(flags), 1), false);
});
test('profile and closure cannot be replaced by fabricated role completion', () => {
	const f = lossFixture(); assert.equal(f.api.bind(f.root, f.ids.writer, 1n), false);
	const g = lossFixture(); g.ready(); assert.equal(g.api.close(g.ids.auth, 1n), false); assert.equal(g.api.finish(1n, receipt()).synthetic_match, false);
	const h = lossFixture(); h.feed('auth', 'server', greeting(101)); assert.equal(h.api.close(h.ids.auth, 1n), false);
});
