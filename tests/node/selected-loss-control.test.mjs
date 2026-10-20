import test from 'node:test';
import assert from 'node:assert/strict';
import { createNativeControlTranscript } from '../../scripts/tests/native-control-transcript.mjs';
import { createSelectedLossControl } from '../../scripts/tests/selected-loss-control.mjs';
import { lossControl } from './support/selected-loss-fixtures.mjs';
const prefix = (f, end) => { for (let i = 0; i < end; i++) assert.equal(f.send(i), true); };
for (const size of [1, 2, 7, 64, 4096]) test(`separate v2 transcript fragmented in ${size} byte chunks`, () => {
	const f = lossControl();
	for (let i = 0; i < 6; i++) { const bytes = f.wire(i); for (let at = 0; at < bytes.length; at += size) assert.equal(f.send(i, 0n, 900000000000n, bytes.subarray(at, at + size)), true); }
	const r = f.finish(); assert.equal(r.synthetic_match, true); assert.equal(Object.isFrozen(r), true);
	for (const key of ['native_proven', 'zero_delivery_proven', 'ipc_proven', 'authentication_proven', 'storage_supported', 'dispatch_supported', 'execution_supported', 'suppression_supported']) assert.equal(r[key], false);
	assert.equal(JSON.stringify(r).includes('aaaa'), false); assert.equal(f.finish(), false);
});
test('v1 observe-only parser is not a selected-loss control path', () => {
	const f = lossControl(createNativeControlTranscript); assert.equal(f.send(0), false); assert.equal(f.finish().synthetic_match, false);
	const g = lossControl(); g.messages[0].v = 1; g.messages[0].intent = 'observe_only'; assert.equal(g.send(0), false);
});
for (let stage = 0; stage < 6; stage++) for (const field of ['attempt', 'seq', 'v']) test(`v2 stage ${stage} refuses mutated ${field} permanently`, () => {
	const f = lossControl(); prefix(f, stage); f.messages[stage][field] = null;
	assert.equal(f.send(stage), false); assert.equal(f.send(stage), false); assert.equal(f.finish().synthetic_match, false);
});
for (const [stage, key, value] of [[0, 'intent', 'observe_only'], [2, 'root', 'd'.repeat(64)], [3, 'auth', 103], [3, 'remaining_ns', '06000000000'],
	[3, 'remaining_ns', '5999999999'], [3, 'remaining_ns', '10000000001'], [4, 'pair', 'd'.repeat(64)], [4, 'writer', 101],
	[5, 'case', 'observe_only'], [5, 'checks', 0], [5, 'checks', 8193], [5, 'groups', 33], [5, 'returned_false', false], [5, 'pre_guards', 7], [5, 'post_guards', 1], [5, 'root_erased', false]]) test(`v2 rejects ${stage}:${key}:${value}`, () => {
	const f = lossControl(); prefix(f, stage); f.messages[stage][key] = value; assert.equal(f.send(stage), false); assert.equal(f.finish().synthetic_match, false);
});
test('no stale writer lifetime, direction substitution, reordered or duplicate keys', () => {
	for (const mode of ['identity', 'direction', 'order', 'duplicate', 'extra', 'escaped', 'trailing', 'unicode', 'oversize', 'empty']) {
		const f = lossControl(); let bytes = f.wire(0);
		if (mode === 'order') bytes = Buffer.from(JSON.stringify({ kind: 'boot', ...f.messages[0] }) + '\n');
		if (mode === 'duplicate') bytes = Buffer.from(bytes.toString().replace('"v":2', '"v":2,"v":2'));
		if (mode === 'extra') bytes = Buffer.from(JSON.stringify({ ...f.messages[0], extra: true }) + '\n');
		if (mode === 'escaped') bytes = Buffer.from(bytes.toString().replace('boot', 'b\\u006fot'));
		if (mode === 'trailing') bytes = Buffer.concat([bytes.subarray(0, -1), Buffer.from(' \n')]);
		if (mode === 'unicode') bytes = Buffer.from('é\n');
		if (mode === 'oversize') bytes = Buffer.alloc(32769, 65);
		if (mode === 'empty') bytes = Buffer.alloc(0);
		const result = mode === 'direction' ? f.api.feed('worker', bytes, 0n, 900000000000n, f.roles.writer.identity) : f.send(0, 0n, 900000000000n, bytes, mode === 'identity' ? Symbol() : f.roles.writer.identity);
		assert.equal(result, false, mode); assert.equal(f.finish().synthetic_match, false, mode);
	}
});
test('two original clock domains, not absolute epochs; BOUND has one-second rendezvous', () => {
	const f = lossControl(); prefix(f, 3); f.messages[3].remaining_ns = '7000000000';
	assert.equal(f.send(3, 3000000000n, 903000000000n), true); assert.equal(f.send(4, 4000000000n, 904000000000n), true);
	assert.equal(f.send(5, 9999999999n, 909999999999n), true); assert.equal(f.finish(9999999999n, 909999999999n).synthetic_match, true);
	for (const [n, w] of [[4000000001n, 904000000000n], [4000000000n, 904000000001n], [2000000001n, 902000000000n]]) {
		const g = lossControl(); prefix(g, 3); g.messages[3].remaining_ns = '9000000000'; assert.equal(g.send(3, 1000000000n, 901000000000n), true);
		assert.equal(g.send(4, n, w), false); assert.equal(g.finish(n, w).synthetic_match, false);
	}
});
test('first ROOT/BIND byte sets clock, never last fragment or ACK', () => {
	const f = lossControl(); prefix(f, 2); const root = f.wire(2);
	assert.equal(f.send(2, 0n, 900000000000n, root.subarray(0, 1)), true);
	assert.equal(f.send(2, 3000000000n, 903000000000n, root.subarray(1)), true);
	assert.equal(f.send(3, 4000000001n, 903000000000n), false);
	const g = lossControl(); prefix(g, 3); const bind = g.wire(3);
	assert.equal(g.send(3, 0n, 900000000000n, bind.subarray(0, 1)), true);
	assert.equal(g.send(3, 1000000000n, 901000000000n, bind.subarray(1)), false); // Reported remaining would overstate worker root.
});
test('partial EOF, repeated final, expired final and regressing clocks cannot match', () => {
	const f = lossControl(); prefix(f, 5); assert.equal(f.send(5, 0n, 900000000000n, f.wire(5).subarray(0, -1)), true); assert.equal(f.finish().synthetic_match, false);
	const g = lossControl(); prefix(g, 6); assert.equal(g.send(5), false); assert.equal(g.finish().synthetic_match, false);
	const h = lossControl(); prefix(h, 5); assert.equal(h.send(5, 10000000000n, 910000000000n), false);
	const j = lossControl(); j.send(0, 2n); assert.equal(j.send(1, 1n), false);
});
test('control pins are copied; exact constructor domains and accessors are rejected', () => {
	const f = lossControl(); f.config.roles.writer.thread = 999; f.config.attempt = 'd'.repeat(64); prefix(f, 6); assert.equal(f.finish().synthetic_match, true);
	for (const change of [(c) => { c.pair = c.root; }, (c) => { c.roles.auth.thread = c.roles.writer.thread; }, (c) => { c.roles.auth.identity = Symbol.for('x'); }]) {
		const g = lossControl(); change(g.config); assert.throws(() => createSelectedLossControl(g.config), TypeError);
	}
	const h = lossControl(); let reads = 0; Object.defineProperty(h.config.roles.writer, 'thread', { get() { reads++; return 103; } });
	assert.throws(() => createSelectedLossControl(h.config), TypeError); assert.equal(reads, 0);
});
