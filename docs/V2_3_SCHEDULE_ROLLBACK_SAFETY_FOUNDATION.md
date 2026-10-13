# V2.3 Schedule Rollback Safety Foundation

Status: local-only foundation approved on 2026-10-13; bounded persistence and worker rejection prerequisites implemented. The subsequently approved atomic-storage slice adds dormant claims, journals and revision binding with isolated Plugin Tester database evidence, recorded in `docs/V2_3_ATOMIC_STORAGE_PROOF.md`. The complete foundation is **not yet proven**: shared WordPress cron writer coordination remains blocked by a reproduced core overwrite. No rollback execution, client opt-in, release, deployment, or live-site changes are included.

Dashboard design authority: companion dashboard `docs/V2_3_SCHEDULE_ROLLBACK_RUNTIME_DESIGN.md`, committed as `643c2c8`. Clean source baselines before this slice: uploader `afaf5d8` and dashboard `643c2c8`; these Git commits provide source recovery points. No website/database backup was created or required for these repository-only changes.

## Initial Implemented Prerequisites

1. `Remote_Action_Store::persist()` clears the option cache and compares the expected state with an option API readback. It no longer inserts a fabricated successful state into cache after a failed write. `update_option()` returning false may mean unchanged; readback determines whether the expected state exists.
2. Action upsert returns an empty record when the readback does not match. Lock acquisition returns false on failed/mismatched persistence. This is failure detection, not an atomic lock or proof of database durability.
3. The action-intent controller returns HTTP 500 with support-safe `action_state_store_failed` rather than accepting/scheduling work without a stored accepted record. Empty record handling also covers failed busy/rate-limit/failure audit writes. A failed later result write reports that no successful outcome is confirmed; it must not be interpreted as proof that no earlier state changed.
4. The worker explicitly checks its existing action allowlist before acquiring a lock or doing work. A stored unknown or reserved `schedule_rollback` action is recorded unsupported and cannot fall through to scan/upload. The REST parser still rejects rollback execution and its proposed payload shape.

No capability, protocol version, schema, option name, signing policy, Schedule Apply opt-in, rollback execution opt-in, or cadence implementation is added. The default rollback availability remains false.

## Storage Review: Existing Store Is Insufficient

| Property | Current source behavior | Consequence for rollback |
| --- | --- | --- |
| Exclusive claim | `get()` then ordinary `update_option()` with an option API readback. | Two interleaved readers can each write/read their own lease before another overwrite. Readback does not provide compare-and-swap or exclusive ownership. |
| Journal fields | `sanitize_state()` reconstructs an explicit existing field list. | New journal/consumed-source/revision fields are discarded; no such storage contract exists today. |
| Retention | Records are sliced to `MAX_RECORDS = 50`, independent of unresolved recovery status. | Recovery evidence must not be stored as ordinary evictable history. |
| Idempotency | Keys expire after 24 hours and refer to records that may already be pruned. | Existing idempotency is insufficient for durable source consumption and pending recovery. |
| Failure visibility | This slice detects API write/readback mismatches. | It does not establish crash durability, atomic reservation, protected journal transitions, or database-level transaction semantics. |

Do not label this existing option store rollback-ready, bolt executable flags onto legacy metadata, or generate execution-ready contract markers. A separate storage change must be reviewed before atomic claims or journals are implemented, as required by the dashboard design.

## Schedule Writer Inventory

| Writer | Existing entry point | Coordination gap |
| --- | --- | --- |
| Remote Schedule Apply | `trait-remote-action-worker-schedule-apply.php` calls `Cron::apply_scan_cadence()`. | Remote action lock is a non-atomic option update and does not cover other cron writers. |
| Init/automatic scan setting | `Cron::maybe_schedule()` creates missing scan/upload events or calls `clear()`. | Does not share an exclusive schedule lock or a mutation revision. |
| Lifecycle removal | `class-deactivator.php` clears scan/upload hooks; uninstall also clears plugin hooks. | Needs invalidation/recovery rules, not a rollback that resurrects intentionally removed events. |
| WordPress recurrence | Core reschedules/unschedules events outside the uploader worker. | Cadence-plus-next-run fingerprint can change during the proposed critical section. |
| Other cron writers | Core/plugins can update the shared cron option. | A plugin-owned action mutex alone cannot prevent stale whole-option writes or prove unrelated-event preservation. |

