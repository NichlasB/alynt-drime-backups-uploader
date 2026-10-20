import test from 'node:test';
import assert from 'node:assert/strict';
import { selectedFailureLocation } from '../../scripts/tests/selected-native-process.mjs';
const receipt = (location, extra = {}) => JSON.stringify({ passed: false, redacted_failure: true, location, ...extra }) + '\n';
test('only one bounded redacted source location escapes failed private stdout', () => {
	const good = receipt(['selected-native-cases.php', 28]);
	assert.deepEqual(selectedFailureLocation('private-token-line\n' + good, 1), { file: 'selected-native-cases.php', line: 28 });
	for (const bad of [receipt(['C:/secret/selected-native-cases.php', 28]), receipt(['token.php', 28]), receipt(['selected-native-cases.php', 0]), receipt(['selected-native-cases.php', '28']), receipt(['selected-native-cases.php', 10001]), receipt(['selected-native-cases.php', 1, 'secret']), receipt(['selected-native-cases.php', 28], { message: 'secret' }), good.trimEnd(), good + good, 'x'.repeat(65536) + good]) assert.equal(selectedFailureLocation(bad, 1), null);
	assert.equal(selectedFailureLocation(good, 0), null);
	assert.equal(selectedFailureLocation(null, 1), null);
});
