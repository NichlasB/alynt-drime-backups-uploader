import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';

const source = readFileSync(new URL('../../scripts/tests/closed-entry-http-profile.mjs', import.meta.url), 'utf8');
const dataModule = (text) => `data:text/javascript;base64,${Buffer.from(text).toString('base64')}`;
const stub = "<?php\nrequire 'C:/Development/WordPress/Plugins/alynt-drime-backups-uploader/scripts/tests/closed-entry-http-probe.php';\n";
function run(kind, preflight) {
  const site = { domain: 'plugin-tester.local', path: kind === 'root' ? 'C:/other-root' : 'C:/owned-root',
    services: { nginx: { ports: { HTTP: [kind === 'port' ? 10006 : 10005] } } } };
  const sites = kind === 'duplicate' ? { a: site, b: site } : { a: site };
  const fs = `export function realpathSync(p){return p.includes('other-root')?'foreign':'fixed-root'}
    export function readFileSync(p){return p.endsWith('sites.json')?${JSON.stringify(JSON.stringify(sites))}:${JSON.stringify(kind === 'stub' ? 'changed' : stub)}}`;
  const http = "export default {request(){throw new Error('OWNED_HTTP_TRAP_REACHED')}}";
  assert.equal(source.split("from 'node:fs'").length, 2);
  assert.equal(source.split("from 'node:http'").length, 2);
  const fixture = source.replace("from 'node:fs'", `from '${dataModule(fs)}'`)
    .replace("from 'node:http'", `from '${dataModule(http)}'`);
  const argv = ['node', 'owned-source', 'alynt-closed-entry-input-proof-123456abcdef.php'];
  if (preflight) argv.push('--preflight-only');
  const code = `process.argv=${JSON.stringify(argv)}; await import(${JSON.stringify(dataModule(fixture))});`;
  return spawnSync(process.execPath, ['--input-type=module', '-e', code], { encoding: 'utf8', timeout: 3000, maxBuffer: 262144 });
}
test('actual driver readonly preflight accepts exact synthetic target without HTTP', () => {
  const r = run('valid', true); assert.equal(r.status, 0, r.stderr);
  assert.deepEqual(JSON.parse(r.stdout), { current_target_matches: true, requests: 0, port: 10005 });
});
test('actual driver valid target and exact stub reach HTTP trap, not an always-refusing preflight', () => {
  const r = run('valid', false); assert.equal(r.status, 1); assert.match(r.stderr, /OWNED_HTTP_TRAP_REACHED/);
});
for (const kind of ['port', 'duplicate', 'root', 'stub']) {
  test(`actual driver refuses ${kind} before HTTP; fixed API doubles only`, () => {
    const r = run(kind, false); assert.equal(r.status, 1);
    assert.match(r.stderr, /Current HTTP target metadata mismatch|Exact owned HTTP stub missing or changed/);
    assert.doesNotMatch(r.stderr, /OWNED_HTTP_TRAP_REACHED/);
    assert.equal(r.stdout, '');
  });
}
