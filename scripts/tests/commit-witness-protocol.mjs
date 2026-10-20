// Test-only classic protocol subset. No IO, credential interpretation or native evidence.
export const CLIENT_PROFILE = 0x0008a208; // DB, protocol41, transactions, secure response, plugin auth.
const command = Buffer.from('\x03COMMIT AND NO CHAIN NO RELEASE', 'ascii');
const plugin = 'mysql_native_password';
const zero = (bytes) => bytes.every((byte) => byte === 0);
const identifier = (value) => typeof value === 'string' && /^[A-Za-z0-9_-]{1,64}$/.test(value);

function text(bytes, offset, max) {
	const end = bytes.indexOf(0, offset);
	if (end < offset + 1 || end - offset > max) return false;
	const raw = bytes.subarray(offset, end);
	if (!raw.every((byte) => byte >= 32 && byte <= 126)) return false;
	return { value: raw.toString('ascii'), next: end + 1 };
}

export function greeting(bytes) {
	if (!Buffer.isBuffer(bytes) || bytes.length > 256 || bytes[0] !== 10) return false;
	const version = text(bytes, 1, 128);
	if (!version || !/^(?:5\.5\.5-)?10\.6\.[0-9]+-MariaDB(?:[- ].*)?$/.test(version.value)) return false;
	const at = version.next;
	if (bytes.length < at + 31) return false;
	const thread = bytes.readUInt32LE(at);
	const capabilities = bytes.readUInt16LE(at + 13) + bytes.readUInt16LE(at + 18) * 65536;
	if (!thread || bytes[at + 12] !== 0 || bytes[at + 15] !== 45 || bytes.readUInt16LE(at + 16) !== 2 ||
		(capabilities & CLIENT_PROFILE) !== CLIENT_PROFILE || bytes[at + 20] !== 21 || !zero(bytes.subarray(at + 21, at + 31))) return false;
	const tail = at + 31;
	if (bytes.length < tail + 13 || bytes[tail + 12] !== 0) return false;
	const auth = text(bytes, tail + 13, 32);
	return auth && auth.value === plugin && auth.next === bytes.length ? thread : false;
}

export function clientHello(bytes, database) {
	if (!Buffer.isBuffer(bytes) || bytes.length < 32 || bytes.length > 256 || !identifier(database) ||
		bytes.readUInt32LE(0) !== CLIENT_PROFILE || bytes.readUInt32LE(4) === 0 || bytes[8] !== 45 || !zero(bytes.subarray(9, 32))) return false;
	const user = text(bytes, 32, 64);
	if (!user || !identifier(user.value) || bytes[user.next] !== 20) return false;
	const db = text(bytes, user.next + 21, 64);
	if (!db || db.value !== database) return false;
	const auth = text(bytes, db.next, 32);
	return !!auth && auth.value === plugin && auth.next === bytes.length;
}

export function commit(bytes) {
	return Buffer.isBuffer(bytes) && bytes.equals(command);
}

export function idleOk(bytes) {
	// Narrow canonical zero affected/insert/warnings, AUTOCOMMIT only; no optional info.
	// Reject FE/EOF, noncanonical lenenc, extra results/session tracking and IN_TRANS.
	return Buffer.isBuffer(bytes) && bytes.length === 7 && bytes[0] === 0 && bytes[1] === 0 && bytes[2] === 0 &&
		bytes.readUInt16LE(3) === 2 && bytes.readUInt16LE(5) === 0;
}
