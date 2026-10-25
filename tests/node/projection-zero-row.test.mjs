import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createProjectionZeroRowDecoder, PROJECTION_CATALOG, PROJECTION_PROTOCOL, PROJECTION_CAPABILITIES } from '../../scripts/tests/projection-zero-row-decoder.mjs';

const sourceUrl = new URL('../../scripts/tests/projection-zero-row-decoder.mjs', import.meta.url);
const source = readFileSync(sourceUrl, 'utf8');
const names = ['hack_file', 'wp_attachment_pages_enabled', 'category_base', 'tag_base', 'use_smilies', 'blog_public', 'theme_switched', 'sidebars_widgets'];
const keys = ['attempt', 'pair', 'generation', 'request', 'session'];
function config() {
	return { ...Object.fromEntries(keys.map((key) => [key, Symbol(key)])), startedNs: 100n, seed: '012345abcdef',
		schema: 'local', name: 'sidebars_widgets', catalog: PROJECTION_CATALOG, protocol: PROJECTION_PROTOCOL, capabilities: PROJECTION_CAPABILITIES };
}
function frame(body, sequence) {
	const header = Buffer.alloc(4); header.writeUIntLE(body.length, 0, 3); header[3] = sequence;
	return Buffer.concat([header, body]);
}
function packets(name = 'sidebars_widgets') {
	// Independent fixed wire fixture: no exported decoder packet builder or native capture.
	const definition = Buffer.from('03646566056c6f63616c2077705f616c796e745f726f5f3031323334356162636465665f6f7074696f6e732077705f616c796e745f726f5f3031323334356162636465665f6f7074696f6e730c6f7074696f6e5f76616c75650c6f7074696f6e5f76616c75650c2d00fffffffffb1100000000', 'hex');
	return [Buffer.from(`\x03SELECT option_value FROM wp_alynt_ro_012345abcdef_options WHERE option_name = '${name}' LIMIT 1`),
		Buffer.from([1]), definition, Buffer.from([254, 0, 0, 2, 0]), Buffer.from([254, 0, 0, 2, 0])].map(frame);
}
function fixture(factory = createProjectionZeroRowDecoder, overrides = {}) {
	const c = { ...config(), ...overrides }, identity = Object.fromEntries(keys.map((key) => [key, c[key]]));
	const d = factory(c), bytes = packets(c.name);
	return { c, identity, d, bytes, send: (index, chunk = bytes[index], now = 101n, id = identity, direction = index ? 'server' : 'client') => d.feed(direction, chunk, id, now),
		finish: (now = 101n, id = identity) => d.finish(id, now) };
}
function result(receipt, match = false) {
	assert.equal(receipt.synthetic_match, match); assert.equal(receipt.synthetic_only, true);
	assert.equal(Object.isFrozen(receipt), true);
	for (const [key, value] of Object.entries(receipt)) if (!['synthetic_match', 'synthetic_only'].includes(key)) assert.equal(value, false, key);
	assert.equal(JSON.stringify(receipt).includes('012345abcdef'), false);
}
function complete(f) { for (let i = 0; i < 5; i++) assert.equal(f.send(i), true); }

for (const name of names) test(`positive complete zero-row exchange for fixed N name ${name}`, () => {
	const f = fixture(undefined, { name }); complete(f); result(f.finish(), true);
	assert.equal(f.finish(), false); assert.equal(f.send(0), false);
	assert.deepEqual(Object.keys(f.d), ['feed', 'finish']); assert.equal(Object.isFrozen(f.d), true);
});

test('fixture bindings match the unchanged PHP v2 catalog and fixed owned prefix', () => {
	const catalog = readFileSync(new URL('../../scripts/tests/sidebar-projection-catalog.php', import.meta.url), 'utf8');
	const prior = readFileSync(new URL('../../scripts/tests/fixed-projection-catalog.php', import.meta.url), 'utf8');
	assert.ok(catalog.includes(`const PROFILE = '${PROJECTION_CATALOG}'`));
	for (const name of names.slice(0, -1)) assert.ok(prior.includes(`'${name}'`));
	assert.ok(catalog.includes("$catalog['absent'][] = 'sidebars_widgets'"));
	assert.ok(prior.includes("'wp_alynt_ro_' . $seed . '_user_roles'"));
	assert.equal(PROJECTION_CAPABILITIES, 0x00088209);
	assert.equal(source.includes("from './relay"), false);
	assert.equal(/\b(?:net|child_process|http|https|tls)\b/.test(source), false);
});

