// Fixed aggregate-owned cases only; no arbitrary mode/SQL/provider from CLI or IPC.
import { randomBytes } from 'node:crypto';
import { realpathSync } from 'node:fs';
import { resolveOwnedStdioRuntime } from './owned-stdio-runtime.mjs';
import { startNativeQualificationPhp } from './uncertain-barrier-native-process.mjs';
import { uncertainNativeIdentities, verifyUncertainNativeIdentity } from './uncertain-barrier-native-identity.mjs';
import { createNativeQualificationRegistry } from './native-qualification-no-fault-registry.mjs';
import { ownNativeQualificationControl } from './uncertain-barrier-native-control.mjs';
import { openNativeQualificationRelay } from './native-qualification-no-fault-relay.mjs';
import { boundedSettlement } from './selected-native-lifetime.mjs';
import { createUncertainNativeRegistry } from './uncertain-barrier-native-registry.mjs';
import { openNativeSelectedRelay } from './native-qualification-selected-relay.mjs';
import { startUncertainNativeHelpers } from './uncertain-barrier-native-helpers.mjs';
import { parseQualificationPrelude } from './qualification-native-grammar.mjs';
export async function runUncertainNativeCase(profile) {
if(arguments.length!==1||!['no_fault','selected','released','held'].includes(profile))throw Error('fixed_case');
const root = 'C:/Users/Captain/Local Sites/plugin-tester/app/public';
if (process.argv.length !== 3 || process.argv[2] !== '--confirmed-plugin-tester' || !process.env.MYSQL_PWD || realpathSync(root).replaceAll('\\', '/') !== root || !verifyUncertainNativeIdentity()) throw Error('qualification_explicit_scope');
const php = resolveOwnedStdioRuntime('plugin-tester'), source = uncertainNativeIdentities();
const prefix = 'wp_alynt_core_probe_' + randomBytes(6).toString('hex') + '_', marker = randomBytes(12).toString('hex');
let helpers=null, helperReceipt=null, relay = null, worker = null, state = null, initial = null, transport = null, cleanup = null, watchdog = null, relayJoin = null, created = false, watched = false;
const result = { passed: false, profile, response_suppression: profile!=='no_fault', production_wiring: false, private_qualification_proven: false };
const resolved = (s) => s.closed && s.exited && s.code === 0 && !s.timed_out && !s.overflow && s.stderr_bytes === 0 && s.cleanup_resolved && s.pipes_destroyed;
async function preflight() {
	if (!verifyUncertainNativeIdentity()) throw Error('qualification_source_changed');
	const p = startNativeQualificationPhp(php, 'uncertain-barrier-native-preflight.php', [], ''); const s = await p.join(8000); let m;
	try { m = JSON.parse(s.private_output); } catch { throw Error('qualification_preflight_output'); }
	if (!resolved(s) || m.passed !== true || m.wordpress_bootstrap !== false || JSON.stringify(m.source) !== JSON.stringify(source)) throw Error('qualification_preflight');
}
async function fixture(action, data = {}, scope = { prefix, marker }) {
	await preflight();
	const p = startNativeQualificationPhp(php, 'uncertain-barrier-native-fixtures.php', [action, realpathSync(root), '10004', scope.prefix, scope.marker], JSON.stringify(data)); const s = await p.join(12000);
	let value = null; try { value = JSON.parse(s.private_output); } catch { /* Inconclusive. Never echo private output or infer a receipt. */ }
	return { state: s, value };
}
async function guardPrelude() {
	const scope = { prefix: 'wp_alynt_core_probe_' + randomBytes(6).toString('hex') + '_', marker: randomBytes(12).toString('hex') };
	if (scope.prefix === prefix || scope.marker === marker) throw Error('qualification_fixture_collision');
	let before = null, child = null, settled = null, receipt = null, cleaned = null, owned = false;
	const report = result.guard_prelude = { passed: false, qualification_attempted: false, fixtures_removed: false };
	try {
		before = await fixture('create', {}, scope);
		if (!resolved(before.state) || before.value?.passed !== true) throw Error('qualification_prelude_fixture');
		owned = true;
		child = startNativeQualificationPhp(php, 'qualification-native-prelude.php', [root, '10004', scope.prefix, scope.marker], '');
		settled = await child.join(10000);
		receipt = parseQualificationPrelude(settled.private_output.endsWith('\n') ? settled.private_output.slice(0, -1) : '');
		if (receipt?.passed === true) { report.checks = receipt.checks; report.groups = receipt.cases.length; }
	} catch { report.failed = true; }
	finally {
		if (child && !settled) settled = await child.join(1000);
		if (settled) report.process = { closed: settled.closed, exited: settled.exited, code: settled.code, timed_out: settled.timed_out, stderr_bytes: settled.stderr_bytes, pipes_destroyed: settled.pipes_destroyed };
		if (owned && settled?.closed && settled.cleanup_resolved && settled.pipes_destroyed && receipt) {
			try {
				cleaned = await fixture('cleanup', { baseline: before.value.baseline, threads: [receipt.auth_thread], worker_closed: true, pipes_closed: true, relay_resolved: true, listener_closed: true }, scope);
				report.cleanup = cleaned.value; report.fixtures_removed = cleaned.value?.cleanup?.length === 3 && cleaned.value.cleanup.every((r) => r.absent === true);
			} catch { report.cleanup_unresolved = true; }
		} else if (owned) report.cleanup_unresolved = true;
		if (owned && !report.fixtures_removed) report.owned_fixture_prefix = scope.prefix;
	}
	report.passed = !report.failed && receipt?.passed === true && resolved(settled) && cleaned?.value?.passed === true && resolved(cleaned.state);
	if (!report.passed) throw Error('qualification_prelude');
}
try {
	if(profile==='no_fault') await guardPrelude(); // Separate process, fixtures, sessions and audit; no qualification relay exists yet.
	initial = await fixture('create');
	if (!resolved(initial.state) || initial.value?.passed !== true) {
		result.fixture_process = { closed: initial.state.closed, exited: initial.state.exited, code: initial.state.code, timed_out: initial.state.timed_out, stderr_bytes: initial.state.stderr_bytes, cleanup_resolved: initial.state.cleanup_resolved };
		throw Error('qualification_fixture_create');
	} created = true;
	result.declaration_preflight_passed = true;
	const registry = profile==='no_fault'?createNativeQualificationRegistry():createUncertainNativeRegistry(profile);
    if(['released','held'].includes(profile))helpers=await startUncertainNativeHelpers(php,[root,'10004',prefix,marker],profile);
    relay = await (profile==='no_fault'?openNativeQualificationRelay:openNativeSelectedRelay)(10004, registry);
	worker = startNativeQualificationPhp(php, ({no_fault:'uncertain-barrier-native-committed.php',selected:'uncertain-barrier-native-selected.php',released:'uncertain-barrier-native-released.php',held:'uncertain-barrier-native-held.php'})[profile], [root, '10004', prefix, marker, String(relay.port)]);
	const control = ownNativeQualificationControl(worker.child, registry, source,profile,helpers);
	watchdog = setTimeout(() => { watched = true; control.abort(); worker.child.kill(); relayJoin = relay.close(); }, 24000);
	state = await worker.join(); const joined = await boundedSettlement(control.finish(), 750);
	const channel = joined.settled && !joined.rejected ? joined.value : { passed: false, pending_unresolved: true };
	transport = await (relayJoin ?? relay.close()); clearTimeout(watchdog); relay = null;
	const witness = registry.finish(transport.passed);
	result.worker = { closed: state.closed, exited: state.exited, code: state.code, timed_out: state.timed_out, stderr_bytes: state.stderr_bytes, pipes_destroyed: state.pipes_destroyed };
	if(helpers){helperReceipt=await helpers.finish();result.direct={...helperReceipt,threads:undefined};}
    result.channel = channel; result.witness = witness; result.transport = { ...transport, threads: undefined };
	result.native_passed = !watched && resolved(state) && channel.passed && witness.passed && transport.passed && !transport.observation_failed && (!helpers||helperReceipt?.passed===true);
	if (!result.native_passed && state.private_output.length <= 65536) {
		const failures = state.private_output.split('\n').flatMap((line) => { try { const m=JSON.parse(line); return m?.passed===false && m.failure==='native_qualification_worker' && ['bootstrap','denials','native_root','root_result','final'].includes(m.stage) && Array.isArray(m.location) && m.location.length===2 && /^[A-Za-z0-9_-]+\.php$/.test(m.location[0]) && Number.isInteger(m.location[1]) && Number.isInteger(m.guards) && m.guards>=0 && m.guards<=24 ? [m] : []; } catch { return []; } });
		if (failures.length === 1) result.redacted_failure = failures[0];
	}
} catch (e) { result.failed = true; result.failure_stage = /^[a-z_]{1,48}$/.test(e.message) ? e.message : 'qualification_owned_failure'; }
finally {
	clearTimeout(watchdog); if (worker && !state) state = await worker.join(1000);
	if (relay) transport = await relay.close();
    if(helpers&&!helperReceipt){helperReceipt=await helpers.finish();result.direct={...helperReceipt,threads:undefined};}
	if (created && (!worker || state?.closed && state?.cleanup_resolved && state?.pipes_destroyed) && transport?.cleanup_resolved && transport.listener_closed && transport.threads.every(Number.isInteger) && (!helpers||helperReceipt?.cleanup_resolved===true)) {
		try {
			const claims = { baseline: initial.value.baseline, threads: [...transport.threads,...(helperReceipt?.threads??[])], worker_closed: true, pipes_closed: true, relay_resolved: true, listener_closed: true };
			const refused = await fixture('cleanup', { ...claims, relay_resolved: false });
			result.unresolved_cleanup_refused = refused.state.closed && refused.state.cleanup_resolved && refused.state.code === 1 && refused.value?.passed === false && refused.value.fixtures_retained === true;
			cleanup = await fixture('cleanup', claims); result.cleanup = cleanup.value; result.cleanup_process_resolved = cleanup.state.cleanup_resolved;
		} catch { result.cleanup_unresolved = true; }
	} else if (created) result.cleanup_unresolved = true;
	if (created && (!cleanup?.value?.passed || !resolved(cleanup.state))) result.owned_fixture_prefix = prefix;
}
result.passed = (profile!=='no_fault'||result.guard_prelude?.passed === true) && result.native_passed === true && result.unresolved_cleanup_refused === true && cleanup?.value?.passed === true && resolved(cleanup.state);
result.private_qualification_proven = result.passed; // Only after transport, process and independent physical/application audit.
result.successful_barrier = result.passed && profile!=='held';
result.expected_refusal = result.passed && profile==='held'; // Historical qualifier only; never successful observation/closure.
return result;
}
