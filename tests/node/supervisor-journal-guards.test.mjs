import assert from 'node:assert/strict';
import { AssertionError } from 'node:assert';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import * as actual from '../../scripts/tests/supervisor-journal-data.mjs';
import { fixture, recover, run, intent, event, trace, prefix, cleanup, ids, objects, probes, terminals } from './support/supervisor-journal-fixtures.mjs';
const source = readFileSync(new URL('../../scripts/tests/supervisor-journal-data.mjs', import.meta.url), 'utf8');
const fresh = () => ({ ...Object.fromEntries([...ids, ...objects].map((k) => [k, Symbol(k)])), startedNs: 100n });
const controls = [
	['profile', 'fields.version !== SUPERVISOR_DATA_PROFILE', 'false', (m) => assert.equal(m.parseSupervisorIntentData(intent('proof', { version: 'foreign' })), false)],
	['canonical duplicate rejection', 'JSON.stringify(fields) === text', 'true', (m) => assert.equal(m.parseSupervisorIntentData(intent().replace('"mode":"proof"', '"mode":"proof","mode":"proof"')), false)],
	['revoked proxy guard ordering', 'types.isProxy(value) || Object.getPrototypeOf(value) !== Object.prototype', 'Object.getPrototypeOf(value) !== Object.prototype || types.isProxy(value)', (m) => {
		const f = fixture(m.createSupervisorJournalData), bad = Proxy.revocable({}, {}); bad.revoke(); let result;
		assert.doesNotThrow(() => { result = f.owner.accept(event(0, ...prefix[0]), bad.proxy, f.owned, 101n); }); assert.equal(result, false);
	}],
	['distinct roles and objects', 'new Set(allKeys.map((k) => config[k])).size !== allKeys.length', 'false', (m) => {
		const config = fresh(); config.consumer = config.supervisor; assert.throws(() => m.createSupervisorJournalData(config, intent()), TypeError);
	}],
	['single proof identity', 'allKeys.some((k) => consumed.has(config[k]))', 'false', (m) => {
		const f = fixture(m.createSupervisorJournalData); assert.throws(() => m.createSupervisorJournalData(f.config, intent()), TypeError);
	}],
	['context binding', '!identityKeys.every((k) => ids[k] === config[k])', 'false', (m) => {
		const f = fixture(m.createSupervisorJournalData); assert.equal(f.owner.accept(event(0, ...prefix[0]), { ...f.identity, attempt: Symbol() }, f.owned, 101n), false);
	}],
	['owned-object binding', '!objectKeys.every((k) => objects[k] === config[k])', 'false', (m) => {
		const f = fixture(m.createSupervisorJournalData); assert.equal(f.owner.accept(event(0, ...prefix[0]), f.identity, { ...f.owned, account: Symbol() }, 101n), false);
	}],
	['sequence', 'fields.seq !== accepted', 'false', (m) => {
		const f = fixture(m.createSupervisorJournalData); assert.equal(f.owner.accept(event(99, ...prefix[0]), f.identity, f.owned, 101n), false);
	}],
	['original deadline', 'now >= config.startedNs + EPOCH_NS', 'false', (m) => {
		const f = fixture(m.createSupervisorJournalData); assert.equal(f.send(...prefix[0], 20000000100n), false);
	}],
	['sticky clock failure', '!clockFault && !pending()', '!pending()', (m) => {
		const f = fixture(m.createSupervisorJournalData); run(f, prefix.slice(0, 3)); assert.equal(f.send('intent', 'create-account', 20000000100n), false);
		const r = f.finish(); assert.throws(() => recover(f, r), TypeError);
	}],
	['sticky refusal', 'if (closed || refused) return false;', 'if (closed) return false;', (m) => {
		const f = fixture(m.createSupervisorJournalData); assert.equal(f.owner.accept(event(0, 'unknown', 'unknown'), f.identity, f.owned, 101n), false);
		assert.equal(f.owner.accept(event(0, ...prefix[0]), f.identity, f.owned, 101n), false);
	}],
	['ambiguous intent quarantine', '!pending()', 'true', (m) => {
		const f = fixture(m.createSupervisorJournalData); run(f, prefix.slice(0, 2)); assert.throws(() => recover(f, f.finish()), TypeError);
	}],
	['sealed recovery provenance', 'recoveries.get(recovery)', 'recoveries.get(recovery) ?? recovery', (m) => {
		const config = fresh(), forged = { config, used: false, account: false, root: true, process: false, retained: 1, attempts: 3, accepted: 3, bytes: 400, last: 102n };
		assert.throws(() => m.createSupervisorJournalData(config, intent('cleanup-only'), forged), TypeError);
	}],
	['one-use recovery', '!prior || prior.used ||', '!prior ||', (m) => {
		const f = fixture(m.createSupervisorJournalData); run(f, prefix.slice(0, 3)); const r = f.finish(); recover(f, r); assert.throws(() => recover(f, r), TypeError);
	}],
	['recovery cannot seed proof', 'recovery !== undefined ||', '', (m) => {
		const f = fixture(m.createSupervisorJournalData); run(f, prefix.slice(0, 3)); const r = f.finish();
		assert.throws(() => m.createSupervisorJournalData(fresh(), intent(), r), TypeError);
	}],
	['no recovery epoch renewal', 'prior.config.startedNs !== config.startedNs', 'false', (m) => {
		const f = fixture(m.createSupervisorJournalData); run(f, prefix.slice(0, 3)); const r = f.finish();
		assert.throws(() => m.createSupervisorJournalData({ ...f.config, startedNs: 1000n }, intent('cleanup-only'), r), TypeError);
	}],
	['cumulative recovery event charge', 'attempts = prior?.attempts ?? 0', 'attempts = 0', (m) => {
		const f = fixture(m.createSupervisorJournalData); run(f, prefix.slice(0, 3)); const r = f.finish(), g = recover(f, r);
		run(g, cleanup.slice(2)); assert.equal(g.finish().model_events, r.model_events + 3);
	}],
	['cumulative recovery bytes', '(prior?.bytes ?? 0) + intentText.length', 'intentText.length', (m) => {
		const f = fixture(m.createSupervisorJournalData); run(f, prefix.slice(0, 3)); const r = f.finish(), g = recover(f, r);
		const tail = cleanup.slice(2); run(g, tail); const expected = r.model_bytes + intent('cleanup-only').length + tail.reduce((n, [k, v], i) => n + event(r.model_accepted + i, k, v).length, 0);
		assert.equal(g.finish().model_bytes, expected);
	}],
	['containment before removal', "process ? 'containment' :", '', (m) => {
		const f = fixture(m.createSupervisorJournalData); run(f, prefix); const g = recover(f, f.finish()); assert.equal(g.send('intent', 'remove-account'), false);
	}],
	['cleanup cannot restart proof', "let stage = mode === 'cleanup-only' ? cleanupStage() : 'fresh';", "let stage = 'fresh';", (m) => {
		const f = fixture(m.createSupervisorJournalData); run(f, prefix.slice(0, 3)); const g = recover(f, f.finish()); assert.equal(g.send(...prefix[0]), false);
	}],
	['byte ceiling before parser', 'if (bytes > MAX_BYTES)', 'if (false)', (m) => {
		const f = fixture(m.createSupervisorJournalData); f.owner.accept('x'.repeat(3000), f.identity, f.owned, 101n); assert.equal(f.finish().reason, 'byte-limit');
	}],
	['event cap across recovery', 'if (attempts > MAX_EVENTS)', 'if (false)', (m) => {
		const f = fixture(m.createSupervisorJournalData); run(f); f.send('journal', 'removed-control-record'); const g = recover(f, f.finish());
		assert.equal(g.send('journal', 'removed-control-record'), false); assert.equal(g.finish().reason, 'event-limit');
	}],
	['complete terminal suffix', 'terminal.size === terminals.length', 'terminal.size === 1', (m) => {
		const f = fixture(m.createSupervisorJournalData); run(f, [...prefix, ...probes.map((v) => ['probe', v]), ['terminal', terminals[0]], ...cleanup]);
		assert.equal(f.finish().proof_model_match, false);
	}],
	['non-vacuous proof completion', "proof_model_match: complete && mode === 'proof'", 'proof_model_match: false', (m) => {
		const f = fixture(m.createSupervisorJournalData); assert.ok(run(f).every(Boolean)); assert.equal(f.finish().proof_model_match, true);
	}],
	['actual authority remains disabled', 'elevation_supported: false', 'elevation_supported: true', (m) => assert.equal(m.parseSupervisorIntentData(intent()).elevation_supported, false)],
];
for (const [name, before, after, exercise] of controls) test(`assertion-killed supervisor control: ${name}`, async () => {
	exercise(actual); assert.equal(source.split(before).length - 1, 1, 'unique source anchor required');
	const mutant = await import(`data:text/javascript;base64,${Buffer.from(source.replace(before, after) + `\n// ${name}`).toString('base64')}`);
	assert.throws(() => exercise(mutant), AssertionError, 'must fail actual assertions, not module import or syntax');
});
