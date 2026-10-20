// Fixed owned-child Windows proof, never a general command/process runner.
import { spawn } from 'node:child_process';
import { realpathSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { createOwnedStdioLines } from './owned-stdio-lines.mjs';
import { resolveOwnedStdioRuntime } from './owned-stdio-runtime.mjs';

export const ownedStdioModes = Object.freeze(['roundtrip', 'stderr', 'eof', 'partial', 'malformed', 'oversize', 'output-overflow', 'output-partial', 'exit', 'stall-read', 'stall-write', 'no-read', 'late-ack', 'tail', 'stderr-overflow']);
const ready = '{"v":1,"kind":"ready","seq":1}';
const reply = '{"v":1,"kind":"reply","seq":2}';
const probe = Buffer.from(`{"v":1,"kind":"probe","seq":1,"value":"${'A'.repeat(1024)}"}\n`);
const ack = Buffer.from('{"v":1,"kind":"ack","seq":2}\n');

export async function runOwnedStdioCase(mode, target = 'standalone') {
	if (process.platform !== 'win32' || !ownedStdioModes.includes(mode)) throw Error('Unsupported owned stdio target.');
	const php = resolveOwnedStdioRuntime(target), worker = fileURLToPath(new URL('./owned-stdio-worker.php', import.meta.url));
	if (realpathSync(php).replaceAll('\\', '/') !== php) throw Error('Unexpected PHP runtime.');
	const env = Object.fromEntries(['SystemRoot', 'WINDIR', 'TEMP', 'TMP'].filter((key) => typeof process.env[key] === 'string').map((key) => [key, process.env[key]]));
	const disabled = 'exec,shell_exec,system,passthru,proc_open,popen,fsockopen,pfsockopen,stream_socket_client,stream_socket_server';
	const child = spawn(php, ['-n', '-d', 'display_errors=0', '-d', 'log_errors=0', '-d', 'output_buffering=0', '-d', 'implicit_flush=1', '-d', 'memory_limit=16M', '-d', `disable_functions=${disabled}`, worker, mode],
		{ shell: false, windowsHide: true, cwd: fileURLToPath(new URL('.', import.meta.url)), env, stdio: ['pipe', 'pipe', 'pipe'] });
	const state = { mode, spawned: false, closed: false, exit_seen: false, killed: false, timed_out: false, stdout_bytes: 0, stderr_bytes: 0,
		input_bytes: 0, input_writes: 0, input_callbacks: 0, input_errors: 0, stdin_stream_errors: 0, backpressure: false, callback_before_timeout: false, ready: false, reply: false, final: false,
		reads: 0, writes_before_final: 0, line_complete: false, refusal: '', writing_marker: false, completed_marker: false, closed_pipe_refused: false };
	let stage = 0, pending = Promise.resolve(), stallTimer = null, cleanupTimer = null, stderrMarker = '', resolved = false;
	const stop = (reason) => {
		if (!state.refusal) state.refusal = reason;
		if (!state.closed && child.exitCode === null && child.signalCode === null && !child.killed) state.killed = child.kill();
		child.stdin.destroy(); child.stdout.resume();
	};
	const stall = () => { if (!stallTimer) stallTimer = setTimeout(() => { state.timed_out = true; stop('watchdog'); }, 750); };
	const write = async (bytes, width = 3) => {
		for (let offset = 0; offset < bytes.length; offset += width) {
			const part = bytes.subarray(offset, offset + width); state.input_bytes += part.length; state.input_writes++;
			await new Promise((resolve, reject) => child.stdin.write(part, (error) => { state.input_callbacks++; if (error) { state.input_errors++; reject(Error('Owned input refused.')); } else resolve(); }));
		}
	};
	const enqueue = (job) => { pending = pending.then(job).catch(() => { stop('input_refused'); }); };
	const lines = createOwnedStdioLines((line) => {
		if (stage === 0 && line === ready) {
			state.ready = true; stage = 1;
			enqueue(async () => {
				if (mode === 'eof') child.stdin.end();
				else if (mode === 'partial') { await write(probe.subarray(0, -1)); child.stdin.end(); }
				else if (mode === 'malformed') await write(Buffer.from('{"v":1,"kind":"foreign"}\n'));
				else if (mode === 'oversize') await write(Buffer.from('X'.repeat(4097) + '\n'), 64);
				else if (mode === 'stall-read') stall();
				else if (mode === 'no-read') {
					const bytes = Buffer.alloc(1048576, 65); state.input_bytes += bytes.length; state.input_writes++;
					state.backpressure = !child.stdin.write(bytes, (error) => { state.input_callbacks++; state.callback_before_timeout = !state.timed_out; if (error) state.input_errors++; });
					stall();
				} else if (!['output-overflow', 'output-partial', 'exit', 'stderr-overflow'].includes(mode)) await write(probe);
			});
			return true;
		}
		if (stage === 1 && line === reply) {
			state.reply = true; stage = 2;
			enqueue(async () => { if (mode === 'late-ack') await new Promise((resolve) => setTimeout(resolve, 1100)); await write(mode === 'tail' ? Buffer.concat([ack, Buffer.from('X')]) : ack); });
			return true;
		}
		if (stage !== 2) return false;
		let value;
		try { value = JSON.parse(line); } catch { return false; }
		if (!value || Object.keys(value).join(',') !== 'v,kind,seq,reads,writes_before_final' || JSON.stringify(value) !== line || value.v !== 1 || value.kind !== 'final' || value.seq !== 3 ||
			!Number.isInteger(value.reads) || value.reads !== probe.length + ack.length || value.reads > 4096 || !Number.isInteger(value.writes_before_final) || value.writes_before_final < 14 || value.writes_before_final > 4096) return false;
		state.final = true; state.reads = value.reads; state.writes_before_final = value.writes_before_final; stage = 3;
		enqueue(async () => child.stdin.end()); return true;
	});
	child.once('spawn', () => { state.spawned = true; });
	child.once('error', () => { stop('spawn_error'); });
	child.once('exit', () => { state.exit_seen = true; });
	child.stdin.on('error', () => { state.stdin_stream_errors++; }); // Count without retaining raw errors/private bytes.
	child.stdout.on('error', () => { stop('stdout_error'); });
	child.stderr.on('error', () => { stop('stderr_error'); });
	child.stdout.on('data', (chunk) => {
		state.stdout_bytes += chunk.length;
		if (mode === 'stall-write') { if (state.stdout_bytes > 4198400) stop('output_cap'); return; }
		if (!state.refusal && !lines.feed(chunk)) stop('stdout_invalid');
	});
	if (mode === 'stall-write') child.stdout.pause();
	child.stderr.on('data', (chunk) => {
		state.stderr_bytes += chunk.length;
		if (state.stderr_bytes > 262144) { stop('stderr_cap'); return; }
		if (mode === 'stall-write') {
			if (stderrMarker.length + chunk.length > 64) { stop('stderr_cap'); return; }
			stderrMarker += chunk.toString('ascii'); state.writing_marker = stderrMarker.includes('writing\n'); state.completed_marker = stderrMarker.includes('completed\n');
			if (state.writing_marker) stall();
		}
	});
	const started = process.hrtime.bigint();
	const watchdog = setTimeout(() => { state.timed_out = true; stop('outer_watchdog'); }, 6000);
	const result = await new Promise((resolve) => {
		cleanupTimer = setTimeout(() => { if (!resolved) { stop('cleanup_unresolved'); resolve({ code: null, signal: null }); } }, 8500);
		child.once('close', (code, signal) => { state.closed = true; resolved = true; clearTimeout(watchdog); clearTimeout(stallTimer); clearTimeout(cleanupTimer); resolve({ code, signal }); });
	});
	if (!state.closed) throw Error('Owned child cleanup unresolved; do not run another case.');
	await pending; state.line_complete = lines.end();
	// A write after the owned close must refuse through its callback, not escape uncaught.
	if (mode === 'exit') await new Promise((resolve) => child.stdin.write('X', (error) => { state.closed_pipe_refused = !!error; resolve(); }));
	child.stdin.destroy(); child.stdout.destroy(); child.stderr.destroy();
	return Object.freeze({ ...state, code: result.code, signal: result.signal, pipes_destroyed: child.stdin.destroyed && child.stdout.destroyed && child.stderr.destroyed,
		elapsed_ms: Number((process.hrtime.bigint() - started) / 1000000n) });
}
