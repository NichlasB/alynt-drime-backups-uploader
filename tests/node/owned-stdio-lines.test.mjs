import test from 'node:test';
import assert from 'node:assert/strict';
import { createOwnedStdioLines } from '../../scripts/tests/owned-stdio-lines.mjs';

test('non-vacuous fragmented/coalesced ASCII lines reach the fixed consumer exactly once', () => {
	for (let split = 1; split < 14; split++) {
		const seen = [], reader = createOwnedStdioLines((line) => { seen.push(line); return true; });
		const bytes = Buffer.from('first\nsecond\n');
		assert.equal(reader.feed(bytes.subarray(0, split)), true);
		if (split < bytes.length) assert.equal(reader.feed(bytes.subarray(split)), true);
		assert.equal(reader.end(), true); assert.deepEqual(seen, ['first', 'second']); assert.equal(reader.end(), false);
	}
});
for (const bytes of [Buffer.alloc(0), Buffer.from('\n'), Buffer.from('x\r\n'), Buffer.from('x\0\n'), Buffer.from('x\\n\n'), Buffer.from('é\n'), Buffer.from([127, 10]), Buffer.from('X'.repeat(4096) + '\n')]) test(`permanent malformed/oversize refusal ${bytes.length}:${bytes[0]}`, () => {
	const reader = createOwnedStdioLines(() => true); assert.equal(reader.feed(bytes), false); assert.equal(reader.feed(Buffer.from('valid\n')), false); assert.equal(reader.end(), false);
});
test('exact line/count/aggregate boundaries and consumer refusal', () => {
	let count = 0; const reader = createOwnedStdioLines(() => { count++; return true; });
	assert.equal(reader.feed(Buffer.from(('X'.repeat(4095) + '\n').repeat(4))), true); assert.equal(count, 4); assert.equal(reader.end(), true);
	const aggregate = createOwnedStdioLines(() => true); assert.equal(aggregate.feed(Buffer.from(('X'.repeat(4095) + '\n').repeat(4))), true); assert.equal(aggregate.feed(Buffer.from('X')), false);
	const extra = createOwnedStdioLines(() => true); assert.equal(extra.feed(Buffer.from('x\n'.repeat(5))), false); assert.equal(extra.end(), false);
	const huge = createOwnedStdioLines(() => { throw Error('Must reject before consuming.'); }); assert.equal(huge.feed(Buffer.alloc(16385, 65)), false);
	const refused = createOwnedStdioLines(() => false); assert.equal(refused.feed(Buffer.from('valid\n')), false); assert.equal(refused.end(), false);
});
test('empty stream, partial EOF, post-end input and wrong types cannot complete', () => {
	const empty = createOwnedStdioLines(() => true); assert.equal(empty.end(), false); assert.equal(empty.feed(Buffer.from('x\n')), false);
	const partial = createOwnedStdioLines(() => true); assert.equal(partial.feed(Buffer.from('x\npartial')), true); assert.equal(partial.end(), false);
	for (const value of [null, 'x\n', new Uint8Array([65, 10])]) { const reader = createOwnedStdioLines(() => true); assert.equal(reader.feed(value), false); }
	assert.throws(() => createOwnedStdioLines(null), TypeError);
});
