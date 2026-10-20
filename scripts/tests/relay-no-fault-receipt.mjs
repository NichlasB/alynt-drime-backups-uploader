// Pure test-supervisor identity check; no lookup, native handle or authority.
export function boundRelayRoles(roles, threads) {
	if (!roles || Object.getPrototypeOf(roles) !== Object.prototype || Object.keys(roles).sort().join(',') !== 'auth,capture,writer' ||
		!Array.isArray(threads) || threads.length !== 3 || new Set(threads).size !== 3) return false;
	return ['auth', 'capture', 'writer'].every((key) => Number.isInteger(roles[key]) && roles[key] > 0 && roles[key] <= 0xffffffff && threads.filter((id) => id === roles[key]).length === 1) && new Set(Object.values(roles)).size === 3;
}
