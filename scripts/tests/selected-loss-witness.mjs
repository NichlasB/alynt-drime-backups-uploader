// Socket-free model ONLY. Credits describe invented egress, never transport authority.
import { canonicalOk, dataObject, exactCommit, nativeGreeting, nativeHello, nativeLimits, validClock, validIdentity } from './native-witness-profile.mjs';
import { createWitnessFrames } from './fixed-drain-stream.mjs';
import { lossLimits, lossReceipt, lossSummary } from './selected-loss-contract.mjs';

export function createSelectedLossWitness(options) {
	if (!dataObject(options, ['username', 'database', 'startedNs', 'root', 'lifetimes']) || !validClock(options.startedNs) ||
		options.startedNs > 9223372036854775807n - lossLimits.cleanup || !validIdentity(options.root) ||
		![options.username, options.database].every((s) => typeof s === 'string' && /^[A-Za-z0-9_-]{1,64}$/.test(s)) ||
		!dataObject(options.lifetimes, ['auth', 'capture', 'writer'])) throw TypeError('Unsupported synthetic loss context.');
	const { username, database, startedNs, root } = options;
	const rows = ['auth', 'capture', 'writer'].map((role) => ({ role, id: options.lifetimes[role], stage: 0, thread: 0, flags: 0, live: true,
		count: 0, ingress: [0, 0], completed: [0, 0], pending: [null, null], streams: [] }));
	if (!rows.every((r) => validIdentity(r.id)) || new Set([root, ...rows.map((r) => r.id)]).size !== 4) throw TypeError('Duplicate synthetic lifetime.');
	let last = startedNs, failed = false, eligible = true, bound = false, cut = false, claimed = false, finished = false, busy = false;
	let reserved = 0, input = 0, observed = 0, request = null, response = null, requestDone = false, targetAttempts = 0;
	const credits = new Map(), writer = rows[2];
	const refuse = () => { eligible = false; return false; };
	const fail = () => { failed = true; return refuse(); };
	const clock = (now) => {
		if (finished) return false;
		if (!validClock(now) || now < last || now - startedNs >= (claimed ? lossLimits.cleanup : lossLimits.window)) return fail();
		last = now; return true;
	};
	const enough = (now) => now - startedNs <= lossLimits.window - lossLimits.remaining;
	const reserve = (n) => { if (reserved + n > nativeLimits.pending) return fail(); reserved += n; return true; };
	const release = (n) => { reserved -= n; };
	const clean = () => rows.every((r) => r.pending.every((p) => p === null) && r.streams.every((s) => s.boundary()));
	const forget = (entry) => { entry.bytes.fill(0); release(entry.bytes.length); entry.bytes = null; entry.row.pending[entry.dir] = null; };
	function observe(row, dir, bytes, now) {
		if (!row.live || row.pending[dir] || ++row.count > nativeLimits.frames || (observed += bytes.length) > nativeLimits.bytes || cut && row === writer) return fail();
		const body = bytes.subarray(4), sequence = bytes[3];
		if (row.stage < 3) {
			if (row.stage === 0) {
				const thread = dir === 1 && sequence === 0 && nativeGreeting(body);
				if (!thread || rows.some((r) => r.thread === thread)) return fail(); row.thread = thread;
			} else if (row.stage === 1) {
				const flags = dir === 0 && sequence === 1 && nativeHello(body, username, database, row !== rows[0]);
				if (!flags) return fail(); row.flags = flags;
			} else if (dir !== 1 || sequence !== 2 || !canonicalOk(body)) return fail();
			row.stage++;
		} else if (row === writer && bound && dir === 0 && sequence === 0 && exactCommit(body)) {
			if (request || !enough(now) || rows.some((r) => r.pending.some(Boolean)) || !row.streams[1].boundary() || rows.some((r) => r.streams.some((s) => !s.boundary()) && r !== row)) refuse();
		} else if (claimed && !(row === rows[0] && dir === 0 && sequence === 0 && body.length === 1 && body[0] === 1)) return fail();
		if (row === writer && request && dir === 0) refuse();
		if (!reserve(bytes.length)) return false;
		let copy;
		try { copy = Buffer.from(bytes); } catch { release(bytes.length); return fail(); }
		const key = Symbol('synthetic-frame'), entry = { row, dir, bytes: copy, attempted: false, withheld: false };
		credits.set(key, entry); row.pending[dir] = key; row.ingress[dir] += bytes.length;
		if (row === writer && bound && dir === 0 && sequence === 0 && exactCommit(body)) { if (request) refuse(); else request = key; }
		if (row === writer && request && dir === 1) {
			if (response || !requestDone || sequence !== 1 || !canonicalOk(body) || !enough(now)) refuse(); else response = key;
		}
		return key;
	}
	for (const row of rows) row.streams = [0, 1].map((dir) => createWitnessFrames(() => {
		if (row === writer && request && dir === 1) return 7;
		return row.stage === 0 ? 256 : row.stage === 1 ? 4096 : row.stage === 2 ? 7 : nativeLimits.frame;
	}, (bytes) => { const key = observe(row, dir, bytes, last); if (key) batch.push(key); return Boolean(key); }, reserve, release));
	let batch = [];
	function lookup(id, direction, key) {
		const entry = credits.get(key), dir = direction === 'client' ? 0 : direction === 'server' ? 1 : -1;
		if (!entry || entry.row.id !== id || entry.dir !== dir) { fail(); return null; }
		return entry;
	}
	return Object.freeze({
		feed(id, direction, bytes, now) {
			if (!clock(now) || failed || busy) return false;
			const row = rows.find((r) => r.id === id), dir = direction === 'client' ? 0 : direction === 'server' ? 1 : -1;
			if (!row || !row.live || cut && row === writer || dir < 0 || !Buffer.isBuffer(bytes) || !bytes.length || (input += bytes.length) > nativeLimits.bytes) return fail();
			batch = []; busy = true;
			try { if (!row.streams[dir].feed(bytes)) return fail(); return Object.freeze([...batch]); }
			finally { busy = false; batch = []; }
		},
		attempt(id, direction, key, bytes, now) {
			if (!clock(now)) return false;
			const entry = lookup(id, direction, key);
			if (!entry) return false;
			if (key === response) { targetAttempts = 1; refuse(); }
			if (entry.withheld || entry.attempted || !Buffer.isBuffer(bytes) || !entry.bytes?.equals(bytes)) return fail();
			entry.attempted = true; return true;
		},
		complete(id, direction, key, now, success = true) {
			if (!clock(now)) return false;
			const entry = lookup(id, direction, key);
			if (!entry || entry.withheld || !entry.attempted || success !== true) return fail();
			entry.row.completed[entry.dir] += entry.bytes.length; forget(entry); credits.delete(key);
			if (key === request) requestDone = true;
			return true;
		},
		bind(identity, id, now) {
			if (!clock(now)) return false;
			if (failed || !eligible || bound || identity !== root || id !== writer.id || !enough(now) || !clean() ||
				!rows.every((r) => r.stage === 3) || rows[1].live || !rows[0].live || !writer.live || rows[1].flags !== writer.flags) return refuse();
			bound = true; return true;
		},
		decide(identity, id, key, now) {
			if (!clock(now)) return false;
			const entry = credits.get(key);
			if (failed || !eligible || cut || !bound || identity !== root || id !== writer.id || key !== response || !entry || entry.attempted ||
				!requestDone || !enough(now) || !writer.live || !rows[0].live || targetAttempts !== 0 ||
				rows.some((r) => r.pending.some((p) => p !== null && p !== key) || r.streams.some((s) => !s.boundary()))) return refuse();
			// Irreversible simulated cut; NEVER a transport instruction or native witness.
			cut = true; entry.withheld = true; forget(entry); return true;
		},
		fault(now) { if (!clock(now)) return false; if (cut) failed = true; return refuse(); },
		claim(identity, id, summary, now) {
			if (!clock(now)) return false;
			if (failed || !eligible || !cut || claimed || identity !== root || id !== writer.id || !lossSummary(summary) || !clean()) return fail();
			claimed = true; return true;
		},
		close(id, now) {
			if (!clock(now)) return false;
			const row = rows.find((r) => r.id === id);
			if (!row || !row.live || row.pending.some(Boolean) || row.streams.some((s) => !s.boundary()) || row === rows[0] && writer.live) return fail();
			row.live = false; return true;
		},
		finish(now, receipt) {
			if (finished) return false;
			const timed = clock(now), conserved = rows.every((r) => r.ingress.every((n, d) => n === r.completed[d] + (r === writer && d === 1 && cut ? 11 : 0)));
			const balanced = conserved && reserved === 0 && clean();
			const match = timed && !failed && eligible && cut && claimed && requestDone && targetAttempts === 0 && lossReceipt(receipt) &&
				rows.every((r) => !r.live) && clean() && reserved === 0 && conserved;
			finished = true;
			for (const entry of credits.values()) if (entry.bytes) forget(entry);
			for (const row of rows) { for (const stream of row.streams) stream.abort(); row.thread = 0; row.id = null; }
			credits.clear();
			return Object.freeze({ synthetic_match: Boolean(match), synthetic_only: true, simulated_cut: cut, outcome: match ? 'simulated_match' : cut ? 'inconclusive' : 'ineligible',
				withheld_bytes: cut ? 11 : 0, target_write_attempts: targetAttempts, accounting_balanced: balanced,
				native_proven: false, zero_delivery_proven: false, ipc_proven: false, authentication_proven: false, storage_supported: false,
				dispatch_supported: false, execution_supported: false, suppression_supported: false });
		},
	});
}
