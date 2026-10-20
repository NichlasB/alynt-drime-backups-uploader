// Owned ephemeral listener only, no WordPress/SQL/backend connection or response loss.
import net from 'node:net';
import { once } from 'node:events';
import assert from 'node:assert/strict';
import { boundedSettlement, closeSelectedTransport } from './selected-native-lifetime.mjs';
export async function selectedCleanupProof() {
	let checks = 0;
	const check = (actual, expected) => { assert.equal(actual, expected); checks++; };
	check((await boundedSettlement(Promise.resolve(true), 50)).value, true);
	check((await boundedSettlement(Promise.reject(Error('owned_refusal')), 50)).rejected, true);
	check((await boundedSettlement(new Promise(() => {}), 20)).settled, false);
	const live = new Set(), server = net.createServer(); server.listen({ host: '127.0.0.1', port: 0, exclusive: true }); await once(server, 'listening');
	let client = null, peer = null;
	try {
		const accepted = once(server, 'connection'); client = net.createConnection({ host: '127.0.0.1', port: server.address().port });
		client.on('error', () => {}); [peer] = await accepted; peer.on('error', () => {}); await once(client, 'connect');
		const pair = { client, upstream: peer }; live.add(pair);
		const closes = Promise.all([once(client, 'close'), once(peer, 'close')]).then(() => live.delete(pair));
		const started = process.hrtime.bigint(); const result = await closeSelectedTransport(server, live, [new Promise(() => {})]);
		check(result.resolved, false); check(result.graceful, false); check(result.listener_closed, true);
		check(process.hrtime.bigint() - started < 2500000000n, true); check((await boundedSettlement(closes, 250)).settled, true); check(live.size, 0);
		check(client.destroyed && peer.destroyed, true);
		return Object.freeze({ passed: true, checks, groups: 2, unresolved_job_remains_unresolved: true, owned_sockets_closed: true, sql_or_wordpress: false });
	} finally { client?.destroy(); peer?.destroy(); if (server.listening) server.close(); }
}
