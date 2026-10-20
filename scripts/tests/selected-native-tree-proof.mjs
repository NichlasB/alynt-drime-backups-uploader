// Fixed owned descendant/stdio stall only. No WordPress, MySQL extension or network.
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { resolveOwnedStdioRuntime } from './owned-stdio-runtime.mjs';
if (process.argv.length !== 2) throw Error('fixed_tree_probe');
const php = resolveOwnedStdioRuntime('plugin-tester');
const disabled = 'exec,shell_exec,system,passthru,proc_open,popen,fsockopen,pfsockopen,stream_socket_client,stream_socket_server';
const child = spawn(php, ['-n', '-d', 'display_errors=0', '-d', 'log_errors=0', '-d', 'output_buffering=0', '-d', 'implicit_flush=1', '-d', 'disable_functions=' + disabled,
	fileURLToPath(new URL('./owned-stdio-worker.php', import.meta.url)), 'stall-read'], { shell: false, windowsHide: true, stdio: ['pipe', 'pipe', 'pipe'] });
child.stdin.on('error', () => {}); child.stderr.resume();
child.on('error', () => { process.exitCode = 1; });
let ready = '';
const observe = (bytes) => {
	ready += bytes.toString('ascii');
	if (ready.length > 64) { child.kill(); process.exitCode = 1; return; }
	if (!ready.includes('\n')) return;
	if (ready !== '{"v":1,"kind":"ready","seq":1}\n') { child.kill(); process.exitCode = 1; return; }
	child.stdout.off('data', observe);
	// Private operator pipe only. The .NET parent opens this exact process instance
	// before killing its OWN root tree; it never kills a PID found by enumeration.
	process.stdout.write(JSON.stringify({ kind: 'owned_tree_ready', child_pid: child.pid }) + '\n');
};
child.stdout.on('data', observe);
process.on('exit', () => { if (child.exitCode === null) child.kill(); });
