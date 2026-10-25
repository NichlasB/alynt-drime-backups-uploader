import assert from 'node:assert/strict';
import test from 'node:test';
import { copyWpdbSetupPayload, decodeWpdbSetupOK, decodeWpdbModeResult, decodeWpdbSetupExchange } from '../../scripts/tests/wpdb-setup-wire.mjs';
import { packet, modeFrames, modeColumnHex, exchange } from './support/wpdb-setup-wire-fixtures.mjs';

test('packet copy is detached and header/sequence exact', () => {
	const frame = packet([1, 2, 3], 1), copy = copyWpdbSetupPayload(frame, 1);
	assert.deepEqual(copy, Buffer.from([1, 2, 3])); frame.fill(0); assert.deepEqual(copy, Buffer.from([1, 2, 3]));
	copy.fill(9); assert.equal(frame.every((v) => v === 0), true);
});
for (const length of [1, 4096]) test(`bounded payload ${length} succeeds`, () => {
	const body = Buffer.alloc(length, 67); assert.deepEqual(copyWpdbSetupPayload(packet(body, 1), 1), body);
});
test('truncation at every boundary and concatenated/suffixed packets refuse', () => {
	const frame = packet([0, 0, 0, 2, 0, 0, 0], 1);
	for (let cut = 0; cut < frame.length; cut++) assert.equal(copyWpdbSetupPayload(frame.subarray(0, cut), 1), false);
	assert.equal(copyWpdbSetupPayload(Buffer.concat([frame, Buffer.from([0])]), 1), false);
	assert.equal(copyWpdbSetupPayload(Buffer.concat([frame, frame]), 1), false);
});
for (const length of [0, 4097, 0xffffff]) test(`unsupported length ${length} refuses before payload allocation`, () => {
	const header = Buffer.alloc(5); header.writeUIntLE(length, 0, 3); header[3] = 1;
	assert.equal(copyWpdbSetupPayload(header, 1), false);
	if (length < 5000) assert.equal(copyWpdbSetupPayload(packet(Buffer.alloc(length), 1), 1), false);
});
for (const sequence of [-1, 1.5, 9, '1', NaN, null]) test(`invalid sequence ${String(sequence)} refuses`, () => {
	assert.equal(copyWpdbSetupPayload(packet([1], 1), sequence), false);
});
test('wrong sequence and exotic/shared inputs cannot cross byte boundary or invoke getters', () => {
	let calls = 0; const frame = packet([1], 1);
	const getter = packet([1], 1); Object.defineProperty(getter, 'length', { get() { calls++; throw Error('getter'); } });
	const bufferGetter = packet([1], 1); Object.defineProperty(bufferGetter, 'buffer', { get() { calls++; throw Error('getter'); } });
	const proxy = new Proxy(frame, { get() { calls++; throw Error('proxy'); } });
	for (const value of [getter, bufferGetter, proxy, Buffer.from(new SharedArrayBuffer(5)), new Uint8Array(frame), {}, null]) {
		assert.equal(copyWpdbSetupPayload(value, 1), false);
	}
	assert.equal(copyWpdbSetupPayload(frame, 2), false); assert.equal(calls, 0);
});

test('exact ordinary protocol41 OK fixture succeeds without altering caller bytes', () => {
	const frame = packet([0, 0, 0, 2, 0, 0, 0], 1), before = Buffer.from(frame);
	assert.equal(decodeWpdbSetupOK(frame), true); assert.deepEqual(frame, before);
});
test('all affected/insert/status/warning/header mutations refuse, not merely first-byte check', () => {
	const body = Buffer.from([0, 0, 0, 2, 0, 0, 0]);
	for (let index = 0; index < body.length; index++) for (let bit = 0; bit < 8; bit++) {
		const changed = Buffer.from(body); changed[index] ^= 1 << bit;
		assert.equal(decodeWpdbSetupOK(packet(changed, 1)), false, `${index}/${bit}`);
	}
});
for (const body of [[0], [0, 0, 0, 2, 0, 0], [0, 0, 0, 2, 0, 0, 0, 0], [254, 0, 0, 2, 0],
	[255, 1, 0, 35, 72, 89, 48, 48, 48], [251], [254, 0, 0, 2, 0, 0, 0]]) {
	test(`non-canonical completion ${Buffer.from(body).toString('hex')} refuses`, () => assert.equal(decodeWpdbSetupOK(packet(body, 1)), false));
}
test('OK cannot hide wrong sequence, trailing frame or length mismatch', () => {
	const frame = packet([0, 0, 0, 2, 0, 0, 0], 1);
	assert.equal(decodeWpdbSetupOK(packet([0, 0, 0, 2, 0, 0, 0], 2)), false);
	assert.equal(decodeWpdbSetupOK(Buffer.concat([frame, frame])), false);
	for (let cut = 0; cut < frame.length; cut++) assert.equal(decodeWpdbSetupOK(frame.subarray(0, cut)), false);
});

