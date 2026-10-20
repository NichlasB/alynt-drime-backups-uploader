// Test-owner lifetime joins. Timeout means unresolved, NEVER successful cleanup.
export async function boundedSettlement(work, milliseconds) {
	if (!Number.isInteger(milliseconds) || milliseconds < 1 || milliseconds > 28000) throw Error('join_budget');
	let timer;
	try {
		return await Promise.race([Promise.resolve(work).then((value) => ({ settled: true, value }), () => ({ settled: true, rejected: true })),
			new Promise((resolve) => { timer = setTimeout(() => resolve({ settled: false }), milliseconds); })]);
	} finally { clearTimeout(timer); }
}
export async function closeOwnedChild(child, completion, milliseconds = 3000) {
	if (!child || typeof child.kill !== 'function' || !child.stdin || !child.stdout || !child.stderr) throw Error('owned_child');
	if (child.exitCode === null && child.signalCode === null) child.kill();
	child.stdin.destroy(); child.stdout.resume(); child.stderr.resume();
	const result = await boundedSettlement(completion, milliseconds);
	child.stdin.destroy(); child.stdout.destroy(); child.stderr.destroy();
	return { resolved: result.settled && !result.rejected && result.value?.closed === true, pipes_destroyed: child.stdin.destroyed && child.stdout.destroyed && child.stderr.destroyed };
}
export async function closeSelectedTransport(server, live, jobs) {
	const listener = new Promise((resolve) => server.close(() => resolve(true)));
	const grace = await boundedSettlement(Promise.all(jobs), 250);
	for (const pair of live) { pair.client.destroy(); pair.upstream.destroy(); }
	const joined = await boundedSettlement(Promise.all([...jobs, listener]), 1500);
	return { resolved: joined.settled && !joined.rejected, graceful: grace.settled && !grace.rejected, listener_closed: !server.listening };
}
