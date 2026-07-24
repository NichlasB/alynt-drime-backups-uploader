import { createHash } from 'node:crypto';
import { promises as fs } from 'node:fs';

import {
	renderRunner,
	runnerOutputPath,
	runnerSourceManifest,
} from './build-runner.mjs';

const firstRender = await renderRunner();
const secondRender = await renderRunner();
const committedOutput = await fs.readFile(runnerOutputPath, 'utf8');

if (firstRender !== secondRender) {
	throw new Error('Runner generation is not deterministic within one process.');
}

if (firstRender !== committedOutput) {
	throw new Error(
		'Generated runner is stale. Run `npm run build:runner` and commit the result.',
	);
}

if (firstRender.includes('\r')) {
	throw new Error('Generated runner must use LF line endings only.');
}

if (!firstRender.endsWith('\n') || firstRender.endsWith('\n\n')) {
	throw new Error('Generated runner must end with exactly one newline.');
}

for (const sourcePath of runnerSourceManifest) {
	if (!firstRender.includes(` * - ${sourcePath}\n`)) {
		throw new Error(`Generated runner does not record source: ${sourcePath}`);
	}
}

const sha256 = createHash('sha256').update(firstRender).digest('hex');
console.log(`Runner build verified: ${sha256}`);
