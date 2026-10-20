// Selected synthetic transcript only, NOT a transparent relay or a native certificate.
import { greeting, clientHello, commit, idleOk } from './commit-witness-protocol.mjs';

const grammar = Object.freeze({
	greeting: ['server', 0, 256], hello: ['client', 1, 256], auth: ['server', 2, 7],
	commit: ['client', 0, 64], response: ['server', 1, 7],
});
const threadId = (id) => Number.isSafeInteger(id) && id > 0 && id <= 0xffffffff;

export function createCommitWitnessDecoder(options) {
	if (!options || typeof options !== 'object' || Object.getPrototypeOf(options) !== Object.prototype) throw new TypeError('Unsupported synthetic decoder context.');
	const keys = Reflect.ownKeys(options), fields = Object.getOwnPropertyDescriptors(options);
	if (!keys.every((key) => typeof key === 'string' && Object.hasOwn(fields[key], 'value')) || keys.sort().join(',') !== 'attempt,authThread,captureThread,database,lifetime,startedNs') throw new TypeError('Unsupported synthetic decoder context.');
	let { attempt, lifetime, startedNs, authThread, captureThread, database } = options;
	if (typeof attempt !== 'symbol' || typeof lifetime !== 'symbol' || attempt === lifetime || Symbol.keyFor(attempt) !== undefined || Symbol.keyFor(lifetime) !== undefined || typeof startedNs !== 'bigint' || startedNs < 0n || startedNs > 9223372026854775807n ||
		!threadId(authThread) || !threadId(captureThread) || authThread === captureThread || typeof database !== 'string' || !/^[A-Za-z0-9_-]{1,64}$/.test(database)) throw new TypeError('Unsupported synthetic decoder context.');
	let stage = 'greeting', thread = 0, last = startedNs, total = 0;
	const deadline = startedNs + 10000000000n;
	let header = Buffer.alloc(4), headerUsed = 0, payload = null, used = 0;
	const clear = () => { header.fill(0); payload?.fill(0); headerUsed = 0; payload = null; used = 0; };
	const fail = () => { clear(); stage = 'failed'; thread = 0; return false; };
	const context = (a, pair, now) => {
		if (stage === 'closed' || stage === 'failed') return false;
		if (a !== attempt || pair !== lifetime || typeof now !== 'bigint' || now < last || now >= deadline) return fail();
		last = now;
		return true;
	};
	const packet = (bytes) => {
		// Five selected frames maximum follows from these irreversible stages.
		if (stage === 'greeting') {
			thread = greeting(bytes);
			if (!thread || thread === authThread || thread === captureThread) return fail();
			stage = 'hello';
		} else if (stage === 'hello') {
			if (!clientHello(bytes, database)) return fail();
			stage = 'auth';
		} else if (stage === 'auth') {
			if (!idleOk(bytes)) return fail();
			stage = 'ready';
		} else if (stage === 'commit') {
			if (!commit(bytes)) return fail();
			stage = 'response';
		} else if (stage === 'response') {
			if (!idleOk(bytes)) return fail();
			stage = 'observed';
		} else return fail();
		return true;
	};
	return Object.freeze({
		feed(direction, chunk, a, pair, now) {
			if (!context(a, pair, now)) return false;
			// Validate before copying/allocation. Whole selected transcript capped at1KiB.
			if (!Buffer.isBuffer(chunk) || chunk.length === 0 || chunk.length > 1024 || total + chunk.length > 1024) return fail();
			total += chunk.length;
			let offset = 0;
			while (offset < chunk.length) {
				const rule = grammar[stage];
				if (!rule || direction !== rule[0]) return fail();
				if (headerUsed < 4) {
					const size = Math.min(4 - headerUsed, chunk.length - offset);
					Buffer.prototype.copy.call(chunk, header, headerUsed, offset, offset + size); offset += size; headerUsed += size;
					if (headerUsed < 4) continue;
					const length = header.readUIntLE(0, 3);
					if (length < 1 || length > rule[2] || header[3] !== rule[1]) return fail();
					payload = Buffer.alloc(length);
				}
				const size = Math.min(payload.length - used, chunk.length - offset);
				Buffer.prototype.copy.call(chunk, payload, used, offset, offset + size); offset += size; used += size;
				if (used === payload.length) {
					const bytes = payload;
					if (!packet(bytes)) return false;
					clear();
				}
			}
			return true; // Fragment accepted, NOT a witness match or a delivery decision.
		},
		arm(a, pair, writerThread, now) {
			if (!context(a, pair, now)) return false;
			if (stage !== 'ready' || headerUsed !== 0 || !threadId(writerThread) || writerThread !== thread || deadline - now < 6000000000n) return fail();
			stage = 'commit';
			return true;
		},
		finish(a, pair, now) {
			const matched = context(a, pair, now) && stage === 'observed' && headerUsed === 0;
			if (stage === 'closed') return false;
			clear(); stage = 'closed'; thread = 0; attempt = null; lifetime = null; database = null; authThread = 0; captureThread = 0;
			return Object.freeze({ synthetic_match: matched, synthetic_only: true, authentication_proven: false,
				native_proven: false, forwarding_proven: false, delivery_proven: false, storage_supported: false,
				retry_supported: false, dispatch_supported: false, execution_supported: false });
		},
	});
}
