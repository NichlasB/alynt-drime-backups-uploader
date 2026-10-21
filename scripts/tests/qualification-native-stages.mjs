// Passive, socket-free support diagnostics. Never stores SQL/rows or controls delivery.
import { drainQueries } from './fixed-drain-grammar.mjs';
const stages = new Map([
	['SET SESSION TRANSACTION READ WRITE', 'read_write'],
	['SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED', 'read_committed'],
	['SET SESSION innodb_lock_wait_timeout=2', 'row_lock_wait'],
	['SET SESSION lock_wait_timeout=3', 'metadata_lock_wait'],
	[drainQueries[1], 'policy_select'],
	["SELECT CAST(COUNT(DISTINCT PRIVILEGE_TYPE) AS CHAR) FROM information_schema.USER_PRIVILEGES WHERE GRANTEE=CONCAT(QUOTE(SUBSTRING_INDEX(CURRENT_USER(),'@',1)),'@',QUOTE(SUBSTRING_INDEX(CURRENT_USER(),'@',-1))) AND PRIVILEGE_TYPE IN ('SELECT','TRIGGER','REFERENCES')", 'metadata_privileges'],
	['SELECT ENGINE,TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 2', 'metadata_engine_prepare'],
	['SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,EXTRA,GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION LIMIT 5', 'metadata_columns_prepare'],
	['SELECT CAST(COUNT(*) AS CHAR) FROM information_schema.TRIGGERS WHERE EVENT_OBJECT_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE=?', 'metadata_triggers_prepare'],
	['SELECT CONSTRAINT_NAME,CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY CONSTRAINT_NAME LIMIT 4', 'metadata_constraints_prepare'],
	['SELECT CAST(COUNT(*) AS CHAR) FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_NAME IS NOT NULL AND ((TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?) OR (REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME=?))', 'metadata_foreign_keys_prepare'],
	['START TRANSACTION READ WRITE', 'begin_command'],
]);
const physical = 'SELECT CAST(CONNECTION_ID() AS CHAR) AS thread, DATABASE() AS db, @@hostname AS host, CAST(@@port AS CHAR) AS port, CAST(@@server_id AS CHAR) AS server, VERSION() AS version, CAST(@@autocommit+0 AS CHAR) AS auto, CAST(@@in_transaction+0 AS CHAR) AS tx, CAST(@@read_only+0 AS CHAR) AS ro, CAST(@@tx_read_only+0 AS CHAR) AS tx_ro';
const failures = new Set(['qualification_transport_cap', 'qualification_queue', 'qualification_write', 'frame_cap', 'byte_cap', 'frame_input', 'unsupported_local_file_or_null_row', 'unsupported_greeting', 'handshake_order', 'unsupported_hello', 'unsupported_auth', 'unsupported_negotiation', 'socket_error', 'premature_close', 'connection_reset', 'unknown_transport_failure']);
function command(direction, frame) {
	if (direction !== 'client' || frame[3] !== 0 || ![3, 22].includes(frame[4]) || frame.length > 4100) return '';
	const bytes = frame.subarray(5); if (!bytes.every((n) => n >= 32 && n <= 126)) return '';
	const sql = bytes.toString('ascii');
	if (sql === physical) return 'physical_facts';
	if (frame[4] === 3 && /^SHOW INDEX FROM `wp_alynt_core_probe_[a-f0-9]{12}_options`$/.test(sql)) return 'metadata_index';
	const label = stages.get(sql) ?? '';
	return label.endsWith('_prepare') ? (frame[4] === 22 ? label : '') : (frame[4] === 3 ? label : '');
}
export function createQualificationStages() {
	if (arguments.length) throw Error('fixed_stage_observer');
	let observed = 0, forwarded = 0, physicalQueries = 0, writer = false, last = 'none', delivered = 'none', serverError = false, firstFailure = null, inconclusive = false;
	const valid = (direction, frame) => ['client', 'server'].includes(direction) && Buffer.isBuffer(frame) && frame.length >= 5 && frame.length <= 2097156 && frame.readUIntLE(0, 3) === frame.length - 4;
	return Object.freeze({
		frame(direction, frame) {
			if (!valid(direction, frame) || observed >= 4097) { inconclusive = true; return; }
			observed++; if (direction === 'server' && frame[4] === 255) serverError = true;
			const stage = command(direction, frame); if (stage === 'physical_facts') physicalQueries++;
			else if (stage) { last = stage; if (stage === 'read_write') writer = true; }
		},
		account(direction, frame) {
			if (!valid(direction, frame) || forwarded >= observed) { inconclusive = true; return; }
			forwarded++; const stage = command(direction, frame); if (stage && stage !== 'physical_facts') delivered = stage;
		},
		failure(label) {
			if (typeof label === 'string' && label.startsWith('unsupported_negotiation_')) label = 'unsupported_negotiation';
			firstFailure ??= failures.has(label) ? label : 'unknown_transport_failure';
		},
		unavailable() { inconclusive = true; },
		summary() { return Object.freeze({ diagnostic_only: true, writer_setup_command_seen: writer, frames_observed: observed, frames_forwarded: forwarded, physical_queries_seen: physicalQueries, last_setup_command_seen: last, last_setup_command_forwarded: delivered, server_error_seen: serverError, first_transport_failure: firstFailure, inconclusive }); },
	});
}
