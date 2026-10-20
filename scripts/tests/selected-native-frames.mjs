// Separate loss accounting. Existing transparent framer/equality contract unchanged.
import { createHash } from 'node:crypto';
export function selectedNativeFrames(onFrame, reserve, release, cap) {
	let header = Buffer.alloc(4), used = 0, body = null, at = 0, count = 0, ended = false, bytes = 0, sent = 0, withheld = 0;
	const received = createHash('sha256'), accounted = createHash('sha256');
	return Object.freeze({
		boundary() { return used === 0 && body === null; },
		async feed(chunk) {
			if (ended || !Buffer.isBuffer(chunk) || !chunk.length || (bytes += chunk.length) > 67108864) throw Error('stream_bytes');
			received.update(chunk);
			for (let i = 0; i < chunk.length;) {
				if (!body) {
					const n = Math.min(4 - used, chunk.length - i); chunk.copy(header, used, i, i + n); used += n; i += n;
					if (used < 4) continue;
					const size = header.readUIntLE(0, 3), ceiling = cap(size);
					if (!Number.isInteger(ceiling) || ceiling < 1 || ceiling > 2097152 || size < 1 || size > ceiling || ++count > 4096) throw Error('frame_cap');
					reserve(size + 4);
					try { body = Buffer.alloc(size + 4); } catch { release(size + 4); throw Error('allocation'); }
					header.copy(body); at = 4;
				}
				const n = Math.min(body.length - at, chunk.length - i); chunk.copy(body, at, i, i + n); at += n; i += n;
				if (at === body.length) {
					const disposition = await onFrame(body, chunk.length - i);
					if (disposition === 'forwarded' && withheld === 0) sent += body.length;
					else if (disposition === 'withheld' && body.length === 11 && withheld === 0) withheld = 11;
					else throw Error('stream_disposition');
					accounted.update(body); body.fill(0); release(body.length); body = null; header.fill(0); used = 0;
				}
			}
		},
		finish() {
			if (ended) throw Error('stream_reuse'); ended = true;
			const complete = used === 0 && body === null;
			if (body) { body.fill(0); release(body.length); body = null; } header.fill(0);
			return Object.freeze({ complete, bytes, sent, withheld, frames: count,
				conserved: complete && bytes === sent + withheld && received.digest('hex') === accounted.digest('hex') });
		},
	});
}
