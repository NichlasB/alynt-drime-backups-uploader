# V2.3 Consent/Lifecycle Isolated Storage Proof

Approved and target-confirmed on 2026-10-14: `plugin-tester local-only`, Plugin Tester at `http://plugin-tester.local`, root `C:\Users\Captain\Local Sites\plugin-tester\app\public`. Source checkpoints: uploader `5690097`, dashboard `acf0a76`. This R3 proof advances the dormant lifecycle persistence item; it does not authenticate consent or authorize activation, schedules, rollback or production integration.

## Proof Boundary

Three opt-in development scripts implement the proof: `scripts/tests/lifecycle-local-proof.php` owns target/bootstrap/fixture/cleanup; `lifecycle-proof-processes.php` owns synchronized readers and child termination; `lifecycle-proof-cases.php` exercises the unchanged production CAS and schema-3 model. No production source, include list, constructor, caller, protocol, dependencies, version or generated output changes. CI adds syntax checking of these scripts on PHP 7.4/8.3, **not database execution**.

Before connection, the script rejects every root except the confirmed Plugin Tester path. Before fixture writes it checks LocalWP metadata against the WordPress database host/name, stored site URL, trusted options-table identifier and absence of external persistent object caching. `SHORTINIT` and `DISABLE_WP_CRON` load no plugins or runtime workers. Site plugins are not installed, activated or invoked.

The parent creates an **empty** `wp_alynt_lifecycle_probe_<12 random hex>` options-table clone only after verifying its absence. An independent owner marker is checked before child use and fixture resets. Only those processes redirect `$wpdb->options`; actual WordPress options remain outside the write target. All baseline/current real options are hashed in name order, including autoload state, with no raw values emitted. The script refuses existing tables and does not repair site state.

Children have fixed operation/actor/scenario vocabularies, bounded 15-second barriers and explicit process handles. Cleanup terminates only children launched by this parent, restores the real options identifier, previews the owned table's row count, drops only the table created by this parent and verifies its absence. Independent-case fixture resets are not lifecycle recovery or downgrade APIs. Cache deletion is process-local; external persistent caching is refused, not certified.

## Actual LocalWP Execution

Novamira HQ inventory contained the exact target. Its doctor reported expired OAuth, so no credential repair was attempted and the approved bundled-MySQL workflow was used. Read-only SQL verified LocalWP MariaDB **10.6.23**, database `local`, endpoint `127.0.0.1:10004`, site metadata and unchanged actual cron/active-plugin digests. The actual safety option was absent before and after; final independent SQL found zero tables matching the proof prefix.

Executed with LocalWP PHP **8.5.1** and its generated INI:

```powershell
& 'C:\Users\Captain\AppData\Roaming\Local\lightning-services\php-8.5.1+1\bin\win64\php.exe' -c 'C:\Users\Captain\AppData\Roaming\Local\run\XPa4sCxmk\conf\php\php.ini' scripts/tests/lifecycle-local-proof.php 'C:\Users\Captain\Local Sites\plugin-tester\app\public'
```

Final receipt: exit 0, all 13 scenario checks true; **19** synthetic rows previewed/removed, owned table removed and absence verified, every real option digest unchanged. No live site was accessed. Actual cron, active plugins, safety state and plugin settings remained untouched. No backup, upload, restore, Drime, schedule apply/rollback or real administrator consent was involved.

## Scenario Evidence

| Check | Real SQL/process observation |
| --- | --- |
| Failed creation | Read-only transaction rejects insert; bounded preparation returns contention, no row or success. The underlying add-option false result does not distinguish insert failure from a competing initializer. |
| Explicit creation | Schema 3 starts with `new_model` provenance, no consent and disabled authority. A missing row is not proof of never-activated history. |
| Schema-1 migration | One authoritative read plus one CAS; permission starts at zero, provenance unknown, non-autoloaded and disabled. |
| Cache safety | Authoritative snapshot ignores stale option/alloptions/notoptions entries; consent CAS clears them and ordinary get-option matches stored bytes. No distributed cache claim. |
| Failed migration/grant/revoke | Actual SQL writes rejected by read-only transactions; generic write error, original bytes retained. Failed revocation retains the prior synthetic consent and binding rather than falsely reporting success. |
| Schema-2 terminal dispatch | Completed receipt/cadence/due/claims survive migration; no inherited consent, authority disabled, no restarted dispatch. Only model transitions occur; no callback effect. |
| Two-process migration | Independent readers synchronize on the same older bytes; only one migration increments storage generation/revision, both observe the same resulting schema without reset. |
| Competing grant/rebind | One accepted permission epoch/CAS, never two; authority stays disabled. Either valid ordering is allowed in this unordered race. |
| Ordered rebind winner | Both readers observe the old bytes; rebind commits first, stale grant reloads and rejects the old binding. |
| Ordered revocation winner | Same binding, revocation commits first; stale grant reloads and rejects the old permission generation. No consent resurrection. |
| Interrupted acknowledgement | Child persists rebind/revocation then pauses before returning its receipt. Parent terminates that exact child, reconstructs a new store and observes committed binding/generation; both old-binding and old-epoch retries reject without changing bytes. |
| Expired uncertainty | Unfinished schema-1 journal still blocks migration after 25 hours; a separately seeded synthetic schema-3 draining journal survives rebind/revoke and rejects consent. This does not prove a runtime recovery route. |
| Corruption | Malformed stored JSON is rejected and left unchanged by preparation and consent APIs. No repair/delete/reinitialize fallback. |

