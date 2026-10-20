// Synthetic ASCII transcript/virtual clocks only; NOT IPC or an authorization API.
import { dataObject, validClock, validIdentity, validThread } from './native-witness-profile.mjs';

const schemas = {
	boot: ['v', 'kind', 'seq', 'attempt', 'intent'], root_request: ['v', 'kind', 'seq', 'attempt'],
	root: ['v', 'kind', 'seq', 'attempt', 'root'], bind: ['v', 'kind', 'seq', 'attempt', 'root', 'auth', 'capture', 'writer', 'remaining_ns'],
	bound: ['v', 'kind', 'seq', 'attempt', 'root', 'writer', 'pair'], rejected: ['v', 'kind', 'seq', 'attempt', 'root', 'reason'],
	final: ['v', 'kind', 'seq', 'attempt', 'root', 'pair', 'passed', 'checks', 'groups'],
};
const order = [['node', 'boot', 1], ['worker', 'root_request', 1], ['node', 'root', 2], ['worker', 'bind', 2], ['node', 'bound', 3], ['worker', 'final', 3]];
const token = (s) => typeof s === 'string' && /^[0-9a-f]{64}$/.test(s);
const duration = (s) => typeof s === 'string' && /^(0|[1-9][0-9]{0,10})$/.test(s) && BigInt(s) <= 10000000000n;

export function createNativeControlTranscript(options) {
	if (!dataObject(options, ['attempt', 'root', 'pair', 'roles']) || ![options.attempt, options.root, options.pair].every(token) ||
		new Set([options.attempt, options.root, options.pair]).size !== 3 || !dataObject(options.roles, ['auth', 'capture', 'writer'])) throw new TypeError('Unsupported synthetic control context.');
	const roles = ['auth', 'capture', 'writer'].map((role) => {
		const value = options.roles[role];
		if (!dataObject(value, ['thread', 'identity']) || !validThread(value.thread) || !validIdentity(value.identity)) throw new TypeError('Unsupported synthetic role.');
		return { thread: value.thread, identity: value.identity };
	});
	if (new Set(roles.map((r) => r.thread)).size !== 3 || new Set(roles.map((r) => r.identity)).size !== 3) throw new TypeError('Duplicate synthetic role.');
	let { attempt, root, pair } = options;
	let stage = 0, failed = false, finished = false, line = '', total = 0, lines = 0, lastNode = null, lastWorker = null;
	let rootNode = null, rootWorker = null, bindNode = null, bindWorker = null;
	const fail = () => { failed = true; line = ''; return false; };
	const clock = (node, worker) => {
		if (failed || finished) return false;
		if (!validClock(node) || !validClock(worker) || (lastNode !== null && (node < lastNode || worker < lastWorker)) ||
			(rootNode !== null && node - rootNode >= 10000000000n) || (rootWorker !== null && worker - rootWorker >= 10000000000n)) return fail();
		lastNode = node; lastWorker = worker; return true;
	};
	const accept = (text, direction, node, worker) => {
		let message;
		try { message = JSON.parse(text); } catch { return fail(); }
		const rule = order[stage], keys = typeof message?.kind === 'string' && Object.hasOwn(schemas, message.kind) ? schemas[message.kind] : null;
		if (!rule || !keys || !dataObject(message, keys) || Object.keys(message).join(',') !== keys.join(',') || JSON.stringify(message) !== text ||
			message.v !== 1 || direction !== rule[0] || message.kind !== rule[1] || message.seq !== rule[2] || message.attempt !== attempt ||
			(stage >= 2 && message.root !== root)) return fail();
		if (stage === 0 && message.intent !== 'observe_only') return fail();
		if (stage === 2) { rootWorker = worker; }
		if (stage === 3) {
			if (message.auth !== roles[0].thread || message.capture !== roles[1].thread || message.writer !== roles[2].thread || !duration(message.remaining_ns) ||
				BigInt(message.remaining_ns) < 6000000000n || BigInt(message.remaining_ns) > 10000000000n - (worker - rootWorker) || node - rootNode > 4000000000n) return fail();
		}
		if (stage === 4 && (message.writer !== roles[2].thread || message.pair !== pair || node - rootNode > 4000000000n || worker - rootWorker > 4000000000n ||
			node - bindNode > 1000000000n || worker - bindWorker > 1000000000n)) return fail();
		if (stage === 5 && (message.pair !== pair || message.passed !== true || !Number.isInteger(message.checks) || message.checks < 1 || message.checks > 8192 ||
			!Number.isInteger(message.groups) || message.groups < 1 || message.groups > 32)) return fail();
		stage++; return true;
	};
	return Object.freeze({
		feed(direction, bytes, node, worker, writerIdentity) {
			if (!clock(node, worker)) return false;
			if (writerIdentity !== roles[2].identity || !['node', 'worker'].includes(direction) || !Buffer.isBuffer(bytes) || !bytes.length || total + bytes.length > 32768 || direction !== order[stage]?.[0]) return fail();
			// Sample supervisor T0 before accepting any ROOT bytes, never at BIND.
			if (stage === 2 && rootNode === null) { if (node > 9223372026854775807n) return fail(); rootNode = node; }
			if (stage === 3 && bindNode === null) { bindNode = node; bindWorker = worker; }
			total += bytes.length;
			for (const byte of bytes) {
				if (byte === 10) {
					if (++lines > 8 || !accept(line, direction, node, worker)) return fail();
					line = '';
				} else {
					if (byte < 32 || byte > 126 || byte === 92 || line.length >= 4095) return fail();
					line += String.fromCharCode(byte);
				}
			}
			return true;
		},
		finish(node, worker, writerIdentity) {
			if (finished) return false;
			const match = clock(node, worker) && stage === 6 && line.length === 0 && writerIdentity === roles[2].identity;
			fail(); finished = true; attempt = null; root = null; pair = null; roles.length = 0;
			return Object.freeze({ synthetic_match: Boolean(match), synthetic_only: true, ipc_proven: false, authentication_proven: false,
				native_proven: false, storage_supported: false, dispatch_supported: false, execution_supported: false });
		},
	});
}
