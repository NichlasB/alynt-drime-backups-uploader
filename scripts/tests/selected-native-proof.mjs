// Explicit fixed Plugin Tester owner. No CI import, endpoint or supplied fault mode.
import { randomBytes } from 'node:crypto';
import { realpathSync } from 'node:fs';
import { resolveOwnedStdioRuntime } from './owned-stdio-runtime.mjs';
import { startSelectedPhp, selectedFailureLocation } from './selected-native-process.mjs';
import { boundedSettlement } from './selected-native-lifetime.mjs';
import { createObservationRegistry } from './observation-native-registry.mjs';
import { ownObservationControl } from './observation-control-owner.mjs';
import { createSelectedNativeRegistry } from './selected-native-registry.mjs';
import { ownSelectedNativeControl } from './selected-native-control.mjs';
import { openSelectedNativeRelay, openSelectedNoFaultRelay } from './selected-native-relay.mjs';
import { selectedCleanupProof } from './selected-native-cleanup-proof.mjs';
const root = 'C:/Users/Captain/Local Sites/plugin-tester/app/public';
if (process.argv.length !== 3 || process.argv[2] !== '--confirmed-plugin-tester' || !process.env.MYSQL_PWD || realpathSync(root).replaceAll('\\', '/') !== root) throw Error('explicit_local_scope_required');
const php = resolveOwnedStdioRuntime('plugin-tester');
async function fixture(action, prefix, marker, data = {}) {
	const job = startSelectedPhp(php, 'selected-native-fixtures.php', [action, realpathSync(root), '10004', prefix, marker], JSON.stringify(data));
	const state = await job.join(12000); let value = null;
	try { value = JSON.parse(state.private_output); } catch { /* Refusal, not a guessed receipt. */ }
	return { state: { ...state, private_output: undefined }, value };
}
async function attempt(loss) {
	const prefix = 'wp_alynt_core_probe_' + randomBytes(6).toString('hex') + '_', marker = randomBytes(12).toString('hex');
	let relay = null, worker = null, created = false, initial = null, state = null, transport = null, channel = null, proof = null, cleanup = null, watchdog = null, relayJoin = null, watched = false;
	const result = { passed: false, response_loss_enabled: loss, production_wiring: false, private_qualification_proven: false };
	try {
		initial = await fixture('create', prefix, marker); if (!initial.value?.passed || !initial.state.cleanup_resolved || initial.state.stderr_bytes !== 0 || initial.state.code !== 0) throw Error('fixture_create'); created = true;
		const registry = loss ? createSelectedNativeRegistry() : createObservationRegistry();
		relay = await (loss ? openSelectedNativeRelay : openSelectedNoFaultRelay)(10004, registry);
		const args = [root, '10004', prefix, marker, String(relay.port)]; if (!loss) args.push('--observation-only');
		worker = startSelectedPhp(php, loss ? 'selected-native-worker.php' : 'relay-no-fault-worker.php', args);
		const control = loss ? ownSelectedNativeControl(worker.child, registry) : ownObservationControl(worker.child, registry);
		watchdog = setTimeout(() => { watched = true; worker.child.stdin.destroy(); worker.child.kill(); relayJoin = relay.close(); }, 24000);
		state = await worker.join();
		// No unbounded channel join after the lifetime/pipe boundary.
		const joined = await boundedSettlement(control.finish(), 750); channel = joined.settled && !joined.rejected ? joined.value : { passed: false, pending_unresolved: true };
		transport = await (relayJoin ?? relay.close()); clearTimeout(watchdog); relay = null; proof = registry.finish(transport.passed);
		result.worker = { closed: state.closed, exited: state.exited, code: state.code, timed_out: state.timed_out, stderr_bytes: state.stderr_bytes, pipes_destroyed: state.pipes_destroyed, redacted_failure_location: selectedFailureLocation(state.private_output, state.code) };
		result.channel = channel; result.witness = proof; result.transport = { ...transport, threads: undefined };
		result.native_passed = !watched && state.code === 0 && state.closed && state.exited && !state.timed_out && !state.overflow && state.stderr_bytes === 0 && channel.passed && proof.passed && transport.passed;
	} catch (error) { result.failed = true; result.failure_stage = /^[a-z_]{1,40}$/.test(error.message) ? error.message : 'owned_proof_failure'; }
	finally {
		clearTimeout(watchdog);
		if (worker && !state) state = await worker.join(1000);
		if (relay) { transport = await relay.close(); relay = null; }
		const safe = !worker || state?.closed && state?.cleanup_resolved && state?.pipes_destroyed;
		if (created && safe && transport?.cleanup_resolved && transport.listener_closed && transport.threads.every(Number.isInteger)) {
			const claims = { baseline: initial.value.baseline, threads: transport.threads, worker_closed: true, pipes_closed: true, relay_resolved: true, listener_closed: true };
			if (!loss) {
				const refusal = await fixture('cleanup', prefix, marker, { ...claims, relay_resolved: false });
				result.unresolved_cleanup_refused = refusal.state.cleanup_resolved && refusal.state.code === 1 && refusal.value?.passed === false && refusal.value.fixtures_retained === true;
			}
			cleanup = await fixture('cleanup', prefix, marker, claims);
			result.cleanup = cleanup.value; result.cleanup_process_resolved = cleanup.state.cleanup_resolved;
		} else if (created) { result.fixtures_retained = true; result.owned_fixture_prefix = prefix; }
	}
	result.passed = result.native_passed === true && (loss || result.unresolved_cleanup_refused === true) && cleanup?.value?.passed === true && cleanup.state.code === 0 && cleanup.state.stderr_bytes === 0 && cleanup.state.cleanup_resolved;
	return result;
}
const boundedCleanup = await selectedCleanupProof(); process.stdout.write(JSON.stringify({ stage: 'owned_unresolved_cleanup_prerequisite', ...boundedCleanup }) + '\n');
const noFault = await attempt(false);
process.stdout.write(JSON.stringify({ stage: 'no_fault', ...noFault }) + '\n');
// Compiled prerequisite gate. Never retry or induce loss after any failed conjunct.
if (!noFault.passed) process.exitCode = 1;
else {
	const selected = await attempt(true); process.stdout.write(JSON.stringify({ stage: 'single_selected_response_loss', ...selected }) + '\n'); process.exitCode = selected.passed ? 0 : 1;
}
