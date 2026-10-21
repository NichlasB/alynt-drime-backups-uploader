import test from 'node:test';
import assert from 'node:assert/strict';
import { createQualificationStages } from '../../scripts/tests/qualification-native-stages.mjs';
import { passiveObservation } from '../../scripts/tests/observation-passive-sink.mjs';
import { frameStream } from '../../scripts/tests/relay-no-fault-frames.mjs';
import { drainQueries } from '../../scripts/tests/fixed-drain-grammar.mjs';
function packet(payload, sequence = 0) { const b = Buffer.from(payload), f = Buffer.alloc(b.length + 4); f.writeUIntLE(b.length, 0, 3); f[3] = sequence; b.copy(f, 4); return f; }
const query = (sql, type = 3) => packet(Buffer.concat([Buffer.from([type]), Buffer.from(sql, 'ascii')]));
test('passive stages distinguish observed from forwarded writer setup without altering bytes', () => {
	const p = createQualificationStages(); assert.throws(() => createQualificationStages('provider'));
	const fixtures = [['SET SESSION TRANSACTION READ WRITE', 'read_write'], ['SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED', 'read_committed'], ['SET SESSION innodb_lock_wait_timeout=2', 'row_lock_wait'], ['SET SESSION lock_wait_timeout=3', 'metadata_lock_wait'], [drainQueries[1], 'policy_select']];
	let n = 0;
	for (const [sql, label] of fixtures) {
		const f = query(sql), original = Buffer.from(f); p.frame('client', f); n++;
		assert.equal(p.summary().last_setup_command_seen, label); assert.equal(p.summary().frames_forwarded, n - 1);
		p.account('client', f); assert.deepEqual(f, original); assert.equal(p.summary().last_setup_command_forwarded, label);
	}
	const result = p.summary(); assert.equal(result.writer_setup_command_seen, true); assert.equal(result.frames_observed, 5); assert.equal(result.frames_forwarded, 5); assert.equal(result.diagnostic_only, true);
	assert.equal(Object.hasOwn(result, 'passed'), false); assert.equal(Object.hasOwn(result, 'policy_verified'), false); // Command forwarding is not an ACK or policy proof.
});
test('metadata preparation, ownership-shaped identifiers and unknown SQL stay support-safe', () => {
	const p = createQualificationStages(), sql = 'SELECT ENGINE,TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 2';
	p.frame('client', query(sql)); assert.equal(p.summary().last_setup_command_seen, 'none');
	p.frame('client', query(sql, 22)); assert.equal(p.summary().last_setup_command_seen, 'metadata_engine_prepare');
	p.frame('client', query('SHOW INDEX FROM `wp_alynt_core_probe_012345abcdef_options`')); assert.equal(p.summary().last_setup_command_seen, 'metadata_index');
	p.frame('client', query('SHOW INDEX FROM `wp_options`')); assert.equal(p.summary().last_setup_command_seen, 'metadata_index');
	p.frame('client', query('SELECT secret-cookie=private-do-not-export')); const summary = JSON.stringify(p.summary());
	assert.equal(summary.includes('private-do-not-export'), false); assert.equal(summary.includes('012345abcdef'), false); assert.equal(summary.includes('SELECT'), false);
	const server = packet([255, 1, 0, 35, 72, 89, 48, 48, 48], 1), original = Buffer.from(server); p.frame('server', server); assert.deepEqual(server, original); assert.equal(p.summary().server_error_seen, true);
});
test('first transport failure is fixed and latched; counters remain bounded', () => {
	const p = createQualificationStages(), f = query('unknown');
	for (let n = 0; n < 4100; n++) { p.frame('client', f); p.account('client', f); }
	assert.equal(p.summary().frames_observed, 4097); assert.equal(p.summary().frames_forwarded, 4097); assert.equal(p.summary().inconclusive, true);
	p.failure('qualification_transport_cap'); p.failure('secret raw SQL'); assert.equal(p.summary().first_transport_failure, 'qualification_transport_cap');
	const unknown = createQualificationStages(); unknown.failure('secret raw SQL'); assert.equal(unknown.summary().first_transport_failure, 'unknown_transport_failure');
	const negotiation = createQualificationStages(); negotiation.failure('unsupported_negotiation_tls'); assert.equal(negotiation.summary().first_transport_failure, 'unsupported_negotiation');
});
test('invalid diagnostic inputs refuse without exceptions, retained bytes or transport authority', () => {
	for (const f of [null, 'secret', Buffer.alloc(0), Buffer.from([1, 0, 0, 0]), packet([3], 0).subarray(0, 4)]) {
		const p = createQualificationStages(); assert.doesNotThrow(() => p.frame('client', f)); assert.equal(p.summary().inconclusive, true); assert.equal(p.summary().frames_observed, 0);
	}
	const p = createQualificationStages(); p.account('client', query('SET SESSION TRANSACTION READ WRITE')); assert.equal(p.summary().inconclusive, true); assert.equal(p.summary().frames_forwarded, 0);
});
test('actual bounded framer remains byte-equal with normal or throwing passive diagnostics', async () => {
	const input = Buffer.concat([query('SET SESSION TRANSACTION READ WRITE'), query(drainQueries[1])]);
	for (const throwing of [false, true]) {
		const stage = createQualificationStages(), observer = throwing ? { frame() { throw Error('private'); }, unavailable() { throw Error('private'); } } : stage;
		const sink = passiveObservation(observer), output = [];
		const p = frameStream(async (f) => { sink.call('frame', 'client', f); output.push(Buffer.from(f)); sink.call('account', 'client', f); });
		for (let n = 0; n < input.length; n += 3) await p.feed(input.subarray(n, n + 3));
		const result = p.finish(); assert.equal(result.complete, true); assert.equal(result.equal, true); assert.deepEqual(Buffer.concat(output), input);
		assert.equal(sink.failed(), throwing); if (!throwing) assert.equal(stage.summary().last_setup_command_forwarded, 'policy_select');
	}
});
