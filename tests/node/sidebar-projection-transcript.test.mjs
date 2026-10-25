import test from 'node:test';
import assert from 'node:assert/strict';
import { createSidebarProjectionShutdownTranscript as create, parseSidebarProjectionCandidate as parse,
	sidebarProjectionProfile as profile, projectionProfile as oldProfile, parseProjectionCandidate as oldParse,
	createProjectionShutdownTranscript as oldCreate } from '../../scripts/tests/projection-shutdown-transcript.mjs';

const root = 'a'.repeat(24);
const frame = (mode = 'autoload') => ['alynt.projection.candidate', 1, root, mode, profile, 'core_shutdown_returned', [17,17,1,29,8,8,140,0,0,0,1,1], 'modeled_only'];
const orders = [['close','stdout_eof','stderr_eof'],['close','stderr_eof','stdout_eof'],['stdout_eof','close','stderr_eof'],['stdout_eof','stderr_eof','close'],['stderr_eof','close','stdout_eof'],['stderr_eof','stdout_eof','close']];
function ready(mode = 'autoload', start = 0) {
	const p = create(root, mode, start);
	assert.equal(p.event('fenced', profile, start), true);
	assert.equal(p.event('core_returned', true, start + 1), true);
	assert.equal(p.event('core_shutdown_returned', [1,1], start + 2), true);
	return p;
}
function closed(p, order = orders[0], tick = 10) {
	for (const kind of order) assert.equal(p.event(kind, kind === 'close' ? [0,null] : null, tick), true);
	return p.finish(tick + 1);
}
function authorityFree(result, accepted) {
	assert.equal(result.synthetic_match, accepted);
	assert.equal(Object.keys(result).length, 8);
	for (const [key, value] of Object.entries(result)) if (key !== 'synthetic_match') assert.equal(value, false, key);
}
for (const mode of ['autoload','fallback']) for (const order of orders) for (const width of [1,7,4096]) test(`v2 synthetic ${mode}/${order.join('/')}/${width}`, () => {
	const f = frame(mode), bytes = JSON.stringify(f) + '\n';
	assert.deepEqual(parse(bytes, root, mode), f);
	assert.equal(oldParse(bytes, root, mode), false);
	const p = ready(mode);
	for (let at = 0; at < bytes.length; at += width) assert.equal(p.event('stdout', bytes.slice(at, at + width), 3), true);
	authorityFree(closed(p, order), true);
	assert.equal(p.finish(12), false);
});
test('fixed wrappers cannot select or silently promote another profile', () => {
	const bytes = JSON.stringify(frame()) + '\n';
	const old = frame(); old[4] = oldProfile;
	assert.notEqual(profile, oldProfile);
	assert.equal(parse(JSON.stringify(old) + '\n', root, 'autoload'), false);
	assert.notEqual(oldParse(JSON.stringify(old) + '\n', root, 'autoload'), false);
	for (const args of [[], [root,'autoload'], [root,'autoload',0,profile], [root,'other',0], [root,'autoload',-1]]) {
		assert.equal(create(...args), false);
	}
	assert.equal(parse(bytes, root, 'autoload', profile), false);
	const p = oldCreate(root, 'autoload', 0);
	assert.equal(p.event('fenced', profile, 0), false);
	authorityFree(p.finish(1), false);
});
