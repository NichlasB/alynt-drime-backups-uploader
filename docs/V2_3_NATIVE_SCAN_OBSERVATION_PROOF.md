# V2.3 Fixed Scan-Effect Manifest and Closed Target Observation Proof

2026-10-18; approved R3 **Plugin Tester local-only**, including exact target and
three owned disposable options/users/usermeta tables, synthetic authentication
records and cleanup. Existing application state and production wiring untouched.
Development source 0.5.25, dashboard 0.1.77; no release candidate or deploy.

## Coherent source-scoped boundary

Excluded uploader base `08a47072e934b28f0fae0eb996e6648d1b3270df`; dashboard
`e867c6d256938d4c20a2f4885904fc9c566c1f34`; toolkit
`1c6eed179324b72ceef0d0da786f30e1576d4cea`. Review the full feature additions
against these bases, not a subsequent receipt-only commit.
Predecessor: [closed ledger/codec foundation](V2_3_CLOSED_SCAN_EFFECT_CODEC_FOUNDATION.md).
Companion dashboard owns `docs/V2_3_NATIVE_SCAN_EFFECT_STORAGE_PARTICIPANT_DESIGN.md`.

Four new source files, two test classes, two native proof helpers, one new mode in
the existing three-table supervisor/worker/audit entry and CI syntax coverage.
Both plans and the design cross-reference track progress. Existing native owners,
shared core guard/session/table-profile traits, original parsers, fixed manifest,
ledger/target codecs, schema-8 null grant and bootstrap remain unchanged. Source
reference search confirms new owner loading only from explicit tests/proof code.

Restore workflow Option A/check ran before editing: uploader clean/synced master
at the excluded base, checkpoint eligible, existing external September 20 archive.
No archive, checkpoint branch, backup or restore created/performed.

## Implementation and trust boundaries

- `class-scan-observation-request.php`: flat exact eight-field string form,
  once-unslashed, bounded 4096 raw/encoded bytes and 2048 per value. Exact contract
  `scan_effect_observation_v1`, purpose `observe_closed_manifest`, confirmation
  `observe`, canonical UUIDs/decimal timestamps, original window at most 300s.
  Nonce action `alynt_drime_backups_scan_observation_v1:` plus SHA-256 of every
  ordered typed envelope field. No issuance, nonce creation or posted target port.
  This domain is separate from all existing command, receipt and issuance domains.
- `class-scan-native-observer.php`: zero-IO/no-argument factory, private actual
  observation and always-unavailable non-authorizing public `inspect`. Reuses the
  genuine-core session/capability guard before opening its own reader and after
  twenty fixed row reads. Original cookie/actor/core frame/auth native handle and
  idle session facts are pinned and rechecked. Never repairs, adopts or reconnects
  the borrowed authentication connection. Non-reentrant; no clone/serialization.
- The private nonpersistent reader uses the unchanged loopback MariaDB 10.6,
  utf8mb4/mysqlnd/64-bit, single-site/native-cache/no-drop-in profile. It is set
  server-read-only and checked between row reads. All ten compiled manifest roles
  are read twice in fixed order, with no callbacks/providers or locks. Full unique
  option-name BTREE and InnoDB BASE TABLE metadata are required. The original
  two-row selector remains private and unchanged; this separate owner uses only
  the new `scan_row` selector. Shared table-profile logic is reused, not copied.
- `trait-scan-observation-rows.php`: fixed names/caps only, prepared name values,
  validated pinned identifier, LIMIT 2 and exact binary name check. SQL CASE
  projections bound value/metadata before fetching, returning NULL rather than
  truncating oversized bytes. Counter ceiling twenty, not a generic query API.
- `class-scan-observation-data.php`: complete exact frame/tuple equality plus
  private closed ledger scope, settings UUID and no-op snapshot/queue codecs.
  Safety is bounded JSON reference data and connection is bounded plain array
  data: **not** full safety/connection runtime eligibility, trusted enrollment,
  active-state schema or admission validation. Active/registry checks are limited
  to the predecessor codec's supported no-op grammar, not an upload authorization.
  No candidate bytes, row values, fingerprints, cookies or handles are exported.
