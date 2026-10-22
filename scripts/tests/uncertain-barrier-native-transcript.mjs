// Synthetic duplex bytes and virtual clocks only. No process, pipe or network APIs.
import { parseNativeQualificationLine, sourceShape, profiles } from './uncertain-barrier-native-grammar.mjs';
const kinds = ['boot', 'root_request', 'root', 'bind_writer', 'bound_writer', 'bind_barrier', 'bound_barrier', 'final'];
const validTick = (n) => typeof n === 'bigint' && n >= 0n && n <= 9223372016854775807n;
function transcript(source, profile) {
	if (!sourceShape(source) || !profiles.includes(profile)) return false;
	source = Object.freeze({ ...source });
	let stage = 0, bytes = 0, line = '', failed = false, spent = false, attempt = null, root = null, last = null, rootAt = null, bindAt = null, readEnd = null, rootEnd = null, writer = null, barrier = null;
	const fail = () => { failed = true; line = ''; attempt = root = writer = barrier = last = rootAt = bindAt = readEnd = rootEnd = null; return false; };
	const rolesSame = (m, b, fourth = false) => ['auth', 'capture', 'writer', ...(fourth ? ['barrier'] : [])].every((k) => m[k] === b[k]);
	return Object.freeze({
		feed(direction, chunk, tick) {
			if (arguments.length !== 3 || failed || spent || stage >= 8 || direction !== (stage % 2 ? 'worker' : 'parent') || !validTick(tick) || last !== null && tick < last || typeof chunk !== 'string' || !chunk.length || chunk.length > 32768 - bytes) return fail();
			if (stage === 2 && rootAt === null) rootAt = tick;
			if ((stage === 3 || stage === 5) && bindAt === null) bindAt = tick;
			if (rootAt !== null && tick - rootAt >= 20000000000n || rootEnd !== null && tick >= rootEnd || readEnd !== null && tick >= readEnd || (stage === 4 || stage === 6) && tick - bindAt > 1000000000n) return fail();
			last = tick; bytes += chunk.length;
			for (let i = 0; i < chunk.length; i++) {
				const byte = chunk.charCodeAt(i);
				if (byte !== 10) { if (byte < 32 || byte > 126 || byte === 92 || line.length >= 4095) return fail(); line += chunk[i]; continue; }
				if (i !== chunk.length - 1) return fail();
				const m = parseNativeQualificationLine(line, source); line = '';
				if (!m || m.kind !== kinds[stage] || stage && m.attempt !== attempt || Object.hasOwn(m, 'profile') && m.profile !== profile) return fail();
				if (stage === 0) attempt = m.attempt;
				if (stage === 2) root = m.root;
				if (stage > 2 && m.root !== root) return fail();
				if (stage === 3) { if (tick - rootAt > 4000000000n) return fail(); writer = m; }
				if (stage === 4) { if (!rolesSame(m, writer) || m.remaining_ns !== writer.remaining_ns || tick - rootAt > 4000000000n) return fail(); bindAt = null; }
				if (stage === 5) {
					if (!rolesSame(m, writer)) return fail();
					barrier = m; readEnd = bindAt + BigInt(m.read_remaining_ns); rootEnd = bindAt + BigInt(m.root_remaining_ns);
					if (tick >= readEnd || tick >= rootEnd) return fail();
				}
				if (stage === 6 && (!rolesSame(m, barrier, true) || m.root_remaining_ns !== barrier.root_remaining_ns || m.read_remaining_ns !== barrier.read_remaining_ns)) return fail();
				if (stage === 7 && !rolesSame(m, barrier, true)) return fail();
				stage++;
			}
			return true;
		},
		finish() {
			if (spent) return false; spent = true;
			const good = arguments.length === 0 && !failed && stage === 8 && line === '';
			fail();
			return Object.freeze({ synthetic_match: good, native_proven: false, authentication_proven: false, root_invocation_proven: false, root_erasure_proven: false, ipc_proven: false, response_loss_proven: false, cleanup_proven: false, storage_supported: false, dispatch_supported: false, execution_supported: false });
		},
	});
}
export function createNativeQualificationNativeTranscript(source, profile) { return arguments.length === 2 ? transcript(source, profile) : false; }
