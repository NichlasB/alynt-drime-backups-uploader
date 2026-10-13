# V2.3 Client-Owned Cadence Authority Safety Foundation

Approved: 2026-10-13. Scope: dormant internal model and synthetic proof only. Baselines: uploader `0d893c3`, dashboard `bff6242`. No runtime scheduler, existing Schedule Apply/preview contract, endpoint, capability, opt-in, settings, pairing, lifecycle, release or deployment changes. `schedule_rollback` remains rejected.

## Reviewed Internal Storage Contract

Use schema **2** of the already approved dedicated, non-autoloaded `alynt_drime_backups_schedule_safety_state`; no additional persistent option/table. Keep schema-1 validator/API unchanged for legacy callers. Explicit initialization/migration only, never bootstrap writes. Schema-1 journals are retained field-for-field; migration blocks if any claim is unfinished. Invalid/unknown/autoloaded state is never repaired or overwritten. Older schema-1 code rejects schema 2 rather than discarding new fields.

Schema 2 retains generation, enrollment binding, schedule revision and bounded claims, adding:

- `authority`: fixed `client_owned_v1` mode, supported cadence, positive UTC `next_due_at`, boolean `enabled`. This boolean enables the **model**, not production execution.
- `dispatch`: null or one bounded owner UUID/request-hash record with enrollment, schedule revision, captured due time, claim/lease times, phase (`claimed`, `started`, `recovery_required`, `completed`, `cancelled`) and completion time. It carries no credentials, raw cron, file/backup/Drime IDs or arbitrary settings.

Authority fingerprints include internal version/mode, enrollment, revision, cadence, enabled and next-due time. They are not accepted wire evidence and never reinterpret legacy cadence-plus-cron-next-run fingerprints. New trusted eligibility/preview producers remain later work.

## Atomic Operations And Invariants

- Reserve a candidate source using existing protected claims, without mutating authority. Commit a model cadence change only for its owner/hash, fresh bounded evidence, matching revision/current authority fingerprint and matching intended fingerprint. Journal terminal state and authority/revision update occur in **one CAS**. An exact repeated commit observes the consumed result; it does not repeat mutation.
- Reject authority mutation while dispatch is claimed, started or unresolved, even after lease expiry. Reject pending legacy write/recovery journals. Cap and pruning policy remain 50 claims / 128 KiB; unresolved records are never evicted.
- A wake-up must present a matching current revision/fingerprint. Due time is inclusive (`now >= next_due_at`); missing/malformed/disabled/future/stale authority fails closed. Claiming dispatch writes ownership only; it does not execute work or advance next-due time.
- Starting dispatch is a separate single-use conditional transition requiring the same owner/hash, enrollment/revision/due snapshot, and a live five-minute lease. A duplicate start never grants execution permission. Lease expiry never transfers ownership or implies prior work stopped.
- Completing a started dispatch requires the live matching lease/authority and atomically marks completion plus advances next due to completion time plus the configured interval. No catch-up burst. Failure/expired completion leaves protected evidence and blocks another dispatch pending inspection; recovery marking does not grant replay permission.
- Cancelling an unstarted claim is owner-bound and explicit. Started/uncertain work cannot be cancelled as if it never ran. Invalidation disables authority and increments revision, closes unstarted claims and preserves uncertain dispatch/journal evidence; re-binding never erases uncertainty.
- All internal timestamps/counters/types are strict and bounded; malformed stored phase relationships fail closed. At most three CAS retries re-evaluate the current state. No cross-option transaction or raw cron mutation is used.

## Planned Proof

Pure tests and the approved Plugin Tester owned-table fixture will cover migration retention/blocking, stale/early/duplicate wake-ups, interleaved authority mutation versus dispatch, owner mismatch, exact expiry, failed writes, restart reconstruction, capacity/invalidation and atomic journal+authority outcomes. Fixture-only child processes may prove competing claims; no actual scan/backup/upload or core cron dispatch is loaded. Real crash/power-loss, distributed cache, production wiring and WP minimum-version runtime compatibility remain unproven.

Source-only dormant definitions may be loaded at bootstrap; no object/hook or option is created there. Existing production scheduling and remote-action behavior must remain unchanged and regression-tested.

## Implemented Milestone And Proof

`class-cadence-authority-store.php` and the validation/mutation/dispatch traits implement this schema-2 model. The schema-1 store gains protected extension points only; its public behavior and validation remain unchanged. Its reservation policy defaults to true, while the schema-2 policy checks enabled/pending state **inside each CAS attempt**, including after a conflict. Separate legacy initializer/claim/transition entry points on the authority class reject use rather than bypass atomic authority semantics. Canonical fingerprints are field-order independent. Backward clock movement cannot grant an early dispatch start or cadence commit.

Twenty-three focused tests passed (81 assertions), including both mutation/dispatch race orderings, a single-use start race, disabled-state/invalidation reservation races, exact expiry, owner mismatch, invalid/unknown state, preserved migration journals, protected capacity, failed writes, due advancement, restart reconstruction and retained uncertainty.

The approved `plugin-tester local-only` database proof uses the existing hard-root/siteurl-guarded owned options clone and `SHORTINIT`/`DISABLE_WP_CRON` harness. Novamira complete inventory confirms `http://plugin-tester.local`; its doctor authorization is expired, so current LocalWP metadata and bundled `mysql.exe` verified the baseline before PHP access. The target/root are unchanged from the approved shared-writer proof. No development plugin is installed.

