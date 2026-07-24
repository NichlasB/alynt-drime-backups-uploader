import { spawnSync } from 'node:child_process';

import {
	runnerOutputPath,
	runnerSourcePaths,
} from './build-runner.mjs';

for (const filePath of [...runnerSourcePaths, runnerOutputPath]) {
	const result = spawnSync('php', ['-l', filePath], {
		encoding: 'utf8',
		shell: false,
	});

	if (result.stdout) {
		process.stdout.write(result.stdout);
	}

	if (result.stderr) {
		process.stderr.write(result.stderr);
	}

	if (result.error) {
		throw result.error;
	}

	if (result.status !== 0) {
		process.exit(result.status ?? 1);
	}
}