The existing `apply_scan_cadence()` clears the old event before scheduling its replacement. This slice does not change it or claim to have proven recovery. Any new persistence primitive must demonstrate failure handling and restart reconciliation using real isolated WordPress APIs; mocks only cover the prerequisite failure contracts above.

## Validation And Limits

New regressions cover failed record writes, failed lock acquisition, failed lock release without invented cache state, successful round trips, competing readback, no worker queue on failed acceptance, parser rejection of rollback, and worker rejection of an injected rollback record. Failed-write and stored-worker tests reproduced the old behavior before the fixes.

Existing remote-action tests continue to exercise signing, opt-in, schedule preview/apply, non-mutating rollback preview, cleanup preview, redaction, and history limits. The two new persistence test classes run in separate processes: Brain Monkey's optional cache-function definitions must not leak into unrelated tests that intentionally run without WordPress cache APIs. Existing assertions and test expectations are preserved.

No database, WordPress site, actual concurrent cron worker, live client, crash/restart, or durable journal integration was exercised. Passing unit tests must not be reported as those proofs.

Executed local evidence on 2026-10-13:

- `php vendor/bin/phpunit --do-not-cache-result --filter 'RemoteAction(Store|Worker|Intent|Rollback)'`: 36 tests, 219 assertions passed.
- `php vendor/bin/phpunit --do-not-cache-result tests/RemoteActionIntentEndpointTest.php`: 17 tests, 83 assertions passed.
- Final `php vendor/bin/phpunit --do-not-cache-result`: exit 0, 310 tests, 2595 assertions, four skipped tests. The skips remain visible; this is not a claim of complete runtime/integration proof.
- `php vendor/bin/phpcs`: passed; the installed PHPCompatibility rule emits a pre-existing deprecated array-property syntax notice.
- `npm run build`, `npm run verify:runner`, and `npm run lint:runner`: passed; generated assets and runner remain unchanged.
- Both repository diffs passed `git diff --check`; dashboard `npm run check:structure` passed.

Initial focused tests failed on false acceptance/lock persistence and the stored-worker boundary before fixes. The first full run also exposed cache-function fixture leakage; isolated test processes resolved it without removing assertions, adding skips, or changing unrelated runtime code. The final complete run above supersedes that intermediate result. Exact pushed-commit CI is verified separately after these local checks.

## Historical Follow-Up Approval And Contract

### Development Dependency Audit Remediation

