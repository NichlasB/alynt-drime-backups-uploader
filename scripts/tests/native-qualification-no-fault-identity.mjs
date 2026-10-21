// Fixed repository integrity only. No source paths/hashes accepted from IPC or an operator.
import { readFileSync, realpathSync, statSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { sourceKeys } from './native-qualification-grammar.mjs';
import { verifyNativeQualificationIdentity } from './native-qualification-identity.mjs';
import { verifyQualificationIdentity } from './qualification-native-identity.mjs';
const expected = '5883cd7caae6a75ecd4e18c7890f4b3731cf91d2ac92a1ae630eca8b10afae7a';
const digest = (s) => createHash('sha256').update(s).digest('hex');
function groups() {
	const url = new URL('./native-qualification-no-fault-identities.json',import.meta.url);
	if (statSync(url).size > 16384) throw Error('native_no_fault_identity_size');
	const raw=readFileSync(url,'utf8').replaceAll('\r\n','\n'); if (digest(raw)!==expected) throw Error('native_no_fault_identity_digest');
	const rows=JSON.parse(raw); if (Object.keys(rows).join(',')!==sourceKeys.join(',')) throw Error('native_no_fault_identity_groups'); return rows;
}
export function nativeNoFaultIdentities() { if (arguments.length) throw Error('native_no_fault_identity_arguments'); return Object.freeze(Object.fromEntries(Object.entries(groups()).map(([k,v])=>[k,digest(JSON.stringify(v))]))); }
export function verifyNativeNoFaultIdentity() {
	if (arguments.length) return false;
	try {
		if (!verifyNativeQualificationIdentity() || !verifyQualificationIdentity()) return false;
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