for (const [index, bytes] of packets().entries()) {
	for (let split = 1; split < bytes.length; split++) test(`packet ${index} split ${split} preserves completion`, () => {
		const f = fixture();
		for (let i = 0; i < 5; i++) {
			if (i === index) { assert.equal(f.send(i, bytes.subarray(0, split)), true); assert.equal(f.send(i, bytes.subarray(split)), true); }
			else assert.equal(f.send(i), true);
		}
		result(f.finish(), true);
	});
	for (const fault of ['sequence', 'direction', 'truncated', 'zero', 'oversize', 'continuation', 'duplicate', 'content']) test(`packet ${index} ${fault} refuses permanently`, () => {
		const f = fixture(); for (let i = 0; i < index; i++) assert.equal(f.send(i), true);
		let changed = Buffer.from(bytes);
		if (fault === 'sequence') changed[3] ^= 1;
		if (fault === 'truncated') changed = changed.subarray(0, -1);
		if (fault === 'zero') changed.fill(0, 0, 3);
		if (fault === 'oversize') changed.writeUIntLE(4097, 0, 3);
		if (fault === 'continuation') changed.fill(255, 0, 3);
		if (fault === 'duplicate') changed = Buffer.concat([changed, changed]);
		if (fault === 'content') changed[changed.length - 1] ^= 1;
		assert.equal(f.send(index, changed, 101n, f.identity, fault === 'direction' ? (index ? 'client' : 'server') : (index ? 'server' : 'client')), fault === 'truncated');
		result(f.finish()); assert.equal(f.send(index), false);
	});
	for (let cut = 1; cut < bytes.length; cut++) test(`unfinished packet ${index} prefix ${cut} never earns a result`, () => {
		const f = fixture(); for (let i = 0; i < index; i++) assert.equal(f.send(i), true);
		assert.equal(f.send(index, bytes.subarray(0, cut)), true); result(f.finish());
	});
}

test('one-byte fragments and coalesced response buffers are supported without retaining input', () => {
	for (const coalesced of [true, false]) {
		const f = fixture(); assert.equal(f.send(0), true);
		const response = Buffer.concat(f.bytes.slice(1)), original = Buffer.from(response);
		if (coalesced) assert.equal(f.send(1, response), true);
		else for (const byte of response) assert.equal(f.send(1, Buffer.from([byte])), true);
		assert.deepEqual(response, original); response.fill(0); result(f.finish(), true);
	}
});

const invalidBodies = {
	empty_row: [0], null_row: [251], zero_row: [1, 48], error: [255, 1, 0], ok: [0, 0, 0, 2, 0, 0, 0],
	infile: [251, 65], auth_switch: [254, 65], binary_row: [0, 0],
	warning: [254, 1, 0, 2, 0], more_results: [254, 0, 0, 10, 0], transaction: [254, 0, 0, 3, 0],
	deprecated_eof: [254, 0, 0, 2, 0, 0, 0], eof_tail: [254, 0, 0, 2, 0, 0], fe_row: [254, 0, 0, 0, 0, 0, 0, 0, 0],
};
for (const [name, body] of Object.entries(invalidBodies)) for (const index of [3, 4]) test(`${name} cannot replace terminator ${index}`, () => {
	const f = fixture(); for (let i = 0; i < index; i++) assert.equal(f.send(i), true);
	assert.equal(f.send(index, frame(Buffer.from(body), index)), false); result(f.finish());
});
for (const body of [[0], [2], [251], [252, 1, 0], [253, 1, 0, 0], [254, 1, 0, 0, 0, 0, 0, 0, 0], [1, 1], [255]]) test(`noncanonical/wrong column count ${body}`, () => {
	const f = fixture(); assert.equal(f.send(0), true); assert.equal(f.send(1, frame(Buffer.from(body), 1)), false); result(f.finish());
});

