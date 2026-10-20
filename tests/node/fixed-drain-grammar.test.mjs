import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createFixedDrain, drainQueries } from '../../scripts/tests/fixed-drain-grammar.mjs';
import { createDrainFrames } from '../../scripts/tests/fixed-drain-stream.mjs';
import { drainPackets, result, column, text, frame, queries } from './support/fixed-drain-fixtures.mjs';

test('two exact literal queries remain tied to fixed source and post-COMMIT source order', () => {
	assert.deepEqual(drainQueries, queries);
	const source = readFileSync(new URL('../../includes/trait-native-closed-begin-session.php', import.meta.url), 'utf8');
	for (const q of queries) assert.ok(source.includes(`$this->reader->query( '${q}' )`));
	const transaction = readFileSync(new URL('../../includes/trait-registration-admission-transaction.php', import.meta.url), 'utf8');
	assert.match(transaction, /query\( 'COMMIT AND NO CHAIN NO RELEASE' \)[\s\S]+?if \( true !== \$ack \|\| ! \$this->session\( false \) \)/);
	assert.match(source, /\$expected === \$r->fetch_row\(\) && \$this->policy\(\)/);
});
for (const width of [1, 2, 3, 4, 7, 64, 99999]) test(`positive fragmented/coalesced fixed drain width ${width}`, () => {
	const grammar = createFixedDrain(), bytes = Buffer.concat(drainPackets());
	const adapter = createDrainFrames(4096, (f) => grammar.accept(f[3] === 0 ? 'client' : 'server', f[3], f.subarray(4)));
	for (let i = 0; i < bytes.length; i += width) assert.equal(adapter.feed(bytes.subarray(i, i + width)), true);
	assert.equal(adapter.end(), true); assert.equal(grammar.done(), true); assert.equal(adapter.end(), false);
});
const accepts = (frames) => { const g = createFixedDrain(); for (const f of frames) if (!g.accept(f[3] === 0 ? 'client' : 'server', f[3], f.subarray(4))) return false; return g.done(); };
for (const mode of ['missing', 'swapped', 'duplicate', 'third', 'second-commit', 'early-quit', 'no-quit', 'bad-seq', 'count', 'extra-row', 'empty-row', 'null', 'warnings', 'status', 'eof-size', 'err', 'ok', 'catalog', 'definition-tail', 'row-tail', 'long-string', 'nonminimal', 'wide-length']) test(`grammar refuses ${mode}`, () => {
	const p = drainPackets();
	if (mode === 'missing') p.splice(0, 15);
	if (mode === 'swapped') [p[0], p[15]] = [p[15], p[0]];
	if (mode === 'duplicate') p[15] = p[0];
	if (mode === 'third') p[25] = p[0];
	if (mode === 'second-commit') p[15] = frame(Buffer.from('\x03COMMIT AND NO CHAIN NO RELEASE'));
	if (mode === 'early-quit') p[15] = frame(Buffer.from([1]));
	if (mode === 'no-quit') p.pop();
	if (mode === 'bad-seq') p[2][3] = 99;
	if (mode === 'count') p[1][4] = 9;
	if (mode === 'extra-row') p.splice(14, 0, p[13]);
	if (mode === 'empty-row') p.splice(13, 1);
	if (mode === 'null') p[13] = frame(Buffer.concat(Array.from({ length: 10 }, (_, i) => text(i === 0 ? null : 'x'))), 13);
	if (mode === 'warnings') p[14][5] = 1;
	if (mode === 'status') p[14][7] = 10;
	if (mode === 'eof-size') p[14] = frame(Buffer.from([254, 0, 0, 2, 0, 0]), 14);
	if (mode === 'err') p[14] = frame(Buffer.from([255]), 14);
	if (mode === 'ok') p[13] = frame(Buffer.from([0, 0, 0, 2, 0, 0, 0]), 13);
	if (mode === 'catalog') p[2][5] = 88;
	if (mode === 'definition-tail') p[2] = frame(Buffer.concat([column(), Buffer.from([0])]), 2);
	if (mode === 'row-tail') p[13] = frame(Buffer.concat([p[13].subarray(4), Buffer.from([0])]), 13);
	if (mode === 'long-string') p[13] = result(0, [Buffer.alloc(1025), ...Array(9).fill('x')])[12];
	if (mode === 'nonminimal') p[13] = frame(Buffer.from([252, 1, 0, 65, ...Array(9).fill(0)]), 13);
	if (mode === 'wide-length') p[13] = frame(Buffer.from([253, 1, 0, 0, 65, ...Array(9).fill(0)]), 13);
	assert.equal(accepts(p), false);
});
test('canonical string lengths 250/251/1024; header and aggregate caps, partial EOF and throw', () => {
	for (const n of [250, 251, 1024]) { const p = drainPackets(); p[13] = result(0, [Buffer.alloc(n, 65), ...Array(9).fill('')])[12]; assert.equal(accepts(p), true); }
	for (const bytes of [Buffer.from([1, 16, 0, 0]), Buffer.alloc(32769), Buffer.from([0, 0, 0, 0])]) { const a = createDrainFrames(4096, () => true); assert.equal(a.feed(bytes), false); assert.equal(a.end(), false); }
	const a = createDrainFrames(4096, () => true); assert.equal(a.feed(Buffer.from([1, 0])), true); assert.equal(a.end(), false);
	const b = createDrainFrames(4096, () => { throw Error('invented'); }); assert.equal(b.feed(frame(Buffer.from([1]))), false); assert.equal(b.end(), false);
});
test('aggregate cap counts complete valid fields and both command/response directions', () => {
	const padded = Buffer.concat(['def', 'A'.repeat(1000), 'B'.repeat(1000), 'C'.repeat(1000), 'field', ''].map(text).concat([Buffer.from([12]), Buffer.alloc(12)]));
	const p = drainPackets();
	for (const i of [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 17, 18, 19, 20, 21]) p[i] = frame(padded, p[i][3]);
	assert.ok(padded.length < 4096); assert.ok(p.reduce((n, b) => n + b.length, 0) > 32768); assert.equal(accepts(p), false);
	const g = createFixedDrain(); for (let i = 0; i < 11; i++) assert.equal(g.accept(p[i][3] ? 'server' : 'client', p[i][3], p[i].subarray(4)), true);
	assert.equal(g.accept('server', p[11][3], p[11].subarray(4)), true);
	assert.equal(g.accept('server', p[12][3], p[12].subarray(4)), true);
	// A later otherwise-valid field packet crosses the aggregate guard.
	let refused = false; for (const b of p.slice(13)) if (!g.accept(b[3] ? 'server' : 'client', b[3], b.subarray(4))) { refused = true; break; }
	assert.equal(refused, true);
});
test('standalone framer admits exact byte/frame bounds then refuses one over', () => {
	const a = createDrainFrames(4096, () => true);
	const blocks = Array.from({ length: 7 }, () => frame(Buffer.alloc(4096)));
	blocks.push(frame(Buffer.alloc(32768 - 7 * 4100 - 4)));
	assert.equal(a.feed(Buffer.concat(blocks)), true); assert.equal(a.end(), true);
	const b = createDrainFrames(4096, () => true); assert.equal(b.feed(Buffer.concat([...blocks, frame(Buffer.from([1]))])), false);
	const c = createDrainFrames(4096, () => true); assert.equal(c.feed(Buffer.concat(Array.from({ length: 26 }, () => frame(Buffer.from([1]))))), true); assert.equal(c.feed(frame(Buffer.from([1]))), false);
});
