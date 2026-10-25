// Synthetic complete-packet subset only. No driver/session negotiation or IO.
import { types } from 'node:util';

export const WPDB_SETUP_WIRE_PROFILE = 'wpdb-setup-wire.synthetic.v1';
const ordinaryBytes = (value) => !types.isProxy(value) && Buffer.isBuffer(value) && Object.getPrototypeOf(value) === Buffer.prototype &&
	!Object.hasOwn(value, 'buffer') && !Object.hasOwn(value, 'length') && !(value.buffer instanceof SharedArrayBuffer);

// Caller owns this detached copy. Command owners must clear it after use.
export function copyWpdbSetupPayload(frame, sequence) {
	if (!Number.isInteger(sequence) || sequence < 0 || sequence > 8 || !ordinaryBytes(frame) || frame.length < 5 || frame.length > 4100) return false;
	const length = Buffer.prototype.readUIntLE.call(frame, 0, 3);
	if (length < 1 || length > 4096 || frame.length !== length + 4 || frame[3] !== sequence) return false;
	const copy = Buffer.alloc(length);
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
