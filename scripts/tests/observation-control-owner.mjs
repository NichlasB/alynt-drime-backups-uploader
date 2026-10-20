// Private inherited-pipe owner. Tokens and thread IDs never leave this module's channel.
import { randomBytes } from 'node:crypto';
import { createOwnedStdioLines } from './owned-stdio-lines.mjs';
import { validThread } from './native-witness-profile.mjs';

export function ownObservationControl(child, registry) {
	const attempt = randomBytes(32).toString('hex'), root = randomBytes(32).toString('hex'), pair = randomBytes(32).toString('hex');
	if (new Set([attempt, root, pair]).size !== 3) throw Error('token_collision');
	let stage = 0, bytes = 0, failed = false, rootAt = null, bindAt = null, pending = Promise.resolve(), final = null, inputWrites = 0;
	const fail = () => { failed = true; child.stdin.end(); return false; };
	const queue = (job) => { pending = pending.then(job).catch(fail); };
	const send = async (message) => {
		const data = Buffer.from(JSON.stringify(message) + '\n', 'ascii'); bytes += data.length;
		if (failed || bytes > 32768 || data.length > 4096) throw Error('channel_cap');
		for (let at = 0; at < data.length; at += 3) { inputWrites++; await new Promise((resolve, reject) => child.stdin.write(data.subarray(at, at + 3), (error) => error ? reject(Error('pipe_write')) : resolve())); }
	};
	const exact = (m, line, keys, kind, seq) => m && Object.getPrototypeOf(m) === Object.prototype && Object.keys(m).join(',') === keys && JSON.stringify(m) === line && m.v === 1 && m.kind === kind && m.seq === seq && m.attempt === attempt;
	const parser = createOwnedStdioLines((line) => {
		let m; try { m = JSON.parse(line); } catch { return fail(); }
		if (stage === 0 && exact(m, line, 'v,kind,seq,attempt', 'root_request', 1)) {
			stage = 1;
			queue(async () => { rootAt = process.hrtime.bigint(); if (!registry.root(rootAt)) throw Error('root'); await send({ v: 1, kind: 'root', seq: 2, attempt, root }); }); return true;
		}
		if (stage === 1 && exact(m, line, 'v,kind,seq,attempt,root,auth,capture,writer,remaining_ns', 'bind', 2) && m.root === root &&
			[m.auth, m.capture, m.writer].every(validThread) && new Set([m.auth, m.capture, m.writer]).size === 3 && typeof m.remaining_ns === 'string' && /^[1-9][0-9]{0,10}$/.test(m.remaining_ns) && BigInt(m.remaining_ns) >= 6000000000n && BigInt(m.remaining_ns) <= 10000000000n && rootAt !== null) {
			stage = 2; bindAt = process.hrtime.bigint();
			queue(async () => {
				const now = process.hrtime.bigint();
				if (now < rootAt || now - rootAt > 4000000000n || now < bindAt || now - bindAt > 1000000000n || !registry.bind({ auth: m.auth, capture: m.capture, writer: m.writer })) {
					await send({ v: 1, kind: 'rejected', seq: 3, attempt, root, reason: 'unavailable' }); fail(); return;
				}
				await send({ v: 1, kind: 'bound', seq: 3, attempt, root, writer: m.writer, pair });
				const done = process.hrtime.bigint(); if (done < bindAt || done - bindAt > 1000000000n || done - rootAt > 4000000000n) fail();
			}); return true;
		}
		if (stage === 2 && exact(m, line, 'v,kind,seq,attempt,root,pair,passed,checks,groups', 'final', 3) && m.root === root && m.pair === pair && m.passed === true &&
			Number.isInteger(m.checks) && m.checks > 0 && m.checks <= 8192 && Number.isInteger(m.groups) && m.groups > 0 && m.groups <= 32 && rootAt !== null && process.hrtime.bigint() - rootAt < 10000000000n) {
			if (!registry.claim()) return fail();
			stage = 3; final = { checks: m.checks, groups: m.groups }; queue(async () => child.stdin.end()); return true;
		}
		return fail();
	});
	child.stdin.on('error', fail);
	child.stdout.on('error', fail);
	child.stdout.on('data', (chunk) => { bytes += chunk.length; if (bytes > 32768 || failed || !parser.feed(chunk)) fail(); });
	queue(() => send({ v: 1, kind: 'boot', seq: 1, attempt, intent: 'observe_only' }));
	return Object.freeze({ async finish() { await pending; const complete = parser.end(); return { passed: !failed && stage === 3 && complete, ...(final ?? {}), channel_bytes: bytes, small_input_writes: inputWrites, original_root_bound: stage === 3, private_tokens_exported: false }; } });
}
