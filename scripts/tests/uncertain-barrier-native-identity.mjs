// Fixed repository integrity only. No source paths/hashes accepted from IPC or an operator.
import { readFileSync, realpathSync, statSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { sourceKeys } from './uncertain-barrier-native-grammar.mjs';
import { verifyNativeQualificationIdentity } from './native-qualification-identity.mjs';
import { verifyQualificationIdentity } from './qualification-native-identity.mjs';
import { verifyNativeSelectedIdentity } from './native-qualification-selected-identity.mjs';
import { verifyUncertainNoFaultIdentity } from './uncertain-barrier-no-fault-identity.mjs';
export const nativeSourceDigest = 'f8e212660cfc198ad611a1c3d17540e9ce1b698a29e358067334f7055b8def77';
const digest = (s) => createHash('sha256').update(s).digest('hex');
function groups() {
	const url = new URL('./uncertain-barrier-native-identities.json',import.meta.url);
	if (statSync(url).size > 16384) throw Error('native_no_fault_identity_size');
	const raw=readFileSync(url,'utf8').replaceAll('\r\n','\n'); if (digest(raw)!==nativeSourceDigest) throw Error('native_no_fault_identity_digest');
	const rows=JSON.parse(raw); if (Object.keys(rows).join(',')!==sourceKeys.join(',')) throw Error('native_no_fault_identity_groups'); return rows;
}
export function uncertainNativeIdentities() { if (arguments.length) throw Error('native_no_fault_identity_arguments'); return Object.freeze(Object.fromEntries(Object.entries(groups()).map(([k,v])=>[k,digest(JSON.stringify(v))]))); }
export function verifyUncertainNativeIdentity() {
	if (arguments.length) return false;
	try {
        if (!verifyNativeQualificationIdentity() || !verifyQualificationIdentity() || !verifyNativeSelectedIdentity() || !verifyUncertainNoFaultIdentity()) return false;
		const base=realpathSync(new URL('../../',import.meta.url)); let bytes=0;
		for (const rows of Object.values(groups())) {
			if (!rows || Array.isArray(rows) || !Object.keys(rows).length || Object.keys(rows).length>32) return false;
			for (const [name,hash] of Object.entries(rows)) {
				if (!/^(?:tests\/Support|scripts\/tests)\/[A-Za-z0-9_-]+\.(?:php|mjs|json|ps1)$/.test(name) || !/^[a-f0-9]{64}$/.test(hash)) return false;
				const path=realpathSync(new URL('../../'+name,import.meta.url)), size=statSync(path).size;
				if (!path.startsWith(base+(process.platform==='win32'?'\\':'/')) || size>262144 || (bytes+=size)>2097152 || digest(readFileSync(path,'utf8').replaceAll('\r\n','\n'))!==hash) return false;
			}
		}
		return true;
	} catch { return false; }
}
