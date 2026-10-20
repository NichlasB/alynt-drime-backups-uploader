// Explicit opt-in only; never imported by CI/unit tests or a site endpoint.
import assert from 'node:assert/strict';
import { ownedStdioModes, runOwnedStdioCase } from './owned-stdio-case.mjs';

if (process.platform !== 'win32' || process.argv.length !== 3 || process.argv[2] !== '--owned-stdio-only') {
	process.stderr.write('Requires the approved Windows owned-stdio-only scope.\n'); process.exitCode = 1;
} else {
	let checks = 0;
	const receipts = [];
	const check = (actual, expected, message) => { assert.equal(actual, expected, message); checks++; };
	try {
		for (const mode of ownedStdioModes) {
			const receipt = await runOwnedStdioCase(mode); receipts.push(receipt);
			for (const key of ['spawned', 'closed', 'exit_seen', 'pipes_destroyed']) check(receipt[key], true, `${mode}: ${key}`);
			check(receipt.elapsed_ms < 8500, true, `${mode}: bounded completion`);
			if (['roundtrip', 'stderr'].includes(mode)) {
				for (const key of ['ready', 'reply', 'final', 'line_complete']) check(receipt[key], true, `${mode}: ${key}`);
				check(receipt.code, 0, `${mode}: clean EOF exit`); check(receipt.killed, false, `${mode}: no kill`); check(receipt.timed_out, false, `${mode}: no timeout`);
				check(receipt.input_callbacks, receipt.input_writes, `${mode}: every write callback`); check(receipt.input_errors, 0, `${mode}: no input error`);
				check(receipt.input_bytes, 1095, `${mode}: byte-exact request/ack`); check(receipt.input_writes, 366, `${mode}: small serialized writes`); check(receipt.stdin_stream_errors, 0, `${mode}: no stream error`);
				check(receipt.refusal, '', `${mode}: no refusal`); check(receipt.stderr_bytes, mode === 'stderr' ? 131072 : 0, `${mode}: drained error stream`);
			} else {
				check(receipt.final, mode === 'tail', `${mode}: final observation alone is not success`);
				const codes = { eof: 23, partial: 24, malformed: 25, oversize: 26, 'output-partial': 0, exit: 27, 'late-ack': 28, tail: 25 };
				if (Object.hasOwn(codes, mode)) check(receipt.code, codes[mode], `${mode}: exact child refusal`);
				if (['stall-read', 'stall-write', 'no-read'].includes(mode)) { check(receipt.timed_out, true, `${mode}: watchdog`); check(receipt.killed, true, `${mode}: owned child killed`); }
				if (mode === 'output-overflow') check(receipt.refusal, 'stdout_invalid', 'bounded output refusal');
				if (mode === 'stderr-overflow') check(receipt.refusal, 'stderr_cap', 'bounded error-stream refusal');
				if (mode === 'output-partial') check(receipt.line_complete, false, 'partial EOF is not a complete line');
				if (mode === 'stall-write') { check(receipt.writing_marker, true, 'child reached output write'); check(receipt.completed_marker, false, 'child did not complete blocked write'); }
				if (mode === 'no-read') { check(receipt.ready, true, 'child started'); check(receipt.backpressure, true, 'parent write backpressure'); check(receipt.callback_before_timeout, false, 'pending write before watchdog'); check(receipt.input_callbacks, 1, 'pending write callback settled'); check(receipt.input_errors, 1, 'pending write refused after teardown'); }
				if (mode === 'exit') check(receipt.closed_pipe_refused, true, 'closed owned stdin callback refusal');
			}
		}
		process.stdout.write(JSON.stringify({ passed: true, scope: 'owned_child_stdio_only', platform: process.platform, checks, groups: receipts.length, receipts }) + '\n');
	} catch {
		process.stdout.write(JSON.stringify({ passed: false, scope: 'owned_child_stdio_only', checks, groups: receipts.length, receipts, failure: 'Owned stdio proof failed; inspect compiled case evidence.' }) + '\n');
		process.exitCode = 1;
	}
}
