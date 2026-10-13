# V2.3 Atomic Safety Storage Proof

Date: 2026-10-13. Status: approved local-only storage milestone implemented; shared cron coordination remains blocked. Uploader version stays `0.5.25`. No release, installation, production integration, remote action, or rollback execution is included.

## Scope And Ownership

User approval covered repository implementation of the dedicated safety option and isolated Plugin Tester database/concurrency proof. Baseline source recovery points: uploader `c286e30`, dashboard `85cd5bd`. The existing rollback request/worker rejection remains mandatory. Bootstrap loads definitions only; no safety object, hook, option, capability, opt-in or action is automatically created or registered.

| Component | Responsibility |
| --- | --- |
| `class-schedule-safety-option-cas.php` | Authoritative reads and exact-byte prepared conditional updates for fixed safety/cron option names; trusted table identifier; cache invalidation; generic errors. |
| `class-schedule-safety-store.php` | Explicit initialization, three-attempt bounded CAS, duplicate observation, single-use source claims, retention/capacity protection. |
| `trait-schedule-safety-state-validation.php` | Exact internal schema, scalar types, UUIDs/hashes, cadences, deadlines and phase invariants; no coercion/repair. |
| `trait-schedule-safety-state-transitions.php` | Owner/revision-bound phases, unresolved-writer exclusion, explicit invalidation preserving uncertain journals. |
| `class-schedule-safety-cron-cas.php` | Conditional replacement of exactly one supported owned scan event while preserving unrelated event data; no global writer coordination. |

## Implemented Internal Contract

- Dedicated non-autoloaded `alynt_drime_backups_schedule_safety_state`, JSON schema 1; not a wire/status field. Unsupported, malformed or autoloaded state fails closed without repair. No table migration or uninstall change.
- Enrollment hash, monotonic generation/revision, source UUID, distinct action/preview IDs, request/preview/metadata/schedule hashes, allowlisted before/intended cadences, UTC evidence deadlines and owner-bound lease. No raw cron, credentials, paths, backup IDs or arbitrary settings enter the journal.
- Effective deadline is the earliest metadata, preview, preview-created-plus-15-minutes, or intent expiry; lease is capped at five minutes. Exact duplicates observe original state and cannot renew ownership.
- Phases: `claimed`, `write_started`, `recovery_required`, `succeeded`, `failed_closed`. An unresolved write blocks another source entering `write_started`; expiry does not remove the journal or grant a new execution lease.
- Explicit revision/enrollment invalidation closes unstarted claims and marks started writes for recovery. These methods are not connected to production settings, pairing or lifecycle hooks yet.
- At most 50 claims / 128 KiB; only final records older than 24 hours are prunable. Capacity rejects new claims instead of evicting unresolved journals. Eligibility must still validate the original one-hour source evidence; this store does not authenticate evidence or implement eligibility/reconciliation.
- Exact-byte conditional SQL detects competing mutations; at most three attempts reload and validate state. Successful writes invalidate the option, `alloptions`, and `notoptions` cache entries. This is not a transaction spanning journal and cron.

## Isolated WordPress Database Proof

Target: `plugin-tester.local`, `http://plugin-tester.local`, approved root `C:\Users\Captain\Local Sites\plugin-tester\app\public`. LocalWP bundled MariaDB 10.6.23 / PHP 8.5.1 were verified before the probe. Novamira inventory confirmed the target; its doctor OAuth check was unavailable, so the approved LocalWP database workflow was used.

`scripts/tests/schedule-safety-local-proof.php` hard-checks the root and stored site URL, loads WordPress with `SHORTINIT` and `DISABLE_WP_CRON`, and loads development classes directly. It creates an empty randomly named options-table clone, redirects only its PHP processes to that fixture, and removes only its owned table in `finally`. No development plugin is installed and no worker/backup/cron dispatch is loaded. Actual site cron bytes and safety option are compared before/after.

Executed command (LocalWP PHP with its generated site INI):

```powershell
& 'C:\Users\Captain\AppData\Roaming\Local\lightning-services\php-8.5.1+1\bin\win64\php.exe' -c 'C:\Users\Captain\AppData\Roaming\Local\run\XPa4sCxmk\conf\php\php.ini' scripts/tests/schedule-safety-local-proof.php 'C:\Users\Captain\Local Sites\plugin-tester\app\public'
```

Final receipt: exit 0; non-autoloaded state, two-process single winner, cache invalidation, expired reconstructed-journal protection, real database write failure closure, scoped cron CAS and unguarded core overwrite all observed. Five synthetic rows and their table were removed. Actual site cron and safety option were unchanged. Persistent external object cache was **not active**. LocalWP emitted an existing missing `php_imagick.dll` startup warning; database assertions still ran successfully.

The failure probe uses a real read-only transaction to reject an attempted revision/state write, rolls it back, and verifies the original claim remains. Raw expected database errors are suppressed only inside that fault probe and converted into an asserted generic error; the prior setting is restored in `finally`.

## Blocking Counterexample And Limits

After a successful scoped cron CAS, real WordPress `_set_cron_array($stale_snapshot, true)` successfully overwrote it on the fixture table. Core ultimately performs an unconditional option update. A client-only mutex/CAS therefore cannot promise preservation against all writers. Do not enable execution, advertise coordination readiness, or use passing tests as evidence that this race is solved.

Proven restart evidence is journal reconstruction through a new store instance plus competing independent PHP processes. Not proven: process/server failure at every journal/cron boundary, power-loss durability, persistent distributed cache behavior, actual cron-runner overlap, full recovery reconciliation, multisite, production writer integration, real action eligibility or end-to-end rollback. No lease timeout grants permission to retry uncertain execution.

## Repository Validation

- `php vendor/bin/phpunit --do-not-cache-result`: exit 0, 323 tests, 2683 assertions, four existing skips. Thirteen new tests cover claims, contention, expiry, capacity, invalidation, malformed input, unresolved-writer exclusion, scope and stale cron snapshots. Process isolation prevents optional Brain Monkey functions leaking into unrelated fixtures; no assertions or audit thresholds were weakened.
- `php vendor/bin/phpcs`: passed across 119 files; pre-existing PHPCompatibility deprecation notice remains visible.
- `php -l scripts/tests/schedule-safety-local-proof.php`: passed.
- `npm run test:dev-tools`: three tests passed.
- `npm run build`, `npm run verify:runner`: passed; generated runtime assets unchanged.
- `npm audit --audit-level=high`: zero vulnerabilities.

Exact pushed-commit CI is a separate completion gate, verified after commit/push. No version bump or package is justified for this dormant foundation alone.

## Recommended Next Gated Slice

**V2.3 Shared WP-Cron Writer Coordination Design/Prototype**, local-only on Plugin Tester with execution still disabled. First review core scheduling/rescheduling APIs and uploader settings/lifecycle writers; prototype a cooperative conditional-writer boundary only in the isolated fixture. Test core and unrelated-plugin stale writers, cache contention, missed interception and fail-closed behavior. Do not install global production cron hooks as an implicit extension of this slice.

Preferred decision rule: a boundary must preserve unrelated writes and reject stale writes without silently losing legitimate cron work. A uploader-only advisory lock cannot cover nonparticipants; broad option interception may alter every plugin and needs explicit scope and compatibility review. If a bounded strategy cannot meet those criteria, retain rollback deferral instead of weakening the safety contract. Runtime opt-in, endpoint/worker wiring, lifecycle integration, release, deployment and live schedule mutation remain subsequent approvals.