The first probe failed an overly strict harness assertion: LocalWP's existing missing `php_imagick.dll` startup warning appeared in child output and was mistaken for an acknowledgement. Cleanup succeeded and real options were unchanged. The corrected assertion scans for the actual structured receipt instead of demanding empty stdout; the expected warning remains visible. This was a proof-fixture defect, not a lifecycle/runtime defect or weakened success contract. Subsequent runs, including the final metadata/cleanup guard tree, passed. All four invocation tables were removed (68 total synthetic rows across runs); no fixture was retained.

Expected SQL-error output is suppressed only during deliberate read-only fault transactions, and the prior setting is restored in finally. No unhandled exception is treated as a definitive no-write result. The acknowledgement probe specifically demonstrates why restart must inspect authoritative state instead of blindly replaying an operation whose response was lost.

## Verification And Limits

`php -l` passed on all three new scripts. A wrong-root CLI probe returned the expected rejection before loading WordPress. Uploader PHPCS passed 130 runtime files (existing PHPCompatibility deprecation remains); scripts under `scripts/tests` are excluded by the existing standards configuration and separately syntax/source-reviewed. Build, deterministic runner verification, portable runner syntax, three dev-tool tests, npm audit and Composer audit passed; audits reported zero vulnerabilities and generated outputs stayed unchanged. Dashboard docs-only PHPUnit passed 299 tests / 1423 assertions with two existing Windows skips, with PHPCS, structure, build and both dependency audits passing. Full uploader `php vendor/bin/phpunit --do-not-cache-result` passed **634 tests / 3749 assertions**, four existing Windows skips, in 7:20.250, no failures/errors/risky tests. No PHPUnit expectations, assertions, skips or thresholds changed. Exact-commit CI is the remaining publication gate.

This proves the tested local database commits, exact-byte CAS, independent-process ordering, error closure and retained uncertainty under this environment. It is **not** proof of power-loss/host crash durability, distributed cache isolation, multisite, every SQL engine/version, after-effects queue reconciliation, real scan/upload effects, capability/nonce enforcement, trusted administrator consent or production lifecycle/wake-up fencing. The interruption is after the SQL adapter confirms a commit, not a database outage at an unknown server-commit boundary. Existing internal exception semantics are unchanged; future request adapters must redact failures and reconcile uncertain outcomes before exposure.

Versions remain uploader `0.5.25` / dashboard `0.1.77`. No package/release/deploy is warranted for development-only proof tooling. Restore-point check confirmed clean source checkpoint `5690097`; no archive, site/database backup or restore was created. Actual LocalWP options and live state are not rollback targets for these source changes.

## Implementation-Plan Progress And Next Gate

The approved Consent/Lifecycle Isolated Storage Proof is complete at its local persistence layer. The next meaningful gate is **local-only V2.3 Trusted Consent Context and Lifecycle Admission Design**: define how an authoritative lifecycle snapshot, current enrollment/key context, current automatic-scan setting and authenticated client-local permission establish the private policy projection. Address missing/revoked/stale context, exception/ambiguous-write handling, and why `new_model`/`unknown` cannot establish never-activated or verified handoff. Keep production wiring, actual opt-in, all authority execution, schedule/queue effects and rollback disabled. This needs separate approval for the new permission/provenance decision, not more maintenance or packaging.

## Publication Receipt

Proof tooling commit `8611d72206f6ab4143e6c8726ccd4c62f3d2e9a1` is pushed to `origin/master`; exact-commit [Quality 38554025197](https://github.com/NichlasB/alynt-drime-backups-uploader/actions/runs/38554025197) passed PHP 7.4/8.3, each 634 tests / 3803 assertions without skips, with the new proof-script syntax checks plus PHPCS/runner and Node build/dev-tool/generated-asset/audit checks. Dashboard plan commit `321dc48498628fe09b5417a789ccc66bd6ac8dcd` passed exact-commit [CI 38554038117](https://github.com/NichlasB/alynt-drime-backups-dashboard/actions/runs/38554038117), each PHP version 299 tests / 1430 assertions without skips plus lint/structure/build/audits. Both implementation branches are clean and synced; the local database proof is separately executed evidence, not a Linux CI claim. The receipt-only follow-up changes no tested code and requires its own exact-head CI check before handoff.
