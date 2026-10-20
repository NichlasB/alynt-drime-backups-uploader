// Pure fragment/coalescence adapter. Callback gets owned scratch bytes, not a socket.
export function createDrainFrames(payloadCap, onFrame) {
	if (!Number.isInteger(payloadCap) || payloadCap < 1 || payloadCap > 4096) throw TypeError('Unsupported synthetic frame cap.');
	return frames(() => payloadCap, onFrame, () => true, () => {}, true);
}
export function createWitnessFrames(cap, onFrame, reserve, release) { return frames(cap, onFrame, reserve, release, false); }
function frames(cap, onFrame, reserve, release, tight) {
	if (![cap, onFrame, reserve, release].every((f) => typeof f === 'function')) throw TypeError('Unsupported synthetic frame sink.');
	let header = Buffer.alloc(4), used = 0, body = null, held = 0, at = 0, total = 0, count = 0, failed = false, ended = false;
	const clear = () => { header.fill(0); body?.fill(0); body = null; if (held) { const size = held; held = 0; release(size); } };
	const fail = () => { failed = true; clear(); return false; };
	return Object.freeze({
		boundary() { return used === 0 && !body; },
		abort() { return fail(); },
		feed(bytes) {
			if (failed || ended || !Buffer.isBuffer(bytes) || bytes.length < 1 || (total += bytes.length) > (tight ? 32768 : 67108864)) return fail();
			try {
				for (let offset = 0; offset < bytes.length;) {
					if (!body) {
						const n = Math.min(4 - used, bytes.length - offset); bytes.copy(header, used, offset, offset + n); used += n; offset += n;
						if (used < 4) continue;
						const length = header.readUIntLE(0, 3), payloadCap = cap();
						if (!Number.isInteger(payloadCap) || payloadCap < 1 || payloadCap > 2097152 || length < 1 || length > payloadCap || ++count > (tight ? 26 : 4096) || reserve(length + 4) !== true) return fail();
						held = length + 4; body = Buffer.alloc(held); header.copy(body); at = 4;
					}
					const n = Math.min(body.length - at, bytes.length - offset); bytes.copy(body, at, offset, offset + n); offset += n; at += n;
					if (at === body.length) { if (onFrame(body) !== true) return fail(); clear(); used = 0; }
				}
				return true;
			} catch { return fail(); }
		},
		end() { if (ended) return false; const ok = !failed && !body && used === 0; ended = true; fail(); return ok; },
	});
}
