// Separate v2 synthetic grammar. Deliberately does not relax observe-only v1.
import { dataObject, validClock, validIdentity, validThread } from './native-witness-profile.mjs';
import { lossCase, lossSummary } from './selected-loss-contract.mjs';
const schemas = {
	boot: ['v', 'kind', 'seq', 'attempt', 'intent'], root_request: ['v', 'kind', 'seq', 'attempt'], root: ['v', 'kind', 'seq', 'attempt', 'root'],
	bind: ['v', 'kind', 'seq', 'attempt', 'root', 'auth', 'capture', 'writer', 'remaining_ns'], bound: ['v', 'kind', 'seq', 'attempt', 'root', 'writer', 'pair'],
	final: ['v', 'kind', 'seq', 'attempt', 'root', 'pair', 'case', 'checks', 'groups', 'returned_false', 'pre_guards', 'post_guards', 'root_erased'],
};
const order = [['node', 'boot', 1], ['worker', 'root_request', 1], ['node', 'root', 2], ['worker', 'bind', 2], ['node', 'bound', 3], ['worker', 'final', 3]];
const token = (s) => typeof s === 'string' && /^[0-9a-f]{64}$/.test(s);
export function createSelectedLossControl(options) {
	if (!dataObject(options, ['attempt', 'root', 'pair', 'roles']) || ![options.attempt, options.root, options.pair].every(token) ||
		new Set([options.attempt, options.root, options.pair]).size !== 3 || !dataObject(options.roles, ['auth', 'capture', 'writer'])) throw TypeError('Unsupported synthetic control context.');
	const roles = ['auth', 'capture', 'writer'].map((role) => {
		const r = options.roles[role];
		if (!dataObject(r, ['thread', 'identity']) || !validThread(r.thread) || !validIdentity(r.identity)) throw TypeError('Unsupported synthetic role.');
		return { thread: r.thread, identity: r.identity };
	});
	if (new Set(roles.map((r) => r.thread)).size !== 3 || new Set(roles.map((r) => r.identity)).size !== 3) throw TypeError('Duplicate synthetic role.');
	let { attempt, root, pair } = options;
	let stage = 0, failed = false, finished = false, line = '', total = 0, lines = 0, lastNode = null, lastWorker = null;
	let rootNode = null, rootWorker = null, bindNode = null, bindWorker = null;
	const fail = () => { failed = true; line = ''; return false; };
	const clock = (node, worker) => {
		if (failed || finished) return false;
		if (!validClock(node) || !validClock(worker) || lastNode !== null && (node < lastNode || worker < lastWorker) ||
			rootNode !== null && node - rootNode >= 10000000000n || rootWorker !== null && worker - rootWorker >= 10000000000n) return fail();
		lastNode = node; lastWorker = worker; return true;
	};
	function accept(text, direction, node, worker) {
		let m;
		try { m = JSON.parse(text); } catch { return fail(); }
		const rule = order[stage], keys = typeof m?.kind === 'string' && Object.hasOwn(schemas, m.kind) ? schemas[m.kind] : null;
		if (!rule || !keys || !dataObject(m, keys) || Object.keys(m).join(',') !== keys.join(',') || JSON.stringify(m) !== text ||
			m.v !== 2 || direction !== rule[0] || m.kind !== rule[1] || m.seq !== rule[2] || m.attempt !== attempt || stage >= 2 && m.root !== root) return fail();
		if (stage === 0 && m.intent !== lossCase) return fail();
		if (stage === 2) rootWorker = worker;
		if (stage === 3 && (m.auth !== roles[0].thread || m.capture !== roles[1].thread || m.writer !== roles[2].thread ||
			typeof m.remaining_ns !== 'string' || !/^(0|[1-9][0-9]{0,10})$/.test(m.remaining_ns) || BigInt(m.remaining_ns) < 6000000000n ||
			BigInt(m.remaining_ns) > 10000000000n - (worker - rootWorker) || node - rootNode > 4000000000n)) return fail();
		if (stage === 4 && (m.writer !== roles[2].thread || m.pair !== pair || node - rootNode > 4000000000n || worker - rootWorker > 4000000000n ||
			node - bindNode > 1000000000n || worker - bindWorker > 1000000000n)) return fail();
		if (stage === 5) {
			const summary = Object.fromEntries(keys.slice(6).map((key) => [key, m[key]]));
			if (m.pair !== pair || !lossSummary(summary)) return fail();
		}
		stage++; return true;
	}
	return Object.freeze({
		feed(direction, bytes, node, worker, writerIdentity) {
			if (!clock(node, worker)) return false;
			if (writerIdentity !== roles[2].identity || !Buffer.isBuffer(bytes) || !bytes.length || total + bytes.length > 32768 || direction !== order[stage]?.[0]) return fail();
			if (stage === 2 && rootNode === null) rootNode = node;
			if (stage === 3 && bindNode === null) { bindNode = node; bindWorker = worker; }
			total += bytes.length;
			for (const byte of bytes) {
				if (byte === 10) { if (++lines > 8 || !accept(line, direction, node, worker)) return fail(); line = ''; }
				else { if (byte < 32 || byte > 126 || byte === 92 || line.length >= 4095) return fail(); line += String.fromCharCode(byte); }
			}
			return true;
		},
		finish(node, worker, identity) {
			if (finished) return false;
			const match = clock(node, worker) && stage === 6 && line.length === 0 && identity === roles[2].identity;
			fail(); finished = true; attempt = null; root = null; pair = null; roles.length = 0;
			return Object.freeze({ synthetic_match: Boolean(match), synthetic_only: true, ipc_proven: false, authentication_proven: false,
				native_proven: false, zero_delivery_proven: false, storage_supported: false, dispatch_supported: false, execution_supported: false, suppression_supported: false });
		},
	});
}
