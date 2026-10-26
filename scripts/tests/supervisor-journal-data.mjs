// In-memory DATA contract only. Symbols/enums are not OS identities or permissions.
import { types } from 'node:util';
export const SUPERVISOR_DATA_PROFILE = 'supervisor-intent-journal.data.v1';
const identityKeys = ['attempt', 'supervisor', 'initiator', 'consumer', 'generation'];
const objectKeys = ['account', 'root', 'process', 'journal'];
const allKeys = [...identityKeys, ...objectKeys];
const consumed = new WeakSet(), recoveries = new WeakMap();
const wellKnown = new Set(Object.getOwnPropertyNames(Symbol).map((key) => Symbol[key]).filter((v) => typeof v === 'symbol'));
const MAX_EVENTS = 24, MAX_BYTES = 2048, EPOCH_NS = 20000000000n;
const probes = ['allowed-read', 'mutable-control', 'write-denial', 'replace-delete-denial', 'owner-ancestor-alias-denial', 'no-privileged-handles'];
const terminals = ['natural-shutdown', 'engine-complete', 'process-exit-zero', 'client-eof', 'server-eof'];
const actual = Object.freeze({ elevation_supported: false, authentication_proven: false, account_supported: false,
	file_supported: false, process_supported: false, cleanup_proven: false, source_ownership_proven: false,
	storage_supported: false, wiring_supported: false, execution_supported: false });
const record = (value, keys) => {
	if (!value || typeof value !== 'object' || types.isProxy(value) || Object.getPrototypeOf(value) !== Object.prototype) return null;
	if (Reflect.ownKeys(value).length !== keys.length) return null;
	const entries = keys.map((key) => [key, Object.getOwnPropertyDescriptor(value, key)]);
	return entries.every(([, d]) => d && Object.hasOwn(d, 'value')) ? Object.fromEntries(entries.map(([k, d]) => [k, d.value])) : null;
};
const canonical = (text, keys, max) => {
	if (typeof text !== 'string' || text.length === 0 || text.length > max || !/^[\x20-\x7e]+$/.test(text)) return null;
	let value; try { value = JSON.parse(text); } catch { return null; }
	const fields = record(value, keys);
	return fields && JSON.stringify(fields) === text ? fields : null;
};

export function parseSupervisorIntentData(text) {
	const fields = canonical(text, ['version', 'mode', 'target', 'artifact', 'policy', 'parent'], 512);
	if (!fields || fields.version !== SUPERVISOR_DATA_PROFILE || !['proof', 'cleanup-only'].includes(fields.mode) ||
		fields.target !== 'plugin-tester/local-only' || fields.artifact !== 'fixed-supervisor.fixture.v1' ||
		fields.policy !== 'one-owned-root.closed.v1' || fields.parent !== 'program-files.candidate.v1') return false;
	return Object.freeze({ ...fields, synthetic_only: true, ...actual });
}

