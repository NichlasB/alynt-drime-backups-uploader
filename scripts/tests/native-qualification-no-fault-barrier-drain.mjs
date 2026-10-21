// Exact source-audited post-barrier-COMMIT suffix. No generic SQL exemption.
import { drainQueries } from './fixed-drain-grammar.mjs';
const counters = ['Com_insert','Com_insert_select','Com_update','Com_update_multi','Com_delete','Com_delete_multi','Com_replace','Com_replace_select','Com_create_table','Com_alter_table','Com_drop_table','Com_truncate','Handler_write','Handler_update','Handler_delete'];
const zero = "SHOW SESSION STATUS WHERE Variable_name IN ('Com_insert','Com_insert_select','Com_update','Com_update_multi','Com_delete','Com_delete_multi','Com_replace','Com_replace_select','Com_create_table','Com_alter_table','Com_drop_table','Com_truncate','Handler_write','Handler_update','Handler_delete')";
const queries = [...drainQueries, zero], eof = Buffer.from([254,0,0,2,0]), showMetadataEof = Buffer.from([254,0,0,34,0]);
function fields(bytes, count, nullAt = -1, metadata = false) {
	let at = 0; const values = [];
	for (let i = 0; i < count; i++) {
		if (at >= bytes.length) return false; let n = bytes[at++];
		if (i === nullAt) { if (n !== 251) return false; values.push(null); continue; }
		if (n === 252) { if (at + 2 > bytes.length) return false; n = bytes.readUInt16LE(at); at += 2; if (n < 251) return false; }
		else if (n > 250) return false;
		if (n > 1024 || at + n > bytes.length) return false;
		values.push(bytes.subarray(at, at + n).toString('ascii')); at += n;
	}
	return (metadata ? bytes.length - at === 13 && bytes[at] === 12 && values[0] === 'def' : at === bytes.length) ? values : false;
}
export function createNativeBarrierDrain() {
	if (arguments.length) throw Error('fixed_barrier_drain');
	let query = 0, seq = 0, active = false, quit = false, failed = false, total = 0, reason = ''; const seen = new Set();
	const fail = (label) => { failed = true; reason ||= label; return false; };
	return Object.freeze({ accept(direction, sequence, bytes) {
		if (failed || quit || !['client','server'].includes(direction) || !Buffer.isBuffer(bytes) || !bytes.length || bytes.length > 4096 || (total += bytes.length + 4) > 32768) return fail('bounds');
		if (direction === 'client') {
			if (active || sequence !== 0) return fail('command_order');
			if (query === 3) { if (!bytes.equals(Buffer.from([1]))) return fail('quit'); quit = true; return true; }
			if (!bytes.equals(Buffer.from('\x03' + queries[query], 'ascii'))) return fail('query'); active = true; seq = 0; return true;
		}
		if (!active || sequence !== ++seq) return fail('sequence');
		const count = [10,5,2][query], rows = query === 2 ? 15 : 1;
		if (seq === 1) { if (bytes.length !== 1 || bytes[0] !== count) return fail('columns'); }
		else if (seq <= count + 1) { if (!fields(bytes, 6, -1, true)) return fail('metadata'); }
		// Fixed MariaDB 10.6 SHOW metadata carries AUTOCOMMIT|NO_INDEX_USED (34).
		// Only that position differs: final SHOW and both SELECT EOFs remain exactly 2.
		else if (seq === count + 2 || seq === count + 3 + rows) { if (!bytes.equals(query === 2 && seq === count + 2 ? showMetadataEof : eof)) return fail('eof'); }
		else if (seq >= count + 3 && seq < count + 3 + rows) {
			const values = fields(bytes, count, query === 1 ? 3 : -1); if (!values) return fail('row');
			if (query === 2) { if (!counters.includes(values[0]) || seen.has(values[0]) || values[1] !== '0') return fail('zero_counter'); seen.add(values[0]); }
		} else return fail('result_order');
		if (seq === count + 3 + rows) { query++; active = false; }
		return true;
	}, done() { return !failed && quit && query === 3 && seen.size === 15; }, diagnostic() { return Object.freeze({ reason, query, sequence: seq, counters: seen.size, quit }); } });
}
