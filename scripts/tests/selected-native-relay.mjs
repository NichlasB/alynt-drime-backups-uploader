// Dedicated owned relay. No fault selector in request, argv or environment.
import net from 'node:net';
import { once } from 'node:events';
import { selectedNativeFrames } from './selected-native-frames.mjs';
import { handshakeGate } from './relay-no-fault-frames.mjs';
import { closeSelectedTransport } from './selected-native-lifetime.mjs';
export const openSelectedNativeRelay = (backend, registry) => open(backend, registry, true);
export const openSelectedNoFaultRelay = (backend, registry) => open(backend, registry, false);
async function open(backend, registry, loss) {
	if (backend !== 10004 || !registry) throw Error('fixed_backend');
	const live = new Set(), records = [], jobs = []; let stopping = false, failed = false, accepted = 0, queued = 0, total = 0, closed = null;
	const server = net.createServer({ allowHalfOpen: true }, (client) => {
		if (stopping || ++accepted > 8 || live.size >= 4) { failed = true; client.destroy(); return; }
		const upstream = net.createConnection({ host: '127.0.0.1', port: backend, allowHalfOpen: true }), id = Symbol('owned_pair');
		const pair = { client, upstream }, gate = handshakeGate(), row = { thread: null, streams: [], cut: false, quit: false, attempts: 0, completions: 0 }; records.push(row); live.add(pair);
		if (!registry.open(id)) { failed = true; client.destroy(); upstream.destroy(); }
		const parsers = {}, fail = () => { failed = true; registry.unavailable(); client.destroy(); upstream.destroy(); };
		const quitError = (error) => row.quit && ['ECONNRESET', 'ERR_STREAM_PREMATURE_CLOSE'].includes(error.code);
		for (const socket of [client, upstream]) { socket.setNoDelay(true); socket.setTimeout(6000, fail); socket.on('error', (error) => { if (!row.cut && !quitError(error)) fail(); }); }
		const pump = async (source, target, direction) => {
			const parser = selectedNativeFrames(async (frame, tail) => {
				gate.inspect(direction, frame); total += frame.length; if (total > 67108864) throw Error('attempt_cap');
				const credit = Symbol('frame'), selected = registry.frame(id, direction, frame, credit, tail);
				if (loss && selected === 'response' && registry.cut(id, credit)) {
					row.cut = true; client.destroy(); upstream.destroy(); return 'withheld';
				}
				if (row.cut) throw Error('post_cut_write');
				if (selected === 'response') row.attempts++;
				await new Promise((resolve, reject) => target.write(frame, (error) => error ? reject(Error('write')) : resolve()));
				if (selected === 'response') row.completions++;
				if (!registry.account(id, direction, credit)) throw Error('account');
				if (direction === 'client' && frame.length === 5 && frame[3] === 0 && frame[4] === 1) row.quit = true;
				return 'forwarded';
			}, (n) => { if (queued + n > 8388608) throw Error('queue_cap'); queued += n; }, (n) => { queued -= n; }, (size) => loss ? registry.cap(id, direction, size) : 2097152);
			parsers[direction] = parser;
			try { for await (const bytes of source.iterator({ destroyOnReturn: false })) await parser.feed(bytes); if (!row.cut && !target.destroyed && !target.writableEnded) target.end(); }
			catch (error) { if ((!row.cut && !quitError(error)) || !parser.boundary()) fail(); }
			finally { row.streams.push(parser.finish()); }
		};
		jobs.push((async () => {
			try { await once(upstream, 'connect'); await Promise.all([pump(client, upstream, 'client'), pump(upstream, client, 'server')]); }
			catch { fail(); }
			finally { row.thread = gate.cleanupIdentity(); registry.close(id); client.destroy(); upstream.destroy(); live.delete(pair); }
		})());
		registry.boundary(id, (direction) => parsers[direction]?.boundary() ?? false);
	});
	server.on('error', () => { failed = true; }); server.listen({ host: '127.0.0.1', port: 0, exclusive: true }); await once(server, 'listening');
	return Object.freeze({
		port: server.address().port,
		close() {
			if (closed) return closed;
			closed = (async () => {
				stopping = true; const joined = await closeSelectedTransport(server, live, jobs);
				const streams = records.flatMap((r) => r.streams), cuts = records.filter((r) => r.cut);
				return { passed: joined.resolved && !failed && live.size === 0 && queued === 0 && records.length === 3 && records.every((r) => Number.isInteger(r.thread) && r.streams.length === 2) && new Set(records.map((r) => r.thread)).size === 3 &&
					streams.every((s) => s.complete && s.conserved && s.bytes > 0) && cuts.length === (loss ? 1 : 0) && streams.filter((s) => s.withheld === 11).length === (loss ? 1 : 0) && (!loss || cuts[0].attempts === 0 && cuts[0].completions === 0),
					cleanup_resolved: joined.resolved, graceful_pumps: joined.graceful, listener_closed: joined.listener_closed, live_pairs: live.size, queued_bytes: queued,
					pairs: records.length, streams: streams.length, conserved_streams: streams.filter((s) => s.complete && s.conserved).length,
					withheld_bytes: streams.reduce((n, s) => n + s.withheld, 0), target_write_attempts: records.reduce((n, r) => n + r.attempts, 0),
					target_write_completions: records.reduce((n, r) => n + r.completions, 0), bytes: total, threads: records.map((r) => r.thread) };
			})(); return closed;
		},
	});
}
