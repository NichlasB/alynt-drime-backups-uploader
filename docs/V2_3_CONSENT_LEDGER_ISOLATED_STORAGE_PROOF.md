# V2.3 Consent Ledger Isolated Storage and Admission-Closure Proof

Approved and target-confirmed on 2026-10-14: `plugin-tester local-only`, Plugin Tester at `http://plugin-tester.local`, `C:\Users\Captain\Local Sites\plugin-tester\app\public`, including owned fixture creation/cleanup. Source checkpoints: uploader `c6e29742192582d26e9fedff0554eeedda385a21`, dashboard `b1b5ce70cefb62cb6532aa0231569b2d1e8b49eb`. This proves bounded local SQL/process behavior of [the dormant fixture ledger](V2_3_CONSENT_LEDGER_SAFETY_FOUNDATION.md), not real administrator authorization, production writer coupling or authority activation. Canonical milestone: dashboard `docs/IMPLEMENTATION_PLAN.md`.

## Environment, Access And Isolation

Current LocalWP metadata and wp-config agreed on Plugin Tester, database `local`, loopback port 10004 and prefix `wp_`. Initial SQL used LocalWP's bundled MariaDB client, not PHP bootstrap. It confirmed MariaDB 10.6.23, the exact site URL, 545 actual options, no actual safety-state option and no existing proof tables. Novamira HQ inventory recognized the exact profile; its read-only doctor check found expired OAuth and could not complete server/REST checks. The approved direct LocalWP database workflow was used without reconnecting or modifying credentials.

Proof runtime was LocalWP PHP 8.5.1 with the site's current php.ini and WordPress 7.1.2. The existing missing-Imagick startup warning remained nonfatal and visible; no PHP/site configuration was repaired. Persistent external object-cache isolation is unsupported and explicitly rejected by the harness.

`scripts/tests/lifecycle-local-proof.php` now accepts explicit `--ledger` / owned `--ledger-child` selectors. Default schema-3 proof behavior remains available and was rerun successfully. New ledger definitions/helpers are loaded only for the new selector, never through plugin bootstrap. Four compact proof helpers separate real SQL cases, process barriers, competing writers and edge cases. CI only syntax-checks these tools; it never connects to LocalWP or runs their database cases.

The shared harness retains exact root/domain/current metadata-port/database checks, SHORTINIT and disabled WP-Cron. No plugins, workers, administrator handler, scan/queue/upload callback or real schedule is loaded or executed. It creates an **empty** randomly named table using the actual options schema, not a copy of actual option data, after proving the name absent. The ownership marker and strict table-name pattern are checked before fixture access; only that process's `$wpdb->options` points to the fixture. The real CAS/ledger use that scoped table. SQL resets, synthetic secondary-setting changes and barrier markers stay in it. Option-cache probes are process-local and never a distributed cache certificate.

Both ledger invocations previewed and dropped only their newly created owned tables. Finally blocks stop owned children, restore the process's actual-table pointer, clear private in-process caches, preview fixture row counts, remove the exact fixture table and verify absence. No actual options, cron, activation, users, credentials or production tables were mutated by the proof. No real authority opt-in, migration, grant, restore/backup/cleanup or live-site operation occurred.

## Executed SQL And Process Scenarios

The final run passed all **19 scenario checks** on real MariaDB through production CAS/ledger code. Parent cases do not mock SQL outcomes. Five races each use two independent PHP processes paused after their initial authoritative read; the parent releases a bounded barrier, and ordered cases hold the grant until closure/rebind committed. Two further children are terminated after a committed write marker but before emitting an acknowledgement. Receipt reconciliation uses a fresh model instance without replaying the transition.

| Checks | Executed evidence |
| --- | --- |
| 1-2: missing state / migration | Reads and explicit migration do not create a missing option. Existing strict schema 3 migrates closed in one read plus one CAS, preserves due/history, advances the permission epoch and stays non-autoloaded/authority-disabled. |
| 3-4: grant / receipt observations | Authoritative read ignores stale option/all-options/not-options cache entries; a stored grant invalidates caches. Canonical duplicate takes one read and no write. Rebind closes consent; retained old grant becomes superseded without a new write. |
| 5: real failed writes | Read-only SQL transactions force migration, grant and revoke CAS failures. Each returns unresolved rather than success and retains exact prior bytes, including the old synthetic grant after failed revoke. Transaction rollback only ends the fixture fault injection. |
| 6-10: competing processes | One accepted migration/epoch transition; unordered grant/rebind has one winner. Ordered rebind and ordered revoke reject the old grant at its original epoch/context. Identical grant race yields one committed and one observed-current result, with one epoch advance and no added history. |
| 11-12: interrupted acknowledgement | Terminated-after-commit grant and revoke retain their durable receipts. Restart observes the exact command in one read/no write; old grant after committed closure is superseded, never regranted. Startup diagnostics are not mistaken for structured acknowledgement. |
| 13-14: capacity / counters | Saturated history rejects a new grant, but closures keep the latest receipt and preserve all retained history. Near-exhausted storage generation rejects a grant yet permits the reserved final closure without wrap/reset. |
| 15-17: protected records | Expired recovery dispatch is retained verbatim through rebind closure. Expired journal blocks schema-3 migration; in schema 4, closure preserves that journal's old ownership/evidence. Terminal native journal, unknown history and due survive schema-4 migration verbatim. |
| 18-19: secondary failure / corruption | After confirmed closure, a real failed write to a **synthetic fixture setting** does not reopen consent or change that row. Malformed stored ledger bytes are neither repaired nor admitted. |

