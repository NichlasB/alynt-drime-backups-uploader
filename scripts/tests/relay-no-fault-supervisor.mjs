// Invoked only by the confirmed three-table PHP owner, never via a site endpoint.
import { readFileSync, realpathSync } from 'node:fs';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { openNoFaultRelay } from './relay-no-fault-server.mjs';
import { boundRelayRoles } from './relay-no-fault-receipt.mjs';

const [php, ext, root, backendText, prefix, marker] = process.argv.slice(2);
let relay = null, receipt = { passed: false }, worker = null, phase = 'target_validation';
try {
	const expectedRoot = 'C:/Users/Captain/Local Sites/plugin-tester/app/public';
	const services = 'C:/Users/Captain/AppData/Roaming/Local/lightning-services/';
	const sites = Object.values(JSON.parse(readFileSync('C:/Users/Captain/AppData/Roaming/Local/sites.json', 'utf8')));
	const matches = sites.filter((s) => s.domain === 'plugin-tester.local' && realpathSync(s.path + '/app/public') === realpathSync(expectedRoot));
	if (process.argv.length !== 8 || matches.length !== 1 || realpathSync(root) !== realpathSync(expectedRoot) || !/^[0-9]{1,5}$/.test(backendText) ||
		Number(backendText) !== matches[0].services.mariadb.ports.MYSQL[0] || !/^wp_alynt_core_probe_[a-f0-9]{12}_$/.test(prefix) || !/^[a-f0-9]{24}$/.test(marker) || !process.env.MYSQL_PWD) throw Error('target');
	const phpPath = realpathSync(php).replaceAll('\\', '/');
	if (!phpPath.startsWith(services + 'php-' + matches[0].services.php.version + '+') || !phpPath.endsWith('/bin/win64/php.exe') || realpathSync(ext) !== realpathSync(phpPath.slice(0, -7) + 'ext')) throw Error('runtime');
	phase = 'listener_start'; relay = await openNoFaultRelay(Number(backendText));
	const args = ['-d', 'extension_dir=' + ext, '-d', 'extension=mysqli', '-d', 'display_errors=0', '-d', 'log_errors=0', fileURLToPath(new URL('./relay-no-fault-worker.php', import.meta.url)), root, backendText, prefix, marker, String(relay.port)];
	let output = '', errors = 0, overflow = false;
	phase = 'worker_start'; const result = await new Promise((resolve) => {
		worker = spawn(php, args, { windowsHide: true, stdio: ['pipe', 'pipe', 'pipe'] });
		worker.stdin.end();
		const timer = setTimeout(() => { overflow = true; worker.kill(); }, 24000);
		worker.stdout.on('data', (chunk) => { if (Buffer.byteLength(output) + chunk.length > 65536) { overflow = true; worker.kill(); } else output += chunk.toString('utf8'); });
		worker.stderr.on('data', (chunk) => { errors += chunk.length; });
		worker.on('error', () => { overflow = true; });
		worker.on('close', (code) => { clearTimeout(timer); resolve(code); });
	});
	phase = 'worker_receipt'; receipt.worker_exit = result; receipt.output_bytes = Buffer.byteLength(output); receipt.stderr_bytes = errors;
	receipt.invalid_output_kind = output.includes('Usage: php') ? 'cli_usage' : output.includes('<html') || output.includes('<!DOCTYPE') ? 'html_refusal' : 'invalid_json_redacted';
	const child = JSON.parse(output);
	const transport = await relay.close(); relay = null;
	const ids = child.relay_roles;
	const roleBound = boundRelayRoles(ids, transport.threads);
	delete child.relay_roles;
	receipt = { passed: result === 0 && !overflow && errors === 0 && child.passed === true && transport.passed && roleBound, worker: child, transport: { ...transport, roles_bound_by_genuine_thread: !!roleBound }, owned_threads: transport.threads };
	delete receipt.transport.threads;
} catch { receipt.failed = true; receipt.failure = 'Owned relay proof failed; private diagnostics withheld.'; receipt.failure_stage = phase; }
finally {
	if (relay) { try { const state = await relay.close(); receipt.owned_threads = state.threads; delete state.threads; receipt.transport = state; receipt.listener_closed = state.listener_closed; } catch { receipt.cleanup_unresolved = true; } }
}
process.stdout.write(JSON.stringify(receipt) + '\n');
process.exitCode = receipt.passed ? 0 : 1;
