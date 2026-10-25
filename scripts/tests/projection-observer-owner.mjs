// Socket-free composition candidate only. No transport/worker or arbitrary observer callback.
import { types } from 'node:util';
import { createProjectionZeroRowDecoder } from './projection-zero-row-decoder.mjs';

const identities = ['attempt', 'pair', 'generation', 'request', 'session'];
const configKeys = [...identities, 'startedNs', 'seed', 'schema', 'name', 'catalog', 'protocol', 'capabilities'];
const data = (value, keys) => {
	if (!value || typeof value !== 'object' || types.isProxy(value) || Object.getPrototypeOf(value) !== Object.prototype || Reflect.ownKeys(value).length !== keys.length) return null;
	const fields = Object.getOwnPropertyDescriptors(value);
	return keys.every((key) => Object.hasOwn(fields, key) && Object.hasOwn(fields[key], 'value')) ? Object.fromEntries(keys.map((key) => [key, fields[key].value])) : null;
};
const bytes = (value) => !types.isProxy(value) && Buffer.isBuffer(value) && Object.getPrototypeOf(value) === Buffer.prototype &&
	!Object.hasOwn(value, 'length') && !Object.hasOwn(value, 'buffer') && !(value.buffer instanceof SharedArrayBuffer);

export function createProjectionObserverOwner(options) {
	const config = data(options, configKeys);
	if (!config) throw new TypeError('Unsupported synthetic observer context.');
	let decoder = createProjectionZeroRowDecoder(config);
	const owned = Object.fromEntries(identities.map((key) => [key, config[key]]));
	const deadline = config.startedNs + 20000000000n;
	let last = config.startedNs, refused = false, closed = false, copied = 0, pending = 0;
	const entries = new Map(), order = [];
	const clear = () => { for (const entry of entries.values()) entry.copy.fill(0); entries.clear(); order.length = 0; pending = 0; };
	const disposeDecoder = () => { if (decoder) decoder.finish(owned, last); decoder = null; };
	const fail = () => { refused = true; clear(); disposeDecoder(); return false; };
	const context = (identity, now) => {
		if (closed || refused) return false;
		const current = data(identity, identities);
		if (!current || !identities.every((key) => current[key] === owned[key]) || typeof now !== 'bigint' || now < last || now >= deadline) return fail();
		last = now; return true;
	};
	return Object.freeze({
		capture(direction, chunk, identity, now) {
			if (!context(identity, now)) return false;
			if (!['client', 'server'].includes(direction) || !bytes(chunk) || chunk.length < 1 ||
				chunk.length > 16384 || copied + chunk.length > 16384 || pending + chunk.length > 16384 || order.length >= 512) return fail();
			const copy = Buffer.alloc(chunk.length); Buffer.prototype.copy.call(chunk, copy);
			const credit = Symbol('projection-copy'); entries.set(credit, { direction, copy }); order.push(credit);
			copied += copy.length; pending += copy.length;
			return credit;
		},
		observe(credit, identity, now) {
			if (!context(identity, now)) return false;
			if (credit !== order[0] || !entries.has(credit)) return fail();
			const entry = entries.get(credit);
			if (!decoder.feed(entry.direction, entry.copy, owned, now)) return fail();
			pending -= entry.copy.length; entry.copy.fill(0); entries.delete(credit); order.shift();
			return true;
		},
		finish(identity, now) {
			if (closed) return false;
			const valid = context(identity, now) && entries.size === 0;
			const matched = valid && decoder.finish(owned, now).synthetic_match;
			disposeDecoder(); clear(); closed = true;
			return Object.freeze({ synthetic_match: matched, synthetic_only: true, authentication_proven: false,
				native_proven: false, forwarding_proven: false, delivery_proven: false, backing_absence_proven: false,
				resource_closure_proven: false, storage_supported: false, wiring_supported: false, execution_supported: false });
		},
	});
}