export function createSupervisorJournalData(options, intentText, recovery) {
	const config = record(options, [...allKeys, 'startedNs']), intent = parseSupervisorIntentData(intentText);
	if (!config || !intent || typeof config.startedNs !== 'bigint' || config.startedNs < 0n ||
		!allKeys.every((k) => typeof config[k] === 'symbol' && Symbol.keyFor(config[k]) === undefined && !wellKnown.has(config[k])) ||
		new Set(allKeys.map((k) => config[k])).size !== allKeys.length) throw new TypeError('Invalid synthetic supervisor context.');
	const prior = recoveries.get(recovery), mode = intent.mode;
	if (mode === 'cleanup-only') {
		if (!prior || prior.used || !allKeys.every((k) => prior.config[k] === config[k]) || prior.config.startedNs !== config.startedNs) throw new TypeError('Invalid synthetic recovery handoff.');
		prior.used = true;
	} else {
		if (recovery !== undefined || allKeys.some((k) => consumed.has(config[k]))) throw new TypeError('Synthetic identity reuse.');
		for (const key of allKeys) consumed.add(config[key]);
	}
	let account = prior?.account ?? false, root = prior?.root ?? false, process = prior?.process ?? false;
	let retained = prior?.retained ?? 0, attempts = prior?.attempts ?? 0, accepted = prior?.accepted ?? 0;
	let bytes = Math.min(MAX_BYTES + 1, (prior?.bytes ?? 0) + intentText.length), last = prior?.last ?? config.startedNs;
	let refused = false, closed = false, clockFault = false, reason = 'none', probeIndex = 0;
	const terminal = new Set();
	const cleanupStage = () => process ? 'containment' : account ? 'account-cleanup' : root ? 'root-cleanup' : 'journal-disposition';
	let stage = mode === 'cleanup-only' ? cleanupStage() : 'fresh';
	const fail = (code) => { refused = true; if (reason === 'none') reason = code; return false; };
	const guard = (identity, owned, now) => {
		const ids = record(identity, identityKeys), objects = record(owned, objectKeys);
		if (!ids || !identityKeys.every((k) => ids[k] === config[k]) || !objects || !objectKeys.every((k) => objects[k] === config[k])) return fail('identity');
		if (typeof now !== 'bigint' || now < last || now >= config.startedNs + EPOCH_NS) { clockFault = true; return fail('clock'); }
		last = now; return true;
	};
	const pending = () => stage.endsWith('-intended');
	return Object.freeze({
		accept(text, identity, owned, now) {
			if (closed || refused) return false;
			if (!guard(identity, owned, now)) return false;
			attempts++;
			if (typeof text === 'string') bytes = text.length > MAX_BYTES ? MAX_BYTES + 1 : Math.min(MAX_BYTES + 1, bytes + Buffer.byteLength(text, 'utf8'));
			if (attempts > MAX_EVENTS) return fail('event-limit');
			if (bytes > MAX_BYTES) return fail('byte-limit');
			if (typeof text === 'string' && text.length > 256) return fail('record-limit');
			const fields = canonical(text, ['seq', 'kind', 'value'], 256);
			if (!fields || !Number.isSafeInteger(fields.seq) || fields.seq !== accepted || typeof fields.kind !== 'string' || typeof fields.value !== 'string') return fail('event-format');
			const { kind, value } = fields;
			if (stage === 'fresh' && kind === 'handoff' && value === 'protected-model') { retained = 1; stage = 'ready'; }
			else if (stage === 'ready' && kind === 'intent' && value === 'create-root') stage = 'root-intended';
			else if (stage === 'root-intended' && kind === 'result' && value === 'root-owned') { root = true; stage = 'root-ready'; }
			else if (stage === 'root-ready' && kind === 'intent' && value === 'create-account') stage = 'account-intended';
			else if (stage === 'account-intended' && kind === 'result' && value === 'account-owned') { account = true; stage = 'account-ready'; }
			else if (stage === 'account-ready' && kind === 'intent' && value === 'launch-probe') stage = 'probe-intended';
			else if (stage === 'probe-intended' && kind === 'result' && value === 'probe-owned') { process = true; stage = 'probing'; }
			else if (stage === 'probing' && kind === 'probe' && value === probes[probeIndex]) {
				probeIndex++; if (probeIndex === probes.length) stage = 'terminal';
			} else if (stage === 'terminal' && kind === 'terminal' && terminals.includes(value) && !terminal.has(value)) {
				terminal.add(value); if (terminal.size === terminals.length) { process = false; stage = cleanupStage(); }
			} else if (stage === 'containment' && kind === 'containment' && value === 'owned-processes-and-eofs-closed') { process = false; stage = cleanupStage(); }
			else if (stage === 'account-cleanup' && kind === 'intent' && value === 'remove-account') stage = 'account-remove-intended';
			else if (stage === 'account-remove-intended' && kind === 'result' && value === 'account-removed') { account = false; stage = cleanupStage(); }
			else if (stage === 'root-cleanup' && kind === 'intent' && value === 'remove-root') stage = 'root-remove-intended';
			else if (stage === 'root-remove-intended' && kind === 'result' && value === 'root-removed') { root = false; stage = 'journal-disposition'; }
			else if (stage === 'journal-disposition' && kind === 'journal' && ['retained-control-record', 'removed-control-record'].includes(value)) {
				retained = value === 'retained-control-record' ? 1 : 0; stage = 'complete';
			} else return fail('transition');
			accepted++; return true;
		},
		finish(identity, owned, now) {
			if (closed) return false;
			const valid = guard(identity, owned, now); closed = true;
			const complete = valid && !refused && stage === 'complete';
			const receipt = Object.freeze({ profile: SUPERVISOR_DATA_PROFILE, synthetic_only: true, mode, stage, refused, reason,
				proof_model_match: complete && mode === 'proof', cleanup_model_match: complete && mode === 'cleanup-only',
				outcome: complete ? 'model-complete' : 'quarantine', model_events: attempts, model_accepted: accepted,
				model_bytes: bytes, model_account: account, model_root: root, model_process: process, retained_control_records: retained, ...actual });
			if (mode === 'proof' && !complete && valid && !clockFault && !pending() && (account || root || process || retained)) {
				recoveries.set(receipt, { config, account, root, process, retained, attempts, accepted, bytes, last, used: false });
			}
			return receipt;
		},
	});
}
