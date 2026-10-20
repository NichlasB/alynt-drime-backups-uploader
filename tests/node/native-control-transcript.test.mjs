import test from 'node:test';
import assert from 'node:assert/strict';
import { control } from './support/native-witness-fixtures.mjs';

test('positive canonical transcript with separate virtual clock epochs; result redacted', () => {
	const f = control(); for (let i = 0; i < 6; i++) assert.equal(f.send(i), true);
	const result = f.finish(); assert.equal(result.synthetic_match, true); assert.ok(Object.isFrozen(result));
	assert.deepEqual(Object.keys(f.api), ['feed', 'finish']);
	assert.ok(Object.entries(result).every(([key, value]) => ['synthetic_match', 'synthetic_only'].includes(key) || value === false));
	assert.equal(f.finish(), false);
});
test('inherited schema names and malformed top-level shapes refuse without throwing', () => {
	for (const text of ['{"kind":"constructor"}\n', '{"kind":"__proto__"}\n', '{"kind":"toString"}\n', 'null\n', '[]\n', '"boot"\n']) {
		const f = control(); assert.equal(f.send(0, 0n, 900000000000n, Buffer.from(text)), false); assert.equal(f.finish().synthetic_match, false);
	}
});
for (const width of [1, 2, 3, 7, 64, 4096]) test(`canonical lines split at ${width}`, () => {
	const f = control(); for (let i = 0; i < 6; i++) { const b = f.wire(i); for (let j = 0; j < b.length; j += width) assert.equal(f.send(i, 0n, 900000000000n, b.subarray(j, j + width)), true); }
	assert.equal(f.finish().synthetic_match, true);
});
for (const kind of ['duplicate-key', 'key-order', 'unknown-key', 'whitespace', 'escape', 'cr', 'nul', 'unicode', 'bom', 'overflow', 'wrong-attempt', 'wrong-type', 'wrong-sequence', 'wrong-intent', 'oversize']) test(`canonical refusal ${kind}`, () => {
	const f = control(); let s = f.wire(0).toString('ascii');
	if (kind === 'duplicate-key') s = s.replace('"v":1', '"v":1,"v":1');
	if (kind === 'key-order') s = s.replace('"v":1,"kind":"boot"', '"kind":"boot","v":1');
	if (kind === 'unknown-key') s = s.replace('{', '{"unknown":0,');
	if (kind === 'whitespace') s = s.replace('{', '{ ');
	if (kind === 'escape') s = s.replace('boot', 'b\\u006fot');
	if (kind === 'cr') s = s.replace('\n', '\r\n');
	if (kind === 'nul') s = s.replace('boot', 'bo\0ot');
	if (kind === 'unicode') s = s.replace('boot', 'bóot');
	if (kind === 'bom') s = '\ufeff' + s;
	if (kind === 'overflow') s = s.replace('"seq":1', '"seq":9007199254740993');
	if (kind === 'wrong-attempt') s = s.replace('a'.repeat(64), 'd'.repeat(64));
	if (kind === 'wrong-type') s = s.replace('"v":1', '"v":"1"');
	if (kind === 'wrong-sequence') s = s.replace('"seq":1', '"seq":2');
	if (kind === 'wrong-intent') s = s.replace('observe_only', 'execute');
	if (kind === 'oversize') s = 'x'.repeat(4096) + '\n';
	assert.equal(f.send(0, 0n, 900000000000n, Buffer.from(s, 'utf8')), false);
	assert.equal(f.send(0), false); assert.equal(f.finish().synthetic_match, false);
});
for (const kind of ['wrong-root', 'wrong-role', 'role-alias', 'duration-leading-zero', 'duration-negative', 'duration-float', 'duration-overflow', 'foreign-identity', 'missing-ack', 'extra', 'partial-eof', 'rejected', 'false-summary', 'zero-checks', 'groups-overflow', 'pair-reuse']) test(`lifecycle refusal ${kind}`, () => {
	const f = control(); let stop = false;
	if (kind === 'wrong-root') f.messages[2].root = 'd'.repeat(64);
	if (kind === 'wrong-role') f.messages[3].writer = 104;
	if (kind === 'role-alias') f.messages[3].writer = 101;
	if (kind === 'duration-leading-zero') f.messages[3].remaining_ns = '06000000000';
	if (kind === 'duration-negative') f.messages[3].remaining_ns = '-1';
	if (kind === 'duration-float') f.messages[3].remaining_ns = 6000000000;
	if (kind === 'duration-overflow') f.messages[3].remaining_ns = '10000000001';
	if (kind === 'false-summary') f.messages[5].passed = false;
	if (kind === 'zero-checks') f.messages[5].checks = 0;
	if (kind === 'groups-overflow') f.messages[5].groups = 33;
	if (kind === 'pair-reuse') f.messages[4].pair = f.messages[4].root;
	if (kind === 'rejected') f.messages[4] = { v: 1, kind: 'rejected', seq: 3, attempt: f.messages[0].attempt, root: f.messages[2].root, reason: 'unavailable' };
	for (let i = 0; i < 6; i++) {
		if (kind === 'missing-ack' && i === 4) break;
		const bytes = kind === 'partial-eof' && i === 5 ? f.wire(i).subarray(0, -1) : f.wire(i);
		if (!f.send(i, 0n, 900000000000n, bytes, kind === 'foreign-identity' ? Symbol('foreign') : f.roles.writer.identity)) { stop = true; break; }
	}
	if (kind === 'extra') assert.equal(f.send(5), false);
	if (!['missing-ack', 'partial-eof', 'extra'].includes(kind)) assert.equal(stop, true);
	assert.equal(f.finish().synthetic_match, false);
});
test('exact original six-second and one-second acceptance boundaries', () => {
	const f = control(); for (let i = 0; i < 3; i++) assert.equal(f.send(i), true);
	f.messages[3].remaining_ns = '7000000000'; assert.equal(f.send(3, 3000000000n, 903000000000n), true);
	assert.equal(f.send(4, 4000000000n, 904000000000n), true); assert.equal(f.send(5, 4000000000n, 904000000000n), true);
	assert.equal(f.finish(4000000000n, 904000000000n).synthetic_match, true);
});
test('partial BIND serialization consumes the same rendezvous interval, not a fresh ACK interval', () => {
	const f = control(); for (let i = 0; i < 3; i++) assert.equal(f.send(i), true);
	f.messages[3].remaining_ns = '8999999999'; const b = f.wire(3);
	assert.equal(f.send(3, 0n, 900000000000n, b.subarray(0, 1)), true);
	assert.equal(f.send(3, 1000000001n, 901000000001n, b.subarray(1)), true);
	assert.equal(f.send(4, 1000000001n, 901000000001n), false);
	assert.equal(f.finish(1000000001n, 901000000001n).synthetic_match, false);
});
for (const kind of ['bind-late', 'ack-late', 'worker-ack-late', 'node-regression', 'worker-regression', 'root-overflow', 'half-open', 'no-reset']) test(`clock refusal ${kind}`, () => {
	const f = control(); for (let i = 0; i < 2; i++) assert.equal(f.send(i), true);
	if (kind === 'root-overflow') assert.equal(f.send(2, 9223372026854775808n), false);
	else {
		assert.equal(f.send(2), true);
		if (kind === 'bind-late' || kind === 'no-reset') { f.messages[3].remaining_ns = '10000000000'; assert.equal(f.send(3, 4000000001n, 904000000001n), false); }
		else if (kind === 'half-open') assert.equal(f.api.finish(10000000000n, 910000000000n, f.roles.writer.identity).synthetic_match, false);
		else {
			f.messages[3].remaining_ns = '9000000000'; assert.equal(f.send(3, 1000000000n, 901000000000n), true);
			if (kind === 'ack-late') assert.equal(f.send(4, 2000000001n, 902000000000n), false);
			if (kind === 'worker-ack-late') assert.equal(f.send(4, 2000000000n, 902000000001n), false);
			if (kind === 'node-regression') assert.equal(f.send(4, 999999999n, 901000000000n), false);
			if (kind === 'worker-regression') assert.equal(f.send(4, 1000000000n, 900999999999n), false);
		}
	}
	assert.notEqual(f.finish()?.synthetic_match, true);
});
test('wrong direction, coalesced wrong-direction lines, cross-attempt input and total cap consume attempt', () => {
	for (const kind of ['direction', 'coalesced', 'cross-attempt', 'total']) {
		const f = control();
		const bytes = kind === 'coalesced' ? Buffer.concat([f.wire(0), f.wire(1)]) : kind === 'total' ? Buffer.alloc(32769, 65) : f.wire(0);
		if (kind === 'cross-attempt') bytes.fill(100, bytes.indexOf('a'.repeat(64)), bytes.indexOf('a'.repeat(64)) + 64);
		assert.equal(f.api.feed(kind === 'direction' ? 'worker' : 'node', bytes, 0n, 900000000000n, f.roles.writer.identity), false);
		assert.equal(f.finish().synthetic_match, false);
	}
});
