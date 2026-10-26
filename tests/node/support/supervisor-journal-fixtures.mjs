import { createSupervisorJournalData, SUPERVISOR_DATA_PROFILE } from '../../../scripts/tests/supervisor-journal-data.mjs';
export const ids = ['attempt', 'supervisor', 'initiator', 'consumer', 'generation'];
export const objects = ['account', 'root', 'process', 'journal'];
export const probes = ['allowed-read', 'mutable-control', 'write-denial', 'replace-delete-denial', 'owner-ancestor-alias-denial', 'no-privileged-handles'];
export const terminals = ['natural-shutdown', 'engine-complete', 'process-exit-zero', 'client-eof', 'server-eof'];
export function intent(mode = 'proof', patch = {}) {
	return JSON.stringify({ version: SUPERVISOR_DATA_PROFILE, mode, target: 'plugin-tester/local-only',
		artifact: 'fixed-supervisor.fixture.v1', policy: 'one-owned-root.closed.v1', parent: 'program-files.candidate.v1', ...patch });
}
export const event = (seq, kind, value) => JSON.stringify({ seq, kind, value });
export const prefix = [['handoff', 'protected-model'], ['intent', 'create-root'], ['result', 'root-owned'],
	['intent', 'create-account'], ['result', 'account-owned'], ['intent', 'launch-probe'], ['result', 'probe-owned']];
export const cleanup = [['intent', 'remove-account'], ['result', 'account-removed'], ['intent', 'remove-root'],
	['result', 'root-removed'], ['journal', 'retained-control-record']];
export const trace = [...prefix, ...probes.map((v) => ['probe', v]), ...terminals.map((v) => ['terminal', v]), ...cleanup];
function bind(factory, config, mode, receipt) {
	const identity = Object.fromEntries(ids.map((k) => [k, config[k]])), owned = Object.fromEntries(objects.map((k) => [k, config[k]]));
	const owner = factory(config, intent(mode), receipt); let seq = receipt?.model_accepted ?? 0;
	return { factory, config, identity, owned, owner,
		send: (kind, value, now = receipt ? 103n : 101n) => owner.accept(event(seq++, kind, value), identity, owned, now),
		finish: (now = receipt ? 104n : 102n) => owner.finish(identity, owned, now) };
}
export function fixture(factory = createSupervisorJournalData) {
	const config = { ...Object.fromEntries([...ids, ...objects].map((k) => [k, Symbol(k)])), startedNs: 100n };
	return bind(factory, config, 'proof');
}
export function recover(f, receipt) { return bind(f.factory, { ...f.config }, 'cleanup-only', receipt); }
export function run(f, events = trace) { return events.map(([kind, value]) => f.send(kind, value)); }
