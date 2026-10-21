// Independent synthetic claims/bytes only. Not an invoked native request or receipt.
import { nativeQualificationIdentities } from '../../../scripts/tests/native-qualification-identity.mjs';
import { createNativeQualificationWitness, createNativeQualificationNoFaultWitness } from '../../../scripts/tests/native-qualification-eligibility.mjs';
import { frame, greeting, hello, ok, commit } from './native-witness-fixtures.mjs';
export { frame, greeting, hello, ok, commit };
export function qualificationFixture(noFault = false) {
	const source = nativeQualificationIdentities(), attempt = 'a'.repeat(64), root = 'b'.repeat(64);
	const profile = noFault ? 'native_lifecycle_no_fault' : 'native_lifecycle_selected_commit_response_loss';
	const make = (kind, seq, more) => ({ domain: 'alynt.native-lifecycle-observation.selected.v1', v: 1, kind, seq, attempt, ...more });
	const observation = { valid: true, entries: 1, returns: 1, kind: 'closed_array', result: { historical_outcome: noFault ? 'committed' : 'uncertain', client_release_claimed: true, current_acceptance_claimed: false, request_lifecycle_closed_claimed: true, diagnostic_candidate_only: false, candidate_only: true, authenticated: false, barrier_verified: false, closure_proven: false, retry_supported: false, storage_supported: false, dispatch_supported: false, execution_supported: false }, sealed: true, empty_slots: true, capture_unchanged: true, io_unchanged: true };
	const writer = { root, auth: 101, capture: 102, writer: 103, remaining_ns: '6000000000', guard_index: 9 };
	const barrier = { root, auth: 101, capture: 102, writer: 103, barrier: 104, root_remaining_ns: '19000000000', read_remaining_ns: '9000000000', guard_index: 21 };
	const result = { classification: 'registered_closed', authenticated: false, barrier_verified: true, closure_proven: false, retry_supported: false, storage_supported: false, dispatch_supported: false, execution_supported: false, candidate_only: false };
	const final = make('final', 4, { root, profile, source, auth: 101, capture: 102, writer: 103, barrier: 104, native_result: result, pre_guards: 10, post_guards: 14, guard_order: ['M','M','M','M','M','MF','M','MF','M','MF','M','S','S','F','S','F','S','F','S','F','S','F','S','F'], admission_reads: 40, barrier_reads: 10, writes: 1, metadata_checks: 2, zero_dml_checks: 2, original_slots_empty: true, writer_erased_before_read: true, barrier_closed_before_finish: true, observation });
	const frames = [make('boot', 1, { profile, source }), make('root_request', 1, { profile, source }), make('root', 2, { profile, source, root }), make('bind_writer', 2, writer), make('bound_writer', 3, writer), make('bind_barrier', 3, barrier), make('bound_barrier', 4, barrier), final];
	return { source, frames, lines: frames.map((m) => JSON.stringify(m) + '\n') };
}
export const cleanup = () => ({ worker_exit_zero: true, worker_close_seen: true, stdout_eof: true, pipes_closed: true, stderr_bounded: true, listener_closed: true, sessions_absent: true, locks_available: true, fixtures_absent: true, application_unchanged: true, ledger_matches: true, other_rows_unchanged: true, guards_passed: true });
export function witnessFixture(noFault = false, factory = noFault ? createNativeQualificationNoFaultWitness : createNativeQualificationWitness) {
	const { source, frames } = qualificationFixture(noFault), root = Symbol('root');
	const ids = { auth: Symbol('auth'), capture: Symbol('capture'), writer: Symbol('writer'), barrier: Symbol('barrier') };
	const config = { username: 'synthetic', database: 'fixture', startedNs: 0n, root, lifetimes: ids, source, context: { attempt: frames[0].attempt, root: frames[2].root } }, api = factory(config);
	const forward = (role, dir, bytes, key, now = 1n) => api.attempt(ids[role], dir, key, bytes, now) && api.complete(ids[role], dir, key, now);
	const send = (role, dir, body, seq = 0, now = 1n) => { const bytes = frame(body, seq), keys = api.feed(ids[role], dir, bytes, now); return keys && keys.length === 1 && forward(role, dir, bytes, keys[0], now); };
	const handshake = (role, id, now = 1n) => send(role, 'server', greeting(id), 0, now) && send(role, 'client', hello(role === 'auth' ? 0x8a200 : 0x8a208), 1, now) && send(role, 'server', ok(), 2, now);
	const ready = () => handshake('auth', 101) && handshake('capture', 102) && handshake('writer', 103) && api.close(ids.capture, 1n) && api.bindWriter(root, ids.writer, 1n);
	const select = (now = 2n) => {
		if (!send('writer', 'client', commit(), 0, now)) return false;
		const bytes = frame(ok(), 1), keys = api.feed(ids.writer, 'server', bytes, now);
		return keys && keys.length === 1 && (noFault ? forward('writer', 'server', bytes, keys[0], now) : api.decide(root, ids.writer, keys[0], now));
	};
	const observe = (now = 3n) => api.close(ids.writer, now) && api.beginRead(root, now) && api.openBarrier(root, ids.barrier, now) && handshake('barrier', 104, now) && api.bindBarrier(root, ids.barrier, now) && send('barrier', 'client', commit(), 0, now) && send('barrier', 'server', ok(), 1, now) && api.close(ids.barrier, now);
	const finish = (now = 4n, receipt = cleanup(), final = frames[7]) => api.claim(root, JSON.stringify(final), now) && api.close(ids.auth, now) && api.finish(now, receipt);
	return { api, root, ids, config, source, frames, send, forward, handshake, ready, select, observe, finish };
}
