// Invented single-session profile consistency only; NOT authentication or grant evidence.
import { types } from 'node:util';
const version = Buffer.from('\x0a5.5.5-10.6.23-MariaDB\0');
const plugin = Buffer.from('mysql_native_password\0');

export function projectionSessionProfile(frames, seed) {
	if (typeof seed !== 'string' || seed.length !== 12 || !/^[a-f0-9]{12}$/.test(seed) ||
		types.isProxy(frames) || !Array.isArray(frames) || Object.getPrototypeOf(frames) !== Array.prototype || Reflect.ownKeys(frames).length !== 4) return false;
	const fields = Object.getOwnPropertyDescriptors(frames);
	if (fields.length.value !== 3 || ![0, 1, 2].every((i) => Object.hasOwn(fields[i] ?? {}, 'value'))) return false;
	const copies = [];
	try {
		for (let i = 0; i < 3; i++) {
			const input = fields[i].value;
			if (types.isProxy(input) || !Buffer.isBuffer(input) || Object.getPrototypeOf(input) !== Buffer.prototype ||
				Object.hasOwn(input, 'length') || Object.hasOwn(input, 'buffer') || input.buffer instanceof SharedArrayBuffer || input.length < 5 || input.length > 256) return false;
			const copy = Buffer.alloc(input.length); copies.push(copy); Buffer.prototype.copy.call(input, copy);
			if (copy.readUIntLE(0, 3) !== copy.length - 4 || copy[3] !== i) return false;
		}
		const greeting = copies[0].subarray(4), at = version.length;
		if (greeting.length !== at + 44 + plugin.length || !greeting.subarray(0, at).equals(version) ||
			greeting.readUInt32LE(at) === 0 || greeting[at + 12] !== 0 || greeting.readUInt16LE(at + 13) !== 0xa209 ||
			greeting[at + 15] !== 45 || greeting.readUInt16LE(at + 16) !== 2 || greeting.readUInt16LE(at + 18) !== 8 ||
			greeting[at + 20] !== 21 || !greeting.subarray(at + 21, at + 31).every((b) => b === 0) ||
			greeting[at + 43] !== 0 || !greeting.subarray(at + 44).equals(plugin)) return false;
		const hello = copies[1].subarray(4), username = Buffer.from(`alynt_ro_${seed}_i\0`), suffix = Buffer.from('local\0mysql_native_password\0');
		const response = 32 + username.length;
		if (hello.length !== response + 21 + suffix.length || hello.readUInt32LE(0) !== 0x00088209 ||
			hello.readUInt32LE(4) !== 16384 || hello[8] !== 45 || !hello.subarray(9, 32).every((b) => b === 0) ||
			!hello.subarray(32, response).equals(username) || hello[response] !== 20 || !hello.subarray(response + 21).equals(suffix) ||
			!copies[2].subarray(4).equals(Buffer.from([0, 0, 0, 2, 0, 0, 0]))) return false;
		return greeting.readUInt32LE(at); // Correlation integer only, never a credential/handle.
	} finally { for (const copy of copies) copy.fill(0); }
}
