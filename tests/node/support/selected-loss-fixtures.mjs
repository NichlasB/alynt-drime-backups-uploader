// Independent invented bytes and receipts. Never invokes WordPress or a transport.
import { createSelectedLossWitness } from '../../../scripts/tests/selected-loss-witness.mjs';
import { createSelectedLossControl } from '../../../scripts/tests/selected-loss-control.mjs';
import { frame, greeting, hello, ok, commit } from './native-witness-fixtures.mjs';
export { frame, greeting, hello, ok, commit };
export const summary = () => ({ case: 'selected_commit_response_loss', checks: 242, groups: 2, returned_false: true, pre_guards: 8, post_guards: 0, root_erased: true });
export const receipt = () => ({ worker_exit_zero: true, pipes_closed: true, stderr_empty: true, listener_closed: true, sessions_absent: true,
	locks_available: true, fixtures_absent: true, application_unchanged: true, ledger_matches: true, other_rows_unchanged: true, guards_passed: true });
export function lossFixture(factory = createSelectedLossWitness) {
	const ids = { auth: Symbol('auth'), capture: Symbol('capture'), writer: Symbol('writer') }, root = Symbol('root');
	const config = { username: 'synthetic', database: 'fixture', startedNs: 0n, root, lifetimes: ids }, api = factory(config);
	const feed = (role, dir, body, seq = 0, now = 1n) => api.feed(ids[role], dir, frame(body, seq), now);
	const forward = (role, dir, bytes, key, now = 1n) => api.attempt(ids[role], dir, key, bytes, now) && api.complete(ids[role], dir, key, now);
	const send = (role, dir, body, seq = 0, now = 1n) => { const bytes = frame(body, seq), keys = api.feed(ids[role], dir, bytes, now); return keys && forward(role, dir, bytes, keys[0], now); };
	const ready = (now = 1n, bind = true) => {
		for (const [i, role] of ['auth', 'capture', 'writer'].entries()) {
			if (!send(role, 'server', greeting(101 + i)) || !send(role, 'client', hello(role === 'auth' ? 0x8a200 : 0x8a208), 1) || !send(role, 'server', ok(), 2)) return false;
		}
		return api.close(ids.capture, 1n) && (!bind || api.bind(root, ids.writer, now));
	};
	const select = (now = 2n) => {
		if (!send('writer', 'client', commit(), 0, now)) return false;
		const keys = feed('writer', 'server', ok(), 1, now);
		return keys && api.decide(root, ids.writer, keys[0], now);
	};
	const complete = (now = 3n, claims = receipt()) => api.claim(root, ids.writer, summary(), now) && api.close(ids.writer, now) && api.close(ids.auth, now) && api.finish(now, claims);
	return { api, ids, root, config, feed, forward, send, ready, select, complete };
}
export function lossControl(factory = createSelectedLossControl) {
	const attempt = 'a'.repeat(64), root = 'b'.repeat(64), pair = 'c'.repeat(64);
	const roles = Object.fromEntries(['auth', 'capture', 'writer'].map((r, i) => [r, { thread: 101 + i, identity: Symbol(r) }]));
	const config = { attempt, root, pair, roles }, api = factory(config);
	const messages = [
		{ v: 2, kind: 'boot', seq: 1, attempt, intent: 'selected_commit_response_loss' }, { v: 2, kind: 'root_request', seq: 1, attempt },
		{ v: 2, kind: 'root', seq: 2, attempt, root }, { v: 2, kind: 'bind', seq: 2, attempt, root, auth: 101, capture: 102, writer: 103, remaining_ns: '10000000000' },
		{ v: 2, kind: 'bound', seq: 3, attempt, root, writer: 103, pair }, { v: 2, kind: 'final', seq: 3, attempt, root, pair, ...summary() },
	];
	const wire = (i) => Buffer.from(`${JSON.stringify(messages[i])}\n`, 'ascii');
	const send = (i, n = 0n, w = 900000000000n, bytes = wire(i), id = roles.writer.identity) => api.feed(i % 2 ? 'worker' : 'node', bytes, n, w, id);
	const finish = (n = 0n, w = 900000000000n) => api.finish(n, w, roles.writer.identity);
	return { api, config, roles, messages, wire, send, finish };
}
