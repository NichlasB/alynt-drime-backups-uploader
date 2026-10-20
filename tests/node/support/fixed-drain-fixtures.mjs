// Independent invented packets. No production or native proof imports.
import { createDrainWitness } from '../../../scripts/tests/fixed-drain-witness.mjs';
import { frame, greeting, hello, ok, commit } from './native-witness-fixtures.mjs';
export { frame, ok, commit };
export const queries = [
	'SELECT CAST(CONNECTION_ID() AS CHAR),DATABASE(),@@hostname,CAST(@@port AS CHAR),CAST(@@server_id AS CHAR),VERSION(),CAST(@@autocommit+0 AS CHAR),CAST(@@in_transaction+0 AS CHAR),CAST(@@read_only+0 AS CHAR),CAST(@@tx_read_only+0 AS CHAR)',
	'SELECT @@tx_isolation,CAST(@@innodb_lock_wait_timeout AS CHAR),CAST(@@lock_wait_timeout AS CHAR),CURRENT_ROLE(),CAST(@@innodb_flush_log_at_trx_commit AS CHAR)',
];
export const eof = () => Buffer.from([254, 0, 0, 2, 0]);
export function text(value) {
	if (value === null) return Buffer.from([251]);
	const bytes = Buffer.isBuffer(value) ? value : Buffer.from(value);
	const size = bytes.length < 251 ? Buffer.from([bytes.length]) : Buffer.from([252, bytes.length & 255, bytes.length >> 8]);
	return Buffer.concat([size, bytes]);
}
export function column() { return Buffer.concat(['def', '', '', '', 'field', ''].map(text).concat([Buffer.from([12]), Buffer.alloc(12)])); }
export function result(index, row = null) {
	const n = index ? 5 : 10;
	row ??= Array.from({ length: n }, (_, i) => index === 1 && i === 3 ? null : 'opaque COMMIT AND NO CHAIN NO RELEASE');
	return [Buffer.from([n]), ...Array.from({ length: n }, column), eof(), Buffer.concat(row.map(text)), eof()].map((body, i) => frame(body, i + 1));
}
export function drainPackets() { return [frame(Buffer.from('\x03' + queries[0])), ...result(0), frame(Buffer.from('\x03' + queries[1])), ...result(1), frame(Buffer.from([1]))]; }
export function drainWitness() {
	const ids = { auth: Symbol('a'), capture: Symbol('c'), writer: Symbol('w') }, root = Symbol('root');
	const config = { username: 'synthetic', database: 'fixture', startedNs: 0n, root, lifetimes: ids }, api = createDrainWitness(config);
	const receipt = Object.fromEntries(['worker_exit_zero', 'pipes_closed', 'stderr_empty', 'transport_equal', 'sessions_absent', 'fixtures_absent', 'application_unchanged', 'guards_passed'].map((key) => [key, true]));
	const send = (role, direction, bytes, now = 1n, account = true) => { const c = api.observe(ids[role], direction, bytes, now); return c && (!account || api.complete(ids[role], direction, c, now)); };
	const ready = (bind = true, now = 1n) => {
		for (const [i, role] of ['auth', 'capture', 'writer'].entries()) for (const [dir, bytes] of [['server', frame(greeting(101 + i), 0)], ['client', frame(hello(role === 'auth' ? 0x8a200 : 0x8a208), 1)], ['server', frame(ok(), 2)]]) if (!send(role, dir, bytes)) return false;
		return api.close(ids.capture, 1n) && (!bind || api.bind(ids.writer, now));
	};
	const selected = () => send('writer', 'client', frame(commit())) && send('writer', 'server', frame(ok(), 1));
	const drain = (now = 2n) => drainPackets().every((b) => send('writer', b[3] === 0 ? 'client' : 'server', b, now));
	const claim = (now = 2n) => api.claim(root, ids.writer, { passed: true, checks: 1, groups: 1 }, now);
	const finish = (now = 2n) => claim(now) && api.close(ids.writer, now) && send('auth', 'client', frame(Buffer.from([1])), now) && api.close(ids.auth, now) && api.finish(now, receipt);
	return { api, ids, root, config, receipt, send, ready, selected, drain, claim, finish };
}
