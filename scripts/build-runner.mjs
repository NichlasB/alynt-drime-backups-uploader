import { promises as fs } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

export const runnerSourceManifest = [
	'server-runner/src/inventory-cleanup.php',
	'server-runner/src/package-restore.php',
	'server-runner/src/backup-archive.php',
	'server-runner/src/staging-restore.php',
	'server-runner/src/production-preflight.php',
	'server-runner/src/production-apply.php',
	'server-runner/src/production-rollback.php',
	'server-runner/src/production-control.php',
	'server-runner/src/filesystem-security.php',
	'server-runner/src/package-support.php',
	'server-runner/src/drime-client.php',
	'server-runner/src/config-runtime.php',
	'server-runner/src/runner.php',
	'server-runner/src/cli-entrypoint.php',
];
export const runnerSourcePaths = runnerSourceManifest.map((relativePath) =>
	path.join(projectRoot, relativePath),
);
export const runnerOutputPath = path.join(
	projectRoot,
	'server-runner',
	'alynt-backup-runner.php',
);

const runnerPrefix = '#!/usr/bin/env php\n<?php\n';
const phpPrefix = '<?php\n';
const runnerEntrypoint = 'server-runner/src/runner.php';

function normalizeText(text) {
	return text.replace(/^\uFEFF/, '').replace(/\r\n?/g, '\n');
}

export async function renderRunner() {
	const renderedSources = [];

	for (const [index, relativePath] of runnerSourceManifest.entries()) {
		const sourcePath = runnerSourcePaths[index];
		const source = normalizeText(await fs.readFile(sourcePath, 'utf8'));

		const isEntrypoint = relativePath === runnerEntrypoint;
		const expectedPrefix = isEntrypoint ? runnerPrefix : phpPrefix;

		if (!source.startsWith(expectedPrefix)) {
			throw new Error(
				`Runner source has an invalid opening prefix: ${relativePath}`,
			);
		}

		renderedSources.push(source.slice(expectedPrefix.length));
	}

	const generatedHeader = [
		'/**',
		' * GENERATED FILE. DO NOT EDIT DIRECTLY.',
		' *',
		' * Source manifest:',
		...runnerSourceManifest.map((relativePath) => ` * - ${relativePath}`),
		' */',
		'',
	].join('\n');

	return `${runnerPrefix}${generatedHeader}${renderedSources.join('\n')}`.replace(
		/\n*$/,
		'\n',
	);
}

export async function buildRunner() {
	const output = await renderRunner();
	let existing = '';

	try {
		existing = await fs.readFile(runnerOutputPath, 'utf8');
	} catch (error) {
		if (error.code !== 'ENOENT') {
			throw error;
		}
	}

	if (existing === output) {
		return false;
	}

	const temporaryPath = `${runnerOutputPath}.${process.pid}.tmp`;

	try {
		await fs.writeFile(temporaryPath, output, {
			encoding: 'utf8',
			flag: 'wx',
		});
		await fs.rename(temporaryPath, runnerOutputPath);
	} finally {
		await fs.rm(temporaryPath, { force: true });
	}

	return true;
}

const invokedPath = process.argv[1] ? path.resolve(process.argv[1]) : '';

if (invokedPath === fileURLToPath(import.meta.url)) {
	const changed = await buildRunner();
	console.log(changed ? 'Runner build complete.' : 'Runner already current.');
}
