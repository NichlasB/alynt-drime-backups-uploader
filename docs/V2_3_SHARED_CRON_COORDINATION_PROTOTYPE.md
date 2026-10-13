# V2.3 Shared WP-Cron Writer Coordination Design/Prototype

Date: 2026-10-13. Outcome: approved local-only prototype complete; **universal writer coordination is not established and rollback execution remains deferred**. Risk tier: user-approved R3 concurrency/database fixture scope. Uploader remains `0.5.25`; dashboard remains `0.1.77`. No runtime integration, release or deployment.

## Approval, Baselines And Plan

The user approved this exact local-only slice after the atomic-store proof reproduced a core whole-option overwrite. Targets: uploader repo, dashboard planning docs, and `plugin-tester.local` at `C:\Users\Captain\Local Sites\plugin-tester\app\public`. Baselines: uploader `9aee5d5`, dashboard `131e20b`, clean/synced before editing. The toolkit source restore-point helper ran in read-only `check` mode: clean Git recovery point confirmed; its September external archive is historical, not a new source snapshot or database backup. No restore was run.

Plan executed: inspect actual core/uploader writers; implement a test-only event-delta CAS adapter; test real core scheduling APIs and independent-process contention on the owned fixture; deliberately test bypasses; record the architectural decision; validate, commit, push and verify exact-commit CI. No prototype class is included by the plugin bootstrap.

## Writer And Dispatch Inventory

| Path | Observed behavior / implication |
| --- | --- |
| `Cron::maybe_schedule()` and `clear()` | Init/settings can create/remove scan and upload events; not covered by remote action ownership. |
| `Cron::apply_scan_cadence()` | Existing guarded apply clears then schedules the owned event; unchanged by this slice. |
| Deactivation/uninstall | Native clear-hook calls remove plugin events; a future authority must invalidate rather than resurrect them. |
| `wp_schedule_event()` / `wp_schedule_single_event()` | `pre_schedule_event` can short-circuit persistence, but later filters can replace the result. Normal core writes the whole cron option. |
| `wp_reschedule_event()` | Eventually delegates to schedule; interval/state reads and other filters precede that delegation. |
| `wp_unschedule_event()` / `wp_clear_scheduled_hook()` / `wp_unschedule_hook()` | Native pre-filters can return correct boolean/count/error results for a cooperative adapter. |
| `_set_cron_array()` / `update_option('cron', ...)` / direct SQL | Bypass the event API filters; stale whole-option writes remain possible. No plugin-owned advisory lock forces these writers to participate. |
| `wp-cron.php` dispatch loop | Logs reschedule/unschedule errors and then calls the event callback. Rejecting writer APIs does not prevent an already-read callback from executing. |

Inventory is based on the installed WordPress **7.1.2** source, not an assertion that every supported WordPress/plugin version behaves identically. Native API comments identify pre-filters introduced before the declared WP 6.0 minimum, but this local runtime proof is for 7.1.2 only.

## Bounded Cooperative Prototype

`tests/Support/ScheduleSafetyCronWriterPrototype.php` is test support only. It exposes callbacks for four native pre-filters and permits only `alynt_coordination_fixture_*` unrelated events; owned scan-event mutations are rejected. Registration occurs explicitly in the proof process after verifying its randomly named options clone. Constructors register nothing.

Each participating writer expresses an event intent rather than a stale whole cron array. The adapter reads authoritative database bytes, computes only that event delta, and attempts exact-byte CAS. A conflict reloads/rebases the delta, up to three attempts, preserving unrelated events. It returns true for scheduling/removing a single event, exact counts for clear/remove-hook, and generic errors on failure. Absent removals are no-op results. Malformed/version-unknown state, objects, oversized scalar values and bounded traversal fail closed. Existing fixed-name storage/cache invalidation is reused.

This is deliberately **not** a production cron replacement. It supports only hourly/single synthetic events, honors another filter's short-circuit, does not implement complete single-event duplicate rules or `schedule_event` transformation semantics, and does not solve persisted cross-process lease integration. Broad installation could affect every plugin. None is approved or performed here.

## Real WordPress / MariaDB Evidence

Novamira complete inventory resolved `http://plugin-tester.local`; doctor authorization is expired. The normal approved LocalWP database workflow verified metadata and baseline using bundled `mysql.exe` before PHP bootstrap. MariaDB 10.6.23, port resolved from current LocalWP metadata, local database; PHP 8.5.1 with generated site INI.

Run `scripts/tests/schedule-safety-local-proof.php <approved-root> --coordination` with the LocalWP PHP/INI command recorded in `V2_3_ATOMIC_STORAGE_PROOF.md`. The existing hard root/siteurl guard and `SHORTINIT`/`DISABLE_WP_CRON` boundary remain. Only translation helpers and core scheduling functions are additionally loaded; no active plugins or backup/cron worker are bootstrapped.

Final receipt: exit 0. It repeats the prior atomic-state proof, then demonstrates:

