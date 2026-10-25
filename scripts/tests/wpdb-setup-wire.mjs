// Synthetic complete-packet subset only. No driver/session negotiation or IO.
import { types } from 'node:util';

export const WPDB_SETUP_WIRE_PROFILE = 'wpdb-setup-wire.synthetic.v1';
const ordinaryBytes = (value) => !types.isProxy(value) && Buffer.isBuffer(value) && Object.getPrototypeOf(value) === Buffer.prototype &&
	!Object.hasOwn(value, 'buffer') && !Object.hasOwn(value, 'length') && !(value.buffer instanceof SharedArrayBuffer);

const validFrame = (frame, sequence) => {
	if (!Number.isInteger(sequence) || sequence < 0 || sequence > 8 || !ordinaryBytes(frame) || frame.length < 5 || frame.length > 4100) return false;
	const length = Buffer.prototype.readUIntLE.call(frame, 0, 3);
	return length >= 1 && length <= 4096 && frame.length === length + 4 && frame[3] === sequence;
};
// Caller owns this detached copy. Command owners must clear it after use.
export function copyWpdbSetupPayload(frame, sequence) {
	if (!validFrame(frame, sequence)) return false;
	const copy = Buffer.alloc(frame.length - 4);
	Buffer.prototype.copy.call(frame, copy, 0, 4);
	return copy;
}

const setupOK = Buffer.from([0, 0, 0, 2, 0, 0, 0]);
export function decodeWpdbSetupOK(frame) {
	const payload = copyWpdbSetupPayload(frame, 1);
	if (!payload) return false;
	try { return payload.equals(setupOK); }
	finally { payload.fill(0); }
}

const modeColumn = Buffer.concat([Buffer.from([3]), Buffer.from('def'), Buffer.from([0, 0, 0, 18]),
	Buffer.from('@@SESSION.sql_mode'), Buffer.from([0, 12, 45, 0, 0, 4, 0, 0, 253, 0, 0, 0, 0, 0])]);
const ordinaryEOF = Buffer.from([254, 0, 0, 2, 0]);
const nonemptyMode = Buffer.from('\x13STRICT_TRANS_TABLES');
const frameList = (frames, count) => {
	if (types.isProxy(frames) || !Array.isArray(frames) || Object.getPrototypeOf(frames) !== Array.prototype ||
		frames.length !== count || Reflect.ownKeys(frames).length !== count + 1) return false;
	const fields = Object.getOwnPropertyDescriptors(frames);
	const indexes = Array.from({ length: count }, (_, i) => i);
	return indexes.every((i) => Object.hasOwn(fields, i) && Object.hasOwn(fields[i], 'value')) ? indexes.map((i) => fields[i].value) : false;
};
export function decodeWpdbModeResult(frames) {
	const list = frameList(frames, 5); if (!list) return false;
	const bodies = []; let total = 0;
	try {
		for (let i = 0; i < 5; i++) {
			const frame = list[i];
			if (!ordinaryBytes(frame) || total + frame.length > 16384) return false;
			total += frame.length;
			const body = copyWpdbSetupPayload(frame, i + 1); if (!body) return false; bodies.push(body);
		}
		if (!bodies[0].equals(Buffer.from([1])) || !bodies[1].equals(modeColumn) ||
			!bodies[2].equals(ordinaryEOF) || !bodies[4].equals(ordinaryEOF)) return false;
		if (bodies[3].equals(Buffer.from([0]))) return 'one-empty-string';
		if (bodies[3].equals(nonemptyMode)) return 'one-filterable-nonempty';
		return false;
	} finally { for (const body of bodies) body.fill(0); }
}

const modeRequest = Buffer.from('\x03SELECT @@SESSION.sql_mode');
const schemaRequest = Buffer.from('\x02owned_synthetic');
export function decodeWpdbSetupExchange(kind, request, responses) {
	if (kind !== 'mode-read' && kind !== 'schema-select') return false;
	const payload = copyWpdbSetupPayload(request, 0); if (!payload) return false;
	try {
		if (!payload.equals(kind === 'mode-read' ? modeRequest : schemaRequest)) return false;
		if (kind === 'mode-read') return decodeWpdbModeResult(responses);
		const list = frameList(responses, 1);
		return list && decodeWpdbSetupOK(list[0]) ? 'schema-selected' : false;
	} finally { payload.fill(0); }
}

const identities = ['attempt', 'pair', 'generation', 'request', 'session', 'command'];
const usedIdentities = new WeakSet();
const dataRecord = (value, keys) => {
	if (!value || typeof value !== 'object' || types.isProxy(value) || Object.getPrototypeOf(value) !== Object.prototype || Reflect.ownKeys(value).length !== keys.length) return false;
	const fields = Object.getOwnPropertyDescriptors(value);
	return keys.every((k) => Object.hasOwn(fields, k) && Object.hasOwn(fields[k], 'value')) ? Object.fromEntries(keys.map((k) => [k, fields[k].value])) : false;
};
export function createWpdbSetupWireOwner(options) {
	const config = dataRecord(options, [...identities, 'profile', 'kind', 'startedNs']);
	if (!config || config.profile !== WPDB_SETUP_WIRE_PROFILE || !['mode-read', 'schema-select'].includes(config.kind) ||
		typeof config.startedNs !== 'bigint' || config.startedNs < 0n ||
		!identities.every((k) => typeof config[k] === 'symbol' && Symbol.keyFor(config[k]) === undefined && !usedIdentities.has(config[k])) ||
		new Set(identities.map((k) => config[k])).size !== identities.length) throw new TypeError('Unsupported synthetic wire context.');
	for (const key of identities) usedIdentities.add(config[key]);
	const frames = [], count = config.kind === 'mode-read' ? 6 : 2;
	let last = config.startedNs, closed = false, refused = false, copied = 0, released = 0, peak = 0;
	const clear = () => { for (const frame of frames) { released += frame.length; frame.fill(0); } frames.length = 0; };
	const fail = () => { refused = true; clear(); return false; };
	const context = (identity, now) => {
		if (closed || refused) return false;
		const fields = dataRecord(identity, identities);
		if (!fields || !identities.every((k) => fields[k] === config[k]) || typeof now !== 'bigint' || now < last || now >= config.startedNs + 20000000000n) return fail();
		last = now; return true;
	};
	return Object.freeze({
		capture(direction, frame, identity, now) {
			if (!context(identity, now)) return false;
			if (frames.length >= count || direction !== (frames.length ? 'server' : 'client') || !validFrame(frame, frames.length) || copied + frame.length > 16384) return fail();
			const copy = Buffer.alloc(frame.length); Buffer.prototype.copy.call(frame, copy);
			frames.push(copy); copied += copy.length; peak = Math.max(peak, copied - released); return true;
		},
		finish(identity, now) {
			if (closed) return false;
			let outcome = false;
			try { if (context(identity, now) && frames.length === count) outcome = decodeWpdbSetupExchange(config.kind, frames[0], frames.slice(1)); }
			finally { clear(); closed = true; }
			return Object.freeze({ synthetic_match: outcome !== false, synthetic_only: true, outcome: outcome || null, refused,
				profile: WPDB_SETUP_WIRE_PROFILE, source_proven: false, authentication_proven: false, native_proven: false,
				forwarding_proven: false, backing_absence_proven: false, resource_closure_proven: false,
				storage_supported: false, wiring_supported: false, execution_supported: false,
				capture_accounting: Object.freeze({ copied_bytes: copied, released_bytes: released, pending_bytes: copied - released, peak_bytes: peak }) });
		},
	});
}
