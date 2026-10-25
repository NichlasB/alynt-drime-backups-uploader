// Test-only primitive DATA model. No packet parser, IO, driver or native authority.
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
