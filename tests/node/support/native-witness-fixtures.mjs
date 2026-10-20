// Invented bytes/tokens only, independent of implementation capability constants.
import { createNativeProfileWitness } from '../../../scripts/tests/native-profile-witness.mjs';
import { createNativeControlTranscript } from '../../../scripts/tests/native-control-transcript.mjs';
export const ok = () => Buffer.from([0, 0, 0, 2, 0, 0, 0]);
export const commit = () => Buffer.from('\x03COMMIT AND NO CHAIN NO RELEASE');
export function frame(body, seq = 0) { const h = Buffer.alloc(4); h.writeUIntLE(body.length, 0, 3); h[3] = seq; return Buffer.concat([h, body]); }
export function greeting(thread = 103, collation = 8) {
	const fixed = Buffer.alloc(31); fixed.writeUInt32LE(thread); fixed.fill(65, 4, 12); fixed.writeUInt16LE(0xa208, 13);
	fixed[15] = collation; fixed.writeUInt16LE(2, 16); fixed.writeUInt16LE(8, 18); fixed[20] = 21; fixed.writeUInt32LE(0x12345678, 27);
	return Buffer.concat([Buffer.from('\x0a5.5.5-10.6.23-MariaDB\0'), fixed, Buffer.from('BBBBBBBBBBBB\0mysql_native_password\0')]);
}
export function hello(flags = 0x0008a208, attrs = Buffer.alloc(0)) {
	const fixed = Buffer.alloc(32); fixed.writeUInt32LE(flags >>> 0); fixed.writeUInt32LE(2097152, 4); fixed[8] = 45;
	return Buffer.concat([fixed, Buffer.from('synthetic\0'), Buffer.from([20]), Buffer.alloc(20, 67),
		(flags & 8) ? Buffer.from('fixture\0') : Buffer.alloc(0), Buffer.from('mysql_native_password\0'), attrs]);
}
export function witness() {
	const ids = { auth: Symbol('auth'), capture: Symbol('capture'), writer: Symbol('writer') };
	const config = { username: 'synthetic', database: 'fixture', startedNs: 0n, lifetimes: ids };
	const api = createNativeProfileWitness(config);
	const send = (role, direction, body, seq = 0, now = 1n, account = true) => api.feed(ids[role], direction, frame(body, seq), now) && (!account || api.account(ids[role], direction, 1, now));
	const ready = (bindNow = 1n, bind = true) => {
		for (const [index, role] of ['auth', 'capture', 'writer'].entries()) {
			if (!send(role, 'server', greeting(101 + index), 0) || !send(role, 'client', hello(role === 'auth' ? 0x8a200 : 0x8a208), 1) || !send(role, 'server', ok(), 2)) return false;
		}
		return api.close(ids.capture, 1n) && (!bind || api.bind(ids.writer, bindNow));
	};
	const complete = (now = 2n, receipt = true) => api.close(ids.writer, now) && api.close(ids.auth, now) && api.finish(now, receipt);
	return { api, ids, config, send, ready, complete };
}
export function control() {
	const attempt = 'a'.repeat(64), root = 'b'.repeat(64), pair = 'c'.repeat(64);
	const roles = Object.fromEntries(['auth', 'capture', 'writer'].map((role, i) => [role, { thread: 101 + i, identity: Symbol(role) }]));
	const api = createNativeControlTranscript({ attempt, root, pair, roles });
	const messages = [
		{ v: 1, kind: 'boot', seq: 1, attempt, intent: 'observe_only' }, { v: 1, kind: 'root_request', seq: 1, attempt },
		{ v: 1, kind: 'root', seq: 2, attempt, root }, { v: 1, kind: 'bind', seq: 2, attempt, root, auth: 101, capture: 102, writer: 103, remaining_ns: '10000000000' },
		{ v: 1, kind: 'bound', seq: 3, attempt, root, writer: 103, pair }, { v: 1, kind: 'final', seq: 3, attempt, root, pair, passed: true, checks: 1, groups: 1 },
	];
	const wire = (i) => Buffer.from(`${JSON.stringify(messages[i])}\n`, 'ascii');
	const send = (i, node = 0n, worker = 900000000000n, bytes = wire(i), identity = roles.writer.identity) => api.feed(i % 2 ? 'worker' : 'node', bytes, node, worker, identity);
	const finish = (node = 0n, worker = 900000000000n) => api.finish(node, worker, roles.writer.identity);
	return { api, roles, messages, wire, send, finish };
}