for (const [value, expected] of [['', 'one-empty-string'], ['STRICT_TRANS_TABLES', 'one-filterable-nonempty']]) {
	test(`exact one-value mode ${expected} has non-vacuous positive grammar`, () => {
		const frames = modeFrames(value), original = frames.map((f) => Buffer.from(f));
		assert.equal(decodeWpdbModeResult(frames), expected); assert.deepEqual(frames, original);
	});
}
test('every fixed column metadata byte is significant', () => {
	const column = Buffer.from(modeColumnHex, 'hex');
	for (let i = 0; i < column.length; i++) {
		const frames = modeFrames(); const changed = Buffer.from(column); changed[i] ^= 1; frames[1] = packet(changed, 2);
		assert.equal(decodeWpdbModeResult(frames), false, `metadata byte ${i}`);
	}
});
for (const row of [[251], [], [1, 48], [1, 0], [252, 0, 0], [255, 1], [0, 0]]) {
	test(`NULL/error/noncanonical mode row ${Buffer.from(row).toString('hex')} refuses`, () => {
		const frames = modeFrames(); frames[3] = packet(row, 4); assert.equal(decodeWpdbModeResult(frames), false);
	});
}
for (const value of ['0', 'STRICT_ALL_TABLES', 'STRICT_TRANS_TABLES,NO_ZERO_DATE', 'strict_trans_tables', ' ', 'é']) {
	test(`unqualified dynamic mode ${value} refuses`, () => assert.equal(decodeWpdbModeResult(modeFrames(value)), false));
}
test('metadata/row EOF errors, warnings, more-results and alternate terminators refuse', () => {
	for (const index of [2, 4]) for (const body of [[254, 1, 0, 2, 0], [254, 0, 0, 10, 0], [254, 0, 0, 2],
		[0, 0, 0, 2, 0, 0, 0], [255, 1, 0], [254, 0, 0, 2, 0, 0]]) {
		const frames = modeFrames(); frames[index] = packet(body, index + 1); assert.equal(decodeWpdbModeResult(frames), false);
	}
});
test('missing/additional rows, packets, wrong sequences or counts refuse', () => {
	for (let index = 0; index < 5; index++) {
		const frames = modeFrames(); assert.equal(decodeWpdbModeResult(frames.filter((_, i) => i !== index)), false);
		frames[index][3] = 0; assert.equal(decodeWpdbModeResult(frames), false);
	}
	for (const count of [[0], [2], [251], [252, 1, 0]]) {
		const frames = modeFrames(); frames[0] = packet(count, 1); assert.equal(decodeWpdbModeResult(frames), false);
	}
	assert.equal(decodeWpdbModeResult([...modeFrames(), packet([0], 6)]), false);
});
test('array descriptors reject holes/getters/proxies and excess keys without reading protected values', () => {
	let calls = 0; const getter = modeFrames(); Object.defineProperty(getter, '0', { get() { calls++; throw Error('getter'); } });
	const extra = modeFrames(); extra[Symbol()] = true; const hole = modeFrames(); delete hole[2];
	for (const frames of [getter, extra, hole, new Proxy([], { get() { calls++; throw Error('trap'); } }), null, {}]) {
		assert.equal(decodeWpdbModeResult(frames), false);
	}
	assert.equal(calls, 0);
});

for (const [kind, value, expected] of [['mode-read', '', 'one-empty-string'], ['mode-read', 'STRICT_TRANS_TABLES', 'one-filterable-nonempty'],
	['schema-select', '', 'schema-selected']]) test(`exact ${kind}/${value} request/result pair succeeds synthetically`, () => {
	const f = exchange(kind, value); assert.equal(decodeWpdbSetupExchange(kind, f.request, f.responses), expected);
});
test('schema OK alone, wrong command/schema, old handshake or a generic USE query cannot select schema', () => {
	const f = exchange('schema-select');
	for (const request of [f.responses[0], packet(Buffer.from('\x02foreign'), 0), packet(Buffer.from('\x03USE owned_synthetic'), 0),
		packet(Buffer.from('\x02owned_synthetic\0'), 0), packet([0x02], 0), packet(Buffer.from('owned_synthetic'), 0)]) {
		assert.equal(decodeWpdbSetupExchange('schema-select', request, f.responses), false);
	}
});
test('mode commands cannot substitute schema result or unbound dynamic query', () => {
	const mode = exchange(), schema = exchange('schema-select');
	assert.equal(decodeWpdbSetupExchange('mode-read', mode.request, schema.responses), false);
	assert.equal(decodeWpdbSetupExchange('schema-select', schema.request, mode.responses), false);
	for (const query of ['SELECT @@sql_mode', 'SELECT @@SESSION.sql_mode;', 'SELECT @@SESSION.sql_mode --', 'SELECT 1']) {
		assert.equal(decodeWpdbSetupExchange('mode-read', packet(Buffer.from('\x03' + query), 0), mode.responses), false);
	}
});
test('schema response missing/extra/error/getter forms refuse', () => {
	let calls = 0; const f = exchange('schema-select'), getter = [null]; Object.defineProperty(getter, 0, { get() { calls++; } });
	for (const responses of [[], [...f.responses, ...f.responses], [packet([255, 1, 0], 1)], getter, null]) {
		assert.equal(decodeWpdbSetupExchange('schema-select', f.request, responses), false);
	}
	assert.equal(calls, 0);
});
test('unsupported kind or malformed request never reads response accessors', () => {
	let calls = 0; const responses = new Proxy([], { get() { calls++; throw Error('response read'); } });
	const f = exchange();
	for (const kind of ['mode-write', 'connect', 'commit', {}, undefined]) assert.equal(decodeWpdbSetupExchange(kind, f.request, responses), false);
	assert.equal(decodeWpdbSetupExchange('mode-read', packet([3], 0), responses), false); assert.equal(calls, 0);
});
