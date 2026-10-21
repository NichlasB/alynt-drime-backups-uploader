// Bounded repository reads only. Never import/execute a native worker or relay.
import { readFileSync, realpathSync, statSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { qualificationIdentities, verifyQualificationIdentity } from './qualification-native-identity.mjs';
import { sourceShape } from './selected-qualification-grammar.mjs';
const load = (name) => {
	const url = new URL(name, import.meta.url);
	if (statSync(url).size > 4096) throw Error('selected_manifest_size');
	return JSON.parse(readFileSync(url, 'utf8'));
};
export function selectedIdentities() {
	if (arguments.length) throw Error('selected_manifest_arguments');
	const m = load('selected-qualification-identities.json'), old = qualificationIdentities();
	if (!sourceShape(m) || JSON.stringify(Object.fromEntries(Object.entries(m).slice(0, 8))) !== JSON.stringify(old)) throw Error('selected_manifest_shape');
	return Object.freeze(m);
}
export function verifySelectedIdentity() {
	if (arguments.length) return false;
	try {
		if (!verifyQualificationIdentity()) return false;
		const m = selectedIdentities(), groups = load('selected-qualification-groups.json'), root = realpathSync(new URL('../../', import.meta.url));
		if (!groups || Object.keys(groups).join(',') !== Object.keys(m).slice(8).join(',')) return false;
		for (const [key, names] of Object.entries(groups)) {
			if (!Array.isArray(names) || !names.length || names.length > 32) return false;
			let bytes = 0;
			const rows = names.map((name) => {
				if (typeof name !== 'string' || name.length > 160 || !/^(?:tests\/Support|scripts\/tests)\/[A-Za-z0-9_-]+\.(?:php|mjs|json|ps1)$/.test(name)) throw Error('selected_source_name');
				const path = realpathSync(new URL('../../' + name, import.meta.url)), size = statSync(path).size;
				if (!path.startsWith(root + (process.platform === 'win32' ? '\\' : '/')) || size > 262144 || (bytes += size) > 2097152) throw Error('selected_source_boundary');
				return [name, readFileSync(path, 'utf8').replaceAll('\r\n', '\n')];
			});
			if (createHash('sha256').update(JSON.stringify(rows)).digest('hex') !== m[key]) return false;
		}
		return true;
	} catch { return false; }
}
