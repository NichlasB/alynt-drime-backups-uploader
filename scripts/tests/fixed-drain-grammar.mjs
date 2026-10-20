// Socket-free candidate grammar. These literals never execute SQL.
export const drainQueries = Object.freeze([
	'SELECT CAST(CONNECTION_ID() AS CHAR),DATABASE(),@@hostname,CAST(@@port AS CHAR),CAST(@@server_id AS CHAR),VERSION(),CAST(@@autocommit+0 AS CHAR),CAST(@@in_transaction+0 AS CHAR),CAST(@@read_only+0 AS CHAR),CAST(@@tx_read_only+0 AS CHAR)',
	'SELECT @@tx_isolation,CAST(@@innodb_lock_wait_timeout AS CHAR),CAST(@@lock_wait_timeout AS CHAR),CURRENT_ROLE(),CAST(@@innodb_flush_log_at_trx_commit AS CHAR)',
]);
const eof = Buffer.from([254, 0, 0, 2, 0]);
function fields(bytes, count, nullAt = -1, metadata = false) {
	let offset = 0;
	for (let field = 0; field < count; field++) {
		if (offset >= bytes.length) return false;
		const first = bytes[offset++];
		if (field === nullAt) { if (first !== 251) return false; continue; }
		let size = first;
		if (first === 252) { if (offset + 2 > bytes.length) return false; size = bytes.readUInt16LE(offset); offset += 2; if (size < 251) return false; }
		else if (first > 250) return false;
		if (size > 1024 || offset + size > bytes.length) return false;
		if (metadata && field === 0 && (size !== 3 || !bytes.subarray(offset, offset + size).equals(Buffer.from('def')))) return false;
		offset += size;
	}
	return metadata ? bytes.length - offset === 13 && bytes[offset] === 12 : offset === bytes.length;
}
export function createFixedDrain() {
	let query = 0, seq = 0, active = false, quit = false, failed = false, total = 0, server = 0, client = 0;
	const fail = () => { failed = true; return false; };
	return Object.freeze({
		accept(direction, sequence, bytes) {
			if (failed || quit || !['client', 'server'].includes(direction) || !Buffer.isBuffer(bytes) || bytes.length < 1 || bytes.length > 4096 || (total += bytes.length + 4) > 32768) return fail();
			if (direction === 'client') {
				if (++client > 3 || active || sequence !== 0) return fail();
				if (query === 2) { if (!bytes.equals(Buffer.from([1]))) return fail(); quit = true; return true; }
				if (!bytes.equals(Buffer.from('\x03' + drainQueries[query], 'ascii'))) return fail();
				active = true; seq = 0; return true;
			}
			if (++server > 23 || !active || sequence !== ++seq) return fail();
			const count = query === 0 ? 10 : 5;
			if (seq === 1) { if (bytes.length !== 1 || bytes[0] !== count) return fail(); }
			else if (seq <= count + 1) { if (!fields(bytes, 6, -1, true)) return fail(); }
			else if (seq === count + 2 || seq === count + 4) { if (!bytes.equals(eof)) return fail(); }
			else if (seq === count + 3) { if (!fields(bytes, count, query === 1 ? 3 : -1)) return fail(); }
			else return fail();
			if (seq === count + 4) { query++; active = false; }
			return true;
		},
		done() { return !failed && quit && query === 2 && server === 23 && client === 3; },
	});
}