- Acceptance requires acknowledged reader close, fresh original deadline and
  monotonic budget. Own handle and warning policy are released/restored in finally.
  Private positive boolean is observed by reflection only in the isolated proof;
  it is not a continuation, native commit receipt, registration or grant.

Public output remains `unavailable` with storage/dispatch/execution all false.
No hooks, REST/admin handlers, cron, activation, cancellation, rollback execution,
producer/queue/cache integration, fixture provisioning on ordinary construction,
production option creation or scheduling are introduced.

## Genuine owned proof and safety reconciliation

Resolved registry/Local metadata: `plugin-tester`, local-only,
`http://plugin-tester.local`, `C:/Users/Captain/Local Sites/plugin-tester/app/public`,
MariaDB 10004/web 10005. Novamira HQ profile exists; doctor reports expired token
and unavailable runtime checks. No credentials/connection changed; approved direct
LocalWP fallback used. Bundled mysql initial SELECT confirmed exact URL, MariaDB
10.6.23, idle autocommit and 77 application tables before PHP/core access.

Run `scripts/tests/fixed-core-proof-audit.ps1 -Proof native-scan-observation`, with
MYSQL_PWD supplied only from the confirmed local secret source. It resolves unique
matching bundled runtimes from current metadata. The supervisor creates three
fresh marker-owned tables with random prefix, cloned from local core schemas.
Genuine WordPress **7.1.2 / PHP 8.5.1 / MariaDB 10.6.23** boots only on them, with
synthetic users/roles/session tokens and fresh process-only salts. Actual site
config/plugins/themes are not bootstrapped, HTTP and cron blocked. Native guard
functions are verified against actual wp-includes source, not stubs/true ports.

Every admitted normal, failed and mutation run has three-table cleanup after
exact row-count previews, verified fixture absence, internal actual options/users/
usermeta digest equality and independent bundled-mysql **77 application-table
inventory/checksum equality**. No raw checksums, fixture nonces, cookies, user data
or option payloads are published. Required fixtures and core-owned setup records
are discarded, not recoverable/retained; existing application state is unchanged.

Final genuine proof: **245 checks / 11 groups**:

1. Positive actual private owner decision, twenty reads, no-op codecs, unchanged
   authentication session, closed owned reader and public unavailable result.
2. Distinct native reader thread, server read-only state and actual owned-fixture
   UPDATE refusal with exact MariaDB **1792**. No arbitrary exception counts as
   denial. Public APIs expose no handle; reflection is proof instrumentation,
   not a hostile same-process sandbox.
3. `off` and `auto-off` positives; `yes` independently refused for all ten roles.
4. Every missing role refuses at its exact read position; no default row repair.
5. SQL pre-fetch cap refusals for connection/ledger/queue; exact 131072-byte plain
   connection positive. No machine exhaustion or native memory benchmark.
6. Stable but malformed closed ledger, reference scope, settings/connection,
   snapshots/queue/registry/active data refuse; restored positive recovers.
7. Malformed/extra/stale intent, wrong purpose/nonces, both other nonce prefixes
   over the SAME typed envelope, changed site without renewed nonce, logged-out,
   subscriber and revoked session refuse before protected reads. Fresh nonce for
   wrong site reaches reads but cannot pass the settings/ledger binding.
8. Genuine session revocation inside the second nonce validation denies after
   twenty reads, not a cached capability or nonce-only pass.
9. Borrowed ambient read-only transaction, native handle swap and disconnect
   refuse without repair/reconnect/end of the borrowed transaction.
10. Uppercase binary option-name mismatch refuses despite collation equality.
11. Dropped unique index on the owned fixture only refuses before protected reads;
    restored index and final positive recover. No application index/schema changed.

Unchanged genuine core guard rerun **266 / 11 groups** and prior native observer
**122 / eight** both pass, with the same independent cleanup/checksum audit.
Previous paired-writer proof is historical and was not rerun/extended here.

## Canonical focused reviews and findings

