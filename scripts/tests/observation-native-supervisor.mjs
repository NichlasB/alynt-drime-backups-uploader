// Fixed Plugin Tester observation-only entry, invoked by the existing three-table owner.
import { realpathSync } from 'node:fs';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { resolveOwnedStdioRuntime } from './owned-stdio-runtime.mjs';
import { openNoFaultRelay } from './relay-no-fault-server.mjs';
import { createObservationRegistry } from './observation-native-registry.mjs';
import { ownObservationControl } from './observation-control-owner.mjs';

const [php, ext, root, backendText, prefix, marker] = process.argv.slice(2);
let relay = null, worker = null, closed = false, receipt = { passed: false }, phase = 'target';
try {
	if (process.argv.length !== 8 || realpathSync(php) !== realpathSync(resolveOwnedStdioRuntime('plugin-tester')) ||
		realpathSync(ext) !== realpathSync(php.slice(0, -7) + 'ext') || realpathSync(root) !== realpathSync('C:/Users/Captain/Local Sites/plugin-tester/app/public') ||
		backendText !== '10004' || !/^wp_alynt_core_probe_[a-f0-9]{12}_$/.test(prefix) || !/^[a-f0-9]{24}$/.test(marker) || !process.env.MYSQL_PWD) throw Error('target');
	const registry = createObservationRegistry(); phase = 'listener'; relay = await openNoFaultRelay(10004, registry);
	const args = ['-d', 'extension_dir=' + ext, '-d', 'extension=mysqli', '-d', 'display_errors=0', '-d', 'log_errors=0', '-d', 'output_buffering=0', '-d', 'implicit_flush=1', fileURLToPath(new URL('./relay-no-fault-worker.php', import.meta.url)), root, backendText, prefix, marker, String(relay.port), '--observation-only'];
	phase = 'native_worker'; let errors = 0, timedOut = false;
	worker = spawn(php, args, { shell: false, windowsHide: true, stdio: ['pipe', 'pipe', 'pipe'] });
	const channel = ownObservationControl(worker, registry);
	worker.stderr.on('data', (chunk) => { errors += chunk.length; if (errors > 262144 && !worker.killed) worker.kill(); });
	worker.stderr.on('error', () => { errors++; });
	worker.on('error', () => { errors++; });
	const code = await new Promise((resolve, reject) => {
		const timer = setTimeout(() => { timedOut = true; if (!worker.killed) worker.kill(); }, 24000);
		const cleanup = setTimeout(() => { clearTimeout(timer); reject(Error('owned_cleanup_unresolved')); }, 28000);
		worker.once('close', (value) => { closed = true; clearTimeout(timer); clearTimeout(cleanup); resolve(value); });
	});
	phase = 'reconcile'; const control = await channel.finish();
	worker.stdin.destroy(); worker.stdout.destroy(); worker.stderr.destroy();
	const transport = await relay.close(); relay = null;
	const observation = registry.finish(transport.passed);
	receipt = { passed: code === 0 && closed && !timedOut && errors === 0 && control.passed && transport.passed && !transport.observation_failed && observation.passed,
		worker_exit: code, worker_closed: closed, pipes_destroyed: worker.stdin.destroyed && worker.stdout.destroyed && worker.stderr.destroyed,
		stderr_bytes: errors, timed_out: timedOut, control, observation, transport: { ...transport }, owned_threads: transport.threads, production_wiring: false, response_suppression: false };
	delete receipt.transport.threads;
} catch { receipt.failed = true; receipt.failure_stage = phase; }
finally {
	if (worker && !closed) {
		try {
			const ended = new Promise((resolve) => { const timer = setTimeout(() => resolve(false), 3000); worker.once('close', () => { closed = true; clearTimeout(timer); resolve(true); }); });
			worker.kill(); if (!await ended) receipt.cleanup_unresolved = true;
		} catch { receipt.cleanup_unresolved = true; }
	}
	if (worker) { worker.stdin.destroy(); worker.stdout.destroy(); worker.stderr.destroy(); receipt.worker_closed = closed; receipt.pipes_destroyed = worker.stdin.destroyed && worker.stdout.destroyed && worker.stderr.destroyed; }
	if (relay) { try { const state = await relay.close(); receipt.owned_threads = state.threads; delete state.threads; receipt.transport = state; receipt.listener_closed = state.listener_closed; } catch { receipt.cleanup_unresolved = true; } }
}
process.stdout.write(JSON.stringify(receipt) + '\n'); process.exitCode = receipt.passed ? 0 : 1;
