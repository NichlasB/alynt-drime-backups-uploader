// Fixed proof-only child ownership; PHP workers cannot create descendant processes.
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { boundedSettlement, closeOwnedChild } from './selected-native-lifetime.mjs';
const files = ['selected-native-fixtures.php', 'selected-native-worker.php', 'relay-no-fault-worker.php'];
// Never expose private protocol lines, messages, paths, SQL or arbitrary worker JSON.
export function selectedFailureLocation(output, code) {
	if (code === 0 || typeof output !== 'string' || Buffer.byteLength(output) > 65536 || !output.endsWith('\n')) return null;
	const locations = [];
	for (const line of output.split('\n')) {
		if (line.length > 256) continue;
		let value; try { value = JSON.parse(line); } catch { continue; }
		if (!value || JSON.stringify(Object.keys(value)) !== '["passed","redacted_failure","location"]' || value.passed !== false || value.redacted_failure !== true || !Array.isArray(value.location) || value.location.length !== 2) continue;
		const [file, number] = value.location;
		if (!['selected-native-cases.php', 'selected-native-channel.php', 'selected-native-worker.php'].includes(file) || !Number.isInteger(number) || number < 1 || number > 10000) continue;
		locations.push({ file, line: number });
	}
	return locations.length === 1 ? locations[0] : null;
}
export function startSelectedPhp(php, file, args, input = null) {
	if (!files.includes(file) || !Array.isArray(args)) throw Error('fixed_child');
	const ext = php.slice(0, -7) + 'ext';
	const child = spawn(php, ['-d', 'extension_dir=' + ext, '-d', 'extension=mysqli', '-d', 'display_errors=0', '-d', 'log_errors=0', '-d', 'output_buffering=0', '-d', 'implicit_flush=1',
		'-d', 'disable_functions=exec,shell_exec,system,passthru,proc_open,popen', fileURLToPath(new URL('./' + file, import.meta.url)), ...args], { shell: false, windowsHide: true, stdio: ['pipe', 'pipe', 'pipe'] });
	let closed = false, exited = false, stderr = 0, output = '', overflow = false;
	child.stdin.on('error', () => {}); // The channel/fixture receipt separately observes refusal.
	child.stdout.on('data', (chunk) => { if (Buffer.byteLength(output) + chunk.length > 65536) { overflow = true; child.kill(); } else output += chunk.toString('utf8'); });
	child.stdout.on('error', () => { overflow = true; }); child.stderr.on('error', () => { overflow = true; });
	child.stderr.on('data', (chunk) => { stderr += chunk.length; if (stderr > 262144) { overflow = true; child.kill(); } });
	child.once('exit', () => { exited = true; });
	const completion = new Promise((resolve) => {
		child.once('error', () => { overflow = true; });
		child.once('close', (code) => { closed = true; resolve({ closed: true, code }); });
	});
	if (input !== null) child.stdin.end(input);
	return Object.freeze({ child, completion,
		async join(milliseconds = 24000) {
			const result = await boundedSettlement(completion, milliseconds);
			let cleanup = { resolved: closed, pipes_destroyed: false };
			if (!result.settled) cleanup = await closeOwnedChild(child, completion, Math.min(3000, 28000 - milliseconds));
			else { child.stdin.destroy(); child.stdout.destroy(); child.stderr.destroy(); cleanup.pipes_destroyed = child.stdin.destroyed && child.stdout.destroyed && child.stderr.destroyed; }
			return { closed, exited, code: result.value?.code ?? null, timed_out: !result.settled, stderr_bytes: stderr, overflow,
				cleanup_resolved: cleanup.resolved && cleanup.pipes_destroyed, pipes_destroyed: cleanup.pipes_destroyed, private_output: output };
		},
	});
}
