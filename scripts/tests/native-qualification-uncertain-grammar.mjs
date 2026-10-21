// Synthetic DATA only: never a root, authentication, transport or timeout witness.
import { guardOrder } from './native-qualification-grammar.mjs';
export const domain = 'alynt.uncertain-barrier.synthetic.v1';
export const exact = (v, keys) => v !== null && typeof v === 'object' && !Array.isArray(v) && Object.keys(v).join(',') === keys.join(',');
export const token = (v) => typeof v === 'string' && /^[a-f0-9]{64}$/.test(v);
export const roleKeys = Object.freeze(['auth','capture','writer','barrier','blocker','monitor']);
const qKeys = ['historical_outcome','client_release_claimed','current_acceptance_claimed','request_lifecycle_closed_claimed','diagnostic_candidate_only','candidate_only','authenticated','barrier_verified','closure_proven','retry_supported','storage_supported','dispatch_supported','execution_supported'];
export function validTerminal(m) {
	if (!exact(m,['terminal','guard_order','qualification','native_proven','timeout_proven','cleanup_proven','execution_supported']) || ['native_proven','timeout_proven','cleanup_proven','execution_supported'].some((k) => m[k] !== false)) return false;
	const t = m.terminal, q = m.qualification;
	if (!exact(t,['outcome','root_kind','phase','barrier_receipt','admission_reads','barrier_reads','writes','metadata_checks','zero_dml_checks','root_result']) || !['observed','refused'].includes(t.outcome) || t.admission_reads !== 40 || t.writes !== 1) return false;
	const positive = t.outcome === 'observed', r = t.root_result;
	if (t.root_kind !== (positive ? 'array' : 'false') || t.barrier_receipt !== positive || t.barrier_reads !== (positive ? 10 : 1) || t.metadata_checks !== (positive ? 2 : 1) || t.zero_dml_checks !== (positive ? 2 : 1) || !Array.isArray(m.guard_order) || JSON.stringify(m.guard_order) !== JSON.stringify(guardOrder.slice(0,positive ? 24 : 22))) return false;
	if (positive) {
		if (t.phase !== 'barrier_released' || !exact(r,['candidate_only','storage_supported','dispatch_supported','execution_supported','retry_supported','authenticated','classification','barrier_verified','closure_proven']) || r.classification !== 'registered_closed' || r.barrier_verified !== true || Object.keys(r).filter((k) => !['classification','barrier_verified'].includes(k)).some((k) => r[k] !== false)) return false;
	} else if (!['rolled_back','poisoned'].includes(t.phase) || r !== null) return false;
	if (!exact(q,['valid','entries','returns','kind','result','sealed','empty_slots','capture_unchanged','io_unchanged']) || q.entries !== 1 || q.returns !== 1 || q.kind !== 'closed_array' || ['valid','sealed','empty_slots','capture_unchanged','io_unchanged'].some((k) => q[k] !== true) || !exact(q.result,qKeys) || !(positive ? ['committed','uncertain'] : ['uncertain']).includes(q.result.historical_outcome)) return false;
	return qKeys.slice(1).every((k) => q.result[k] === ['client_release_claimed','request_lifecycle_closed_claimed','candidate_only'].includes(k));
}
const fields = Object.freeze({ bind:['roles','root_remaining_ns','read_remaining_ns'], ready:['blocker','barrier','zero_dml'], waiting:['requester','blocker','monitor','owned_table','role'], disposition:['case'], terminal:['data'], cleanup:['sessions_absent','locks_available','processes_closed','pipes_closed','fixtures_removed','application_unchanged','streams_accounted','blocker_zero_dml'] });
export function parseUncertainBarrierLine(line) {
	if (typeof line !== 'string' || !line.length || line.length > 4095 || !/^[\x20-\x5b\x5d-\x7e]+$/.test(line)) return false;
	let depth=0, quoted=false; for (const c of line) { if (c === '"') quoted=!quoted; else if (!quoted && (c === '{' || c === '[') && ++depth>8) return false; else if (!quoted && (c === '}' || c === ']')) depth--; }
	let m; try { m=JSON.parse(line); } catch { return false; }
	if (!m || !Object.hasOwn(fields,m.kind) || JSON.stringify(m)!==line || !exact(m,['domain','v','kind','seq','attempt','root','source',...fields[m.kind]]) || m.domain!==domain || m.v!==1 || !token(m.attempt) || !token(m.root) || !token(m.source) || m.attempt===m.root || m.seq!==Object.keys(fields).indexOf(m.kind)+1) return false;
	if (m.kind==='bind' && (!exact(m.roles,roleKeys) || !Object.values(m.roles).every((n)=>Number.isInteger(n)&&n>0&&n<=4294967295) || new Set(Object.values(m.roles)).size!==6 || !['root_remaining_ns','read_remaining_ns'].every((k)=>typeof m[k]==='string'&&/^[1-9][0-9]{0,10}$/.test(m[k])&&BigInt(m[k])<= (k==='root_remaining_ns'?20000000000n:10000000000n)))) return false;
	if (m.kind==='ready' && (!Number.isInteger(m.blocker)||!Number.isInteger(m.barrier)||m.zero_dml!==true)) return false;
	if (m.kind==='waiting' && (m.owned_table!==true || m.role!=='safety' || !['requester','blocker','monitor'].every((k)=>Number.isInteger(m[k])))) return false;
	if (m.kind==='disposition' && !['released','held'].includes(m.case)) return false;
	if (m.kind==='terminal' && !validTerminal(m.data)) return false;
	if (m.kind==='cleanup' && fields.cleanup.some((k)=>m[k]!==true)) return false;
	return m;
}
