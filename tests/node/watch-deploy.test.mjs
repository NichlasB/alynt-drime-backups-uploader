import assert from 'node:assert/strict';
import { mkdtemp, writeFile, mkdir, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import test from 'node:test';
import { createDeploymentWatcher, ignoredFile } from '../../scripts/watch-deploy.mjs';

const pause = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
async function waitFor(condition) {
	const deadline = Date.now() + 5000;
	while (!condition()) {
		if (Date.now() > deadline) throw new Error('Watcher did not reach the expected state.');
		await pause(20);
	}
}

test('watch scope prunes ignored directories and unrelated file extensions', () => {
	const root = path.resolve('fixture');
	const file = { isFile: () => true };
	const directory = { isFile: () => false };
	for (const name of ['node_modules/a.js', '.git/a.php', 'scripts/a.js', '.env']) {
		assert.equal(ignoredFile(root, path.join(root, name), file), true);
	}
	assert.equal(ignoredFile(root, path.join(root, 'notes.md'), file), true);
	assert.equal(ignoredFile(root, path.join(root, 'includes/a.php'), file), false);
	assert.equal(ignoredFile(root, path.join(root, 'includes'), directory), false);
});

test('real watcher filters changes, coalesces bursts, serializes commands, and closes', { timeout: 15000 }, async () => {
	const root = await mkdtemp(path.join(tmpdir(), 'alynt-dev-watch-'));
	let watcher;
	let calls = 0;
	let concurrent = 0;
	let maxConcurrent = 0;
	let release;
	const errors = [];
	try {
		await mkdir(path.join(root, 'scripts'));
		await writeFile(path.join(root, 'site.php'), '<?php // fixture');
		watcher = await createDeploymentWatcher({
			root,
			delayMs: 60,
			onError: (error) => errors.push(error),
			run: async () => {
				calls++;
				concurrent++;
				maxConcurrent = Math.max(maxConcurrent, concurrent);
				if (calls === 2) await new Promise((resolve) => { release = resolve; });
				concurrent--;
			},
		});
		await waitFor(() => calls === 1);
		await writeFile(path.join(root, 'notes.txt'), 'ignored');
		await writeFile(path.join(root, 'scripts', 'tool.js'), 'ignored');
		await pause(250);
		assert.equal(calls, 1);
		await writeFile(path.join(root, 'site.php'), '<?php // changed');
		await writeFile(path.join(root, 'admin.css'), '/* burst */');
		await waitFor(() => calls === 2 && release);
		await writeFile(path.join(root, 'admin.js'), '// during active command');
		await pause(250);
		assert.equal(calls, 2);
		release();
		await waitFor(() => calls === 3);
		assert.equal(maxConcurrent, 1);
		await watcher.close();
		await writeFile(path.join(root, 'site.php'), '<?php // after close');
		await pause(150);
		assert.equal(calls, 3);
		assert.deepEqual(errors, []);
	} finally {
		release?.();
		await watcher?.close();
		// Only remove this test's explicitly named, resolved temporary directory.
		assert.equal(path.dirname(root), path.resolve(tmpdir()));
		assert.match(path.basename(root), /^alynt-dev-watch-/);
		await rm(root, { recursive: true, force: true });
	}
});

test('failed command is reported and a later file change can run again', { timeout: 10000 }, async () => {
	const root = await mkdtemp(path.join(tmpdir(), 'alynt-dev-watch-'));
	let watcher;
	let calls = 0;
	const errors = [];
	try {
		watcher = await createDeploymentWatcher({
			root,
			delayMs: 60,
			onError: (error) => errors.push(error),
			run: () => {
				calls++;
				if (calls === 1) throw new Error('Harmless fixture failure.');
			},
		});
		await waitFor(() => errors.length === 1);
		await writeFile(path.join(root, 'retry.php'), '<?php // fixture');
		await waitFor(() => calls === 2);
		assert.equal(errors[0].message, 'Harmless fixture failure.');
		assert.equal(errors.length, 1);
	} finally {
		await watcher?.close();
		assert.equal(path.dirname(root), path.resolve(tmpdir()));
		assert.match(path.basename(root), /^alynt-dev-watch-/);
		await rm(root, { recursive: true, force: true });
	}
});
