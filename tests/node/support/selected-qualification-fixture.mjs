// Hand-built synthetic claims, not a native receipt or invoked WordPress request.
import { selectedIdentities } from '../../../scripts/tests/selected-qualification-identity.mjs';
export function selectedFixture() {
	const source = selectedIdentities(), attempt = 'a'.repeat(64), root = 'b'.repeat(64), pair = 'c'.repeat(64);
	const make = (kind, seq, more) => ({ domain: 'alynt.qualification-observation.selected.v1', v: 1, kind, seq, attempt, ...more });
	const result = { historical_outcome: 'uncertain', client_release_claimed: true, current_acceptance_claimed: false, request_lifecycle_closed_claimed: true, diagnostic_candidate_only: false, candidate_only: true, authenticated: false, barrier_verified: false, closure_proven: false, retry_supported: false, storage_supported: false, dispatch_supported: false, execution_supported: false };
	const observation = { valid: true, entries: 1, returns: 1, kind: 'closed_array', result, sealed: true, empty_slots: true, capture_unchanged: true, io_unchanged: true };
	const frames = [make('boot', 1, { profile: 'qualification_selected_commit_response_loss' }), make('root_request', 1, { source }), make('root', 2, { root }), make('bind', 2, { root, auth: 11, capture: 22, writer: 33, remaining_ns: '6000000000' }), make('bound', 3, { root, writer: 33, pair }), make('final', 3, { root, pair, profile: 'qualification_selected_commit_response_loss', source, root_returned: false, pre_guards: 8, post_guards: 0, protected_reads: 40, writes: 1, original_slots_empty: true, observation })];
	return { source, frames, lines: frames.map((m) => JSON.stringify(m) + '\n') };
}
