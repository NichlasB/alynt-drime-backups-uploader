// Test-only classic framing. No SQL interpretation and no suppression/arming API.
import { createHash } from 'node:crypto';

export const FRAME_CAP = 2 * 1024 * 1024;
function relayGreeting(payload) {
	const end = payload.indexOf(0, 1), at = end + 1;
	if (payload[0] !== 10 || end < 1 || end > 129 || !/^(?:5\.5\.5-)?10\.6\.[0-9]+-MariaDB(?:[- ].*)?$/.test(payload.subarray(1, end).toString('ascii')) || payload.length !== at + 66) throw Error('unsupported_greeting');
	const flags = payload.readUInt16LE(at + 13) + 65536 * payload.readUInt16LE(at + 18);
	// MariaDB extended capability word is not the six reserved bytes. The server
	// default collation is not the native client's later utf8mb4 session policy.
	if (!payload.readUInt32LE(at) || payload[at + 12] !== 0 || ![8, 45].includes(payload[at + 15]) || payload.readUInt16LE(at + 16) !== 2 ||
		(flags & 0x00088208) !== 0x00088208 || payload[at + 20] !== 21 || !payload.subarray(at + 21, at + 27).every((b) => b === 0) ||
		((flags & 1) && !payload.subarray(at + 27, at + 31).every((b) => b === 0)) || payload[at + 43] !== 0 || !payload.subarray(at + 44).equals(Buffer.from('mysql_native_password\0'))) throw Error('unsupported_greeting');
	return payload.readUInt32LE(at);
}
export function frameStream(onFrame, reserve = () => {}, release = () => {}, onHeader = () => {}) {
	let header = Buffer.alloc(4), used = 0, frame = null, at = 0, count = 0;
	const received = createHash('sha256'), emitted = createHash('sha256');
	let bytes = 0, sent = 0, ended = false;
	return Object.freeze({
		boundary() { return used === 0 && frame === null; },
		async feed(chunk) {
			if (ended || !Buffer.isBuffer(chunk)) throw Error('frame_input');
			received.update(chunk); bytes += chunk.length;
			if (bytes > 64 * 1024 * 1024) throw Error('byte_cap');
			for (let i = 0; i < chunk.length;) {
				if (!frame) {
					const n = Math.min(4 - used, chunk.length - i);
					chunk.copy(header, used, i, i + n); used += n; i += n;
					if (used !== 4) continue;
					const length = header.readUIntLE(0, 3);
					if (length === 0 || length > FRAME_CAP || ++count > 4096) throw Error('frame_cap');
					onHeader(length); // Passive observer; the transparent transport cap is unchanged.
					reserve(length + 4); frame = Buffer.alloc(length + 4); header.copy(frame); at = 4;
				}
				const n = Math.min(frame.length - at, chunk.length - i);
				chunk.copy(frame, at, i, i + n); at += n; i += n;
				if (at === frame.length) {
					await onFrame(frame); emitted.update(frame); sent += frame.length;
					frame.fill(0); release(frame.length); frame = null; header.fill(0); used = 0;
				}
			}
		},
		finish() {
			if (ended) throw Error('frame_reuse'); ended = true;
			const complete = used === 0 && frame === null;
			if (frame) { frame.fill(0); release(frame.length); } header.fill(0); frame = null;
			return Object.freeze({ complete, bytes, frames: count, equal: complete && bytes === sent && received.digest('hex') === emitted.digest('hex') });
		},
	});
}

export function handshakeGate() {
	let stage = 0, thread = null;
	return Object.freeze({
		inspect(direction, frame) {
			if (stage >= 3) { if (direction === 'server' && frame[4] === 0xfb) throw Error('unsupported_local_file_or_null_row'); return; }
			if (frame[3] !== stage || direction !== (stage === 1 ? 'client' : 'server')) throw Error('handshake_order');
			const payload = frame.subarray(4);
			if (stage === 0) {
				thread = relayGreeting(payload);
			} else if (stage === 1) {
				if (payload.length < 33 || payload.length > 4096) throw Error('unsupported_hello');
				const flags = payload.readUInt32LE(0);
				// mysqlnd advertises LOCAL_FILES even with local infile disabled. Never
				// forward a server 0xfb request (or ambiguous NULL-leading result row).
				for (const [bit, label] of [[0x20, 'compression'], [0x800, 'tls'], [0x10000, 'multi'], [0x400000, 'expired'], [0x4000000, 'zstd']]) if (flags & bit) throw Error('unsupported_negotiation_' + label);
				// wpdb selects its database after authentication; owned native readers
				// select it in the handshake. Runtime independently validates DB_NAME.
				if ((flags & 0x00088200) !== 0x00088200) throw Error('unsupported_negotiation_required');
			} else if (payload.length !== 7 || payload[0] !== 0 || payload[1] !== 0 || payload[2] !== 0 || payload.readUInt16LE(5) !== 0) throw Error('unsupported_auth');
			stage++;
		},
		identity() { return stage === 3 ? thread : null; },
		cleanupIdentity() { return thread; },
	});
}
