// Dedicated fixed native candidate. Never imported by production or a public route.
import { nativeGreeting, nativeHello, canonicalOk, exactCommit, validThread } from './native-witness-profile.mjs';
export function createSelectedNativeRegistry() {
	const rows = new Map(); let root = null, roles = null, failed = false, eligible = true, claimed = false, cut = false, target = false, requestDone = false, reply = false, total = 0, reason = '', finished = false;
	const fail = (label) => { failed = true; eligible = false; reason ||= label; return false; };
	const enough = () => root !== null && process.hrtime.bigint() - root <= 4000000000n;
	const budget = () => root !== null && process.hrtime.bigint() - root < (claimed ? 28000000000n : 10000000000n);
	const empty = (r) => !r.pending.client && !r.pending.server;
	const boundaries = (exceptRow = null, exceptDirection = null) => [...rows.values()].every((r) => ['client', 'server'].every((d) => r === exceptRow && d === exceptDirection || r.boundary(d)));
	return Object.freeze({
		open(id) { if (rows.size >= 3 || rows.has(id) || typeof id !== 'symbol') return fail('pair_set'); rows.set(id, { id, stage: 0, live: true, thread: null, profile: null, pending: { client: null, server: null }, boundary: () => false, quit: false, count: 0 }); return true; },
		boundary(id, read) { const r = rows.get(id); if (!r || typeof read !== 'function') return fail('boundary'); r.boundary = read; return true; },
		root(tick) { if (root !== null || typeof tick !== 'bigint' || tick < 0n || tick > process.hrtime.bigint()) return fail('root'); root = tick; return true; },
		bind(ids) {
			if (failed || roles || !enough() || !ids || Object.keys(ids).join(',') !== 'auth,capture,writer' || !Object.values(ids).every(validThread) || new Set(Object.values(ids)).size !== 3 || rows.size !== 3 || !boundaries()) return fail('bind');
			const selected = {};
			for (const name of ['auth', 'capture', 'writer']) {
				const list = [...rows.values()].filter((r) => r.thread === ids[name]);
				if (list.length !== 1 || list[0].stage !== 3 || !empty(list[0]) || list[0].live !== (name !== 'capture')) return fail('bind_lifetime'); selected[name] = list[0];
			}
			if (!(selected.writer.profile & 8) || selected.writer.profile !== selected.capture.profile) return fail('bind_profile'); roles = selected; return true;
		},
		cap(id, direction, length) {
			const r = rows.get(id);
			// Candidate is disqualified before allocation. Transparent transport retains
			// its original independent cap; ineligibility must not cause response loss.
			if (roles && r === roles.writer && target && direction === 'server' && length !== 7) eligible = false;
			return 2097152;
		},
		frame(id, direction, frame, credit, tail) {
			const r = rows.get(id);
			if (failed || !r?.live || r.quit || r.pending[direction] || !['client', 'server'].includes(direction) || typeof credit !== 'symbol' || ++r.count > 4096 || (total += frame.length) > 67108864 || roles && cut && r === roles.writer) throw Error('candidate_frame');
			const seq = frame[3], body = frame.subarray(4); let selected = '';
			if (r.stage < 3) {
				if (seq !== r.stage || direction !== (r.stage === 1 ? 'client' : 'server')) throw Error('profile_order');
				if (r.stage === 0) { r.thread = nativeGreeting(body); if (!r.thread || [...rows.values()].some((x) => x !== r && x.thread === r.thread)) throw Error('profile_greeting'); }
				if (r.stage === 1) { r.profile = nativeHello(body, 'root', 'local', false); if (!r.profile) throw Error('profile_hello'); }
				if (r.stage === 2 && !canonicalOk(body)) throw Error('profile_ok'); r.stage++;
			} else if (roles && r === roles.writer) {
				if (!budget()) throw Error('original_deadline');
				if (direction === 'client' && exactCommit(body) && seq === 0) {
					if (target || !enough() || ![...rows.values()].every(empty) || !boundaries(r, 'client')) eligible = false;
					target = true; selected = 'request';
				} else if (target && direction === 'client') eligible = false;
				else if (target && direction === 'server') {
					if (reply || seq !== 1 || !canonicalOk(body) || !requestDone || !enough() || tail !== 0 || ![...rows.values()].every(empty) || !boundaries(r, 'server')) eligible = false;
					reply = true; selected = 'response';
				}
			} else if (claimed && !(direction === 'client' && seq === 0 && body.length === 1 && body[0] === 1)) throw Error('post_final');
			if (direction === 'client' && seq === 0 && body.length === 1 && body[0] === 1) r.quit = true;
			r.pending[direction] = { credit, selected }; return selected;
		},
		cut(id, credit) {
			const r = rows.get(id), p = r?.pending.server;
			if (failed || !eligible || cut || !roles || r !== roles.writer || !r.live || !roles.auth.live || !enough() || !requestDone || !reply || p?.credit !== credit || p.selected !== 'response') return false;
			cut = true; r.pending.server = null; return true;
		},
		account(id, direction, credit) {
			const r = rows.get(id), p = r?.pending[direction];
			if (!p || p.credit !== credit || roles && cut && r === roles.writer) return fail('credit');
			if (p.selected === 'request') requestDone = true;
			if (p.selected === 'response') eligible = false;
			r.pending[direction] = null; return true;
		},
		unavailable(label = 'external_failure') { return fail(label); },
		claim() { if (failed || !eligible || !cut || claimed || !budget() || !boundaries() || ![...rows.values()].every(empty)) return fail('final'); claimed = true; return true; },
		close(id) { const r = rows.get(id); if (!r?.live || !empty(r)) return fail('close'); r.live = false; return true; },
		finish(transport) {
			const passed = !finished && !failed && eligible && cut && claimed && budget() && transport === true && roles && !roles.auth.live && roles.auth.quit && [...rows.values()].every((r) => !r.live && empty(r)); finished = true;
			return Object.freeze({ passed: Boolean(passed), reason, selected_response_discarded: cut, original_request_completed: requestDone, final_claim: claimed,
				outcome: passed ? 'witness_complete' : cut ? 'inconclusive' : 'ineligible', private_qualification_proven: false, execution_supported: false });
		},
	});
}
