import http from 'node:http';
import { createHash } from 'node:crypto';
import { readFileSync, realpathSync } from 'node:fs';
import path from 'node:path';

// Explicit operator invocation only, never imported into production or CI.
const file = process.argv[2];
const preflightOnly = process.argv.length === 4 && process.argv[3] === '--preflight-only';
if ((!preflightOnly && process.argv.length !== 3) || !/^alynt-closed-entry-input-proof-[a-f0-9]{12}\.php$/.test(file ?? '')) {
  throw new Error('Resolve the exact owned loopback fixture filename.');
}
const root = realpathSync('C:/Users/Captain/Local Sites/plugin-tester/app/public');
const sites = Object.values(JSON.parse(readFileSync('C:/Users/Captain/AppData/Roaming/Local/sites.json', 'utf8')))
  .filter((site) => site.domain === 'plugin-tester.local');
if (sites.length !== 1 || realpathSync(path.join(sites[0].path, 'app/public')) !== root ||
    sites[0].services?.nginx?.ports?.HTTP?.[0] !== 10005) throw new Error('Current HTTP target metadata mismatch.');
if (preflightOnly) {
  console.log(JSON.stringify({ current_target_matches: true, requests: 0, port: 10005 }));
  process.exit(0);
}
const stub = "<?php\nrequire 'C:/Development/WordPress/Plugins/alynt-drime-backups-uploader/scripts/tests/closed-entry-http-probe.php';\n";
if (readFileSync(path.join(root, file), 'utf8').replaceAll('\r\n', '\n') !== stub) throw new Error('Exact owned HTTP stub missing or changed.');
const small = 'action=alynt_drime_backups_register_closed&purpose=register_closed&confirm=register&nonce=probe-value';
const cases = [['metadata-small', 'metadata', small], ['reader-small', 'reader', small],
  ['metadata-boundary', 'metadata', 'x'.repeat(16384)], ['metadata-overflow', 'metadata', 'x'.repeat(16385)]];
const receipts = [];
for (const [name, mode, body] of cases) {
  const result = await new Promise((resolve, reject) => {
    const request = http.request({ host: '127.0.0.1', port: 10005, method: 'POST', path: `/${file}`,
      headers: { Host: 'plugin-tester.local', 'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(body), 'X-Alynt-Input-Probe': mode, Connection: 'close' } }, (response) => {
      let data = ''; response.setEncoding('utf8');
      response.on('data', (chunk) => { data += chunk; if (Buffer.byteLength(data) > 2048) request.destroy(new Error('Bounded receipt exceeded.')); });
      response.on('end', () => {
        try { if (response.statusCode !== 200) throw new Error(`Owned probe status ${response.statusCode}`); resolve(JSON.parse(data)); }
        catch (error) { reject(error); }
      });
      response.on('error', reject);
    });
    const timer = setTimeout(() => request.destroy(new Error('Owned HTTP request deadline exceeded.')), 3000);
    request.on('close', () => clearTimeout(timer)); request.on('error', reject); request.end(body);
  });
  if (result.wordpress_loaded !== false || result.production_entry_available !== false || result.native_execution !== false || result.probe_failed) throw new Error('Probe safety predicate failed.');
  if (mode === 'metadata') {
    if (result.bytes !== Buffer.byteLength(body) || result.reads > 17 || result.bytes > 16385 || !result.closed || result.synthetic_body_sha256 !== createHash('sha256').update(body).digest('hex')) throw new Error('Input byte/cleanup observation failed.');
    delete result.synthetic_body_sha256; // Fixture-only hash is not a published receipt.
  } else if (result.owner_cleanup !== true) throw new Error('Owner cleanup unknown.');
  receipts.push({ name, ...result });
}
console.log(JSON.stringify({ completed: true, requests: receipts, profile_support_approved: false,
  slow_or_malformed_framing_experiments: false, production_ingress_enabled: false }, null, 2));
