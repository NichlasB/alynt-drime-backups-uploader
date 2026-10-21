// Fixed unchanged no-fault qualification program. No supplied executable, path or fault mode.
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { boundedSettlement, closeOwnedChild } from './selected-native-lifetime.mjs';
export const noFaultWaitMs = 24000;
// Owned pipe/process observation is separable for synthetic tests, never a WordPress entry.
export async function settleNoFaultPrerequisite(child) {
	let text = '', errors = 0, overflow = false, exited = false, closed = false, state = null, cleanup = null;
	child.stdin.on('error', () => { overflow = true; }); child.stdout.on('error', () => { overflow = true; }); child.stderr.on('error', () => { overflow = true; });
	child.stdout.on('data', (b) => { if (Buffer.byteLength(text) + b.length > 65536) { overflow = true; child.kill(); } else text += b.toString('utf8'); });
	child.stderr.on('data', (b) => { errors += b.length; if (errors > 262144) { overflow = true; child.kill(); } });
	child.once('exit', () => { exited = true; });
	const completion = new Promise((resolve) => { child.once('error', () => { overflow = true; }); child.once('close', (code) => { closed = true; resolve({ closed: true, code }); }); });
	try {
		child.stdin.end();
		state = await boundedSettlement(completion, noFaultWaitMs);
	} finally {
		try {
			if (!state?.settled) cleanup = await closeOwnedChild(child, completion, 3000);
			else cleanup = { resolved: true };
		} finally {
			child.stdin.destroy(); child.stdout.destroy(); child.stderr.destroy();
		}
	}
	// Killing a parent or settling pipes never proves descendant or fixture cleanup.
	const resolved = state?.settled === true && !state.rejected && exited && closed && state.value?.code === 0 && !overflow && errors === 0 && cleanup?.resolved === true && child.stdin.destroyed && child.stdout.destroyed && child.stderr.destroyed;
	let receipt = null; try { receipt = JSON.parse(text); } catch { /* Refusal, not success. No private raw output exported. */ }
	const passed = resolved && receipt?.stage === 'qualification_no_fault' && receipt.passed === true && receipt.response_suppression === false && receipt.production_wiring === false && receipt.private_qualification_proven === true && receipt.guard_prelude?.passed === true && receipt.unresolved_cleanup_refused === true && receipt.cleanup?.passed === true && receipt.cleanup.all_application_checksums_unchanged === true;
	return Object.freeze({ passed, process_resolved: resolved, genuine_prelude_checks: receipt?.guard_prelude?.checks ?? 0, genuine_prelude_groups: receipt?.guard_prelude?.groups ?? 0, no_fault: receipt, response_loss_attempted: false });
}
export async function runNoFaultPrerequisite() {
	if (arguments.length || !Number.isInteger(noFaultWaitMs) || noFaultWaitMs < 1 || noFaultWaitMs > 28000) throw Error('selected_prerequisite_arguments');
	// Validate the fixed helper budget before creating any owned native-capable child.
	const child = spawn(process.execPath, [fileURLToPath(new URL('./qualification-native-proof.mjs', import.meta.url)), '--confirmed-plugin-tester'], { shell: false, windowsHide: true, stdio: ['pipe', 'pipe', 'pipe'] });
	return settleNoFaultPrerequisite(child);
}
