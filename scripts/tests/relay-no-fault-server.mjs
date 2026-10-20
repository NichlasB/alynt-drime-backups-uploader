// Owned no-fault loopback transport only. Not imported by plugin runtime or CI native tests.
import net from 'node:net';
import { once } from 'node:events';
import { frameStream, handshakeGate } from './relay-no-fault-frames.mjs';

export async function openNoFaultRelay(backend) {
	if (!Number.isInteger(backend) || backend < 1 || backend > 65535) throw Error('backend');
	let accepted = 0, total = 0, failed = false, stopping = false, queued = 0, failure = null;
	const live = new Set(), records = [], jobs = [];
	const server = net.createServer({ allowHalfOpen: true }, (client) => {
		if (stopping || ++accepted > 8 || live.size >= 4) { failed = true; client.destroy(); return; }
		const upstream = net.createConnection({ host: '127.0.0.1', port: backend, allowHalfOpen: true });
		const pair = { client, upstream }; live.add(pair);
		const gate = handshakeGate(), record = { streams: [], thread: null, authenticated: false, quit: false, frames: 0 }; records.push(record);
		const fail = (label = 'socket_failure') => { failed = true; failure ??= typeof label === 'string' && /^[a-z_]{1,40}$/.test(label) ? label : 'socket_failure'; client.destroy(); upstream.destroy(); };
		const quitClose = (error) => record.quit && ['ECONNRESET', 'ERR_STREAM_PREMATURE_CLOSE'].includes(error.code);
		const socketError = (error) => { if (!quitClose(error)) fail(typeof error.code === 'string' && /^[A-Z_]{1,24}$/.test(error.code) ? 'socket_' + error.code.toLowerCase() : 'socket_error'); };
		client.on('error', socketError); upstream.on('error', socketError);
		for (const socket of [client, upstream]) { socket.setNoDelay(true); socket.setTimeout(6000, fail); }
		const pump = async (source, target, direction) => {
			const parser = frameStream(async (frame) => {
				gate.inspect(direction, frame); total += frame.length;
				if (total > 64 * 1024 * 1024 || ++record.frames > 4096) throw Error('attempt_or_pair_cap');
				await new Promise((resolve, reject) => target.write(frame, (error) => error ? reject(Error('write')) : resolve()));
				if (direction === 'client' && gate.identity() !== null && frame.length === 5 && frame[3] === 0 && frame[4] === 1) record.quit = true;
			}, (size) => { if (queued + size > 8 * 1024 * 1024) throw Error('queue_cap'); queued += size; }, (size) => { queued -= size; });
			try { for await (const chunk of source.iterator({ destroyOnReturn: false })) await parser.feed(chunk); if (!target.destroyed && !target.writableEnded) target.end(); }
			catch (error) { if (!quitClose(error)) { failure ??= /^[a-z_]{1,40}$/.test(error.message) ? error.message : 'transport_failure'; fail(); } }
			finally { record.streams.push(parser.finish()); }
		};
		jobs.push((async () => {
			try { await once(upstream, 'connect'); await Promise.all([pump(client, upstream, 'client'), pump(upstream, client, 'server')]); }
			catch { fail(); }
			finally { record.thread = gate.cleanupIdentity(); record.authenticated = gate.identity() !== null; client.destroy(); upstream.destroy(); live.delete(pair); }
		})());
	});
	server.on('error', () => { failed = true; });
	server.listen({ host: '127.0.0.1', port: 0, exclusive: true }); await once(server, 'listening');
	const port = server.address().port;
	if (port === backend) { server.close(); throw Error('listener_identity'); }
	return Object.freeze({
		port,
		async close() {
			stopping = true;
			const closed = new Promise((resolve) => server.close(resolve));
			// Worker has exited. Only this listener's sockets are ever closed.
			for (const pair of live) { pair.client.end(); pair.upstream.end(); }
			await Promise.all(jobs); await closed;
			const threads = records.map((row) => row.thread);
			return { passed: !failed && queued === 0 && live.size === 0 && accepted >= 3 && accepted <= 8 && threads.every(Number.isInteger) && new Set(threads).size === threads.length && records.every((row) => row.authenticated && row.streams.length === 2 && row.streams.every((s) => s.complete && s.equal && s.bytes > 0)), pairs: records.length, authenticated_pairs: records.filter((r) => r.authenticated).length, complete_streams: records.flatMap((r) => r.streams).filter((s) => s.complete).length, equal_streams: records.flatMap((r) => r.streams).filter((s) => s.equal).length, quit_pairs: records.filter((r) => r.quit).length, bytes: total, threads, listener_closed: !server.listening, live_pairs: live.size, queued_bytes: queued, failure, suppression_supported: false };
		},
	});
}