Actual canonical prompts/runner govern this source-scoped checkpoint. Order:
Feature Light -> Bloat/Structure -> DS3 03/04/05/06/07/07A -> final Feature Security.
No UI/UX applies; full pre-release and final whole-plugin security are not replaced.

| Review | Inventory / disposition and execution |
|---|---|
| Feature Light | Native read-only/auth boundary and owned fixture harness only; no UI/cron/third-party runtime path. Escalated SQL/schema, resource, error and adversarial review. No outstanding source defect found. |
| Bloat/Structure | Ten changed PHP files at explicit base, zero oversized: TOTAL 198/60/62/50 source; supervisor 60, worker 77, new proof cases 91/helper 72, tests 35/37. CODE=1 helper artifact not used. Keep cohesive (Consolidate/no edits needed); shared profile helpers have existing live consumers and cannot be removed as dead code. PS1/YAML reviewed separately. |
| 03 Error Handling | Terminal No updates needed: invalid input/native prepare/fetch/metadata/auth/close exceptions refuse, private resources and prior handler restored; no diagnostics/payloads or public-success fiction. No UI/loading/network/user-feedback path in scope. |
| 04 WP Best Practices | Mechanical formatting/docblocks fixed; full WPCS 187 files passes. Existing functionWhitelist dependency-config deprecation disclosed. PHP 7.4, prefixes, ABSPATH, no registrations and fixed role identifiers preserved. Native session required to avoid wpdb reconnect/ambient transactions, not a new generic SQL abstraction. |
| 05 Database | Terminal source-scoped No updates needed after inventory below. No new installed schema/migration/option or runtime writer. Genuine fixture proof establishes read profile/refusal/cleanup, not transaction/commit correctness. Historical whole-plugin Database Review unchanged. |
| 06 Performance | Terminal No updates needed: two fixed ten-row indexed sweeps, at most six-MiB aggregate per frame plus bounded parser representations; native metadata/auth overhead, no production eager loading or retry. Ten-second monotonic acceptance budget and native connect/read timeouts do not guarantee blocking IO finishes within ten seconds. No load/memory-platform claim. |
| 07 Edge Cases | Missing/oversized/metadata/collation/index/profile, malformed/stale/domain, revoked/mutated core session and wrong-site/reference cases mapped to 07A. Two sweeps cannot establish atomicity/ABA resistance, and no such handling/claim is added. |
| 07A Adversarial | Baseline new 7 / 65 and related 41 / 468, no skips. Strong narrow pure/API assertions plus genuine native/private positive and independent state audit. Eight final assertion-killed/restored native controls below. No removed/weakened/skipped tests or artificial active grant. Full exact-head CI required for final Clean status. |
| Feature Security (final focused) | No outstanding source-scoped issue: exact request/nonce domain, fresh core auth on both sides, twenty bounded fixed raw reads, full tuple/closed-codec refusal, no public raw bytes/write handle, compiled SQL and finally cleanup. Not a whole-plugin security or active-permission approval. |

**H1 (proof-only harness defect, fixed):** the server read-only denial test first
expected a mysqli exception, but genuine core can select false-return reporting.
Corrected it to require `query === false` AND driver errno 1792, including the
owner's warning-to-refusal path. No source guard/reporting policy changed.

**H2 (temporary mutation orchestration defect, fixed):** a broad restoration hunk
matched the wrong return. Immediately detected by the required baseline rerun;
discarded contaminated controls, restored exact source, then reran using full
snapshot/context-pinned patches and mandatory green proof after EACH restoration.
No contaminated source committed; shared guard has zero final delta. All failed
attempts still cleaned fixtures and preserved all 77 application checksums.

Eight valid final controls, each assertion-failing and then genuine **245 / 11**
green after restoration: always-unavailable private result; removed pre-read guard
(two-guard ordering); removed renewed authentication (wrongly accepts post-nonce
revocation); changed nonce domain (wrong-domain nonce reaches twenty reads);
relaxed SQL cap; removed binary name check; bypassed complete manifest validation;
bypassed closed ledger validation. No environment/syntax failure counted as a kill.

### Source-scoped database inventory