test('every column definition byte is bound, including schema/table/alias/type/lenenc/filler', () => {
	for (let at = 4; at < packets()[2].length; at++) {
		const f = fixture(); assert.equal(f.send(0), true); assert.equal(f.send(1), true);
		const changed = Buffer.from(f.bytes[2]); changed[at] ^= 1;
		assert.equal(f.send(2, changed), false, `column byte ${at}`); result(f.finish());
	}
});

for (const sql of ['SELECT 1', "SELECT option_value FROM wp_options WHERE option_name = 'sidebars_widgets' LIMIT 1",
	"SELECT option_value FROM wp_alynt_ro_012345abcdef_options WHERE option_name = 'siteurl' LIMIT 1",
	"SELECT option_value FROM wp_alynt_ro_012345abcdef_options WHERE option_name = 'sidebars_widgets'",
	"SELECT option_value AS alias FROM wp_alynt_ro_012345abcdef_options WHERE option_name = 'sidebars_widgets' LIMIT 1"]) test(`unselected SQL refuses ${sql}`, () => {
	const f = fixture(); assert.equal(f.send(0, frame(Buffer.from('\x03' + sql), 0)), false); result(f.finish());
});

for (const key of keys) test(`foreign/stale ${key} refuses and reuse across owners cannot qualify`, () => {
	const f = fixture(); assert.equal(f.send(0), true);
	assert.equal(f.send(1, undefined, 101n, { ...f.identity, [key]: Symbol(key) }), false);
	assert.equal(f.send(1), false); result(f.finish());
	assert.throws(() => createProjectionZeroRowDecoder({ ...config(), [key]: f.c[key] }), TypeError);
	assert.throws(() => createProjectionZeroRowDecoder({ ...config(), [key]: Symbol.for(key) }), TypeError);
});
test('context is a primitive snapshot, not caller-controlled policy or a callback', () => {
	const f = fixture(); f.c.name = 'siteurl'; f.c.startedNs = 999999999999n; complete(f); result(f.finish(), true);
	for (const key of Object.keys(config())) {
		let called = false; const c = config(); Object.defineProperty(c, key, { get() { called = true; throw Error('must not execute'); } });
		assert.throws(() => createProjectionZeroRowDecoder(c), TypeError); assert.equal(called, false);
	}
	const g = fixture(); let called = false;
	const id = { ...g.identity }; Object.defineProperty(id, 'pair', { get() { called = true; throw Error('must not execute'); } });
	assert.equal(g.send(0, undefined, 101n, id), false); assert.equal(called, false); result(g.finish());
	assert.throws(() => createProjectionZeroRowDecoder(new Proxy(config(), { ownKeys() { throw Error('trap'); } })), TypeError);
});
for (const [key, value] of [['seed', '012345ABCDEf'], ['seed', '012345abcdef\n'], ['schema', 'other'], ['name', 'siteurl'],
	['name', 'sidebars_widgets '], ['catalog', 'v1'], ['protocol', 'deprecate-eof'], ['capabilities', PROJECTION_CAPABILITIES | 0x1000000],
	['capabilities', PROJECTION_CAPABILITIES | 0x20], ['capabilities', PROJECTION_CAPABILITIES | 0x800], ['startedNs', -1n], ['startedNs', 0],
	['startedNs', 9223372036854775807n]]) test(`invalid fixed context ${key}=${String(value)}`, () => {
	assert.throws(() => createProjectionZeroRowDecoder({ ...config(), [key]: value }), TypeError);
});
test('unknown keys, symbols, callbacks, inherited fields and aliased identities refuse', () => {
	for (const changed of [{ ...config(), allowlist: names }, { ...config(), sql: () => 'SELECT 1' }, { ...config(), [Symbol()]: true },
		Object.create(config()), { ...config(), attempt: null }]) assert.throws(() => createProjectionZeroRowDecoder(changed), TypeError);
	const c = config(); c.pair = c.attempt; assert.throws(() => createProjectionZeroRowDecoder(c), TypeError);
});
test('original half-open deadline applies to feed and finish; no refresh or clock regression', () => {
	const boundary = 20000000100n;
	const f = fixture(); complete(f); result(f.finish(boundary - 1n), true);
	const g = fixture(); complete(g); result(g.finish(boundary));
	for (const now of [99n, boundary, boundary + 1n, -1n, 102, '102', 9223372036854775808n]) {
		const h = fixture(); assert.equal(h.send(0, undefined, now), false); result(h.finish());
	}
	const h = fixture(); assert.equal(h.send(0, undefined, 1000n), true); assert.equal(h.send(1, undefined, 999n), false); result(h.finish());
});
test('length refusal occurs at header completion, before body; stream overflow is sticky', () => {
	for (const length of [0, 4096, 4097, 0xffffff]) {
		const f = fixture(), header = Buffer.alloc(4); header.writeUIntLE(length, 0, 3);
		assert.equal(f.send(0, header.subarray(0, 3)), true); assert.equal(f.send(0, header.subarray(3)), false);
		assert.equal(f.send(0), false); result(f.finish());
	}
	for (const bytes of [Buffer.alloc(0), Buffer.alloc(16385), new Uint8Array(1), Buffer.from(new SharedArrayBuffer(4))]) {
		const f = fixture(); assert.equal(f.send(0, bytes), false); result(f.finish());
	}
});
test('no activity or replay is accepted after the selected response, even in the same chunk', () => {
	for (const extra of [Buffer.from([1]), packets()[4], packets()[0]]) {
		const f = fixture(); complete(f); assert.equal(f.send(1, extra), false); result(f.finish());
		const g = fixture(); for (let i = 0; i < 4; i++) assert.equal(g.send(i), true);
		assert.equal(g.send(4, Buffer.concat([g.bytes[4], extra])), false); result(g.finish());
	}
});