- Two independent PHP processes both read the same fixture cron snapshot at a database barrier, call real `wp_schedule_event()`, and persist both unrelated events. Existing scan/unrelated data remains. This proves cooperative conflict/rebase, not arbitrary external writer exclusion.
- Real recurring/single scheduling, reschedule, argument-specific removal, clear-hook and whole-hook removal preserve return/count conventions. Deliberately stale option caches do not cause loss through the adapter's authoritative reads.
- Cooperative attempts to change the owned event return the protected error without persisted changes, including error-to-false behavior when `$wp_error` is false.
- A real read-only transaction rejects a cooperative core-API write; it returns a generic failure, not success, and persisted cron remains unchanged. Expected raw database errors are suppressed only inside this asserted fault probe, with rollback and restoration in `finally`.
- A later `pre_schedule_event` filter overrides the protected error with true; the native API reports success despite unchanged storage. This is an explicit false-success counterexample.
- Real `_set_cron_array()` bypasses the installed event filters and overwrites/removes previously preserved events on the fixture. This is an explicit missed-interception counterexample.
- A harmless callback driver replays core's reschedule-error, unschedule-error, callback-dispatch ordering. The synthetic callback still runs. The real `wp-cron.php` process and backup callback are **not** invoked; source inspection establishes the ordering being modeled.

All prototype filters and the harmless observer were removed. Eight synthetic rows and the owned table were removed; actual Plugin Tester cron bytes and safety option were unchanged. The fixture is reproducible; no user/site data was deleted. External persistent object cache was not active. The existing missing `php_imagick.dll` startup warning remains visible and did not prevent the assertions.

## Decision: Do Not Install A Global Cron Replacement

Passing cooperative cases do not satisfy the rollback requirement against nonparticipants. A native pre-filter cannot enforce a database exclusion contract on direct writers, guarantee truthful results after later filters, or block an already-read callback. An advisory database lock only protects participants; transaction row locks delay other writers but do not make their later stale unconditional write safe. A late query filter is process-local, misses other connections, and would require fragile global SQL interception and compatibility semantics. None is proposed as a universal solution.

Keep the existing execution deferral and production scheduler unchanged. Do not wire rollback, rewrite third-party cron options, advertise execution-ready evidence, create a rollback opt-in, or weaken the unrelated-event preservation contract.

## Preferred Architectural Direction (Design Only)

Evaluate **client-owned cadence authority with guarded scan dispatch**, rather than treating the shared cron option as the rollback authority. WP-Cron would provide wake-ups; a client-owned versioned record would determine the effective cadence/due time and whether the scan callback may start. Apply/rollback would conditionally mutate that authority and its protected journal, not replace a shared cron array. A missed wake-up must remain observable as delayed/overdue work, not silently reported healthy.

Source implications are material: `current_scan_schedule_state()` currently derives cadence/next-run/fingerprint from `wp_get_schedule()` and `wp_next_scheduled()`; `Cron::scan()` currently scans unconditionally. A client-owned mode would require separately reviewed changes to both, plus settings/lifecycle invalidation, legacy capability/evidence compatibility and scan-start ownership. This prototype does not implement those changes.

Candidate requirements for the next approval:

1. Review a versioned internal authority/dispatch-lease extension to the existing dedicated safety state before any write; unknown/old schema fails closed. No additional option/table is implicitly approved. Existing schema-1 journal evidence must survive migration or block it explicitly.
2. Keep a dormant API/model first: no bootstrap option creation, settings, pairing, endpoint, scan callback or cron hook wiring. Simulate authority/apply/rollback using synthetic records only; execution remains unavailable.
3. Define effective cadence, next-due time, enrollment/revision binding, dispatch ownership and uncertain-start recovery. Expired ownership must not authorize another scan while prior work may still run. Journal and authority updates must share an atomic boundary or fail closed, not rely on two independent successful writes.
4. Prove stale callbacks, duplicate wake-ups, competing cadence mutations/dispatch claims, exact expiry, failed writes, restart reconstruction, capacity, and invalidation. No real scan/backup/upload or core cron dispatch in the first foundation proof.
5. Preserve the existing legacy WP-Cron-driven apply/preview contract until separately reviewed opt-in/migration. Do not reinterpret historical cadence-plus-next-run fingerprints or promise immediate legacy rollback. Candidate capability/protocol changes remain design-only until approved implementation.
6. Runtime integration, full crash-boundary proof, minimum-WP compatibility, release/package, live deployment and one client pilot remain later gates. Missing core wake-ups affect liveness even if authority safety is preserved; this architecture must not claim otherwise.

## Verification And Exact Next Gate

Seven new focused prototype tests passed (26 assertions): competing delta rebase, protected owned event, argument-specific/count removal, database failure/contention, unknown version/object rejection, short-circuit ownership and malformed/bounded input. Full regression and exact pushed-commit CI remain mandatory completion checks, reported with the delivered commits. No skip or audit threshold was added or weakened.

Executed final local gates: `php vendor/bin/phpunit --do-not-cache-result` passed (330 tests, 2709 assertions, four existing skips); `php vendor/bin/phpcs` passed across 119 runtime files; both proof-script `php -l` checks passed; `npm run test:dev-tools` passed (three tests); `npm run build` and `npm run verify:runner` passed with generated runtime assets unchanged; `npm audit --audit-level=high` found zero vulnerabilities. PHPCS's existing PHPCompatibility deprecation notice remains visible. Test/support and proof-script files are excluded by the existing `/tests/` PHPCS rule, so their syntax, focused/full PHPUnit and actual WordPress proof provide distinct validation rather than a claim of WPCS coverage.

Recommended next gated slice: **V2.3 Client-Owned Cadence Authority Safety Foundation**, local-only, dormant and rollback-disabled, limited to the reviewed internal model and synthetic concurrency/failure proofs above. Approval is required because this changes the scheduling authority/storage contract beyond the current shared-writer test prototype. It is not approval to change existing `Cron::scan()`, Schedule Apply, client settings, actual cron state, production, release or deploy.
