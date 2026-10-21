// Pure fixed no-fault grammar. Parsed claims are not authentication or physical evidence.
export const qualificationDomain = 'alynt.qualification-observation.native.v1';
export const qualificationProfile = 'qualification_no_fault';
const failureCodes = new Set(['qualification_guard_identity', 'qualification_early_io', 'qualification_bind_state', 'qualification_bind_budget', 'qualification_recheck', 'qualification_actual_root', 'qualification_nonce_domains', 'qualification_original_proposal', 'qualification_owned_handle', 'qualification_borrowed_auth', 'qualification_final_budget', 'qualification_channel', 'qualification_reentry', 'qualification_boot', 'qualification_root', 'qualification_bind_reuse', 'qualification_pair', 'qualification_final_reuse', 'qualification_eof', 'qualification_target', 'qualification_dropin', 'qualification_marker', 'qualification_isolation_path', 'qualification_core_diagnostic', 'qualification_core_environment', 'qualification_core_marker', 'qualification_genuine_core', 'qualification_escape', 'qualification_site_content', 'qualification_fixed_factory', 'qualification_denial', 'qualification_denial_no_observation', 'qualification_denial_no_rearm', 'qualification_denial_changed_ledger', 'qualification_positive_factory', 'qualification_unclassified']);
export function parseQualificationFailure(line) {
	if (typeof line !== 'string' || !line.length || line.length > 1024 || !/^[\x20-\x5b\x5d-\x7e]+$/.test(line)) return false;
	let m; try { m = JSON.parse(line); } catch { return false; }
	const p = m?.progress, progress = p === null || p && Object.keys(p).join(',') === 'callback_clean,root_returned_true,guards_observed,nonce_creations_observed,protected_reads_observed,writes_observed' && typeof p.callback_clean === 'boolean' && typeof p.root_returned_true === 'boolean' && Object.entries({ guards_observed: 11, nonce_creations_observed: 2, protected_reads_observed: 40, writes_observed: 1 }).every(([k, max]) => Number.isInteger(p[k]) && p[k] >= 0 && p[k] <= max);
	const code = failureCodes.has(m?.code) || ['qualification_callback_state', 'qualification_root_return', 'qualification_guard_count', 'qualification_nonce_creation_count'].includes(m?.code);
	return m && JSON.stringify(m) === line && Object.keys(m).join(',') === 'passed,redacted_failure,code,location,progress' && m.passed === false && m.redacted_failure === true && code && progress &&
		Array.isArray(m.location) && m.location.length === 2 && ['QualificationNativeWorker.php', 'qualification-native-worker.php', 'qualification-native-channel.php', 'qualification-native-cases.php'].includes(m.location[0]) && Number.isInteger(m.location[1]) && m.location[1] > 0 && m.location[1] <= 10000 ? m : false;
}
export const token = (v) => typeof v === 'string' && /^[a-f0-9]{64}$/.test(v);
const thread = (v) => Number.isInteger(v) && v > 0 && v <= 4294967295;
export const qualificationPreludeCases = Object.freeze([
	'native_admin_session_capability_nonce_positive_guard_not_admission',
	'real_issue_command_receipt_fixture_nonce_domains_disjoint',
	'all_public_purposes_guarded_twice_and_unavailable_without_handles',
	'malformed_and_wrong_method_intent_rejected_before_auth_reads',
	'stale_and_changed_original_intent_denied_with_live_nonce_control',
	'genuine_nonce_expiry_denied',
	'owner_serialization_and_browser_session_export_refused',
	'forged_expired_wrong_actor_session_and_capability_denied',
	'raw_session_user_capability_role_revocation_beats_warm_core_caches',
	'authority_actor_cookie_method_table_blog_loss_inside_real_nonce_denied',
	'genuine_capability_filter_denial_and_recovery',
]);
// A separate prerequisite receipt. Never a native-v1 FINAL or qualification authority.
export function parseQualificationPrelude(line) {
	if (typeof line !== 'string' || !line.length || line.length > 4095 || !/^[\x20-\x5b\x5d-\x7e]+$/.test(line)) return false;
	let m; try { m = JSON.parse(line); } catch { return false; }
	if (!m || JSON.stringify(m) !== line || !thread(m.auth_thread)) return false;
	if (Object.keys(m).join(',') === 'passed,auth_thread' && m.passed === false) return m; // Cleanup identity only; never admission.
	return Object.keys(m).join(',') === 'passed,checks,cases,auth_thread,protected_option_api_reads,public_storage_dispatch_execution,browser_headers_emitted,qualification_attempted' && m.passed === true &&
		Number.isInteger(m.checks) && m.checks > 0 && m.checks <= 10000 && JSON.stringify(m.cases) === JSON.stringify(qualificationPreludeCases) && m.protected_option_api_reads === 0 &&
		m.public_storage_dispatch_execution === false && m.browser_headers_emitted === false && m.qualification_attempted === false ? m : false;
}
const schemas = {
	boot: [1, 'profile'], root_request: [1, 'source'], root: [2, 'root'],
	bind: [2, 'root', 'auth', 'capture', 'writer', 'remaining_ns'], bound: [3, 'root', 'writer', 'pair'],
	final: [3, 'root', 'pair', 'profile', 'source', 'root_returned', 'pre_guards', 'post_guards', 'protected_reads', 'writes', 'original_slots_empty', 'observation'],
};
const keys = (v, names) => v && Object.getPrototypeOf(v) === Object.prototype && Object.keys(v).join(',') === names;
function observation(r) {
	if (!keys(r, 'valid,entries,returns,kind,result,sealed,empty_slots,capture_unchanged,io_unchanged') || r.entries !== 1 || r.returns !== 1 || r.kind !== 'closed_array' || !['valid', 'sealed', 'empty_slots', 'capture_unchanged', 'io_unchanged'].every((k) => r[k] === true)) return false;
	const v = r.result;
	return keys(v, 'historical_outcome,client_release_claimed,current_acceptance_claimed,request_lifecycle_closed_claimed,diagnostic_candidate_only,candidate_only,authenticated,barrier_verified,closure_proven,retry_supported,storage_supported,dispatch_supported,execution_supported') && v.historical_outcome === 'committed' &&
		['client_release_claimed', 'current_acceptance_claimed', 'request_lifecycle_closed_claimed', 'candidate_only'].every((k) => v[k] === true) &&
		['diagnostic_candidate_only', 'authenticated', 'barrier_verified', 'closure_proven', 'retry_supported', 'storage_supported', 'dispatch_supported', 'execution_supported'].every((k) => v[k] === false);
}
export function parseQualificationLine(line, source) {
	if (typeof line !== 'string' || !line.length || line.length > 4095 || !/^[\x20-\x5b\x5d-\x7e]+$/.test(line)) return false;
	let m; try { m = JSON.parse(line); } catch { return false; }
	if (!m || !Object.hasOwn(schemas, m.kind) || JSON.stringify(m) !== line) return false;
	const [seq, ...more] = schemas[m.kind];
	if (!keys(m, ['domain', 'v', 'kind', 'seq', 'attempt', ...more].join(',')) || m.domain !== qualificationDomain || m.v !== 1 || m.seq !== seq || !token(m.attempt)) return false;
	if (Object.hasOwn(m, 'root') && (!token(m.root) || m.root === m.attempt)) return false;
	if (Object.hasOwn(m, 'pair') && (!token(m.pair) || [m.attempt, m.root].includes(m.pair))) return false;
	if (Object.hasOwn(m, 'profile') && m.profile !== qualificationProfile) return false;
	if (Object.hasOwn(m, 'source') && (!keys(m.source, 'production,composition,projection,isolated_worker,isolated_transcript,native_worker,native_channel,native_supervisor') || !Object.values(m.source).every(token) || JSON.stringify(m.source) !== JSON.stringify(source))) return false;
	if (m.kind === 'bind' && (![m.auth, m.capture, m.writer].every(thread) || new Set([m.auth, m.capture, m.writer]).size !== 3 || typeof m.remaining_ns !== 'string' || !/^[1-9][0-9]{9,10}$/.test(m.remaining_ns) || BigInt(m.remaining_ns) < 6000000000n || BigInt(m.remaining_ns) > 10000000000n)) return false;
	if (m.kind === 'bound' && !thread(m.writer)) return false;
	if (m.kind === 'final' && !(m.root_returned === true && m.pre_guards === 8 && m.post_guards === 2 && m.protected_reads === 40 && m.writes === 1 && m.original_slots_empty === true && observation(m.observation))) return false;
	return m;
}
