import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { selectedFixture } from './support/selected-qualification-fixture.mjs';
import { parseSelectedLine } from '../../scripts/tests/selected-qualification-grammar.mjs';
import { createSelectedTranscript } from '../../scripts/tests/selected-qualification-transcript.mjs';
import { selectedIdentities, verifySelectedIdentity } from '../../scripts/tests/selected-qualification-identity.mjs';
import { parseQualificationLine } from '../../scripts/tests/qualification-native-grammar.mjs';
const line = (m) => JSON.stringify(m);
const direction = (n) => n % 2 ? 'worker' : 'parent';
const send = (p, lines, n, tick = BigInt(n)) => p.feed(direction(n), lines[n], tick);
const failProof = (p) => { const r = p.finish(); assert.equal(r.synthetic_match, false); assert.equal(r.native_proven, false); assert.equal(p.finish(), false); };
test('complete source gate preserves all eight no-fault identities and pins every selected group', () => {
	assert.equal(verifySelectedIdentity(), true);
	const source = selectedIdentities(), old = JSON.parse(readFileSync(new URL('../../scripts/tests/qualification-native-identities.json', import.meta.url)));
	assert.deepEqual(Object.fromEntries(Object.entries(source).slice(0, 8)), old);
	assert.equal(Object.keys(source).length, 13); assert.equal(verifySelectedIdentity('alternate'), false);
	assert.throws(() => selectedIdentities('provider'), /arguments/);
});
for (const width of [1, 2, 7, 4096]) test(`synthetic duplex transcript width ${width}, explicit EOF, no native/loss/cleanup authority`, () => {
	const { source, lines, frames } = selectedFixture(), p = createSelectedTranscript(source);
	for (let n = 0; n < 6; n++) {
		assert.deepEqual(parseSelectedLine(lines[n].slice(0, -1), source), frames[n]);
		assert.equal(parseQualificationLine(lines[n].slice(0, -1), source), false);
		for (let i = 0; i < lines[n].length; i += width) assert.equal(p.feed(direction(n), lines[n].slice(i, i + width), BigInt(n)), true);
	}
	const result = p.finish(); assert.equal(result.synthetic_match, true);
	for (const k of Object.keys(result).filter((k) => k !== 'synthetic_match')) assert.equal(result[k], false, k);
	assert.equal(p.finish(), false); assert.equal(send(p, lines, 0), false);
});
test('each selected FINAL guard, source pin and result predicate is independently required', () => {
	const { source, frames } = selectedFixture(), base = frames[5], bad = [];
	for (const [k, v] of Object.entries({ domain: 'alynt.qualification-observation.native.v1', v: 2, kind: '__proto__', seq: 2, attempt: 'd'.repeat(63), root: base.attempt, pair: base.root, profile: 'qualification_no_fault', root_returned: true, pre_guards: 7, post_guards: 1, protected_reads: 39, writes: 0, original_slots_empty: false })) bad.push({ ...base, [k]: v });
	for (const k of Object.keys(base)) { const m = { ...base }; delete m[k]; bad.push(m); }
	for (const k of Object.keys(source)) bad.push({ ...base, source: { ...source, [k]: '0'.repeat(64) } });
	for (const k of Object.keys(base.observation)) bad.push({ ...base, observation: { ...base.observation, [k]: null } });
	for (const [k, v] of Object.entries(base.observation.result)) bad.push({ ...base, observation: { ...base.observation, result: { ...base.observation.result, [k]: k === 'historical_outcome' ? 'committed' : k === 'client_release_claimed' ? 1 : !v } } });
	for (const m of bad) assert.equal(parseSelectedLine(line(m), source), false);
	const m = structuredClone(base); m.observation.result.client_release_claimed = false;
	assert.equal(parseSelectedLine(line(m), source).observation.result.client_release_claimed, false);
});
test('all truncations of every individual message refuse; every transcript prefix is inconclusive', () => {
	const { source, lines } = selectedFixture();
	for (const raw of lines) for (let i = 0; i < raw.length - 1; i++) assert.equal(parseSelectedLine(raw.slice(0, i), source), false);
	for (let n = 0; n < 6; n++) {
		const p = createSelectedTranscript(source); for (let i = 0; i < n; i++) assert.equal(send(p, lines, i), true);
		failProof(p); assert.equal(send(p, lines, 0), false);
	}
});
test('noncanonical/deep/oversized bytes, wrong expected source and hostile objects never produce a message', () => {
	const { source, lines } = selectedFixture(), raw = lines[5].slice(0, -1);
	for (const kind of [null, [], {}, { toString: null, valueOf: null }]) assert.equal(parseSelectedLine(line({ ...JSON.parse(raw), kind }), source), false);
	for (const bad of [raw + 'x', raw + '\n', ' ' + raw, raw.replace('"v":1', '"v":1,"v":1'), raw.replace('"v":1', '"v":1.0'), raw.replace('selected.v1', 'selected.\\u00761'), 'é', 'x'.repeat(4096), '{"nest":' + '['.repeat(10) + '0' + ']'.repeat(10) + '}']) assert.equal(parseSelectedLine(bad, source), false);
	let calls = 0; const poison = { toJSON() { calls++; throw Error('callback'); } };
	assert.equal(parseSelectedLine(poison, source), false); assert.equal(createSelectedTranscript(poison), false);
	const accessor = { ...source }; Object.defineProperty(accessor, 'production', { enumerable: true, get() { calls++; throw Error('getter'); } });
	assert.equal(parseSelectedLine(raw, accessor), false); assert.equal(createSelectedTranscript(accessor), false); assert.equal(calls, 0);
	assert.equal(createSelectedTranscript({ ...source, selected_worker: source.selected_worker + '\n' }), false);
	const p = createSelectedTranscript(source); assert.equal(p.feed('parent', 'x'.repeat(32769), 0n), false); failProof(p);
});
for (let stage = 0; stage < 6; stage++) test(`stage ${stage} rejects direction, replay, label and invalid tick without rearming`, () => {
	for (const fault of ['direction', 'replay', 'label', 'clock']) {
		const { source, lines, frames } = selectedFixture(), p = createSelectedTranscript(source);
		for (let n = 0; n < stage; n++) assert.equal(send(p, lines, n), true);
		let m = structuredClone(frames[stage]); if (fault === 'label') m.attempt = stage ? 'd'.repeat(64) : 'z'.repeat(64);
		assert.equal(p.feed(fault === 'direction' ? direction(stage + 1) : direction(stage), fault === 'replay' ? lines[(stage + 1) % 6] : line(m) + '\n', fault === 'clock' ? -1n : BigInt(stage)), false);
		failProof(p);
	}
});
test('reserve and role type boundaries stay strict and no-fault committed FINAL is not selected', () => {
	const { source, frames } = selectedFixture(), m = frames[3];
	for (const v of ['6000000000', '10000000000']) assert.ok(parseSelectedLine(line({ ...m, remaining_ns: v }), source));
	for (const v of ['5999999999', '10000000001', '06000000000', '6e9', 6000000000, null]) assert.equal(parseSelectedLine(line({ ...m, remaining_ns: v }), source), false);
	assert.equal(parseSelectedLine(line({ ...m, capture: m.auth }), source), false);
	assert.equal(parseSelectedLine(line({ ...m, writer: 4294967296 }), source), false);
});
test('4s bind, 1s ack and strict 10s FINAL boundaries are measured from original fragment epochs', () => {
	for (const [bind, ack, final, passes] of [[3000000000n, 4000000000n, 9999999999n, true], [4000000001n, 4000000001n, 5000000000n, false], [1n, 1000000002n, 2000000000n, false], [1n, 2n, 10000000000n, false]]) {
		const { source, lines } = selectedFixture(), p = createSelectedTranscript(source), ticks = [0n, 0n, 0n, bind, ack, final];
		let accepted = true; for (let n = 0; n < 6 && accepted; n++) accepted = send(p, lines, n, ticks[n]);
		assert.equal(accepted, passes); assert.equal(p.finish().synthetic_match, passes);
	}
	const { source, lines } = selectedFixture(), p = createSelectedTranscript(source);
	assert.equal(send(p, lines, 0, 0n), true); assert.equal(send(p, lines, 1, 0n), true);
	assert.equal(p.feed('parent', lines[2].slice(0, 1), 0n), true);
	assert.equal(p.feed('parent', lines[2].slice(1), 10000000000n), false); failProof(p);
	const backwards = createSelectedTranscript(source);
	for (let n = 0; n < 4; n++) assert.equal(send(backwards, lines, n, BigInt(n)), true);
	assert.equal(send(backwards, lines, 4, 2n), false); failProof(backwards);
});
test('extra arguments, trailing byte, wrong bound writer/pair, partial frame and same-event extra frame stay terminal', () => {
	for (const fault of ['writer', 'pair', 'trailing', 'partial', 'extra_frame', 'arguments']) {
		const { source, frames, lines } = selectedFixture(), p = createSelectedTranscript(source);
		const stop = fault === 'writer' ? 4 : 5;
		for (let n = 0; n < stop; n++) assert.equal(send(p, lines, n), true);
		let m = structuredClone(frames[stop]); if (fault === 'writer') m.writer = 44; if (fault === 'pair') m.pair = 'd'.repeat(64);
		const raw = line(m) + '\n';
		if (fault === 'partial') assert.equal(p.feed(direction(stop), raw.slice(0, -1), 6n), true);
		else if (fault === 'arguments') assert.equal(p.feed(direction(stop), raw, 6n, 'provider'), false);
		else assert.equal(p.feed(direction(stop), raw + (fault === 'trailing' ? 'x' : fault === 'extra_frame' ? raw : ''), 6n), false);
		failProof(p);
	}
});
