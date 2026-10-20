// Synthetic complete-frame successor only. No socket, process, storage or real-clock API.
import { nativeGreeting, nativeHello, canonicalOk, exactCommit, validIdentity, validClock, dataObject, nativeLimits } from './native-witness-profile.mjs';
import { createFixedDrain } from './fixed-drain-grammar.mjs';
import { createWitnessFrames } from './fixed-drain-stream.mjs';

const receiptKeys = ['worker_exit_zero', 'pipes_closed', 'stderr_empty', 'transport_equal', 'sessions_absent', 'fixtures_absent', 'application_unchanged', 'guards_passed'];
export function createDrainWitness(options) {
	if (!dataObject(options, ['username', 'database', 'startedNs', 'root', 'lifetimes']) || !validClock(options.startedNs) || options.startedNs > 9223372008854775807n || !validIdentity(options.root) ||
		!dataObject(options.lifetimes, ['auth', 'capture', 'writer']) || ![options.username, options.database].every((s) => typeof s === 'string' && /^[A-Za-z0-9_-]{1,64}$/.test(s))) throw TypeError('Unsupported synthetic witness.');
	const ids = ['auth', 'capture', 'writer'].map((role) => options.lifetimes[role]);
	if (!ids.every(validIdentity) || new Set([...ids, options.root]).size !== 4) throw TypeError('Duplicate synthetic lifetime.');
	const rows = ids.map((id, i) => ({ id, role: ['auth', 'capture', 'writer'][i], stage: 0, thread: 0, profile: 0, live: true, quit: false, count: 0, pending: { client: null, server: null } }));
	const pending = new Map(), drain = createFixedDrain(), start = options.startedNs, deadline = start + 10000000000n;
	const root = options.root, username = options.username, database = options.database;
	let last = start, total = 0, failed = false, finished = false, bound = false, target = false, response = false, targetEgress = false, responseEgress = false, final = false;
	let queued = 0, feeding = false, dispatching = false, feedNow = start, visitor = null, emitted = null;
	const fail = () => { failed = true; pending.clear(); for (const row of rows) { row.pending.client = null; row.pending.server = null; for (const stream of Object.values(row.streams ?? {})) stream.abort(); } return false; };
	const clock = (now, cleanup = false) => {
		if (failed || finished) return false;
		if (!validClock(now) || now < last || now >= (cleanup && final ? start + 28000000000n : deadline)) return fail();
		last = now; return true;
	};
	const empty = (row) => !row.pending.client && !row.pending.server;
	const boundary = (row) => Object.values(row.streams).every((stream) => stream.boundary());
	const cap = (row) => row.stage < 3 ? [256, 4096, 7][row.stage] : row.role === 'writer' && response ? 4096 : nativeLimits.frame;
	for (const row of rows) row.streams = Object.fromEntries(['client', 'server'].map((direction) => [direction, createWitnessFrames(() => cap(row), (frame) => {
		dispatching = true; const credit = api.observe(row.id, direction, frame, feedNow); dispatching = false;
		if (!credit) return false;
		emitted.push(credit); if (visitor) visitor(credit); return !failed;
	}, (size) => { if (queued + size > nativeLimits.pending) return false; queued += size; return true; }, (size) => { queued -= size; })]));
	const api = {
		feed(id, direction, bytes, now, onCredit = null) {
			const row = rows.find((r) => r.id === id);
			if (feeding || !clock(now, final && row?.role === 'auth') || (onCredit !== null && typeof onCredit !== 'function') || !row?.live || !['client', 'server'].includes(direction)) return fail();
			feeding = true; feedNow = now; visitor = onCredit; emitted = [];
			try { if (!row.streams[direction].feed(bytes) || failed) return fail(); return Object.freeze([...emitted]); }
			finally { feeding = false; dispatching = false; visitor = null; emitted = null; }
		},
		observe(id, direction, frame, now) {
			const row = rows.find((r) => r.id === id);
			if ((feeding && !dispatching) || !clock(now, final && row?.role === 'auth') || !row?.live || row.quit || !['client', 'server'].includes(direction) || !Buffer.isBuffer(frame) || frame.length < 5 || row.pending[direction]) return fail();
			if (frame.length - 4 !== frame.readUIntLE(0, 3) || frame.length - 4 > cap(row) || (total += frame.length) > nativeLimits.bytes || ++row.count > nativeLimits.frames) return fail();
			const seq = frame[3], body = frame.subarray(4); let selected = '';
			if (row.role === 'writer' && row.stage === 3 && direction === 'client' && !row.streams.server.boundary()) return fail();
			if (row.stage < 3) {
				if (seq !== row.stage || direction !== (row.stage === 1 ? 'client' : 'server')) return fail();
				if (row.stage === 0) { row.thread = nativeGreeting(body); if (!row.thread || rows.some((r) => r !== row && r.thread === row.thread)) return fail(); }
				if (row.stage === 1) { row.profile = nativeHello(body, username, database, row.role !== 'auth'); if (!row.profile) return fail(); }
				if (row.stage === 2 && !canonicalOk(body)) return fail();
				row.stage++;
			} else if (row.role === 'writer') {
				if (!target) {
					if (direction === 'server') { if (body[0] === 251) return fail(); }
					else {
						if (seq !== 0 || ![2, 3, 14, 22, 23, 24, 25, 26].includes(body[0])) return fail();
						if (exactCommit(body)) { if (!bound || !empty(row)) return fail(); target = true; selected = 'commit'; }
					}
				} else if (!response) {
					if (direction !== 'server' || seq !== 1 || !canonicalOk(body)) return fail();
					response = true; selected = 'ok';
				} else { if (!drain.accept(direction, seq, body)) return fail(); if (drain.done()) row.quit = true; }
			} else {
				if (direction === 'server' && body[0] === 251) return fail();
				if (direction === 'client' && body.length === 1 && body[0] === 1 && seq === 0) row.quit = true;
				else if (final) return fail();
			}
			const credit = Symbol('synthetic_frame'); row.pending[direction] = credit; pending.set(credit, { row, direction, selected }); return credit;
		},
		complete(id, direction, credit, now) {
			if (!clock(now, true)) return false;
			const item = pending.get(credit);
			if (!item || item.row.id !== id || item.direction !== direction || item.row.pending[direction] !== credit) return fail();
			pending.delete(credit); item.row.pending[direction] = null;
			if (item.selected === 'commit') targetEgress = true;
			if (item.selected === 'ok') responseEgress = true;
			return true;
		},
		bind(id, now) {
			if (!clock(now)) return false;
			const [auth, capture, writer] = rows;
			if (bound || id !== writer.id || now - start > 4000000000n || !auth.live || capture.live || !writer.live || rows.some((r) => r.stage !== 3 || !empty(r) || !boundary(r)) || writer.profile !== capture.profile) return fail();
			bound = true; return true;
		},
		claim(rootId, writer, claim, now) {
			if (!clock(now) || final || rootId !== root || writer !== ids[2] || !drain.done() || !rows.every(boundary) || !dataObject(claim, ['passed', 'checks', 'groups']) || claim.passed !== true ||
				!Number.isInteger(claim.checks) || claim.checks < 1 || claim.checks > 8192 || !Number.isInteger(claim.groups) || claim.groups < 1 || claim.groups > 32) return fail();
			final = true; return true;
		},
		close(id, now) {
			if (!clock(now, true)) return false;
			const row = rows.find((r) => r.id === id);
			if (!row?.live || !empty(row) || !boundary(row) || (row.role === 'writer' && (!drain.done() || !targetEgress || !responseEgress)) || (row.role === 'auth' && (rows[2].live || !row.quit))) return fail();
			row.live = false; return true;
		},
		finish(now, receipt) {
			if (finished) return false;
			const match = clock(now, true) && final && drain.done() && targetEgress && responseEgress && pending.size === 0 && queued === 0 && rows.every((r) => !r.live) && dataObject(receipt, receiptKeys) && receiptKeys.every((key) => receipt[key] === true);
			const result = Object.freeze({ synthetic_match: Boolean(match), synthetic_only: true, selected_commit: target, selected_idle_ok: response, commit_egress: targetEgress, ok_egress: responseEgress,
				native_proven: false, authentication_proven: false, forwarding_proven: false, storage_supported: false, dispatch_supported: false, execution_supported: false });
			fail(); finished = true; return result;
		},
	};
	return Object.freeze(api);
}
