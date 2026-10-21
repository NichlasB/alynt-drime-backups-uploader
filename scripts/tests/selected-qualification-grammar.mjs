// Pure selected grammar. Accepting synthetic bytes never proves native qualification.
export const selectedDomain = 'alynt.qualification-observation.selected.v1';
export const selectedProfile = 'qualification_selected_commit_response_loss';
export const sourceKeys = Object.freeze(['production', 'composition', 'projection', 'isolated_worker', 'isolated_transcript', 'native_worker', 'native_channel', 'native_supervisor', 'selected_worker', 'selected_channel', 'selected_supervisor', 'selected_relay', 'selected_registry']);
export const token = (v) => typeof v === 'string' && v.length === 64 && /^[a-f0-9]{64}$/.test(v);
export function exact(v, names) {
	if (!v || Object.getPrototypeOf(v) !== Object.prototype) return false;
	const fields = Object.getOwnPropertyDescriptors(v), keys = Reflect.ownKeys(fields);
	return keys.length === names.length && keys.every((k, i) => k === names[i] && Object.hasOwn(fields[k], 'value'));
}
export const sourceShape = (v) => exact(v, sourceKeys) && Object.values(v).every(token);
const thread = (v) => Number.isInteger(v) && v > 0 && v <= 4294967295;
const schemas = Object.freeze({ boot: [1, 'profile'], root_request: [1, 'source'], root: [2, 'root'], bind: [2, 'root', 'auth', 'capture', 'writer', 'remaining_ns'], bound: [3, 'root', 'writer', 'pair'], final: [3, 'root', 'pair', 'profile', 'source', 'root_returned', 'pre_guards', 'post_guards', 'protected_reads', 'writes', 'original_slots_empty', 'observation'] });
function observation(r) {
	if (!exact(r, ['valid', 'entries', 'returns', 'kind', 'result', 'sealed', 'empty_slots', 'capture_unchanged', 'io_unchanged']) || r.entries !== 1 || r.returns !== 1 || r.kind !== 'closed_array' || !['valid', 'sealed', 'empty_slots', 'capture_unchanged', 'io_unchanged'].every((k) => r[k] === true)) return false;
	const v = r.result;
	return exact(v, ['historical_outcome', 'client_release_claimed', 'current_acceptance_claimed', 'request_lifecycle_closed_claimed', 'diagnostic_candidate_only', 'candidate_only', 'authenticated', 'barrier_verified', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported']) && v.historical_outcome === 'uncertain' && typeof v.client_release_claimed === 'boolean' && v.request_lifecycle_closed_claimed === true && v.candidate_only === true &&
		['current_acceptance_claimed', 'diagnostic_candidate_only', 'authenticated', 'barrier_verified', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported'].every((k) => v[k] === false);
}
export function parseSelectedLine(line, source) {
	if (arguments.length !== 2 || !sourceShape(source) || typeof line !== 'string' || !line.length || line.length > 4095 || !/^[\x20-\x5b\x5d-\x7e]+$/.test(line)) return false;
	let m; try { m = JSON.parse(line); } catch { return false; }
	if (!m || typeof m.kind !== 'string' || !Object.hasOwn(schemas, m.kind) || JSON.stringify(m) !== line) return false;
	const [seq, ...more] = schemas[m.kind];
	if (!exact(m, ['domain', 'v', 'kind', 'seq', 'attempt', ...more]) || m.domain !== selectedDomain || m.v !== 1 || m.seq !== seq || !token(m.attempt)) return false;
	if (Object.hasOwn(m, 'root') && (!token(m.root) || m.root === m.attempt)) return false;
	if (Object.hasOwn(m, 'pair') && (!token(m.pair) || [m.attempt, m.root].includes(m.pair))) return false;
	if (Object.hasOwn(m, 'profile') && m.profile !== selectedProfile) return false;
	if (Object.hasOwn(m, 'source') && (!sourceShape(m.source) || JSON.stringify(m.source) !== JSON.stringify(source))) return false;
	if (m.kind === 'bind' && (![m.auth, m.capture, m.writer].every(thread) || new Set([m.auth, m.capture, m.writer]).size !== 3 || typeof m.remaining_ns !== 'string' || !/^(?:[6-9][0-9]{9}|10000000000)$/.test(m.remaining_ns))) return false;
	if (m.kind === 'bound' && !thread(m.writer)) return false;
	if (m.kind === 'final' && !(m.root_returned === false && m.pre_guards === 8 && m.post_guards === 0 && m.protected_reads === 40 && m.writes === 1 && m.original_slots_empty === true && observation(m.observation))) return false;
	return m;
}
