// Dedicated transparent relay: no response-discard capability or fault-mode argument.
import net from 'node:net';
import { once } from 'node:events';
import { frameStream, handshakeGate } from './relay-no-fault-frames.mjs';
import { passiveObservation } from './observation-passive-sink.mjs';
import { closeSelectedTransport } from './selected-native-lifetime.mjs';
import { createQualificationStages } from './qualification-native-stages.mjs';
export async function openNativeQualificationRelay(backend, observer) {
	if (backend !== 10004 || !observer) throw Error('qualification_backend');
	const live = new Set(), records = [], jobs = [], sink = passiveObservation(observer);
	let failed = false, stopping = false, queued = 0, total = 0, accepted = 0, closed = null;
	const server = net.createServer({ allowHalfOpen: true }, (client) => {
		if (stopping || ++accepted > 8 || live.size >= 4) { failed = true; client.destroy(); return; }
		const upstream = net.createConnection({ host: '127.0.0.1', port: backend, allowHalfOpen: true }), pair = { client, upstream }, id = Symbol('qualification_pair');
		const gate = handshakeGate(), row = { thread: null, streams: [], quit: false, frames: 0, stages: createQualificationStages() }, parsers = {}; records.push(row); live.add(pair);
		const stageSink = passiveObservation(row.stages);
		const observe = (method, ...args) => sink.call(method, id, ...args);
		observe('open');
		const fail = (label = 'socket_error') => { stageSink.call('failure', label); failed = true; client.destroy(); upstream.destroy(); };
		const quitError = (e) => row.quit && ['ECONNRESET', 'ERR_STREAM_PREMATURE_CLOSE'].includes(e.code);
		for (const socket of [client, upstream]) { socket.setNoDelay(true); socket.setTimeout(6000, () => fail('socket_error')); socket.on('error', (e) => { if (!quitError(e)) fail(e.code === 'ECONNRESET' ? 'connection_reset' : 'socket_error'); }); }
		const pump = async (source, target, direction) => {
			const parser = frameStream(async (frame) => {
				stageSink.call('frame', direction, frame);
				gate.inspect(direction, frame); total += frame.length;
				if (total > 67108864 || ++row.frames > 4096) throw Error('qualification_transport_cap');
				const credit = Symbol('qualified_forward'); observe('frame', direction, frame, credit);
				await new Promise((resolve, reject) => target.write(frame, (e) => e ? reject(Error('qualification_write')) : resolve()));
				observe('account', direction, credit);
				stageSink.call('account', direction, frame);
				if (direction === 'client' && gate.identity() !== null && frame.length === 5 && frame[3] === 0 && frame[4] === 1) row.quit = true;
			}, (n) => { if (queued + n > 8388608) throw Error('qualification_queue'); queued += n; }, (n) => { queued -= n; }, (n) => observe('header', direction, n));
			parsers[direction] = parser;
			try { for await (const bytes of source.iterator({ destroyOnReturn: false })) await parser.feed(bytes); if (!target.destroyed && !target.writableEnded) target.end(); }
			catch (e) { if (!quitError(e) || !parser.boundary()) fail(e.code === 'ERR_STREAM_PREMATURE_CLOSE' ? 'premature_close' : e.message); }
			finally { row.streams.push(parser.finish()); }
		};
		jobs.push((async () => {
			try { await once(upstream, 'connect'); await Promise.all([pump(client, upstream, 'client'), pump(upstream, client, 'server')]); }
			catch { fail('socket_error'); }
			finally { row.thread = gate.cleanupIdentity(); observe('close'); client.destroy(); upstream.destroy(); live.delete(pair); }
		})());
		observe('boundary', (direction) => parsers[direction]?.boundary() ?? false);
	});
	server.on('error', () => { failed = true; }); server.listen({ host: '127.0.0.1', port: 0, exclusive: true }); await once(server, 'listening');
	return Object.freeze({ port: server.address().port, close() {
		if (closed) return closed;
		closed = (async () => {
			stopping = true; const join = await closeSelectedTransport(server, live, jobs), streams = records.flatMap((r) => r.streams), threads = records.map((r) => r.thread);
			return { passed: join.resolved && !failed && queued === 0 && live.size === 0 && records.length === 4 && threads.every(Number.isInteger) && new Set(threads).size === 4 && streams.length === 8 && streams.every((s) => s.complete && s.equal && s.bytes > 0),
				cleanup_resolved: join.resolved, listener_closed: join.listener_closed, live_pairs: live.size, queued_bytes: queued, pairs: records.length, equal_streams: streams.filter((s) => s.complete && s.equal).length, threads, observation_failed: sink.failed(), suppression_supported: false, pre_begin_diagnostics: records.map((r) => r.stages.summary()) };
		})(); return closed;
	} });
}