test('own Buffer getters and proxy traps cannot execute through the byte boundary', () => {
	for (const key of ['buffer', 'length']) {
		const f = fixture(), bytes = Buffer.from(f.bytes[0]); let called = false;
		Object.defineProperty(bytes, key, { get() { called = true; throw Error('must not execute'); } });
		assert.equal(f.send(0, bytes), false); assert.equal(called, false); result(f.finish());
	}
	const f = fixture();
	assert.equal(f.send(0, new Proxy(f.bytes[0], { get() { throw Error('trap'); } })), false); result(f.finish());
});

// Mutants are evaluated from in-memory source only; original files never modified.
const controls = [
	['association', "!identities.every((key) => current[key] === config[key])", 'false', (factory) => {
		const f = fixture(factory); assert.equal(f.send(0, undefined, 101n, { ...f.identity, request: Symbol('foreign') }), false);
	}],
	['result', '!Buffer.prototype.equals.call(payload, expected[stage])', 'false', (factory) => {
		const f = fixture(factory); for (let i = 0; i < 4; i++) assert.equal(f.send(i), true);
		assert.equal(f.send(4, frame(Buffer.from([254, 1, 0, 2, 0]), 4)), false);
	}],
	['header length', 'length > FRAME_LIMIT', 'false', (factory) => {
		const f = fixture(factory), h = Buffer.alloc(4); h.writeUIntLE(4097, 0, 3); assert.equal(f.send(0, h), false);
	}, ['length !== expected[stage].length', 'false']],
	['deadline', 'now >= deadline', 'false', (factory) => { const f = fixture(factory); complete(f); result(f.finish(20000000100n)); }],
	['sticky refusal', 'closed || refused', 'closed', (factory) => {
		const f = fixture(factory); assert.equal(f.send(0, Buffer.from([0, 0, 0, 0])), false); assert.equal(f.send(0), false);
	}],
];
for (const [name, from, to, detect, second] of controls) test(`semantic control kills removed ${name} guard`, async () => {
	detect(createProjectionZeroRowDecoder); assert.equal(source.split(from).length, 2);
	let changed = source.replace(from, to);
	if (second) { assert.equal(changed.split(second[0]).length, 2); changed = changed.replace(...second); }
	const mutant = await import('data:text/javascript;base64,' + Buffer.from(changed).toString('base64'));
	assert.throws(() => detect(mutant.createProjectionZeroRowDecoder), assert.AssertionError);
});
