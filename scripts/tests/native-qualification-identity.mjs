// Bounded repository reads only. Never imports a worker, native proof or networking.
import { readFileSync, realpathSync, statSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { sourceKeys } from './native-qualification-grammar.mjs';
const digest = (s) => createHash('sha256').update(s).digest('hex');
const expected = '2d61b8b249e694c25d3b50f8c87af410b3339a8df56a608d4c9cc910348756e1';
function groups() {
	const url = new URL('native-qualification-identities.json', import.meta.url);
	if (statSync(url).size > 16384) throw Error('lifecycle_identity_size');
	const raw = readFileSync(url, 'utf8').replaceAll('\r\n', '\n');
	if (digest(raw) !== expected) throw Error('lifecycle_identity_digest');
	const rows = JSON.parse(raw);
	if (Object.keys(rows).join(',') !== sourceKeys.join(',')) throw Error('lifecycle_identity_groups');
	return rows;
}
export function nativeQualificationIdentities() {
	if (arguments.length) throw Error('lifecycle_identity_arguments');
	return Object.freeze(Object.fromEntries(Object.entries(groups()).map(([key, rows]) => [key, digest(JSON.stringify(rows))])));
}
export function verifyNativeQualificationIdentity() {
	if (arguments.length) return false;
	try {
		const root = realpathSync(new URL('../../', import.meta.url)); let bytes = 0;
		const verify = (rows, old = false) => {
			if (!rows || Array.isArray(rows) || !Object.keys(rows).length || Object.keys(rows).length > (old ? 250 : 32)) return false;
			for (const [name, hash] of Object.entries(rows)) {
				if (!/^(?:includes|tests\/Support|scripts\/tests)\/[A-Za-z0-9_-]+\.(?:php|mjs|json|ps1)$/.test(name) || !/^[a-f0-9]{64}$/.test(hash)) return false;
				const path = realpathSync(new URL('../../' + name, import.meta.url)), size = statSync(path).size;
				if (!path.startsWith(root + (process.platform === 'win32' ? '\\' : '/')) || size > 262144 || (bytes += size) > 4194304 || digest(readFileSync(path, 'utf8').replaceAll('\r\n', '\n')) !== hash) return false;
			}
			return true;
		};
		if (!Object.values(groups()).every((rows) => verify(rows))) return false;
		const old = JSON.parse(readFileSync(new URL('native-lifecycle-proof-identities.json', import.meta.url), 'utf8'));
		return verify(old, true);
	} catch { return false; }
}
