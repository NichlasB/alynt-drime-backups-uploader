// Fixed repository pins, LF-only source normalization; never caller-selected paths or expected digests.
import { readFileSync, realpathSync, statSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { fileURLToPath } from 'node:url';
const groups = {
	native_worker: ['tests/Support/QualificationNativeManifest.php', 'tests/Support/QualificationNativeWorker.php', 'tests/Support/QualificationNativeDiagnostic.php', 'scripts/tests/qualification-native-worker.php', 'scripts/tests/qualification-native-cases.php', 'scripts/tests/qualification-native-preflight.php'],
	native_channel: ['tests/Support/QualificationNativeProtocol.php', 'scripts/tests/qualification-native-channel.php', 'scripts/tests/qualification-native-grammar.mjs', 'scripts/tests/qualification-native-control.mjs', 'scripts/tests/qualification-native-identity.mjs', 'scripts/tests/owned-stdio-io.php', 'scripts/tests/owned-stdio-lines.mjs'],
	native_supervisor: ['scripts/tests/qualification-native-proof.mjs', 'scripts/tests/qualification-native-process.mjs', 'scripts/tests/qualification-native-relay.mjs', 'scripts/tests/qualification-native-proof.ps1', 'scripts/tests/selected-native-fixtures.php', 'scripts/tests/fixed-core-proof-storage.php', 'scripts/tests/fixed-core-proof-fixtures.php', 'scripts/tests/fixed-core-proof-cases.php', 'scripts/tests/fixed-core-proof-denials.php', 'scripts/tests/selected-native-tree-proof.mjs', 'scripts/tests/owned-stdio-worker.php', 'scripts/tests/registration-join-proof-fixtures.php', 'scripts/tests/registration-outcome-proof-fixtures.php', 'scripts/tests/registration-admission-proof-fixtures.php', 'scripts/tests/registration-proof-fixtures.php', 'scripts/tests/native-scan-proof-fixtures.php', 'scripts/tests/native-scan-ledger-fixtures.php', 'scripts/tests/native-observer-proof-fixtures.php', 'scripts/tests/relay-no-fault-frames.mjs', 'scripts/tests/observation-passive-sink.mjs', 'scripts/tests/observation-native-registry.mjs', 'scripts/tests/native-witness-profile.mjs', 'scripts/tests/fixed-drain-grammar.mjs', 'scripts/tests/selected-native-lifetime.mjs', 'scripts/tests/owned-stdio-runtime.mjs'],
};
groups.native_worker.push('scripts/tests/qualification-native-bootstrap.php', 'scripts/tests/qualification-native-prelude.php');
groups.native_supervisor.push('scripts/tests/qualification-native-stages.mjs');
const root = realpathSync(fileURLToPath(new URL('../../', import.meta.url)));
export function qualificationIdentities() {
	if (arguments.length) throw Error('qualification_identity_arguments');
	const file = new URL('./qualification-native-identities.json', import.meta.url);
	if (statSync(file).size > 4096) throw Error('qualification_identity_size');
	const m = JSON.parse(readFileSync(file, 'utf8'));
	if (!m || Object.keys(m).join(',') !== 'production,composition,projection,isolated_worker,isolated_transcript,native_worker,native_channel,native_supervisor' || !Object.values(m).every((v) => typeof v === 'string' && /^[a-f0-9]{64}$/.test(v))) throw Error('qualification_identity_shape');
	return Object.freeze(m);
}
export function verifyQualificationIdentity() {
	if (arguments.length) return false;
	try {
		const expected = qualificationIdentities();
		for (const [group, files] of Object.entries(groups)) {
			let bytes = 0;
			const rows = files.map((name) => {
				const path = realpathSync(new URL('../../' + name, import.meta.url)), size = statSync(path).size;
				if (!path.startsWith(root + (process.platform === 'win32' ? '\\' : '/')) || size > 262144 || (bytes += size) > 2097152) throw Error('qualification_source_boundary');
				return [name, readFileSync(path, 'utf8').replaceAll('\r\n', '\n')];
			});
			if (createHash('sha256').update(JSON.stringify(rows)).digest('hex') !== expected[group]) return false;
		}
		return true;
	} catch { return false; }
}
