// Fixed owned PHP files only, bounded exits AND close/pipes. No arbitrary executable/provider.
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { boundedSettlement, closeOwnedChild } from './selected-native-lifetime.mjs';
export function startNativeQualificationPhp(php, file, args, input = null) {
	if (!['native-qualification-selected-preflight.php', 'qualification-native-prelude.php', 'native-qualification-selected-worker.php', 'native-qualification-no-fault-fixtures.php'].includes(file) || !Array.isArray(args)) throw Error('qualification_child');
	const child = spawn(php, ['-d', 'extension_dir=' + php.slice(0, -7) + 'ext', '-d', 'extension=mysqli', '-d', 'display_errors=0', '-d', 'log_errors=0', '-d', 'output_buffering=0', '-d', 'implicit_flush=1',
		'-d', 'disable_functions=exec,shell_exec,system,passthru,proc_open,popen', fileURLToPath(new URL('./' + file, import.meta.url)), ...args], { shell: false, windowsHide: true, stdio: ['pipe', 'pipe', 'pipe'] });
	let closed = false, exited = false, stderr = 0, output = '', overflow = false;
	child.stdin.on('error', () => {}); child.stdout.on('error', () => { overflow = true; }); child.stderr.on('error', () => { overflow = true; });
	child.stdout.on('data', (chunk) => { if (Buffer.byteLength(output) + chunk.length > 65536) { overflow = true; child.kill(); } else output += chunk.toString('utf8'); });
	child.stderr.on('data', (chunk) => { stderr += chunk.length; if (stderr > 262144) { overflow = true; child.kill(); } });
	child.once('exit', () => { exited = true; });
	const completion = new Promise((resolve) => { child.once('error', () => { overflow = true; }); child.once('close', (code) => { closed = true; resolve({ closed: true, code }); }); });
	if (input !== null) child.stdin.end(input);
	return Object.freeze({ child, async join(milliseconds = 24000) {
		const joined = await boundedSettlement(completion, milliseconds); let cleanup;
		if (!joined.settled) cleanup = await closeOwnedChild(child, completion, Math.min(3000, 28000 - milliseconds));
		else { child.stdin.destroy(); child.stdout.destroy(); child.stderr.destroy(); cleanup = { resolved: true, pipes_destroyed: child.stdin.destroyed && child.stdout.destroyed && child.stderr.destroyed }; }
		return { closed, exited, code: joined.value?.code ?? null, timed_out: !joined.settled, stderr_bytes: stderr, overflow, cleanup_resolved: cleanup.resolved && cleanup.pipes_destroyed, pipes_destroyed: cleanup.pipes_destroyed, private_output: output };
	} });
}
