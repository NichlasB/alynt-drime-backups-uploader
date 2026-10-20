// Bounded physical-proof framing only. No process, filesystem or network access.
export function createOwnedStdioLines(onLine) {
	if (typeof onLine !== 'function') throw new TypeError('A fixed proof consumer is required.');
	let partial = '', bytes = 0, lines = 0, failed = false, ended = false;
	const fail = () => { failed = true; partial = ''; return false; };
	return Object.freeze({
		feed(chunk) {
			if (failed || ended) return false;
			if (!Buffer.isBuffer(chunk) || !chunk.length || bytes + chunk.length > 16384) return fail();
			bytes += chunk.length;
			for (const byte of chunk) {
				if (byte === 10) {
					if (++lines > 4 || !partial.length || onLine(partial) !== true) return fail();
					partial = '';
				} else {
					if (byte < 32 || byte > 126 || byte === 92 || partial.length >= 4095) return fail();
					partial += String.fromCharCode(byte);
				}
			}
			return true;
		},
		end() {
			if (ended) return false;
			ended = true;
			return !failed && lines > 0 && partial.length === 0;
		},
	});
}
