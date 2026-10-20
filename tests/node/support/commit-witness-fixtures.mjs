// Entirely invented protocol bytes, never imported from a database or authentication session.
import { createCommitWitnessDecoder } from '../../../scripts/tests/commit-witness-decoder.mjs';
const CLIENT_PROFILE = 0x0008a208; // Independent expected bytes, not imported from the decoder.

export function frame(payload, seq) {
	const h = Buffer.alloc(4); h.writeUIntLE(payload.length, 0, 3); h[3] = seq;
	return Buffer.concat([h, payload]);
}
export function serverHello(thread = 103) {
	const fixed = Buffer.alloc(31); fixed.writeUInt32LE(thread, 0); fixed.fill(65, 4, 12);
	fixed.writeUInt16LE(CLIENT_PROFILE & 0xffff, 13); fixed[15] = 45; fixed.writeUInt16LE(2, 16);
	fixed.writeUInt16LE(CLIENT_PROFILE >>> 16, 18); fixed[20] = 21;
	return Buffer.concat([Buffer.from('\x0a5.5.5-10.6.23-MariaDB\0'), fixed, Buffer.from('BBBBBBBBBBBB\0mysql_native_password\0')]);
}
export function clientResponse() {
	const fixed = Buffer.alloc(32); fixed.writeUInt32LE(CLIENT_PROFILE); fixed.writeUInt32LE(2 ** 21, 4); fixed[8] = 45;
	return Buffer.concat([fixed, Buffer.from('synthetic\0'), Buffer.from([20]), Buffer.alloc(20, 67), Buffer.from('fixture\0mysql_native_password\0')]);
}
export const ok = () => Buffer.from([0, 0, 0, 2, 0, 0, 0]);
export const command = () => Buffer.from('\x03COMMIT AND NO CHAIN NO RELEASE');
export function fixture(overrides = {}) {
	const config = { attempt: Symbol('attempt'), lifetime: Symbol('pair'), startedNs: 0n, authThread: 101, captureThread: 102, database: 'fixture', ...overrides };
	const d = createCommitWitnessDecoder(config);
	const send = (dir, bytes, now = 1n) => d.feed(dir, bytes, config.attempt, config.lifetime, now);
	const arm = (id = 103, now = 1n) => d.arm(config.attempt, config.lifetime, id, now);
	const finish = (now = 1n) => d.finish(config.attempt, config.lifetime, now);
	const handshake = () => send('server', frame(serverHello(), 0)) && send('client', frame(clientResponse(), 1)) && send('server', frame(ok(), 2));
	const transcript = () => handshake() && arm() && send('client', frame(command(), 0)) && send('server', frame(ok(), 1));
	return { config, d, send, arm, finish, handshake, transcript };
}