The secondary-write case is a deliberately ordered harness sequence. It is **not** an installed protocol for real settings/enrollment writers, a cross-option transaction or proof that every participating writer closes admissions correctly. Fixture grants retain source `fixture_intent_v1`; nothing authenticates an administrator or converts them into real consent.

## Cleanup And Actual-State Reconciliation

Initial 16-check ledger run succeeded. Before finalizing, coverage was extended to 19 checks with counter reservation, expired journal migration/closure and preserved terminal native evidence; the final run also succeeded. No failure was hidden or test expectation weakened.

- Initial ledger table `wp_alynt_lifecycle_probe_2786274a9352`: 25 rows previewed and removed; absence verified.
- Final ledger table `wp_alynt_lifecycle_probe_d761ab6b81a7`: 25 rows previewed and removed; absence verified.
- Default legacy lifecycle proof rerun: all 13 checks passed, 19 owned rows previewed and removed; absence verified.

Total: **69 fixture rows across three invocation tables removed**, with no leftover proof tables or fixture state. These disposable test records cannot be recovered, but can be recreated only by a separately approved proof rerun; actual site data was never included.

Each harness invocation compared a SHA256 digest of **every** actual option's name/value hash/autoload before and after, including cron/activation, and reported unchanged. Independent bundled-MySQL checks before, between and after the runs confirmed the same aggregate SQL digest and option count (545), zero matching fixture tables, and absence of the actual safety-state option. No raw option values, credentials, owner tokens or private receipts were persisted in documentation or exported.

## Review, Validation And Limits

Focused review confirmed exact target/owner guards, empty fixture ownership, bounded process waits, no legacy-mode guard relaxation, exact SQL failure/receipt assertions, retained uncertainty and cleanup. Wrong-root CLI invocation was executed and rejected before WordPress loading. All five changed/new proof entry/helper files passed PHP syntax checks. The existing default proof passed again after selector changes; production ledger and other runtime definitions were not edited.

Full repository checks and exact-commit CI receipts are recorded below after completion. This proof does not establish authenticated request/session/capability/nonce handling; genuine server-issued intent; authenticated provenance migration; real provider freshness; distributed-cache/global-writer coordination; downgrade/handoff; actual owned-effect fencing; power loss or unknown DB-server commit outcomes. Killing a child after a known committed SQL write is not a database crash, network partition or host outage. Normal production construction, legacy behavior, source versions, activation and rollback remain unchanged. No full pre-release clearance, package/release or rollout is justified here.

Executed local validation: uploader `php vendor/bin/phpunit --do-not-cache-result` passed **790 tests / 5027 assertions**, four existing Windows skips, in 7:34.196. Full PHPCS passed 137 files; build, deterministic runner verification, runner syntax, three dev-tool tests and npm/Composer audits passed, both audits with zero advisories. Composer used the existing dashboard PHAR from the uploader working directory; no dependency install/update occurred. Dashboard full PHPUnit passed **299 tests / 1423 assertions**, two existing Windows skips, plus PHPCS (138 files), structure/build and zero-vulnerability audits. PHP syntax checks passed the five proof entry/helper files, and staged diff/reference checks passed. No production PHP, PHPUnit test/assertion/skip, generated asset, dependency, version or runtime/protocol changed. The restore-point check confirmed clean source checkpoint `c6e2974`; no archive, site backup or restore was created. Exact-commit CI remains the publication completion gate.

## Implementation-Plan Progress And Exact Next Gate

The approved isolated SQL/process proof is complete at the dormant model/persistence boundary. **Recommended next gated slice: local-only V2.3 Authenticated Consent Request Boundary and Provenance Integration Design.** Define the closed client-local capability/nonce/POST intent issuance, fresh server-derived site/context, authenticated receipt/provenance transition and rejection of all existing fixture/synthetic grants before implementing a real permission writer. Resolve the version/upgrade and acknowledgement contract against the now-proven fixture ledger; do not simply relabel `fixture_intent_v1` as authenticated. Keep actual permission controls, options/schema writes, production wiring, activation and rollback disabled during that design. Real authorization and participating writer/effect proofs still require their own later approvals; no maintenance/package batch should substitute for this next permission decision.
