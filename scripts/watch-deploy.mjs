import chokidar from 'chokidar';
import { spawn } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const repository = fileURLToPath(new URL('../', import.meta.url));
const extensions = new Set(['.php', '.js', '.css', '.html']);
const ignoredDirectories = new Set(['node_modules', 'bower_components', 'scripts', 'coverage']);

/** Match the development watcher scope without interpreting glob patterns. */
export function ignoredFile(root, file, stats) {
	const relative = path.relative(root, file);
	if (!relative) return false;
	if (relative.split(path.sep).some((part) => part.startsWith('.') || ignoredDirectories.has(part))) return true;
	return Boolean(stats?.isFile() && !extensions.has(path.extname(file).toLowerCase()));
}

/** Run only the developer's explicitly invoked deployment command. */
function deploy(root) {
	return new Promise((resolve, reject) => {
		const child = spawn('bash', ['deploy.sh'], { cwd: root, stdio: 'inherit', shell: false });
		child.once('error', reject);
		child.once('exit', (code, signal) => {
			if (code === 0) resolve();
			else reject(new Error(`Deployment command exited with ${signal ?? code}.`));
		});
	});
}

/** Debounce changes and serialize commands; tests inject a harmless runner. */
export async function createDeploymentWatcher({ root = repository, delayMs = 2000, run = () => deploy(root), onError = console.error } = {}) {
	let stopped = false;
	let timer;
	let active;
	let pending = false;
	const watcher = chokidar.watch(root, {
		ignoreInitial: true,
		ignorePermissionErrors: true,
		ignored: (file, stats) => ignoredFile(root, file, stats),
	});

	function schedule() {
		if (stopped) return;
		clearTimeout(timer);
		timer = setTimeout(flush, delayMs);
	}

	async function flush() {
		timer = undefined;
		if (stopped) return;
		if (active) {
			pending = true;
			return;
		}
		// Set active before invoking the runner, including synchronous failures.
		active = Promise.resolve().then(run).catch(onError);
		await active;
		active = undefined;
		if (pending) {
			pending = false;
			schedule();
		}
	}

	watcher.on('all', (event, file) => {
		if (['add', 'change', 'unlink'].includes(event) && extensions.has(path.extname(file).toLowerCase())) schedule();
	});
	try {
		await new Promise((resolve, reject) => {
			watcher.once('ready', resolve);
			watcher.once('error', reject);
		});
	} catch (error) {
		await watcher.close();
		throw error;
	}
	watcher.on('error', onError);
	void flush(); // Preserve the previous watcher's initial command run.

	return {
		async close() {
			stopped = true;
			pending = false;
			clearTimeout(timer);
			await watcher.close();
			await active; // Let an active command finish instead of interrupting it.
		},
	};
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
	const watcher = await createDeploymentWatcher();
	console.log('Watching PHP/JS/CSS/HTML changes; deployments are delayed two seconds and run serially.');
	const stop = () => { void watcher.close(); };
	process.once('SIGINT', stop);
	process.once('SIGTERM', stop);
}
