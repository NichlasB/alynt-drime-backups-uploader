// Passive native adapter only. No sockets or write/close transport capabilities.
import { nativeGreeting, nativeHello, canonicalOk, exactCommit, validThread } from './native-witness-profile.mjs';
import { createFixedDrain } from './fixed-drain-grammar.mjs';

export function createObservationRegistry() {
	const rows = new Map(), drain = createFixedDrain();
	let failed = false, reason = '', roles = null, started = null, bound = false, claimed = false, target = false, response = false, targetEgress = false, responseEgress = false, total = 0, finished = false;
	const fail = (label) => { failed = true; reason ||= label; return false; };
	const now = () => process.hrtime.bigint();
	const budget = (cleanup = false) => started !== null && now() >= started && now() - started < (cleanup && claimed ? 28000000000n : 10000000000n);
	const empty = (row) => !row.pending.client && !row.pending.server;
	return Object.freeze({
		unavailable() { return fail('observer_exception'); },
		open(id) {
			if (failed || finished || typeof id !== 'symbol' || rows.has(id) || rows.size >= 3) return fail('pair_set');
			rows.set(id, { id, stage: 0, thread: null, profile: null, live: true, count: 0, pending: { client: null, server: null }, boundary: () => false }); return true;
		},
		boundary(id, read) { const row = rows.get(id); if (!row || typeof read !== 'function') return fail('pair_boundary'); row.boundary = read; return true; },
		root(tick) { if (failed || started !== null || typeof tick !== 'bigint' || tick < 0n || tick > now()) return fail('root_reuse'); started = tick; return true; },
		header(id, direction, length) {
			const row = rows.get(id);
			if (failed) return false;
			if (!row?.live || !['client', 'server'].includes(direction) || !Number.isInteger(length) || length < 1 || length > (bound && row === roles.writer && response ? 4096 : 2097152)) return fail('observation_header_cap');
			return true;
		},
		frame(id, direction, frame, credit) {
			const row = rows.get(id);
			if (failed) return false;
			if (finished || !row?.live || row.quit || !['client', 'server'].includes(direction) || typeof credit !== 'symbol' || row.pending[direction] || !Buffer.isBuffer(frame) || frame.length < 5 || frame.readUIntLE(0, 3) !== frame.length - 4 || (total += frame.length) > 67108864 || ++row.count > 4096) return fail('pair_frame');
			const seq = frame[3], bytes = frame.subarray(4); let selected = '';
			if (row.stage < 3) {
				if (seq !== row.stage || direction !== (row.stage === 1 ? 'client' : 'server')) return fail('profile_order');
				if (row.stage === 0) { row.thread = nativeGreeting(bytes); if (!row.thread || [...rows.values()].some((r) => r !== row && r.thread === row.thread)) return fail('profile_greeting'); }
				if (row.stage === 1) { row.profile = nativeHello(bytes, 'root', 'local', false); if (!row.profile) return fail('profile_hello'); }
				if (row.stage === 2 && !canonicalOk(bytes)) return fail('profile_auth_ok');
				row.stage++;
			} else {
				if (direction === 'server' && bytes[0] === 251) return fail('local_file');
				if (bound && row === roles.writer) {
					if (!budget() || (direction === 'client' && !row.boundary('server'))) return fail('original_deadline_or_partial');
					if (response) {
						if (!drain.accept(direction, seq, bytes)) return fail('fixed_drain');
						if (drain.done()) row.quit = true;
					} else if (direction === 'client') {
						if (seq !== 0 || ![2, 3, 14, 22, 23, 24, 25, 26].includes(bytes[0]) || target) return fail('writer_command');
						if (exactCommit(bytes)) {
							if (!empty(row)) return fail('target_boundary');
							target = true; selected = 'commit';
						}
					} else if (target) {
						if (seq !== 1 || !canonicalOk(bytes)) return fail('target_response');
						response = true; selected = 'ok';
					}
				} else if (direction === 'client' && bytes.length === 1 && bytes[0] === 1 && seq === 0) row.quit = true;
				else if (claimed) return fail('post_claim_command');
			}
			row.pending[direction] = { credit, selected }; return true;
		},
		account(id, direction, credit) {
			const row = rows.get(id);
			if (failed) return false;
			if (!row || !['client', 'server'].includes(direction) || !row.pending[direction] || row.pending[direction].credit !== credit || (bound && !budget(true))) return fail('egress_account');
			const selected = row.pending[direction].selected; row.pending[direction] = null;
			if (selected === 'commit') targetEgress = true;
			if (selected === 'ok') responseEgress = true;
			return true;
		},
		bind(ids) {
			if (failed || bound || !budget() || now() - started > 4000000000n || !ids || Object.keys(ids).join(',') !== 'auth,capture,writer' || !Object.values(ids).every(validThread) || new Set(Object.values(ids)).size !== 3 || rows.size !== 3) return fail('bind_context');
			const picked = {};
			for (const role of ['auth', 'capture', 'writer']) {
				const matches = [...rows.values()].filter((r) => r.thread === ids[role]);
				if (matches.length !== 1 || matches[0].stage !== 3 || !empty(matches[0]) || !matches[0].boundary('client') || !matches[0].boundary('server') || matches[0].live !== (role !== 'capture')) return fail('bind_lifetime');
				picked[role] = matches[0];
			}
			if (!(picked.writer.profile & 8) || !(picked.capture.profile & 8) || picked.writer.profile !== picked.capture.profile) return fail('bind_profile');
			roles = picked; bound = true; return true;
		},
		claim() {
			if (failed || claimed || !bound || !budget() || !drain.done() || ![...rows.values()].every((r) => r.boundary('client') && r.boundary('server'))) return fail('final_claim');
			claimed = true; return true;
		},
		close(id) {
			const row = rows.get(id);
			if (!row?.live || !empty(row)) return fail('close_boundary');
			row.live = false;
			if (bound && (!budget(true) || (row === roles.writer && (!drain.done() || !row.quit || !targetEgress || !responseEgress)) || (row === roles.auth && (roles.writer.live || !row.quit)))) return fail('role_close');
			return !failed;
		},
		finish(transportPassed) {
			const passed = !finished && !failed && claimed && budget(true) && bound && drain.done() && target && response && targetEgress && responseEgress && transportPassed === true && [...rows.values()].every((r) => !r.live && empty(r));
			finished = true;
			return Object.freeze({ passed, reason, fixed_drain_complete: drain.done(), provisional_final_claim: claimed, exact_role_lifetimes: bound, selected_commit: target, selected_idle_ok: response, commit_egress_complete: targetEgress,
				ok_egress_complete: responseEgress, native_profile_pairs: [...rows.values()].filter((r) => r.stage === 3).length, suppression_supported: false });
		},
	});
}
