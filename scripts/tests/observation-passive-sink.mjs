// Test-only exception membrane. Refusal never becomes a transport instruction.
export function passiveObservation(observer) {
	let failed = false;
	return Object.freeze({
		call(method, ...args) {
			if (!observer || failed) return;
			try { if (observer[method](...args) !== false) return; } catch { /* Redacted, latched below. */ }
			failed = true;
			try { observer.unavailable(); } catch { /* Even failure reporting cannot affect transport. */ }
		},
		failed() { return failed; },
	});
}
