// One exact-confirmed selected attempt, only after a fresh independent genuine/no-fault sequence.
import { randomBytes } from 'node:crypto';
import { realpathSync } from 'node:fs';
import { resolveOwnedStdioRuntime } from './owned-stdio-runtime.mjs';
import { startNativeQualificationPhp } from './native-qualification-selected-process.mjs';
import { nativeSelectedIdentities, verifyNativeSelectedIdentity } from './native-qualification-selected-identity.mjs';
import { createNativeSelectedRegistry } from './native-qualification-selected-registry.mjs';
import { ownNativeQualificationControl } from './native-qualification-selected-control.mjs';
import { openNativeSelectedRelay } from './native-qualification-selected-relay.mjs';
import { boundedSettlement } from './selected-native-lifetime.mjs';
import { runNoFaultPrerequisite } from './native-qualification-selected-prerequisite.mjs';
import { selectedCleanupProof } from './selected-native-cleanup-proof.mjs';
const root = 'C:/Users/Captain/Local Sites/plugin-tester/app/public';
if (process.argv.length !== 3 || process.argv[2] !== '--confirmed-plugin-tester' || !process.env.MYSQL_PWD || realpathSync(root).replaceAll('\\', '/') !== root || !verifyNativeSelectedIdentity()) throw Error('qualification_explicit_scope');
const php = resolveOwnedStdioRuntime('plugin-tester'), source = nativeSelectedIdentities();
const prefix = 'wp_alynt_core_probe_' + randomBytes(6).toString('hex') + '_', marker = randomBytes(12).toString('hex');
let relay = null, worker = null, state = null, initial = null, transport = null, cleanup = null, watchdog = null, relayJoin = null, created = false, watched = false;
const result = { passed: false, response_suppression: true, production_wiring: false, private_qualification_proven: false };
const resolved = (s) => s.closed && s.exited && s.code === 0 && !s.timed_out && !s.overflow && s.stderr_bytes === 0 && s.cleanup_resolved && s.pipes_destroyed;
async function preflight() {
	if (!verifyNativeSelectedIdentity()) throw Error('qualification_source_changed');
	const p = startNativeQualificationPhp(php, 'native-qualification-selected-preflight.php', [], ''); const s = await p.join(8000); let m;
	try { m = JSON.parse(s.private_output); } catch { throw Error('qualification_preflight_output'); }
	if (!resolved(s) || m.passed !== true || m.wordpress_bootstrap !== false || JSON.stringify(m.source) !== JSON.stringify(source)) throw Error('qualification_preflight');
}
async function fixture(action, data = {}, scope = { prefix, marker }) {
	await preflight();
	const p = startNativeQualificationPhp(php, 'native-qualification-no-fault-fixtures.php', [action, realpathSync(root), '10004', scope.prefix, scope.marker], JSON.stringify(data)); const s = await p.join(12000);
	let value = null; try { value = JSON.parse(s.private_output); } catch { /* Inconclusive. Never echo private output or infer a receipt. */ }
	return { state: s, value };
}
try {
	result.cleanup_prerequisite = await selectedCleanupProof();
	if (result.cleanup_prerequisite.passed !== true) throw Error('selected_cleanup_prerequisite');
	result.prerequisite = await runNoFaultPrerequisite();
	if (!result.prerequisite.passed || !verifyNativeSelectedIdentity()) throw Error('selected_no_fault_prerequisite');
	initial = await fixture('create');
	if (!resolved(initial.state) || initial.value?.passed !== true) {
		result.fixture_process = { closed: initial.state.closed, exited: initial.state.exited, code: initial.state.code, timed_out: initial.state.timed_out, stderr_bytes: initial.state.stderr_bytes, cleanup_resolved: initial.state.cleanup_resolved };
		throw Error('qualification_fixture_create');
	} created = true;
	result.declaration_preflight_passed = true;
	const registry = createNativeSelectedRegistry(); relay = await openNativeSelectedRelay(10004, registry);
	worker = startNativeQualificationPhp(php, 'native-qualification-selected-worker.php', [root, '10004', prefix, marker, String(relay.port)]);
	const control = ownNativeQualificationControl(worker.child, registry, source);
	watchdog = setTimeout(() => { watched = true; control.abort(); worker.child.kill(); relayJoin = relay.close(); }, 24000);
	state = await worker.join(); const joined = await boundedSettlement(control.finish(), 750);
	const channel = joined.settled && !joined.rejected ? joined.value : { passed: false, pending_unresolved: true };
	transport = await (relayJoin ?? relay.close()); clearTimeout(watchdog); relay = null;
	const witness = registry.finish(transport.passed);
	result.worker = { closed: state.closed, exited: state.exited, code: state.code, timed_out: state.timed_out, stderr_bytes: state.stderr_bytes, pipes_destroyed: state.pipes_destroyed };
	result.channel = channel; result.witness = witness; result.transport = { ...transport, threads: undefined };
	result.native_passed = !watched && resolved(state) && channel.passed && witness.passed && transport.passed && !transport.observation_failed;
	if (!result.native_passed && state.private_output.length <= 65536) {
		const failures = state.private_output.split('\n').flatMap((line) => { try { const m=JSON.parse(line); return m?.passed===false && m.failure==='native_qualification_worker' && ['bootstrap','denials','native_root','root_result','final'].includes(m.stage) && Array.isArray(m.location) && m.location.length===2 && /^[A-Za-z0-9_-]+\.php$/.test(m.location[0]) && Number.isInteger(m.location[1]) && Number.isInteger(m.guards) && m.guards>=0 && m.guards<=24 ? [m] : []; } catch { return []; } });
		if (failures.length === 1) result.redacted_failure = failures[0];
	}
} catch (e) { result.failed = true; result.failure_stage = /^[a-z_]{1,48}$/.test(e.message) ? e.message : 'qualification_owned_failure'; }
finally {
	clearTimeout(watchdog); if (worker && !state) state = await worker.join(1000);
	if (relay) transport = await relay.close();
	if (created && (!worker || state?.closed && state?.cleanup_resolved && state?.pipes_destroyed) && transport?.cleanup_resolved && transport.listener_closed && transport.threads.every(Number.isInteger)) {
		try {
			const claims = { baseline: initial.value.baseline, threads: transport.threads, worker_closed: true, pipes_closed: true, relay_resolved: true, listener_closed: true };
			const refused = await fixture('cleanup', { ...claims, relay_resolved: false });
			result.unresolved_cleanup_refused = refused.state.closed && refused.state.cleanup_resolved && refused.state.code === 1 && refused.value?.passed === false && refused.value.fixtures_retained === true;
			cleanup = await fixture('cleanup', claims); result.cleanup = cleanup.value; result.cleanup_process_resolved = cleanup.state.cleanup_resolved;
		} catch { result.cleanup_unresolved = true; }
	} else if (created) result.cleanup_unresolved = true;
	if (created && (!cleanup?.value?.passed || !resolved(cleanup.state))) result.owned_fixture_prefix = prefix;
}
result.passed = result.prerequisite?.passed === true && result.native_passed === true && result.unresolved_cleanup_refused === true && cleanup?.value?.passed === true && resolved(cleanup.state);
result.private_qualification_proven = result.passed; // Only after transport, process and independent physical/application audit.
process.stdout.write(JSON.stringify({ stage: 'native_qualification_selected', ...result }) + '\n'); process.exitCode = result.passed ? 0 : 1;
