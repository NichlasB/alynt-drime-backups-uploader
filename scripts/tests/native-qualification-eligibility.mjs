// Socket-free accounting model ONLY. Simulated credits/cuts can never direct transport.
import { nativeLimits, nativeGreeting, nativeHello, canonicalOk, exactCommit, validIdentity, validClock, dataObject } from './native-witness-profile.mjs';
import { createWitnessFrames } from './fixed-drain-stream.mjs';
import { parseNativeQualificationLine, sourceShape, token, selectedProfile, noFaultProfile } from './native-qualification-grammar.mjs';
const cleanupKeys = ['worker_exit_zero', 'worker_close_seen', 'stdout_eof', 'pipes_closed', 'stderr_bounded', 'listener_closed', 'sessions_absent', 'locks_available', 'fixtures_absent', 'application_unchanged', 'ledger_matches', 'other_rows_unchanged', 'guards_passed'];
function witness(options, profile) {
	if (!dataObject(options, ['username', 'database', 'startedNs', 'root', 'lifetimes', 'source', 'context']) || !validClock(options.startedNs) || options.startedNs > 9223372008854775807n || !validIdentity(options.root) ||
		!dataObject(options.context, ['attempt', 'root']) || !token(options.context.attempt) || !token(options.context.root) || options.context.attempt === options.context.root ||
		!dataObject(options.lifetimes, ['auth', 'capture', 'writer', 'barrier']) || !sourceShape(options.source) || ![options.username, options.database].every((s) => typeof s === 'string' && /^[A-Za-z0-9_-]{1,64}$/.test(s))) throw TypeError('Unsupported synthetic lifecycle context.');
	const { username, database, startedNs, root } = options, source = Object.freeze({ ...options.source }), context = Object.freeze({ ...options.context });
	const rows = ['auth', 'capture', 'writer', 'barrier'].map((role, i) => ({ role, id: options.lifetimes[role], stage: 0, thread: 0, flags: 0, live: i !== 3, count: 0, ingress: [0, 0], completed: [0, 0], pending: [null, null], streams: [] }));
	if (!rows.every((r) => validIdentity(r.id)) || new Set([root, ...rows.map((r) => r.id)]).size !== 5) throw TypeError('Duplicate synthetic lifecycle identity.');
	const [auth, capture, writer, barrier] = rows;
	let last = startedNs, readAt = null, failed = false, eligible = true, bound = false, barrierOpened = false, barrierBound = false, cut = false, claimed = false, finished = false, busy = false;
	let reserved = 0, input = 0, request = null, response = null, requestDone = false, targetAttempts = 0, batch = [], barrierRequest = null, barrierResponse = null, barrierRequestDone = false, barrierAckDone = false;
	const credits = new Map();
	const refuse = () => { eligible = false; return false; };
	const fail = () => { failed = true; return refuse(); };
	const clock = (now) => {
		if (finished) return false;
		if (!validClock(now) || now < last || now - startedNs >= (claimed ? 28000000000n : 20000000000n) || !claimed && readAt !== null && now - readAt >= 10000000000n) return fail();
		last = now; return true;
	};
	const enough = (now) => now - startedNs <= 4000000000n;
	const reserve = (n) => { if (reserved + n > nativeLimits.pending) return fail(); reserved += n; return true; };
	const release = (n) => { reserved -= n; };
	const clean = () => rows.every((r) => r.pending.every((p) => p === null) && r.streams.every((s) => s.boundary()));
	const forget = (key, e) => { release(e.bytes.length); e.bytes.fill(0); e.bytes = null; e.row.pending[e.dir] = null; credits.delete(key); };
	function observe(row, dir, bytes) {
		if (!row.live || row.pending[dir] || ++row.count > nativeLimits.frames || cut && row === writer) return fail();
		const body = bytes.subarray(4), seq = bytes[3];
		if (row.stage < 3) {
			if (dir !== (row.stage === 1 ? 0 : 1) || seq !== row.stage) return fail();
			if (row.stage === 0) { row.thread = nativeGreeting(body); if (!row.thread || rows.some((r) => r !== row && r.thread === row.thread)) return fail(); }
			if (row.stage === 1) { row.flags = nativeHello(body, username, database, row !== auth); if (!row.flags) return fail(); }
			if (row.stage === 2 && !canonicalOk(body)) return fail();
			row.stage++;
		} else {
			if (dir === 1 && body[0] === 0xfb) return fail();
			const quit = dir === 0 && seq === 0 && body.length === 1 && body[0] === 1;
			if (claimed && !(row === auth && quit)) return fail();
			// Tight synthetic suffix, NOT a SQL firewall or a ready-to-wire native registry.
			// Real idle metadata/row/auth traces require their separately approved adapter.
			if (cut || readAt !== null) {
				if (row === auth && !quit) return fail();
				if (row === barrier && (!barrierBound || !(dir === 0 && seq === 0 && exactCommit(body) || dir === 1 && seq === 1 && canonicalOk(body) || quit && barrierAckDone))) return fail();
			}
			if (row === writer && request && dir === 0 && !quit) refuse();
			if (row === barrier && dir === 0 && exactCommit(body) && !barrierBound) return fail();
		}
		if (!reserve(bytes.length)) return false;
		const key = Symbol('synthetic-lifecycle-credit'), entry = { row, dir, bytes: Buffer.from(bytes), attempted: false };
		credits.set(key, entry); row.pending[dir] = key; row.ingress[dir] += bytes.length;
		if (row === writer && row.stage === 3 && dir === 0 && seq === 0 && exactCommit(body)) {
			if (request || !bound || !enough(last) || rows.some((r) => r.pending.some((p) => p !== null && p !== key) || r.streams.some((s, d) => !(r === writer && d === 0) && !s.boundary()))) refuse();
			if (!request) request = key;
		}
		if (row === writer && request && dir === 1) {
			if (response || !requestDone || seq !== 1 || !canonicalOk(body) || !enough(last)) refuse();
			if (!response) response = key;
		}
		if (row === barrier && row.stage === 3 && dir === 0 && seq === 0 && exactCommit(body)) {
			if (barrierRequest || !barrierBound) return fail(); barrierRequest = key;
		}
		if (row === barrier && barrierRequest && dir === 1) {
			if (barrierResponse || !barrierRequestDone || seq !== 1 || !canonicalOk(body)) return fail(); barrierResponse = key;
		}
		return key;
	}
	for (const row of rows) row.streams = [0, 1].map((dir) => createWitnessFrames(() => row.stage < 3 ? [256, 4096, 7][row.stage] : nativeLimits.frame, (bytes) => {
		const key = observe(row, dir, bytes); if (key) batch.push(key); return Boolean(key);
	}, reserve, release));
	function lookup(id, direction, key) {
		const e = credits.get(key), dir = direction === 'client' ? 0 : direction === 'server' ? 1 : -1;
		if (!e || e.row.id !== id || e.dir !== dir) { fail(); return null; } return e;
	}
	return Object.freeze({
		feed(id, direction, bytes, now) {
			if (arguments.length !== 4 || !clock(now) || failed || busy) return fail();
			const row = rows.find((r) => r.id === id), dir = direction === 'client' ? 0 : direction === 'server' ? 1 : -1;
			if (!row?.live || cut && row === writer || dir < 0 || !Buffer.isBuffer(bytes) || !bytes.length || (input += bytes.length) > nativeLimits.bytes) return fail();
			batch = []; busy = true;
			try { if (!row.streams[dir].feed(bytes)) return fail(); return Object.freeze([...batch]); }
			finally { busy = false; batch = []; }
		},
		attempt(id, direction, key, bytes, now) {
			if (arguments.length !== 5 || !clock(now) || failed) return fail();
			const e = lookup(id, direction, key); if (!e) return false;
			if (key === response) { targetAttempts++; if (profile === selectedProfile) refuse(); }
			if (e.attempted || !Buffer.isBuffer(bytes) || !e.bytes.equals(bytes)) return fail();
			e.attempted = true; return true;
		},
		complete(id, direction, key, now, success = true) {
			if (arguments.length < 4 || arguments.length > 5 || !clock(now) || failed) return fail();
			const e = lookup(id, direction, key); if (!e || !e.attempted || success !== true) return fail();
			e.row.completed[e.dir] += e.bytes.length; forget(key, e); if (key === request) requestDone = true;
			if (key === barrierRequest) barrierRequestDone = true; if (key === barrierResponse) barrierAckDone = true; return true;
		},
		bindWriter(identity, id, now) {
			if (arguments.length !== 3 || !clock(now)) return fail();
			if (failed || !eligible || bound || identity !== root || id !== writer.id || !enough(now) || !clean() || capture.live || !auth.live || !writer.live || rows.slice(0, 3).some((r) => r.stage !== 3) || writer.flags !== capture.flags) return refuse();
			bound = true; return true;
		},
		decide(identity, id, key, now) {
			if (arguments.length !== 4 || !clock(now)) return fail();
			const e = credits.get(key);
			if (profile !== selectedProfile || failed || !eligible || cut || !bound || identity !== root || id !== writer.id || key !== response || !e || e.attempted || !requestDone || !enough(now) || !writer.live || !auth.live || targetAttempts !== 0 ||
				rows.some((r) => r.pending.some((p) => p !== null && p !== key) || r.streams.some((s) => !s.boundary()))) return refuse();
			cut = true; forget(key, e); return true; // Simulation only, never an instruction to discard real bytes.
		},
		beginRead(identity, now) {
			if (arguments.length !== 2 || !clock(now)) return fail();
			if (failed || !eligible || identity !== root || readAt !== null || writer.live || !auth.live || !clean() || !requestDone || (profile === selectedProfile ? !cut : !response || targetAttempts !== 1)) return fail();
			readAt = now; return true;
		},
		openBarrier(identity, id, now) {
			if (arguments.length !== 3 || !clock(now)) return fail();
			if (failed || !eligible || identity !== root || id !== barrier.id || readAt === null || barrierOpened || writer.live || capture.live) return fail();
			barrierOpened = true; barrier.live = true; return true;
		},
		bindBarrier(identity, id, now) {
			if (arguments.length !== 3 || !clock(now)) return fail();
			if (failed || !eligible || identity !== root || id !== barrier.id || !barrierOpened || barrierBound || !barrier.live || !auth.live || writer.live || capture.live || !clean() || barrier.stage !== 3 || barrier.flags !== writer.flags) return fail();
			barrierBound = true; return true;
		},
		close(id, now) {
			if (arguments.length !== 2 || !clock(now)) return fail();
			const r = rows.find((row) => row.id === id);
			if (failed || !r?.live || r.stage !== 3 || !clean() || r === barrier && !barrierAckDone || r === writer && (!requestDone || !response || now - startedNs >= 10000000000n) || r === auth && (writer.live || barrier.live)) return fail();
			r.live = false; return true;
		},
		claim(identity, finalLine, now) {
			if (arguments.length !== 3 || !clock(now)) return fail();
			const m = parseNativeQualificationLine(finalLine, source);
			if (failed || !eligible || claimed || identity !== root || !m || m.attempt !== context.attempt || m.root !== context.root || m.kind !== 'final' || m.profile !== profile || !barrierBound || capture.live || writer.live || barrier.live || !auth.live || !clean() ||
				!rows.every((r) => m[r.role] === r.thread) || (profile === selectedProfile ? !cut || targetAttempts !== 0 : cut || targetAttempts !== 1)) return fail();
			claimed = true; return true;
		},
		finish(now, receipt) {
			if (finished) return false;
			const timed = arguments.length === 2 && clock(now), conserved = rows.every((r) => r.ingress.every((n, d) => n === r.completed[d] + (r === writer && d === 1 && cut ? 11 : 0)));
			const balanced = conserved && reserved === 0 && clean();
			const good = timed && !failed && eligible && claimed && barrierBound && dataObject(receipt, cleanupKeys) && cleanupKeys.every((k) => receipt[k] === true) && rows.every((r) => !r.live) && balanced;
			finished = true; for (const [key, e] of credits) forget(key, e);
			for (const r of rows) { for (const s of r.streams) s.abort(); r.id = null; r.thread = 0; }
			return Object.freeze({ synthetic_match: Boolean(good), simulated_cut: cut, withheld_bytes: cut ? 11 : 0, target_write_attempts: targetAttempts, accounting_balanced: balanced,
				native_proven: false, authentication_proven: false, ipc_proven: false, response_loss_proven: false, cleanup_proven: false, storage_supported: false, retry_supported: false, dispatch_supported: false, execution_supported: false, suppression_supported: false });
		},
	});
}
export function createNativeQualificationWitness(options) { if (arguments.length !== 1) throw TypeError('Synthetic context arity.'); return witness(options, selectedProfile); }
export function createNativeQualificationNoFaultWitness(options) { if (arguments.length !== 1) throw TypeError('Synthetic context arity.'); return witness(options, noFaultProfile); }