Uploader prerequisite commit `d196267` passed PHP 7.4/8.3 CI tests and coding standards, but its Node dependency audit failed on the existing `nodemon -> chokidar 3 -> braces` and `concurrently -> shell-quote` development dependency chains. The user approved remediation on 2026-10-13. The [braces advisory](https://github.com/advisories/GHSA-vfj7-8cjw-p6xm) reports no published patched version, so a forced nodemon downgrade or an unsupported Chokidar override is not used. The [shell-quote advisory](https://github.com/advisories/GHSA-pqg4-j6r4-53mv) is addressed by the patched dependency shipped with concurrently 9.2.5.

The remediation selects `concurrently ^9.2.5` (with patched `shell-quote 1.12.0`) and replaces nodemon with direct `chokidar ^4.0.3` plus `scripts/watch-deploy.mjs`. The explicit `deploy:watch`/`dev:all` commands remain available. The new watcher retains an initial run, PHP/JS/CSS/HTML filtering, ignored directories, and two-second debounce; it serializes commands and lets an active command finish when stopped. The audit threshold remains unchanged. Node tests exercise real file events with an injected harmless runner, never `deploy.sh`.

Local remediation checks: `npm audit --audit-level=high` reports zero vulnerabilities; `npm run test:dev-tools` passes; build and deterministic runner checks pass with generated runtime artifacts unchanged. A clean lockfile install and exact pushed-commit CI provide the final verification. This removes the dependency gate only; atomic rollback storage, journals, and core cron coordination remain unimplemented and gated.

### Storage And Integration Approval

Recommended next slice: **V2.3 Rollback Atomic Storage and Cron Coordination**, beginning with a reviewable storage contract and then separately authorized local implementation/integration proof. Prefer a conditional-update approach confined to client-owned state; do not introduce a new table or option without explicit storage approval. The design must define durable per-source claims, revision/enrollment binding, bounded but protected unresolved journals, recovery phases, and cache invalidation, and must address core cron writers rather than relying on the current action lock.

Preferred storage proposal for that approval: one dedicated, non-autoloaded client-local option, `alynt_drime_backups_schedule_safety_state`, with a versioned internal schema. Keep it separate from ordinary action history so older history sanitizers and pruning cannot erase unresolved recovery evidence. Use conditional database updates against the expected stored value for claims/transitions, with enrollment/revision binding and explicit cache invalidation. Bound completed records; fail closed at capacity rather than evict unresolved journals. Disabling/re-pairing invalidates executable claims while preserving unresolved recovery evidence for inspection. No table migration is proposed. Exact journal fields, lifetime, uninstall preservation, failure semantics, and compatibility with older plugin builds must be reviewed before the first storage write.

This is a proposed option name and strategy only: no new option, SQL operation, schema, uninstall change, or runtime integration is implemented. Conditional client-state storage alone does not solve shared cron-option races. Approve isolated integration work only with rollback still disabled, and require it to demonstrate a safe cron coordination strategy or report that execution must stay deferred.

Candidate storage contract for review:

- Internal schema version 1; unsupported/malformed versions fail closed without overwrite. The state carries an enrollment-binding hash, monotonically increasing schedule revision, and claims keyed by a validated source-apply UUID.
- Each claim binds the action ID, request hash, preview ID/hash, metadata hash, enrollment/revision, supported before/intended cadence, safe schedule fingerprints, UTC creation/deadline, lease owner/deadline, and recovery phase. It contains no raw cron array, path, credential, signature, backup/Drime identifier, or arbitrary settings. Any local reconstruction data beyond these fields requires separate review.
- Phases are `claimed`, `write_started`, `recovery_required`, `succeeded`, and `failed_closed`. An uncertain or started claim is never executable again merely because its lease expires. Recovery is explicit; automatic expiry must not erase its journal.
- Proposed caps are 50 retained claims and 128 KiB serialized state. Expired final records may be pruned after at least 24 hours; unresolved records are protected. Reaching either cap blocks new claims instead of deleting unresolved state. A source cannot become executable again through pruning; its original one-hour evidence deadline still governs.
- Initialization uses one non-autoloaded option creation. Subsequent claims/transitions use compare-and-swap against the exact expected stored state, with at most three contention retries and full validation after each reload. A successful write/readback is not substituted for conditional-update ownership. Cache invalidation must include all affected option cache entries and be tested with persistent caching.
- Re-pair/revoke/deactivation invalidate execution authority, without discarding unresolved journals. Proposed uninstall policy preserves unresolved safety state for explicit operator resolution; any eventual purge requires its own preview/approval. Older-build downgrade must retain the separate option and keep execution unavailable.
- No endpoint, capability flag, opt-in, action request, schedule write, or production integration consumes this proposed store until later approval. Integration testing may exercise the primitive with synthetic records on the confirmed isolated target only.

Before that slice can claim completion, approve its storage contract and confirm an isolated WordPress target and database-write scope for persistence, concurrency, crash/restart, and unrelated-event-preservation tests. Existing website/pilot approvals do not apply. Keep `schedule_rollback` unadvertised, rejected by REST and worker, with no dashboard execution controls throughout.

## Approved Atomic-Storage Follow-Up

The user approved the above dedicated-option contract and isolated `plugin-tester local-only` proof on 2026-10-13. That approval supersedes the pending storage/integration approval statements in the historical sections above, not their runtime deferral. `docs/V2_3_ATOMIC_STORAGE_PROOF.md` records the implemented classes, unit/database evidence and the observed unguarded core overwrite. The initial prerequisite validation above remains historical; it must not be read as the latest full-suite total or as integration evidence.

The subsequent approved **V2.3 Shared WP-Cron Writer Coordination Design/Prototype** is recorded in `docs/V2_3_SHARED_CRON_COORDINATION_PROTOTYPE.md`. Cooperative native-API CAS preserved concurrent writes, but whole-option/filter/dispatch counterexamples prevent universal coordination claims. Execution stays disabled.

Exact next gate: **V2.3 Client-Owned Cadence Authority Safety Foundation**, a separately reviewed local-only dormant authority/dispatch model with synthetic proof. No global cron replacement, production scheduler changes, legacy contract migration or real scan/rollback execution is authorized by the completed prototype.
