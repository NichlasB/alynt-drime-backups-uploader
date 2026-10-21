// Synthetic duplex bytes + virtual monotonic ticks only. No child/pipe/socket/controller.
import { parseSelectedLine, sourceShape } from './selected-qualification-grammar.mjs';
export function createSelectedTranscript(source) {
	if (arguments.length !== 1 || !sourceShape(source)) return false;
	source = Object.freeze({ ...source });
	const kinds = ['boot', 'root_request', 'root', 'bind', 'bound', 'final'];
	let stage = 0, bytes = 0, line = '', failed = false, spent = false, attempt = null, root = null, writer = null, pair = null, rootAt = null, bindAt = null, last = null;
	const fail = () => { failed = true; line = ''; attempt = root = writer = pair = rootAt = bindAt = last = null; return false; };
	const validTick = (n) => typeof n === 'bigint' && n >= 0n && n <= 9223372036854775807n;
	return Object.freeze({
		feed(direction, chunk, tick) {
			if (arguments.length !== 3 || failed || spent || stage >= 6 || direction !== (stage % 2 ? 'worker' : 'parent') || !validTick(tick) || last !== null && tick < last || typeof chunk !== 'string' || !chunk.length || chunk.length > 32768 - bytes) return fail();
			if (stage === 2 && rootAt === null) rootAt = tick; // First ROOT byte, never reset at the final fragment.
			if (stage === 3 && bindAt === null) bindAt = tick;
			if (rootAt !== null && tick - rootAt >= 10000000000n || stage === 4 && tick - bindAt > 1000000000n) return fail();
			last = tick; bytes += chunk.length;
			for (let i = 0; i < chunk.length; i++) {
				const byte = chunk.charCodeAt(i);
				if (byte !== 10) { if (byte < 32 || byte > 126 || byte === 92 || line.length >= 4095) return fail(); line += chunk[i]; continue; }
				if (i !== chunk.length - 1) return fail(); // One directional frame per event, including fragmented frames.
				const m = parseSelectedLine(line, source); line = '';
				if (!m || m.kind !== kinds[stage] || stage && m.attempt !== attempt) return fail();
				if (stage === 0) attempt = m.attempt;
				if (stage === 2) root = m.root;
				if (stage > 2 && (m.root !== root || tick - rootAt >= 10000000000n)) return fail();
				if (stage === 3) { if (tick - rootAt > 4000000000n) return fail(); writer = m.writer; }
				if (stage === 4) { if (m.writer !== writer || tick - rootAt > 4000000000n || tick - bindAt > 1000000000n) return fail(); pair = m.pair; }
				if (stage === 5 && m.pair !== pair) return fail();
				stage++;
			}
			return true;
		},
		finish() {
			if (spent) return false; spent = true;
			const good = arguments.length === 0 && !failed && stage === 6 && line === '';
			fail();
			return Object.freeze({ synthetic_match: good, native_proven: false, authentication_proven: false, root_invocation_proven: false, root_erasure_proven: false, ipc_proven: false, response_loss_proven: false, cleanup_proven: false, storage_supported: false, dispatch_supported: false, execution_supported: false });
		},
	});
}
