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
export function fixture(factory = createProjectionObserverOwner) {
	const c = config(), identity = Object.fromEntries(identityKeys.map((key) => [key, c[key]])), owner = factory(c);
	const capture = (index, chunk = packets()[index], now = 101n) => owner.capture(index ? 'server' : 'client', chunk, identity, now);
	const send = (index, chunk = packets()[index], now = 101n) => { const credit = capture(index, chunk, now); return typeof credit === 'symbol' && owner.observe(credit, identity, now); };
	return { c, identity, owner, capture, send, finish: (now = 101n) => owner.finish(identity, now) };
}
