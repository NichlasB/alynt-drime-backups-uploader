import test from 'node:test';
import assert from 'node:assert/strict';
import { qualificationFixture } from './support/native-qualification-fixture.mjs';
import { parseNativeQualificationLine } from '../../scripts/tests/native-qualification-grammar.mjs';
import { createNativeQualificationTranscript, createNativeQualificationNoFaultTranscript } from '../../scripts/tests/native-qualification-transcript.mjs';
import { nativeQualificationIdentities, verifyNativeQualificationIdentity } from '../../scripts/tests/native-qualification-identity.mjs';
import { parseSelectedLine } from '../../scripts/tests/selected-qualification-grammar.mjs';
const dir = (n) => n % 2 ? 'worker' : 'parent';
const send = (p, lines, n, tick = BigInt(n)) => p.feed(dir(n), lines[n], tick);
test('new identities cover current groups and the unchanged124-file native predecessor', () => {
	assert.equal(verifyNativeQualificationIdentity(), true); assert.equal(Object.keys(nativeQualificationIdentities()).length, 7);
	assert.equal(verifyNativeQualificationIdentity('provider'), false); assert.throws(() => nativeQualificationIdentities('path'), /arguments/);
});
for (const noFault of [false, true]) for (const width of [1, 2, 7, 4096]) test(`eight-frame ${noFault ? 'no-fault' : 'selected'} transcript width${width}, EOF and no authority`, () => {
	const { source, frames, lines } = qualificationFixture(noFault), p = (noFault ? createNativeQualificationNoFaultTranscript : createNativeQualificationTranscript)(source);
	for (let n = 0; n < 8; n++) {
		assert.deepEqual(parseNativeQualificationLine(lines[n].slice(0, -1), source), frames[n]);
		assert.equal(parseSelectedLine(lines[n].slice(0, -1), source), false);
		for (let at = 0; at < lines[n].length; at += width) assert.equal(p.feed(dir(n), lines[n].slice(at, at + width), BigInt(n)), true);
	}
	const result = p.finish(); assert.equal(result.synthetic_match, true); for (const [key, value] of Object.entries(result)) if (key !== 'synthetic_match') assert.equal(value, false, key);
	assert.equal(p.finish(), false); assert.equal(send(p, lines, 0), false);
});
test('each FINAL field/domain/source/result/guard and sealed qualification is independently required', () => {
	const { source, frames } = qualificationFixture(), base = frames[7];
	const bad = [];
	for (const key of Object.keys(base)) { const m = structuredClone(base); delete m[key]; bad.push(m); }
	for (const [key, value] of Object.entries({ native_result: false, pre_guards: 8, post_guards: 0, admission_reads: 39, barrier_reads: 9, writes: 0, metadata_checks: 1, zero_dml_checks: 1, original_slots_empty: false, writer_erased_before_read: false, barrier_closed_before_finish: false })) bad.push({ ...base, [key]: value });
	for (const key of Object.keys(base.native_result)) bad.push({ ...base, native_result: { ...base.native_result, [key]: key === 'classification' ? 'not_observed_at_barrier' : !base.native_result[key] } });
	for (const key of Object.keys(base.observation)) bad.push({ ...base, observation: { ...base.observation, [key]: null } });
	for (const key of Object.keys(base.observation.result)) bad.push({ ...base, observation: { ...base.observation, result: { ...base.observation.result, [key]: key === 'historical_outcome' ? 'committed' : !base.observation.result[key] } } });
	for (const key of Object.keys(source)) bad.push({ ...base, source: { ...source, [key]: '0'.repeat(64) } });
	for (let i = 0; i < 24; i++) { const m = structuredClone(base); m.guard_order.splice(i, 1); bad.push(m); }
	for (const m of bad) assert.equal(parseNativeQualificationLine(JSON.stringify(m), source), false);
});
for (let stage = 0; stage < 8; stage++) test(`stage${stage} consumes wrong direction, replay, identity, arity and nonmonotonic clocks`, () => {
	for (const fault of ['direction', 'replay', 'identity', 'arity', 'clock']) {
		const { source, frames, lines } = qualificationFixture(), p = createNativeQualificationTranscript(source);
		for (let n = 0; n < stage; n++) assert.equal(send(p, lines, n), true);
		const m = structuredClone(frames[stage]); m.attempt = fault === 'identity' ? (stage ? 'd'.repeat(64) : 'bad') : m.attempt;
		const args = [fault === 'direction' ? dir(stage + 1) : dir(stage), fault === 'replay' ? lines[(stage + 1) % 8] : JSON.stringify(m) + '\n', fault === 'clock' ? -1n : BigInt(stage)]; if (fault === 'arity') args.push('extra');
		assert.equal(p.feed(...args), false); assert.equal(p.finish().synthetic_match, false); assert.equal(p.finish(), false);
	}
});
test('all frame truncations and every prefix refuse; duplicate/noncanonical/deep/oversized/trailing bytes reject', () => {
	const { source, lines } = qualificationFixture();
	for (const line of lines) for (let n = 0; n < line.length - 1; n++) assert.equal(parseNativeQualificationLine(line.slice(0, n), source), false);
	for (let prefix = 0; prefix < 8; prefix++) { const p = createNativeQualificationTranscript(source); for (let n = 0; n < prefix; n++) assert.equal(send(p, lines, n), true); assert.equal(p.finish().synthetic_match, false); }
	const raw = lines[7].slice(0, -1);
	for (const bad of [raw + 'x', raw + '\n', ' ' + raw, raw.replace('"v":1', '"v":1,"v":1'), raw.replace('"v":1', '"v":1.0'), raw.replace('selected.v1', 'selected.\\u00761'), 'é', 'x'.repeat(4096), '{"nest":' + '['.repeat(9) + '0' + ']'.repeat(9) + '}']) assert.equal(parseNativeQualificationLine(bad, source), false);
	const p = createNativeQualificationTranscript(source); assert.equal(p.feed('parent', 'x'.repeat(32769), 0n), false); assert.equal(p.finish().synthetic_match, false);
});
test('two idle role/ACK bindings, profiles and durations cannot be swapped, widened or reset', () => {
	for (const [stage, key, value] of [[3,'guard_index',7],[4,'writer',104],[4,'remaining_ns','7000000000'],[5,'auth',105],[5,'barrier',103],[5,'read_remaining_ns','10000000001'],[5,'root_remaining_ns','20000000001'],[6,'barrier',105],[6,'read_remaining_ns','8000000000'],[7,'barrier',105],[7,'profile','native_lifecycle_no_fault']]) {
		const { source, frames, lines } = qualificationFixture(), p = createNativeQualificationTranscript(source);
		for (let n = 0; n < stage; n++) assert.equal(send(p, lines, n), true);
		const m = structuredClone(frames[stage]); m[key] = value; assert.equal(p.feed(dir(stage), JSON.stringify(m) + '\n', BigInt(stage)), false); assert.equal(p.finish().synthetic_match, false);
	}
});
test('4s selection, both1s ACKs, read deadline and strict original20s root use first fragment epochs', () => {
	for (const [ticks, good] of [ [[0n,0n,0n,1n,2n,3n,4n,5n],true], [[0n,0n,0n,4000000001n,4000000002n,4000000003n,4000000004n,4000000005n],false], [[0n,0n,0n,1n,1000000002n,1000000003n,1000000004n,1000000005n],false], [[0n,0n,0n,1n,2n,3n,1000000004n,1000000005n],false], [[0n,0n,0n,1n,2n,3n,4n,9000000003n],false], [[0n,0n,0n,1n,2n,15000000000n,15000000001n,20000000000n],false] ]) {
		const { source, lines } = qualificationFixture(), p = createNativeQualificationTranscript(source); let accepted = true;
		for (let n = 0; n < 8 && accepted; n++) accepted = send(p, lines, n, ticks[n]); assert.equal(accepted, good); assert.equal(p.finish().synthetic_match, good);
	}
	const { source, lines } = qualificationFixture(), p = createNativeQualificationTranscript(source);
	for (let n = 0; n < 6; n++) assert.equal(send(p, lines, n, BigInt(n)), true);
	assert.equal(p.feed('parent', lines[6].slice(0, 1), 6n), true); assert.equal(p.feed('parent', lines[6].slice(1), 1000000006n), false); assert.equal(p.finish().synthetic_match, false);
});
