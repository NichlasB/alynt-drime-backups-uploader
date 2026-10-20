// Passive in-memory model; caller accounting is synthetic, never a transport instruction.
import { nativeLimits as limits, nativeGreeting, nativeHello, exactCommit, canonicalOk, validIdentity, validClock, dataObject } from './native-witness-profile.mjs';

export function createNativeProfileWitness(options) {
	if (!dataObject(options, ['username', 'database', 'startedNs', 'lifetimes']) || !validClock(options.startedNs) || options.startedNs > 9223372026854775807n ||
		!dataObject(options.lifetimes, ['auth', 'capture', 'writer']) || typeof options.username !== 'string' || typeof options.database !== 'string' || !/^[A-Za-z0-9_-]{1,64}$/.test(options.username) || !/^[A-Za-z0-9_-]{1,64}$/.test(options.database)) throw new TypeError('Unsupported synthetic context.');
	const ids = ['auth', 'capture', 'writer'].map((role) => options.lifetimes[role]);
	if (!ids.every(validIdentity) || new Set(ids).size !== 3) throw new TypeError('Unsupported synthetic lifetimes.');
	const pairs = ids.map((id, index) => ({ id, role: ['auth', 'capture', 'writer'][index], stage: 0, thread: 0, profile: 0, live: true,
		client: { header: Buffer.alloc(4), used: 0, body: null, size: 0, pending: 0 }, server: { header: Buffer.alloc(4), used: 0, body: null, size: 0, pending: 0 }, frames: 0 }));
	const username = options.username, database = options.database, deadline = options.startedNs + 10000000000n;
	let last = options.startedNs, total = 0, reserved = 0, failed = false, closed = false, bound = false, target = false, response = false;
	const clear = (stream) => { stream.header.fill(0); stream.body?.fill(0); stream.used = 0; stream.body = null; stream.size = 0; };
	const fail = () => { failed = true; for (const pair of pairs) for (const direction of ['client', 'server']) clear(pair[direction]); reserved = 0; return false; };
	const clock = (now) => {
		if (closed || failed) return false;
		if (!validClock(now) || now < last || now >= deadline) return fail();
		last = now; return true;
	};
	const boundary = (pair) => pair.client.used === 0 && pair.server.used === 0;
	const empty = (pair) => boundary(pair) && !pair.client.pending && !pair.server.pending;
	const packet = (pair, direction, seq, bytes) => {
		if (pair.stage < 3) {
			if (direction !== (pair.stage === 1 ? 'client' : 'server') || seq !== pair.stage) return fail();
			if (pair.stage === 0) { pair.thread = nativeGreeting(bytes); if (!pair.thread || pairs.some((p) => p !== pair && p.thread === pair.thread)) return fail(); }
			if (pair.stage === 1) { pair.profile = nativeHello(bytes, username, database, pair.role !== 'auth'); if (!pair.profile) return fail(); }
			if (pair.stage === 2 && !canonicalOk(bytes)) return fail();
			pair.stage++; return true;
		}
		if (direction === 'server' && bytes[0] === 0xfb) return fail();
		if (pair.role !== 'writer') return true;
		if (direction === 'client') {
			if (seq !== 0 || ![1, 2, 3, 14, 22, 23, 24, 25, 26].includes(bytes[0]) || bytes[0] === 1) return fail();
			if (target) return fail();
			if (exactCommit(bytes)) {
				if (!bound || pair.client.pending || pair.server.pending || pair.server.used) return fail();
				target = true;
			}
		} else if (target) {
			if (response || seq !== 1 || !canonicalOk(bytes)) return fail();
			response = true; // May race request accounting; normal egress is never controlled here.
		}
		return true;
	};
	return Object.freeze({
		feed(id, direction, bytes, now) {
			if (!clock(now)) return false;
			const pair = pairs.find((p) => p.id === id);
			if (!pair?.live || !['client', 'server'].includes(direction) || !Buffer.isBuffer(bytes) || !bytes.length || bytes.length > limits.bytes || total + bytes.length > limits.bytes) return fail();
			total += bytes.length;
			const stream = pair[direction];
			let offset = 0;
			while (offset < bytes.length) {
				if (stream.used < 4) {
					const n = Math.min(4 - stream.used, bytes.length - offset);
					bytes.copy(stream.header, stream.used, offset, offset + n); offset += n; stream.used += n;
					if (stream.used < 4) continue;
					const length = stream.header.readUIntLE(0, 3), cap = pair.stage < 3 ? [256, 4096, 7][pair.stage] : limits.frame;
					if (length < 1 || length > cap || ++pair.frames > limits.frames || reserved + length > limits.pending) return fail();
					reserved += length; stream.body = Buffer.alloc(length);
				}
				const n = Math.min(stream.body.length - stream.size, bytes.length - offset);
				bytes.copy(stream.body, stream.size, offset, offset + n); offset += n; stream.size += n;
				if (stream.size === stream.body.length) {
					if (!packet(pair, direction, stream.header[3], stream.body)) return false;
					stream.pending++; reserved -= stream.body.length; clear(stream);
				}
			}
			return true;
		},
		account(id, direction, count, now) {
			if (!clock(now)) return false;
			const pair = pairs.find((p) => p.id === id), stream = pair?.[direction];
			if (!pair?.live || !['client', 'server'].includes(direction) || !Number.isInteger(count) || count < 1 || count > stream.pending) return fail();
			stream.pending -= count; return true;
		},
		bind(id, now) {
			if (!clock(now)) return false;
			const [auth, capture, writer] = pairs;
			if (bound || id !== writer.id || !writer.live || !auth.live || capture.live || pairs.some((p) => p.stage !== 3 || !empty(p)) || writer.profile !== capture.profile || deadline - now < 6000000000n) return fail();
			bound = true; return true;
		},
		close(id, now) {
			if (!clock(now)) return false;
			const pair = pairs.find((p) => p.id === id);
			if (!pair?.live || !empty(pair) || (pair.role === 'auth' && pairs[2].live) || (pair.role === 'writer' && (!response || !target))) return fail();
			pair.live = false; return true;
		},
		finish(now, noFaultReceipt) {
			if (closed) return false;
			const matched = clock(now) && bound && target && response && pairs.every((p) => !p.live && empty(p)) && noFaultReceipt === true;
			fail(); closed = true;
			return Object.freeze({ synthetic_match: Boolean(matched), synthetic_only: true, native_proven: false, authentication_proven: false,
				forwarding_proven: false, delivery_proven: false, storage_supported: false, retry_supported: false, dispatch_supported: false, execution_supported: false });
		},
	});
}
