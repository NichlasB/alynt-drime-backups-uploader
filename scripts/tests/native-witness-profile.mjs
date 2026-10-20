// Synthetic candidate only. No socket, authentication, SQL or delivery authority.
export const nativeLimits = Object.freeze({ frame: 2097152, pending: 8388608, bytes: 67108864, frames: 4096 });
const required = 0x0008a200;
const optional = 0x00160085; // LONG_PASSWORD, LONG_FLAG, LOCAL_FILES, MULTI/PS_RESULTS, ATTRS.
export const validThread = (n) => Number.isInteger(n) && n > 0 && n <= 0xffffffff;
export const validIdentity = (s) => typeof s === 'symbol' && Symbol.keyFor(s) === undefined;
export const validClock = (n) => typeof n === 'bigint' && n >= 0n && n <= 9223372036854775807n;
export function dataObject(value, keys) {
	if (!value || Object.getPrototypeOf(value) !== Object.prototype) return false;
	const fields = Object.getOwnPropertyDescriptors(value), names = Reflect.ownKeys(fields);
	return names.length === keys.length && names.every((key) => keys.includes(key) && Object.hasOwn(fields[key], 'value'));
}
const identifier = (s) => typeof s === 'string' && /^[A-Za-z0-9_-]{1,64}$/.test(s);
const plugin = Buffer.from('mysql_native_password\0', 'ascii');
export const exactCommit = (b) => b.equals(Buffer.from('\x03COMMIT AND NO CHAIN NO RELEASE', 'ascii'));
export const canonicalOk = (b) => b.equals(Buffer.from([0, 0, 0, 2, 0, 0, 0]));

export function nativeGreeting(bytes) {
	if (!Buffer.isBuffer(bytes) || bytes.length > 256 || bytes[0] !== 10) return false;
	const end = bytes.indexOf(0, 1), at = end + 1;
	if (end < 1 || !bytes.subarray(1, end).every((n) => n >= 32 && n <= 126) || !/^5\.5\.5-10\.6\.[0-9]{1,3}-MariaDB$/.test(bytes.toString('ascii', 1, end)) || at + 44 + plugin.length !== bytes.length) return false;
	const thread = bytes.readUInt32LE(at), flags = bytes.readUInt16LE(at + 13) + bytes.readUInt16LE(at + 18) * 65536;
	if (!validThread(thread) || bytes[at + 12] !== 0 || ![8, 45].includes(bytes[at + 15]) || bytes.readUInt16LE(at + 16) !== 2 ||
		(flags & required) !== required || bytes[at + 20] !== 21 || !bytes.subarray(at + 21, at + 27).every((n) => n === 0) || bytes[at + 43] !== 0 || !bytes.subarray(at + 44).equals(plugin)) return false;
	// The four MariaDB extended-capability bytes are not reserved zeros.
	return thread;
}

export function nativeHello(bytes, username, database, withDatabase = true) {
	if (!identifier(username) || !identifier(database) || !Buffer.isBuffer(bytes) || bytes.length < 33 || bytes.length > 4096) return false;
	const flags = bytes.readUInt32LE(0), allowed = required | optional | 8;
	if ((flags & required) !== required || (flags & ~allowed) !== 0 || (withDatabase && !(flags & 8)) ||
		![8, 45].includes(bytes[8]) || !bytes.subarray(9, 32).every((n) => n === 0)) return false;
	let offset = 32;
	const exactString = (value) => {
		const expected = Buffer.from(`${value}\0`, 'ascii');
		if (!bytes.subarray(offset, offset + expected.length).equals(expected)) return false;
		offset += expected.length; return true;
	};
	if (!exactString(username) || bytes[offset++] !== 20 || offset + 20 > bytes.length) return false;
	offset += 20; // Never retain opaque authentication material.
	if ((flags & 8) && !exactString(database)) return false;
	if (!exactString('mysql_native_password')) return false;
	if (flags & 0x100000) {
		const length = () => {
			if (offset >= bytes.length) return false;
			const first = bytes[offset++];
			if (first < 251) return first;
			if (first !== 252 || offset + 2 > bytes.length) return false;
			const n = bytes.readUInt16LE(offset); offset += 2;
			return n >= 251 && n <= 1024 ? n : false;
		};
		const size = length();
		if (size === false || size > 1024 || offset + size !== bytes.length) return false;
		const end = offset + size;
		let pairs = 0;
		while (offset < end) {
			if (++pairs > 16) return false;
			for (let i = 0; i < 2; i++) {
				const n = length();
				if (n === false || offset + n > end) return false;
				offset += n;
			}
		}
	}
	return offset === bytes.length ? flags : false;
}