Executed with LocalWP PHP 8.5.1 / generated site INI:

```powershell
& 'C:\Users\Captain\AppData\Roaming\Local\lightning-services\php-8.5.1+1\bin\win64\php.exe' -c 'C:\Users\Captain\AppData\Roaming\Local\run\XPa4sCxmk\conf\php\php.ini' scripts/tests/schedule-safety-local-proof.php 'C:\Users\Captain\Local Sites\plugin-tester\app\public' --authority
```

Final receipt: exit 0. Migration blocks unfinished journals without changing bytes; the never-executed synthetic journals are explicitly closed only for the positive migration test, and final records are retained. Real read-only transactions reject migration, cadence commit and dispatch completion; neither journal nor authority partially changes. Two independent processes read the same state at a database barrier and produce one dispatch owner. Cadence commit and dispatch completion each use one authoritative read plus one conditional update (two database queries). Duplicate commit is observation-only. Reconstructed expired dispatch cannot complete or be replaced; invalidation disables authority and retains recovery evidence.

The authority segment leaves even the **fixture cron option** unchanged. The harness's earlier legacy tests still exercise their own fixture cron counterexample. Eight synthetic rows and their owned table were removed; actual Plugin Tester cron and safety option remained unchanged. The prior `--coordination` proof also passed after these changes. Persistent external object cache is not active. The existing missing `php_imagick.dll` startup warning and PHPCompatibility deprecation notice remain visible.

No real scan, backup, upload, remote action, core cron dispatch or production authority is exercised. Process persistence is proven for competing claims plus reconstruction in a new store instance, not forced server crashes or power-loss durability. The one-CAS boundaries remove a separate journal-versus-authority write gap; they do not prove a future real worker's start/side-effect/completion boundaries.

## Next Contract And Runtime Boundary

The model consumes **internally trusted future evidence**, not authenticated legacy action metadata. Its hashes and source IDs are not enough to authorize a real client mutation. Before runtime wiring, define/review a versioned authority-mode evidence contract and eligibility producer/evaluator that binds enrolled client, fresh source apply, current authority revision/fingerprint, preview and intent expiry, source consumption, local opt-in and supported version. Do not reinterpret historical cadence-plus-next-run hashes or advertise execution readiness merely because this model exists.

Preferred next gated slice: **V2.3 Cadence Authority Evidence and Eligibility Integration**, local-only with dispatch/rollback still disabled. Begin by reviewing additive protocol/threat-model candidates and shared uploader/dashboard fixtures, then implement the versioned read-only evidence/eligibility boundary without creating production authority, changing existing action semantics or wiring the scan callback. This needs separate approval because it touches protocol/trust boundaries beyond the dormant internal store.

Follow-up: that slice was approved and implemented locally; see `docs/V2_3_CADENCE_AUTHORITY_EVIDENCE.md`. It adds strict native commit provenance, read-only local-context producer/inspection and a dormant Health Summary injection seam; the dashboard consumes only the separate allowlisted namespace. It does not activate authority or turn model changes into authenticated remote Schedule Apply results. Dispatch and rollback execution remain disabled. The next meaningful gate is local-only **V2.3 Cadence Authority Guarded Scan Callback Integration** and disposable-fixture crash-boundary proof, not routine maintenance or deployment.

Guarded scan callback integration, initialization/migration on a real opted-in site, lifecycle/settings wiring, missed-wakeup visibility, full runtime crash-boundary proof, release/package, live deployment and schedule/rollback pilot remain later approvals. A future consumer must distinguish model ownership from permission to run real work and preserve the default legacy path until explicitly opted in. Keep production scheduling unchanged throughout this milestone.

## Final Repository Verification

- `php vendor/bin/phpunit --do-not-cache-result`: exit 0, 353 tests, 2790 assertions, four existing skips. This final run supersedes intermediate 349/352-test runs before the last policy/clock guards. No new skips, weakened assertions or audit exceptions.
- `php vendor/bin/phpcs`: passed across 123 runtime files; final modified-condition files also passed the focused rerun. Four new cohesive runtime files are each below 200 lines; no broad file splitting/refactor.
- Proof-script `php -l` checks passed. `npm run test:dev-tools` passed (three tests); `npm run build`, `npm run verify:runner`, `npm run lint:runner` passed; generated artifacts unchanged. `npm audit --audit-level=high`: zero vulnerabilities.
- Dashboard companion `php vendor/bin/phpcs`, `php vendor/bin/phpunit --do-not-cache-result` (247 tests, 1347 assertions, two existing skips), and `npm run check:structure` passed. Dashboard changes are planning docs only.
- Both diffs pass `git diff --check`; remote baselines remained synced. Post-proof SQL confirmed zero leftover owned probe tables and zero actual site safety-option rows. Actual cron/state equality is asserted by each successful proof.

Exact pushed-commit PHP 7.4/8.3 and build CI is the publication completion gate. No package/version bump is justified for the dormant foundation alone; uploader stays `0.5.25`, dashboard stays `0.1.77`. This is the completed approved internal safety milestone, not complete runtime rollback readiness.
