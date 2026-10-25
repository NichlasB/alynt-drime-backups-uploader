import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createProjectionObserverOwner } from '../../scripts/tests/projection-observer-owner.mjs';
import { projectionSessionProfile } from '../../scripts/tests/projection-session-profile.mjs';
import { fixture, packets, sessionFrames } from './support/projection-observer-fixtures.mjs';

const ownerUrl = new URL('../../scripts/tests/projection-observer-owner.mjs', import.meta.url);
const original = readFileSync(ownerUrl, 'utf8');
const replacements = ['projection-zero-row-decoder.mjs', 'projection-session-profile.mjs'];
const controls = [
	['session required', [["!sessionBound || !['client', 'server'].includes(direction) || ended[direction] || !bytes(chunk)", "!['client', 'server'].includes(direction) || ended[direction] || !bytes(chunk)"]], (factory) => {
		const f = fixture(factory, false); assert.equal(f.capture(0), false);
	}],
	['detached copy', [['const copy = Buffer.alloc(chunk.length); Buffer.prototype.copy.call(chunk, copy);', 'const copy = chunk;']], (factory) => {
		const f = fixture(factory), input = packets()[0], originalBytes = Buffer.from(input), token = f.capture(0, input);
		input.fill(0); assert.equal(f.owner.forwarded(token, originalBytes, f.identity, 101n), true);
	}],
	['forwarding prerequisite', [['if (!entry.forwarded) return fail();', '']], (factory) => {
		const f = fixture(factory), token = f.capture(0); assert.equal(f.owner.observe(token, f.identity, 101n), false);
	}],
	['acknowledgement equality', [['!Buffer.prototype.equals.call(chunk, entry.copy)', 'false']], (factory) => {
		const f = fixture(factory), token = f.capture(0), changed = packets()[0]; changed[changed.length - 1] ^= 1;
		assert.equal(f.owner.forwarded(token, changed, f.identity, 101n), false);
	}],
	['actual result decoder', [['!decoder.feed(entry.direction, entry.copy, owned, now)', 'false']], (factory) => {
		const f = fixture(factory); assert.equal(f.send(0, Buffer.from([0, 0, 0, 0])), false);
	}],
	['both EOFs', [[' && ended.client && ended.server', '']], (factory) => {
		const f = fixture(factory); for (let i = 0; i < 5; i++) assert.equal(f.send(i), true);
		assert.equal(f.owner.finish(f.identity, 101n).synthetic_match, false);
	}],
	['queue event ceiling', [['events >= 512 || order.length >= 512', 'false']], (factory) => {
		const f = fixture(factory); for (let i = 0; i < 512; i++) assert.equal(typeof f.capture(0, Buffer.from([1])), 'symbol');
		assert.equal(f.capture(0, Buffer.from([1])), false);
	}],
	['cumulative/pending copy ceiling', [['copied + chunk.length > 16384 || pending + chunk.length > 16384', 'false']], (factory) => {
		const f = fixture(factory); assert.equal(typeof f.capture(0, Buffer.alloc(16384)), 'symbol'); assert.equal(f.capture(0, Buffer.from([1])), false);
	}],
	['original epoch', [['now >= deadline', 'false']], (factory) => {
		const f = fixture(factory); assert.equal(f.capture(0, undefined, 20000000100n), false);
	}],
	['sticky refusal', [['closed || refused', 'closed']], (factory) => {
		const f = fixture(factory); assert.equal(f.capture(0, Buffer.alloc(0)), false); assert.equal(f.capture(0), false);
	}],
];
for (const [name, changes, detect] of controls) test(`source control assertion-kills removed observer ${name}`, async () => {
	detect(createProjectionObserverOwner);
	let source = original;
	for (const [from, to] of changes) { assert.equal(source.split(from).length, 2, name); source = source.replace(from, to); }
	for (const file of replacements) {
		const from = `from './${file}'`; assert.equal(source.split(from).length, 2);
		source = source.replace(from, `from '${new URL(file, ownerUrl).href}'`);
	}
	const mutant = await import('data:text/javascript;base64,' + Buffer.from(source).toString('base64'));
	assert.throws(() => detect(mutant.createProjectionObserverOwner), assert.AssertionError);
});

test('source control assertion-kills removed limited username check in actual profile parser', async () => {
	const frames = sessionFrames(); frames[1][36] ^= 1;
	assert.equal(projectionSessionProfile(frames, '012345abcdef'), false);
	const source = readFileSync(new URL('../../scripts/tests/projection-session-profile.mjs', import.meta.url), 'utf8');
	const guard = '!hello.subarray(32, response).equals(username)'; assert.equal(source.split(guard).length, 2);
	const mutant = await import('data:text/javascript;base64,' + Buffer.from(source.replace(guard, 'false')).toString('base64'));
	assert.throws(() => assert.equal(mutant.projectionSessionProfile(frames, '012345abcdef'), false), assert.AssertionError);
});