Runtime custom tables/migrations/writes: **zero**. Ten proposed fixed options are
the unchanged predecessor manifest, observed only; no defaults/autoload mutation.
No post meta, new user meta or transient contract installed. Existing core user
role/session metadata is used only for genuine fixture authentication.

Fixture tables are fresh `wp_alynt_core_probe_<12 hex>_{options,users,usermeta}`
clones via CREATE TABLE LIKE, InnoDB/utf8mb4_unicode_520_ci. Metadata inspected:
options: unsigned bigint auto PK, option_name varchar(191) unique/full BTREE,
longtext option_value, varchar(20) autoload/index. Users: unsigned bigint auto PK;
login(60)/nicename(50)/email(100) indexed; pass(255), URL(100), display_name(250),
registered datetime, activation_key(255), status int. Usermeta: unsigned bigint
auto PK, indexed user_id bigint unsigned, nullable meta_key varchar(255)/index and
longtext meta_value. Relations are core user ID -> fixture usermeta only, no actual
application relationship/cascade. Entire owned tables are removed in finally.

New SELECT pattern: private fixed `scan_row`, option_name prepared, compiled cap
and validated table, no JOIN/ORDER, LIMIT 2/unique index. Reused metadata queries:
information_schema.tables bound table/database LIMIT 2, SHOW INDEX on pinned table,
native session facts via SELECT; no repair/DDL by the observer. Fixture helper
UPSERT binds role-derived names/values/autoload, DELETE previews exactly one owned
role, fixed queue-name case UPDATE binds values; index ALTER is confined to owned
table and restored. Supervisor owns CREATE/DROP after exact marker/target checks;
independent audit only inventory/SELECT/CHECKSUM. No new application index,
dbDelta/migration/uninstall/retention action is appropriate: runtime creates nothing,
and explicit proof cleanup is independent of plugin uninstall.

## Executed repository verification

Uploader new tests **7 / 65**, related **41 / 468**, no skips; commands:

```text
php vendor/bin/phpunit --do-not-cache-result --filter 'ScanNativeObserverBoundaryTest|ScanObservationContractTest'
php vendor/bin/phpunit --do-not-cache-result --filter 'ScanNativeObserverBoundaryTest|ScanObservationContractTest|ClosedScanEffectLedgerTest|ScanEffectManifestTest|ScanTargetCodecTest|NativeObserverBoundaryTest|NativePairedBoundary|NativeClosedBeginBoundary'
```

WPCS **187 files** passes, existing tooling deprecation only; new-source lint pass
after all controls restored. Build, runner build/syntax/verification (unchanged
runner hash), three Node dev tests, npm moderate audit, Composer strict validation/
audit pass. Build output unchanged. Full uploader suite runs on exact pushed-head
CI PHP 7.4/8.3, not inferred from local filtered tests; receipts appended on success.
Dashboard local full **299 / 1423**, two old unrelated Windows skips; structure/lint
138 files pass. No new dashboard source; its exact-head CI separately required.

## Proof limits and next gate

Only this genuine local profile was proved. No WordPress-version/platform matrix,
browser/HTTP route, actual stored enrollment/active grant, client/Drime effects,
native transaction/locking/CAS/commit durability, atomic snapshot/ABA resistance,
cache convergence, producer/queue participation or physical scan-stop proof.
Pure checks do not extend genuine evidence, nor do checksums prove every possible
side effect. Site plugins/themes did not load. No packaging/release/live deployment.

Implementation-plan progress: the fixed-row/closed-codec foundation now has genuine
guarded native observation and owned cleanup evidence. The next meaningful step is
a separately approved write/transaction boundary, not generic maintenance.

Recommended next gated slice: **Plugin Tester local-only V2.3 Closed Scan-Effect
Ledger Transaction Foundation and Owned Failure Proof**. Fixed all-ten lock order,
closed-only bounded ledger successor/reserve plan, exact fixture-ledger CAS/readback
and acknowledged commit/failure proof; writes confined to the owned ledger fixture.
Keep actual application state, snapshot/queue effects, production wiring, grants,
activation, cancellation and rollback execution untouched/disabled. Approve that
explicit write scope before implementation; full pre-release remains required for
a later coherent release candidate before any packaging/publication.
