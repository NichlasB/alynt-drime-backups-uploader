// Private v2 inherited channel only; no runtime API or request-controlled loss selector.
import { randomBytes } from 'node:crypto';
import { createOwnedStdioLines } from './owned-stdio-lines.mjs';
import { validThread } from './native-witness-profile.mjs';
import { lossSummary } from './selected-loss-contract.mjs';
import { boundedSettlement } from './selected-native-lifetime.mjs';
export function ownSelectedNativeControl(child, registry) {
	const attempt = randomBytes(32).toString('hex'), root = randomBytes(32).toString('hex'), pair = randomBytes(32).toString('hex');
	if (new Set([attempt, root, pair]).size !== 3) throw Error('token_collision');
	let failed = false, stage = 0, total = 0, pending = Promise.resolve(), rootAt = null, bindAt = null, final = null;
	const fail = () => { failed = true; child.stdin.destroy(); return false; };
	const send = async (value) => {
		const bytes = Buffer.from(JSON.stringify(value) + '\n', 'ascii'); total += bytes.length;
		if (failed || total > 32768 || bytes.length > 4096) throw Error('control_budget');
		for (let i = 0; i < bytes.length; i += 3) {
			const written = await boundedSettlement(new Promise((resolve, reject) => child.stdin.write(bytes.subarray(i, i + 3), (error) => error ? reject(Error('private_write')) : resolve(true))), 500);
			if (!written.settled || written.rejected || failed) throw Error('private_write_unresolved');
		}
	};
	const queue = (job) => { pending = pending.then(job).catch(fail); };
	const exact = (m, line, keys, kind, seq) => m && Object.getPrototypeOf(m) === Object.prototype && Object.keys(m).join(',') === keys && JSON.stringify(m) === line && m.v === 2 && m.kind === kind && m.seq === seq && m.attempt === attempt;
	const parser = createOwnedStdioLines((line) => {
		let m; try { m = JSON.parse(line); } catch { return fail(); }
		if (stage === 0 && exact(m, line, 'v,kind,seq,attempt', 'root_request', 1)) {
			stage = 1; queue(async () => { rootAt = process.hrtime.bigint(); if (!registry.root(rootAt)) throw Error('root'); await send({ v: 2, kind: 'root', seq: 2, attempt, root }); }); return true;
		}
		if (stage === 1 && exact(m, line, 'v,kind,seq,attempt,root,auth,capture,writer,remaining_ns', 'bind', 2) && m.root === root &&
			[m.auth, m.capture, m.writer].every(validThread) && new Set([m.auth, m.capture, m.writer]).size === 3 && typeof m.remaining_ns === 'string' && /^[1-9][0-9]{0,10}$/.test(m.remaining_ns) && BigInt(m.remaining_ns) >= 6000000000n && BigInt(m.remaining_ns) <= 10000000000n && rootAt !== null) {
			stage = 2; bindAt = process.hrtime.bigint();
			queue(async () => { if (bindAt - rootAt > 4000000000n || process.hrtime.bigint() - bindAt > 1000000000n || !registry.bind({ auth: m.auth, capture: m.capture, writer: m.writer })) throw Error('bind');
				await send({ v: 2, kind: 'bound', seq: 3, attempt, root, writer: m.writer, pair }); if (process.hrtime.bigint() - bindAt > 1000000000n || process.hrtime.bigint() - rootAt > 4000000000n) fail(); }); return true;
		}
		if (stage === 2 && exact(m, line, 'v,kind,seq,attempt,root,pair,case,checks,groups,returned_false,pre_guards,post_guards,root_erased', 'final', 3) && m.root === root && m.pair === pair && rootAt !== null && process.hrtime.bigint() - rootAt < 10000000000n) {
			const summary = Object.fromEntries(Object.entries(m).slice(6)); if (!lossSummary(summary) || !registry.claim()) return fail();
			stage = 3; final = summary; queue(async () => child.stdin.end()); return true;
		}
		return fail();
	});
	child.stdin.on('error', fail); child.stdout.on('error', fail);
	child.stdout.on('data', (chunk) => { total += chunk.length; if (failed || total > 32768 || !parser.feed(chunk)) fail(); });
	queue(() => send({ v: 2, kind: 'boot', seq: 1, attempt, intent: 'selected_commit_response_loss' }));
	return Object.freeze({
		abort: fail,
		async finish() {
			const joined = await boundedSettlement(pending, 750), complete = parser.end();
			return { passed: joined.settled && !joined.rejected && !failed && stage === 3 && complete, pending_settled: joined.settled, channel_bytes: total,
				checks: final?.checks ?? 0, groups: final?.groups ?? 0, private_tokens_exported: false };
		},
	});
}
