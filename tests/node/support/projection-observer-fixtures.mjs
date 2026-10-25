// Invented byte fixtures, never a native capture or actual transport.
import { createProjectionObserverOwner } from '../../../scripts/tests/projection-observer-owner.mjs';
export const identityKeys = ['attempt', 'pair', 'generation', 'request', 'session'];
export function config() {
	return { ...Object.fromEntries(identityKeys.map((key) => [key, Symbol(key)])), startedNs: 100n, seed: '012345abcdef',
		schema: 'local', name: 'sidebars_widgets', catalog: 'a066782b53b105be3386bc2f186b90a59c262cfe47b9bccdd07a180cf62828e0',
		protocol: 'projection-text41-ordinary-eof-v1', capabilities: 0x00088209 };
}
export function frame(body, sequence) { const h = Buffer.alloc(4); h.writeUIntLE(body.length, 0, 3); h[3] = sequence; return Buffer.concat([h, body]); }
export function packets() {
	const column = Buffer.from('03646566056c6f63616c2077705f616c796e745f726f5f3031323334356162636465665f6f7074696f6e732077705f616c796e745f726f5f3031323334356162636465665f6f7074696f6e730c6f7074696f6e5f76616c75650c6f7074696f6e5f76616c75650c2d00fffffffffb1100000000', 'hex');
	return [Buffer.from("\x03SELECT option_value FROM wp_alynt_ro_012345abcdef_options WHERE option_name = 'sidebars_widgets' LIMIT 1"),
		Buffer.from([1]), column, Buffer.from([254, 0, 0, 2, 0]), Buffer.from([254, 0, 0, 2, 0])].map(frame);
}
export function sessionFrames() {
	const fixed = Buffer.alloc(31); fixed.writeUInt32LE(103); fixed.fill(65, 4, 12); fixed.writeUInt16LE(0xa209, 13);
	fixed[15] = 45; fixed.writeUInt16LE(2, 16); fixed.writeUInt16LE(8, 18); fixed[20] = 21;
	const hello = Buffer.alloc(32); hello.writeUInt32LE(0x88209); hello.writeUInt32LE(16384, 4); hello[8] = 45;
	return [Buffer.concat([Buffer.from('\x0a5.5.5-10.6.23-MariaDB\0'), fixed, Buffer.from('BBBBBBBBBBBB\0mysql_native_password\0')]),
		Buffer.concat([hello, Buffer.from('alynt_ro_012345abcdef_i\0'), Buffer.from([20]), Buffer.alloc(20, 67), Buffer.from('local\0mysql_native_password\0')]),
		Buffer.from([0, 0, 0, 2, 0, 0, 0])].map(frame);
}
export function fixture(factory = createProjectionObserverOwner, bind = true) {
	const c = config(), identity = Object.fromEntries(identityKeys.map((key) => [key, c[key]])), owner = factory(c);
	if (bind && !owner.bindSession(sessionFrames(), identity, 100n)) throw Error('synthetic fixture session refused');
	const capture = (index, chunk = packets()[index], now = 101n) => owner.capture(index ? 'server' : 'client', chunk, identity, now);
	const send = (index, chunk = packets()[index], now = 101n) => { const credit = capture(index, chunk, now); return typeof credit === 'symbol' && owner.forwarded(credit, chunk, identity, now) && owner.observe(credit, identity, now); };
	return { c, identity, owner, capture, send, finish: (now = 101n) => {
		owner.end('client', identity, now); owner.end('server', identity, now); return owner.finish(identity, now);
	} };
}
