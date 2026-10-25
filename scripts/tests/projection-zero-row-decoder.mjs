// Synthetic selected exchange only. No transport, worker, ledger or native caller.
import { types } from 'node:util';

export const PROJECTION_CATALOG = 'a066782b53b105be3386bc2f186b90a59c262cfe47b9bccdd07a180cf62828e0';
export const PROJECTION_PROTOCOL = 'projection-text41-ordinary-eof-v1';
export const PROJECTION_CAPABILITIES = 0x00088209; // LONG_PASSWORD, CONNECT_WITH_DB, PROTOCOL_41, SECURE_CONNECTION, PLUGIN_AUTH.
const absent = Object.freeze(['hack_file', 'wp_attachment_pages_enabled', 'category_base', 'tag_base',
	'use_smilies', 'blog_public', 'theme_switched', 'sidebars_widgets']);
const identities = ['attempt', 'pair', 'generation', 'request', 'session'];
const consumed = new WeakSet(); // Non-registered symbols; no unbounded strong retention.
const FRAME_LIMIT = 4096, TOTAL_LIMIT = 16384, FRAME_COUNT_LIMIT = 8, WINDOW_NS = 20000000000n;
const clock = (n) => typeof n === 'bigint' && n >= 0n && n <= 9223372036854775807n;

function snapshot(value, keys) {
	if (!value || typeof value !== 'object' || types.isProxy(value) || Object.getPrototypeOf(value) !== Object.prototype) return null;
	if (Reflect.ownKeys(value).length !== keys.length) return null;
	const fields = Object.getOwnPropertyDescriptors(value);
	if (!keys.every((key) => Object.hasOwn(fields, key) && Object.hasOwn(fields[key], 'value'))) return null;
	return Object.fromEntries(keys.map((key) => [key, fields[key].value]));
}

// All strings are bounded ASCII, below 0xfb: canonical one-byte lenenc only.
function column(schema, table) {
	const strings = ['def', schema, table, table, 'option_value', 'option_value'];
	return Buffer.concat([...strings.map((s) => Buffer.concat([Buffer.from([s.length]), Buffer.from(s, 'ascii')])),
		// Fixed synthetic LONG_BLOB, utf8mb4_general_ci(45), NOT_NULL|BLOB, no decimals/filler.
		Buffer.from([12, 45, 0, 255, 255, 255, 255, 251, 17, 0, 0, 0, 0])]);
}

export function createProjectionZeroRowDecoder(options) {
	let config = snapshot(options, [...identities, 'startedNs', 'seed', 'schema', 'name', 'catalog', 'protocol', 'capabilities']);
	if (!config || !clock(config.startedNs) || config.startedNs > 9223372036854775807n - WINDOW_NS ||
		typeof config.seed !== 'string' || config.seed.length !== 12 || !/^[a-f0-9]{12}$/.test(config.seed) || config.schema !== 'local' ||
		!absent.includes(config.name) || config.catalog !== PROJECTION_CATALOG || config.protocol !== PROJECTION_PROTOCOL ||
		config.capabilities !== PROJECTION_CAPABILITIES || new Set(identities.map((key) => config[key])).size !== identities.length ||
		!identities.every((key) => typeof config[key] === 'symbol' && Symbol.keyFor(config[key]) === undefined && !consumed.has(config[key]))) {
		throw new TypeError('Unsupported synthetic projection context.');
	}
	for (const key of identities) consumed.add(config[key]);
	const table = `wp_alynt_ro_${config.seed}_options`;
	let expected = [Buffer.from(`\x03SELECT option_value FROM ${table} WHERE option_name = '${config.name}' LIMIT 1`, 'ascii'),
		Buffer.from([1]), column(config.schema, table), Buffer.from([254, 0, 0, 2, 0]), Buffer.from([254, 0, 0, 2, 0])];
	const deadline = config.startedNs + WINDOW_NS;
	let last = config.startedNs, stage = 0, total = 0, frames = 0, refused = false, closed = false;
	let header = Buffer.alloc(4), headerUsed = 0, payload = null, used = 0;
	const clear = () => { header.fill(0); payload?.fill(0); headerUsed = 0; payload = null; used = 0; };
	const fail = () => { clear(); refused = true; return false; };
	const context = (identity, now) => {
		if (closed || refused) return false;
		const current = snapshot(identity, identities);
		if (!current || !identities.every((key) => current[key] === config[key]) || !clock(now) || now < last || now >= deadline) return fail();
		last = now;
		return true;
	};
	return Object.freeze({
		feed(direction, chunk, identity, now) {
			if (!context(identity, now)) return false;
			// No caller Buffer methods/getters, shared memory, or payload allocation before header bounds.
			if (types.isProxy(chunk) || !Buffer.isBuffer(chunk) || Object.getPrototypeOf(chunk) !== Buffer.prototype ||
				Object.hasOwn(chunk, 'length') || Object.hasOwn(chunk, 'buffer') || chunk.buffer instanceof SharedArrayBuffer ||
				chunk.length === 0 || chunk.length > TOTAL_LIMIT || total + chunk.length > TOTAL_LIMIT) return fail();
			total += chunk.length;
			let offset = 0;
			while (offset < chunk.length) {
				if (stage >= expected.length || direction !== (stage === 0 ? 'client' : 'server')) return fail();
				if (headerUsed < 4) {
					const size = Math.min(4 - headerUsed, chunk.length - offset);
					Buffer.prototype.copy.call(chunk, header, headerUsed, offset, offset + size);
					offset += size; headerUsed += size;
					if (headerUsed < 4) continue;
					const length = header.readUIntLE(0, 3);
					if (length < 1 || length > FRAME_LIMIT || ++frames > FRAME_COUNT_LIMIT ||
						length !== expected[stage].length || header[3] !== stage) return fail();
					payload = Buffer.alloc(length);
				}
				const size = Math.min(payload.length - used, chunk.length - offset);
				Buffer.prototype.copy.call(chunk, payload, used, offset, offset + size);
				offset += size; used += size;
				if (used === payload.length) {
					if (!Buffer.prototype.equals.call(payload, expected[stage])) return fail();
					clear(); stage++;
				}
			}
			return true; // Accepted fragment, never result/forwarding/delivery credit.
		},
		finish(identity, now) {
			if (closed) return false;
			const matched = context(identity, now) && stage === expected.length && headerUsed === 0;
			clear(); closed = true; config = null;
			for (const bytes of expected) bytes.fill(0);
			expected = null;
			return Object.freeze({ synthetic_match: matched, synthetic_only: true, authentication_proven: false,
				native_proven: false, backing_absence_proven: false, forwarding_proven: false, delivery_proven: false,
				resource_closure_proven: false, storage_supported: false, wiring_supported: false, execution_supported: false });
		},
	});
}
