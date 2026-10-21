import test from 'node:test';
import assert from 'node:assert/strict';
import { EventEmitter } from 'node:events';
import { PassThrough } from 'node:stream';
import { readFileSync } from 'node:fs';
import { noFaultWaitMs, runNoFaultPrerequisite, settleNoFaultPrerequisite } from '../../scripts/tests/selected-qualification-prerequisite.mjs';
// Fake owned streams/events, not a real child, WordPress, database or cleanup proof.
const receipt = () => ({ stage: 'qualification_no_fault', passed: true, response_suppression: false, production_wiring: false, private_qualification_proven: true, guard_prelude: { passed: true, checks: 266, groups: 11 }, unresolved_cleanup_refused: true, cleanup: { passed: true, all_application_checksums_unchanged: true } });
function childFixture() {
	const child = new EventEmitter();
	Object.assign(child, { stdin: new PassThrough(), stdout: new PassThrough(), stderr: new PassThrough(), exitCode: null, signalCode: null, killed: 0 });
	child.kill = () => { child.killed++; child.signalCode = 'SIGTERM'; queueMicrotask(() => { child.emit('exit', null); child.emit('close', null); }); return true; };
	return child;
}
async function observed(fault = '', change = () => {}) {
	const child = childFixture(), r = receipt(); change(r);
	const pending = settleNoFaultPrerequisite(child);
	if (fault === 'stderr') child.stderr.write('non-secret diagnostic');
	if (fault === 'overflow') child.stdout.write(Buffer.alloc(65537));
	else child.stdout.write(fault === 'json' ? 'invalid' : JSON.stringify(r));
	if (fault === 'error') child.emit('error', Error('owned'));
	child.exitCode = fault === 'code' ? 1 : 0;
	if (fault !== 'exit') child.emit('exit', child.exitCode);
	child.emit('close', child.exitCode);
	return { child, result: await pending };
}
test('fixed wait fits the existing join budget and invalid invocation refuses before spawning', async () => {
	assert.equal(noFaultWaitMs, 24000); assert.ok(noFaultWaitMs <= 28000);
	await assert.rejects(runNoFaultPrerequisite('provider'), /selected_prerequisite_arguments/);
	const text = readFileSync(new URL('../../scripts/tests/selected-qualification-prerequisite.mjs', import.meta.url), 'utf8');
	assert.ok(text.indexOf('noFaultWaitMs > 28000') < text.indexOf('const child = spawn'));
});
test('owned successful child requires exit and close, preserves receipt, and destroys every pipe', async () => {
	const { child, result } = await observed();
	assert.equal(result.passed, true); assert.equal(result.process_resolved, true);
	assert.deepEqual(result.no_fault, receipt()); assert.equal(result.response_loss_attempted, false);
	assert.equal(child.killed, 0);
	for (const pipe of [child.stdin, child.stdout, child.stderr]) assert.equal(pipe.destroyed, true);
});
for (const fault of ['stderr', 'overflow', 'json', 'error', 'code', 'exit']) test('prerequisite refuses ' + fault + ' without leaving pipes', async () => {
	const { child, result } = await observed(fault); assert.equal(result.passed, false);
	for (const pipe of [child.stdin, child.stdout, child.stderr]) assert.equal(pipe.destroyed, true);
});
for (const key of ['passed', 'private_qualification_proven', 'unresolved_cleanup_refused']) test('missing prerequisite conjunct ' + key + ' cannot permit selection', async () => {
	assert.equal((await observed('', (r) => { r[key] = false; })).result.passed, false);
});
for (const group of ['guard_prelude', 'cleanup']) test('failed physical prerequisite ' + group + ' stays failed', async () => {
	assert.equal((await observed('', (r) => { r[group].passed = false; })).result.passed, false);
});
test('no-fault prerequisite never accepts suppression, wiring or checksum drift', async () => {
	for (const key of ['response_suppression', 'production_wiring']) assert.equal((await observed('', (r) => { r[key] = true; })).result.passed, false);
	assert.equal((await observed('', (r) => { r.cleanup.all_application_checksums_unchanged = false; })).result.passed, false);
});
test('timed-out owned child is joined and every pipe destroyed without promoting a printed receipt', { timeout: 28000 }, async () => {
	const child = childFixture(), pending = settleNoFaultPrerequisite(child);
	child.stdout.write(JSON.stringify(receipt())); // No exit/close; cannot count as completion.
	const result = await pending;
	assert.equal(result.passed, false); assert.equal(result.process_resolved, false);
	assert.equal(child.killed, 1);
	for (const pipe of [child.stdin, child.stdout, child.stderr]) assert.equal(pipe.destroyed, true);
});
