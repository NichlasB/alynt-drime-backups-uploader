// Pure synthetic events/ASCII bytes/virtual milliseconds. No process, I/O or core APIs.
export const projectionProfile = '8c8eb406d22d6e0033b64c2cd1255e64925fd08450e1c4965fbacdb36189cacc';
export const sidebarProjectionProfile = 'a066782b53b105be3386bc2f186b90a59c262cfe47b9bccdd07a180cf62828e0';
const tickShape = (n) => Number.isSafeInteger(n) && n >= 0;
const rootShape = (s) => typeof s === 'string' && /^[a-f0-9]{24}$/.test(s) && s.length === 24;
// attempted SQL, completed data SQL, bulk, P provenance, N requests, empty N results,
// includes, writer intents, HTTP intents, diagnostics, core shutdown returns, cache closes.
function countersShape(c) {
	return Array.isArray(c) && c.length === 12 && c.every((n) => Number.isSafeInteger(n) && n >= 0) &&
		c[0] <= 32 && c[1] <= c[0] && c[2] <= 1 && c[3] === 29 && c[4] <= c[0] && c[5] === c[4] &&
		c[6] >= 1 && c[6] <= 1900 && c[7] === 0 && c[8] === 0 && c[9] === 0 && c[10] === 1 && c[11] === 1;
}
export function parseProjectionCandidate(line, root, mode) {
	return arguments.length === 3 && parseForProfile(line, root, mode, projectionProfile);
}
export function parseSidebarProjectionCandidate(line, root, mode) {
	return arguments.length === 3 && parseForProfile(line, root, mode, sidebarProjectionProfile);
}
// Private fixed-profile composition only. No exported caller-selectable profile parameter.
function parseForProfile(line, root, mode, projectionProfile) {
	if (!rootShape(root) || !['autoload', 'fallback'].includes(mode) || typeof line !== 'string' ||
		line.length > 4096 || !/^\x5b[\x20-\x7e]*\x5d\n$/.test(line) || line.includes('\\')) return false;
	let m;
	try { m = JSON.parse(line); } catch { return false; }
	if (!Array.isArray(m) || m.length !== 8 || JSON.stringify(m) + '\n' !== line || m[0] !== 'alynt.projection.candidate' ||
		m[1] !== 1 || m[2] !== root || m[3] !== mode || m[4] !== projectionProfile || m[5] !== 'core_shutdown_returned' ||
		!countersShape(m[6]) || m[7] !== 'modeled_only') return false;
	return Object.freeze(m.map((v) => Array.isArray(v) ? Object.freeze([...v]) : v));
}
export function createProjectionShutdownTranscript(root, mode, start) {
	return arguments.length === 3 && createForProfile(root, mode, start, projectionProfile);
}
export function createSidebarProjectionShutdownTranscript(root, mode, start) {
	return arguments.length === 3 && createForProfile(root, mode, start, sidebarProjectionProfile);
}
function createForProfile(root, mode, start, projectionProfile) {
	if (!rootShape(root) || !['autoload', 'fallback'].includes(mode) || !tickShape(start)) return false;
	let stage = 0, last = start, failed = false, spent = false, line = '', closed = false, stdoutEof = false, stderrEof = false;
	const refuse = () => { failed = true; line = ''; return false; };
	const clock = (tick) => tickShape(tick) && tick >= last && tick >= start && tick - start < 20000;
	return Object.freeze({
		event(kind, value, tick) {
			if (arguments.length !== 3 || failed || spent || !clock(tick)) return refuse();
			last = tick;
			if (kind === 'fenced' && stage === 0 && value === projectionProfile) { stage = 1; return true; }
			if (kind === 'core_returned' && stage === 1 && value === true) { stage = 2; return true; }
			if (kind === 'core_shutdown_returned' && stage === 2 && Array.isArray(value) && value.length === 2 && value[0] === 1 && value[1] === 1) { stage = 3; return true; }
			if (kind === 'stdout' && stage === 3 && !closed && !stdoutEof && typeof value === 'string' && value.length > 0 && value.length <= 4096 - line.length) {
				if (!/^[\x20-\x7e\n]+$/.test(value)) return refuse();
				line += value;
				if (line.includes('\n')) {
					if (!parseForProfile(line, root, mode, projectionProfile)) return refuse();
					stage = 4; line = '';
				}
				return true;
			}
			if (stage === 4 && kind === 'close' && !closed && Array.isArray(value) && value.length === 2 && value[0] === 0 && value[1] === null) { closed = true; return true; }
			if (stage === 4 && kind === 'stdout_eof' && value === null && !stdoutEof) { stdoutEof = true; return true; }
			if (stage === 4 && kind === 'stderr_eof' && value === null && !stderrEof) { stderrEof = true; return true; }
			return refuse();
		},
		// Latch BEFORE throwing; swallowing this error can never restore acceptance.
		violation() { refuse(); throw new Error('projection_refused'); },
		finish(tick) {
			if (spent) return false;
			const good = arguments.length === 1 && !failed && clock(tick) && stage === 4 && closed && stdoutEof && stderrEof;
			spent = true; refuse();
			return Object.freeze({ synthetic_match: good, native_proven: false, authentication_proven: false, absence_proven: false,
				cleanup_proven: false, storage_supported: false, wiring_supported: false, execution_supported: false });
		},
	});
}
