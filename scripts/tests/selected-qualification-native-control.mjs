// Fixed private selected child handoff, never a source classifier or response-loss selector.
import { randomBytes } from 'node:crypto';
import { createOwnedStdioLines } from './owned-stdio-lines.mjs';
import { boundedSettlement } from './selected-native-lifetime.mjs';
import { parseSelectedLine, selectedDomain as domain, selectedProfile as profile } from './selected-qualification-grammar.mjs';
export function ownSelectedQualificationControl(child, registry, source) {
	const attempt = randomBytes(32).toString('hex'), root = randomBytes(32).toString('hex'), pair = randomBytes(32).toString('hex');
	if (new Set([attempt, root, pair]).size !== 3) throw Error('qualification_collision');
	let failed = false, stage = 0, total = 0, pending = Promise.resolve(), rootAt = null, bindAt = null, final = null, eof = false;
	const fail = () => { failed = true; child.stdin.destroy(); return false; };
	const send = async (kind, seq, more) => {
		const m = { domain, v: 1, kind, seq, attempt, ...more }, line = JSON.stringify(m);
		if (!parseSelectedLine(line, source)) throw Error('qualification_outbound');
		const bytes = Buffer.from(line + '\n', 'ascii'); total += bytes.length;
		if (failed || total > 32768 || bytes.length > 4096) throw Error('qualification_byte_cap');
		for (let i = 0; i < bytes.length; i += 3) {
			const written = await boundedSettlement(new Promise((resolve, reject) => child.stdin.write(bytes.subarray(i, i + 3), (e) => e ? reject(Error('write')) : resolve(true))), 500);
			if (!written.settled || written.rejected || failed) throw Error('qualification_write');
		}
	};
	const queue = (f) => { pending = pending.then(f).catch(fail); };
	const parser = createOwnedStdioLines((line) => {
		const m = parseSelectedLine(line, source); if (!m || m.attempt !== attempt) return fail();
		if (stage === 0 && m.kind === 'root_request') {
			stage = 1; queue(async () => { rootAt = process.hrtime.bigint(); if (!registry.root(rootAt)) throw Error('root'); await send('root', 2, { root }); }); return true;
		}
		if (stage === 1 && m.kind === 'bind' && m.root === root && rootAt !== null) {
			stage = 2; bindAt = process.hrtime.bigint();
			queue(async () => {
				if (bindAt - rootAt > 4000000000n || process.hrtime.bigint() - bindAt > 1000000000n || !registry.bind({ auth: m.auth, capture: m.capture, writer: m.writer })) throw Error('bind');
				await send('bound', 3, { root, writer: m.writer, pair });
				if (process.hrtime.bigint() - bindAt > 1000000000n || process.hrtime.bigint() - rootAt > 4000000000n) fail();
			}); return true;
		}
		if (stage === 2 && m.kind === 'final' && m.root === root && m.pair === pair && rootAt !== null && process.hrtime.bigint() - rootAt < 10000000000n && registry.claim()) {
			stage = 3; final = m; queue(async () => child.stdin.end()); return true;
		}
		return fail();
	});
	child.stdout.once('end', () => { eof = true; });
	child.stdin.on('error', fail); child.stdout.on('error', fail);
	child.stdout.on('data', (chunk) => { total += chunk.length; if (failed || total > 32768 || !parser.feed(chunk)) fail(); });
	queue(() => send('boot', 1, { profile }));
	return Object.freeze({ abort: fail, async finish() {
		const joined = await boundedSettlement(pending, 750), complete = parser.end();
		const result = { passed: joined.settled && !joined.rejected && !failed && stage === 3 && complete && eof, pending_settled: joined.settled, channel_bytes: total,
			root_returned: final?.root_returned ?? false, pre_guards: final?.pre_guards ?? 0, post_guards: final?.post_guards ?? 0, protected_reads: final?.protected_reads ?? 0, writes: final?.writes ?? 0, original_slots_empty: final?.original_slots_empty ?? false,
			qualifier_entries: final?.observation.entries ?? 0, qualifier_returns: final?.observation.returns ?? 0, historical_outcome: final?.observation.result.historical_outcome ?? null, current_acceptance_claimed: final?.observation.result.current_acceptance_claimed ?? null, client_release_claimed: final?.observation.result.client_release_claimed ?? null, private_tokens_exported: false };
		final = null; return result;
	} });
}
