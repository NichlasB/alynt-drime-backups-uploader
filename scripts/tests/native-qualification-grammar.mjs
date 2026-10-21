// Pure, bounded claims grammar. Never invokes a root, a native API or a transport.
import { exact, token } from './selected-qualification-grammar.mjs';
export { exact, token };
export const domain = 'alynt.native-lifecycle-observation.selected.v1';
export const selectedProfile = 'native_lifecycle_selected_commit_response_loss';
export const noFaultProfile = 'native_lifecycle_no_fault';
export const sourceKeys = Object.freeze(['production', 'composition', 'projection', 'protocol', 'transcript', 'eligibility', 'isolated_worker']);
export const sourceShape = (s) => exact(s, sourceKeys) && Object.values(s).every(token);
export const thread = (n) => Number.isInteger(n) && n > 0 && n <= 4294967295;
export const guardOrder = Object.freeze(['M','M','M','M','M','MF','M','MF','M','MF','M','S','S','F','S','F','S','F','S','F','S','F','S','F']);
const roleKeys = ['auth', 'capture', 'writer'];
const duration = (s, max, min = 1n) => typeof s === 'string' && /^[1-9][0-9]{0,10}$/.test(s) && BigInt(s) >= min && BigInt(s) <= max;
const roles = (m, barrier = false) => {
	const ids = [...roleKeys, ...(barrier ? ['barrier'] : [])].map((k) => m[k]);
	return ids.every(thread) && new Set(ids).size === ids.length;
};
const schemas = Object.freeze({
	boot: [1, 'profile', 'source'], root_request: [1, 'profile', 'source'], root: [2, 'profile', 'source', 'root'],
	bind_writer: [2, 'root', 'auth', 'capture', 'writer', 'remaining_ns', 'guard_index'],
	bound_writer: [3, 'root', 'auth', 'capture', 'writer', 'remaining_ns', 'guard_index'],
	bind_barrier: [3, 'root', 'auth', 'capture', 'writer', 'barrier', 'root_remaining_ns', 'read_remaining_ns', 'guard_index'],
	bound_barrier: [4, 'root', 'auth', 'capture', 'writer', 'barrier', 'root_remaining_ns', 'read_remaining_ns', 'guard_index'],
	final: [4, 'root', 'profile', 'source', 'auth', 'capture', 'writer', 'barrier', 'native_result', 'pre_guards', 'post_guards', 'guard_order', 'admission_reads', 'barrier_reads', 'writes', 'metadata_checks', 'zero_dml_checks', 'original_slots_empty', 'writer_erased_before_read', 'barrier_closed_before_finish', 'observation'],
});
function qualification(r, profile) {
	if (!exact(r, ['valid', 'entries', 'returns', 'kind', 'result', 'sealed', 'empty_slots', 'capture_unchanged', 'io_unchanged']) || r.entries !== 1 || r.returns !== 1 || r.kind !== 'closed_array' || !['valid', 'sealed', 'empty_slots', 'capture_unchanged', 'io_unchanged'].every((k) => r[k] === true)) return false;
	const v = r.result;
	return exact(v, ['historical_outcome', 'client_release_claimed', 'current_acceptance_claimed', 'request_lifecycle_closed_claimed', 'diagnostic_candidate_only', 'candidate_only', 'authenticated', 'barrier_verified', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported']) &&
		v.historical_outcome === (profile === selectedProfile ? 'uncertain' : 'committed') && v.client_release_claimed === true && v.request_lifecycle_closed_claimed === true && v.candidate_only === true &&
		['current_acceptance_claimed', 'diagnostic_candidate_only', 'authenticated', 'barrier_verified', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported'].every((k) => v[k] === false);
}
function final(m) {
	const r = m.native_result;
	return roles(m, true) && exact(r, ['classification', 'authenticated', 'barrier_verified', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported', 'candidate_only']) &&
		r.classification === 'registered_closed' && r.barrier_verified === true && ['authenticated', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported', 'candidate_only'].every((k) => r[k] === false) &&
		m.pre_guards === 10 && m.post_guards === 14 && JSON.stringify(m.guard_order) === JSON.stringify(guardOrder) && m.admission_reads === 40 && m.barrier_reads === 10 && m.writes === 1 && m.metadata_checks === 2 && m.zero_dml_checks === 2 &&
		m.original_slots_empty === true && m.writer_erased_before_read === true && m.barrier_closed_before_finish === true && qualification(m.observation, m.profile);
}
export function parseNativeQualificationLine(line, source) {
	if (arguments.length !== 2 || !sourceShape(source) || typeof line !== 'string' || !line.length || line.length > 4095 || !/^[\x20-\x5b\x5d-\x7e]+$/.test(line)) return false;
	let depth = 0, quoted = false;
	for (const c of line) { if (c === '"') quoted = !quoted; else if (!quoted && (c === '{' || c === '[') && ++depth > 8) return false; else if (!quoted && (c === '}' || c === ']')) depth--; }
	let m; try { m = JSON.parse(line); } catch { return false; }
	if (!m || typeof m.kind !== 'string' || !Object.hasOwn(schemas, m.kind) || JSON.stringify(m) !== line) return false;
	const [seq, ...more] = schemas[m.kind];
	if (!exact(m, ['domain', 'v', 'kind', 'seq', 'attempt', ...more]) || m.domain !== domain || m.v !== 1 || m.seq !== seq || !token(m.attempt)) return false;
	if (Object.hasOwn(m, 'root') && (!token(m.root) || m.root === m.attempt)) return false;
	if (Object.hasOwn(m, 'profile') && ![selectedProfile, noFaultProfile].includes(m.profile)) return false;
	if (Object.hasOwn(m, 'source') && (!sourceShape(m.source) || JSON.stringify(m.source) !== JSON.stringify(source))) return false;
	if (['bind_writer', 'bound_writer'].includes(m.kind) && (!roles(m) || m.guard_index !== 9 || !duration(m.remaining_ns, 10000000000n, 6000000000n))) return false;
	if (['bind_barrier', 'bound_barrier'].includes(m.kind) && (!roles(m, true) || m.guard_index !== 21 || !duration(m.root_remaining_ns, 20000000000n) || !duration(m.read_remaining_ns, 10000000000n))) return false;
	return m.kind !== 'final' || final(m) ? m : false;
}
