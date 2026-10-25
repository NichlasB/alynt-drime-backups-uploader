// Test-only primitive DATA model. No packet parser, IO, driver or native authority.
import { types } from 'node:util';
export const WPDB_SETUP_DATA_PROFILE = 'wpdb-postconnect-setup.data.v1';
const references = Object.freeze([
	['WordPress7.1.3', 'wp-includes/class-wpdb.php', 121437, 'e15403e90032dd0508811301505e491d4212de5152581a98f51b744a1a3903bd'],
	['PHP8.5.12', 'ext/mysqli/mysqli_api.c', 55351, '29e23960a109b5f8b3fb268f1f44aa9b400e5495dac788981aa32978b0bd4941'],
	['PHP8.5.12', 'ext/mysqli/mysqli_nonapi.c', 33465, '7a60f688bcbc2bb3f4f0990d0c235a43707c3ba5f6b8a3b3fae67a2b9b905c23'],
	['PHP8.5.12', 'ext/mysqlnd/mysqlnd_connection.c', 69567, '9a00e4689676d29bd3e69630af50477b2309f6d685f87fc6f580fd745e450430'],
	['PHP8.5.12', 'ext/mysqlnd/mysqlnd_auth.c', 47207, '2e1e1c92227a8d451e7a05ea0290d4b0f8a56f1a171eae3e2ece2710aa96e67e'],
	['PHP8.5.12', 'ext/mysqlnd/mysqlnd_wireprotocol.c', 94756, 'bc0121d607070261b0ca90c3dfd6d6a75972f57f2279f28b120e4780ea6b55e8'],
	['PHP8.5.12', 'ext/mysqlnd/mysqlnd_enum_n_def.h', 20820, '27689dc30fdd4ffa3af386fd24ea83a5c63f677f2f1e1f4f5e8d12875c636585'],
].map(([release, member, bytes, sha256], index) => Object.freeze({ release, member, bytes, sha256,
	evidence: 'public-reference-bytes', prior_installed_member_match: index === 0 })));
const catalog = Object.freeze({ profile: WPDB_SETUP_DATA_PROFILE, source_catalog: 'wpdb-713-php-8512-reference.v1',
	session: 'synthetic-postconnect-context.v1', setup: 'ordered-typed-setup.v1', result: 'primitive-result-enums.v1',
	lifetime: 'private-symbol-original-epoch.v1', resource: 'model-events-only.v1', references,
	old_wire_profile_compatible: false, installed_build_proven: false, observed_session_proven: false,
	immutable_ownership_proven: false, transitive_source_closure_proven: false, native_supported: false });

export function wpdbSetupCatalog(profile) {
	if (profile !== WPDB_SETUP_DATA_PROFILE) throw new TypeError('Unsupported synthetic setup profile.');
	return catalog;
}

const identityKeys = ['attempt', 'pair', 'generation', 'request', 'session'];
const consumedIdentities = new WeakSet();
const record = (value, keys) => {
	if (!value || typeof value !== 'object' || types.isProxy(value) || Object.getPrototypeOf(value) !== Object.prototype || Reflect.ownKeys(value).length !== keys.length) return null;
	const descriptors = Object.getOwnPropertyDescriptors(value);
	return keys.every((k) => Object.hasOwn(descriptors, k) && Object.hasOwn(descriptors[k], 'value')) ?
		Object.fromEntries(keys.map((k) => [k, descriptors[k].value])) : null;
};

export function createWpdbSetupData(options) {
	const config = record(options, [...identityKeys, 'startedNs', 'profile', 'schema']);
	if (!config || config.profile !== WPDB_SETUP_DATA_PROFILE || config.schema !== 'owned-synthetic' ||
		typeof config.startedNs !== 'bigint' || config.startedNs < 0n ||
		!identityKeys.every((k) => typeof config[k] === 'symbol' && Symbol.keyFor(config[k]) === undefined && !consumedIdentities.has(config[k])) ||
		new Set(identityKeys.map((k) => config[k])).size !== identityKeys.length) throw new TypeError('Unsupported synthetic setup context.');
	for (const key of identityKeys) consumedIdentities.add(config[key]);
	let stage = 'fresh', refused = false, closed = false, last = config.startedNs;
	const accounting = { model_events: 0, api_calls: 0, emitted_operations: 0, successful_wire_results: 0 };
	const terminal = new Set();
	const terminalNames = ['natural-shutdown', 'engine-complete', 'process-exit-zero', 'client-eof', 'server-eof'];
	const fail = () => { refused = true; return false; };
	const context = (identity, now) => {
		if (closed || refused) return false;
		const fields = record(identity, identityKeys);
		if (!fields || !identityKeys.every((k) => fields[k] === config[k]) || typeof now !== 'bigint' || now < last || now >= config.startedNs + 20000000000n) return fail();
		last = now; return true;
	};
	return Object.freeze({
		accept(event, identity, now) {
			if (!context(identity, now)) return false;
			const fields = record(event, ['kind', 'value']);
			if (!fields || typeof fields.kind !== 'string' || typeof fields.value !== 'string') return fail();
			const { kind, value } = fields;
			if (accounting.model_events >= 16) return fail();
			if (stage === 'fresh' && kind === 'connect' && value === 'fresh-nonpersistent-no-database') stage = 'authentication';
			else if (stage === 'authentication' && kind === 'auth-result' && value === 'synthetic-ok') stage = 'charset';
			else if (stage === 'charset' && kind === 'charset-api' && value === 'ok-zero-wire') stage = 'mode-read';
			else if (stage === 'charset' && kind === 'charset-api' && value === 'ok-one-wire') stage = 'charset-wire';
			else if (stage === 'charset-wire' && kind === 'charset-statement-result' && value === 'ok') stage = 'mode-read';
			else if (stage === 'mode-read' && kind === 'mode-read-result' && value === 'one-empty-string') stage = 'select-schema';
			else if (stage === 'mode-read' && kind === 'mode-read-result' && value === 'one-filterable-nonempty') stage = 'mode-write';
			else if (stage === 'mode-write' && kind === 'mode-write-result' && value === 'ok-fixed-filtered') stage = 'select-schema';
			else if (stage === 'select-schema' && kind === 'schema-intent' && value === config.schema) stage = 'schema-result';
			else if (stage === 'schema-result' && kind === 'schema-result' && value === 'ok') stage = 'selected-data';
			else if (stage === 'selected-data' && kind === 'data-profile' && value === 'synthetic-separate-result-grammar') stage = 'data-admitted';
			else if (stage === 'data-admitted' && kind === 'terminal' && terminalNames.includes(value) && !terminal.has(value)) {
				terminal.add(value); if (terminal.size === terminalNames.length) stage = 'complete';
			}
			else return fail();
			accounting.model_events++;
			if (['connect', 'charset-api', 'mode-read-result', 'mode-write-result', 'schema-intent'].includes(kind)) accounting.api_calls++;
			if (['auth-result', 'charset-statement-result', 'mode-read-result', 'mode-write-result', 'schema-result'].includes(kind)) {
				accounting.emitted_operations++; accounting.successful_wire_results++;
			}
			return true;
		},
		finish(identity, now) {
			if (closed) return false;
			const valid = context(identity, now); closed = true;
			return Object.freeze({ synthetic_match: valid && stage === 'complete', synthetic_only: true, stage, refused,
				model_accounting: Object.freeze({ ...accounting }),
				source_proven: false, authentication_proven: false, native_proven: false, forwarding_proven: false,
				backing_absence_proven: false, resource_closure_proven: false, storage_supported: false,
				wiring_supported: false, execution_supported: false });
		},
	});
}
