import * as esbuild from 'esbuild';

const isWatch = process.argv.includes('--watch');

const buildOptions = {
	bundle: true,
	minify: false,
	sourcemap: isWatch,
	target: ['es2020'],
	loader: {
		'.css': 'css',
	},
};

const buildTargets = [
	{
		...buildOptions,
		entryPoints: ['assets/src/admin/index.js'],
		outfile: 'assets/admin.js',
	},
	{
		...buildOptions,
		entryPoints: ['assets/src/admin/workspaces.js'],
		outfile: 'assets/admin-workspaces.js',
	},
];

if (isWatch) {
	const contexts = await Promise.all(buildTargets.map((target) => esbuild.context(target)));
	await Promise.all(contexts.map((context) => context.watch()));
	console.log('Watching for changes...');
} else {
	await Promise.all(buildTargets.map((target) => esbuild.build(target)));
	console.log('Build complete.');
}
