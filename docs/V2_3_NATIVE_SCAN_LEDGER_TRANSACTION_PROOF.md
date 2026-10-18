# V2.3 Closed Scan-Effect Ledger Transaction Foundation and Owned Failure Proof

2026-10-18; approved R3 **Plugin Tester local-only**, continuing the explicitly
confirmed three-table disposable fixture target/scope. Native transaction writes
are confined to its ledger row. Existing application state and production wiring
remain untouched. Source 0.5.25 / dashboard 0.1.77; no release candidate.

## Scope, plan and explicit review boundary

Excluded uploader base `c86cea98e5a0e2f9f5bbc1396b4e08b7f7591a2f`, dashboard
`fdc4398414f7cdb06c9e5691901caf5f71640849`, toolkit
`e696c61e1bb3b7a8cfbc9f5c3ca6041c0416f6eb`. Review all new source/tests/harness
and directly reused native traits against these bases, not a receipt-only tail.
Predecessor: [native observation proof](V2_3_NATIVE_SCAN_OBSERVATION_PROOF.md).
Companion dashboard owns `docs/V2_3_NATIVE_SCAN_EFFECT_STORAGE_PARTICIPANT_DESIGN.md`.

Bounded plan executed: verify restore/target -> separate dormant owner and pure
intent/successor -> genuine fixture failure proof -> canonical focused reviews and
controls -> validation/commit/push/exact-head CI. Restore workflow Option A/check
confirmed clean/synced master at the excluded base and the existing external
September 20 archive. No new archive, backup, restore or checkpoint branch.

**Conservative successor decision:** this is a closed-ledger **checkpoint**, not
new scan registration. Increment only `ledger_revision`, preserve all operation
records/slot identities/receipts, generation and `closed=true`. Refuse any pending
operation, exhausted history, invalid scope/codec, overflow, or a successor without
the existing 16384-byte terminal plus 6144-byte incoming-record headroom. No UUID
construction, authenticated event, operation admission or receipt is invented to
exercise a positive write. Actual registration/reservation remains the next gate.

## Implementation and ownership

- `class-scan-ledger-checkpoint-request.php`: exact nine-field bounded flat form,
  reusing the unchanged observation parser's string/UUID/decimal/deadline grammar
  only after exact checkpoint purpose/contract/confirmation checks. Added lowercase
  64-hex `context_digest`; complete raw/encoded request remains bounded at 4096
  bytes. Separate `alynt_drime_backups_scan_ledger_checkpoint_v1:` action hashes
  every ordered typed field. No issuance or posted target/provider port.
- `class-scan-ledger-checkpoint-plan.php`: pure complete-frame fingerprint binds
  blog/prefix/database and every ID/name/autoload/value/octet field. Exact original
  fingerprint and predecessor closed-codec checks precede the revision-only
  successor. JSON encoding errors refuse rather than fingerprint `false`.
  Pure fingerprints/candidate bytes are private data, not provenance or authority.
- `class-scan-ledger-checkpoint.php`: no-IO/no-argument factory; one-shot private
  `checkpoint`, consumed even on refusal. No public writer/inspect/continuation or
  handle; public disposition is always candidate-only with all support flags false.
  Genuine core cookie/session/capability/nonce guards run before protected reads,
  after preparation/before BEGIN and after acknowledged close/release. Pin original
  actor/cookie/frame/borrowed authentication session; no repair/adoption/reconnect.
- Existing narrow loopback MariaDB 10.6/native-cache/single-site/mysqlnd/utf8mb4/
  64-bit profile and owned-session policy/finish traits are reused unchanged.
  Reader creation is followed by verified owned READ COMMITTED/read-write policy,
  lock timeouts 2s/3s and durability policy. Metadata checks require exact supported
  InnoDB core options schema, full unique BTREE, visible no-trigger/no-FK profile.
  No privileges are granted to satisfy this profile.
- `trait-scan-ledger-checkpoint-storage.php`: separate fixed-role selector with
  SQL pre-fetch caps, prepared fixed option names, validated pinned identifier,
  LIMIT 2, exact binary name and fixed 30-read fence. Original selectors/owners
  remain unchanged. One private ledger-only UPDATE uses exact ID, binary name,
  binary autoload and old-value predicates; acknowledgement AND affected_rows=1.
- `trait-scan-ledger-checkpoint-transaction.php`: fixed ten-row prefetch, then
  BEGIN-sent latch/acknowledged ownership, all ten locks in manifest order, schema
  revalidation after safety acquires table MDL, exact full-tuple comparisons, one
  ledger CAS, full ten-row locked readback, COMMIT-sent latch/acknowledged idle
  policy. Under locks: native SQL, primitive comparisons and fixed clocks only;
  no WordPress auth/filter/cache/producer/parser callbacks. Prepare before locks.
  Close owned session before renewed core authorization/publication.

There is no automatic retry or cache publication. Definite precommit failures use
acknowledged owned rollback; uncertain BEGIN/COMMIT/rollback poisons and closes the
owner. An idle session never substitutes for an acknowledgement. Post-commit auth
refusal does not erase acknowledged historical commit. No resolver, cancellation,
effect receipt, snapshot/queue persistence, lifecycle hook, route or cron is added.
Shared traits contain private old-owner helpers with existing consumers; they are
not removed/widened or exposed as new public methods. Source reference search
finds this owner loaded only by explicit tests/proof scripts, never bootstrap.

## Genuine owned proof and reconciliation

Registry/current Local metadata agree on `plugin-tester`, local-only,
`http://plugin-tester.local`, `C:/Users/Captain/Local Sites/plugin-tester/app/public`,
MariaDB 10004/web 10005. Novamira inventory includes its exact profile; doctor
reports expired OAuth token and unavailable runtime checks. No connection/secret
changed. Approved direct LocalWP fallback: bundled mysql initial SELECT confirms
origin, MariaDB 10.6.23, idle autocommit and 77 application tables before PHP/core.

Run `scripts/tests/fixed-core-proof-audit.ps1 -Proof native-scan-ledger`, supplying
MYSQL_PWD only from the confirmed local source. Existing supervisor creates exactly
three marker-owned `wp_alynt_core_probe_<12hex>_{options,users,usermeta}` tables,
synthetic roles/users/sessions and a fresh-salt isolated full **WordPress 7.1.2 /
PHP 8.5.1 / MariaDB 10.6.23** process. No actual wp-config, site plugins/themes,
HTTP, cron or production plugin wiring. Real core auth function origins checked.

Final proof **740 checks / 13 groups**:

1. Bad nonce before any protected reads; genuine session revocation before locks
   and after historical commit, with actual private decisions (not stubbed auth).
2. Genuine positive 30-read/one-write acknowledged commit, exact ledger successor
   and all nine other tuples unchanged; independent NOWAIT probes from every core
   auth callback verify no WordPress callback runs under the native locks. Borrowed
   authentication session unchanged. Stale original full-context replay refuses.
3. Malformed/extra/wrong purpose/stale intent, wrong nonce prefixes over the SAME
   typed envelope, subscriber, revoked session and ambient auth transaction refuse.
4. Each of ten metadata changes in the second pre-lock nonce callback refuses at
   its exact ordered locked read, before UPDATE; definite owned rollback.
5. Pre-lock and post-commit revocation distinguish no-write refusal from historical
   committed state and withheld positive decision.
6. Independent native NOWAIT probes cannot acquire ANY of the ten owned locks;
   acknowledged rollback leaves original rows.
7. Real contention on the LAST dependency causes bounded timeout, no UPDATE/retry.
8. Wrong ledger ID, binary autoload or old bytes causes actual zero-row CAS refusal.
9. Real UPDATE followed by exhausted private readback fence rolls back with no
   durable delta; readback failure is not a commit success.
10. Protocol acknowledgement-omission simulation on physically committed AND
    uncommitted SQL sessions leaves poisoned/no-replay owners. **Not a real dropped
    COMMIT response on the wire**, server crash, power-loss or crash-recovery test.
11. Real KILL of the exclusively owned connection after UPDATE, never borrowed
    handles, produces poison/no reconnect and unchanged durable fixture rows.
12. Supported schema positive, wrong schema/identifier and SQL pre-fetch ledger/
    queue caps refuse; no schema/index mutation by the new proof.
13. Malformed ledger refuses before BEGIN; final genuine positive and reconciliation.

Core guard rerun **266 / 11** and unchanged scan observer **245 / 11** both pass.
Every admitted proof/control run independently checks all **77 application-table
inventories/checksums unchanged**, actual core-table digest equality, and absence
of every owned fixture after exact row-count preview and cleanup. Disposable fixture
data is discarded, not retained/recoverable. Existing application state unchanged.
One separate pre-admission checksum response was incomplete: audit refused before
fixture creation, one identical bounded rerun passed; no gate/check was relaxed.
Initial mysql short-host argument was split by PowerShell; explicit long arguments
resolved it before any fixture work. No raw credentials/checksums/payloads emitted.

## Canonical source-scoped review checkpoint

Actual canonical runner/prompts used, sequence: Feature Light -> Bloat/Structure ->
DS3 03/04/05/06/07/07A -> final Feature Security after fixes/controls/validation.
Routine fixes were already approved. UI/UX N/A (backend-only); full pre-release,
whole-plugin 13 Security Audit and historical release readiness are NOT refreshed.

Feature Light: 13 source/test/harness files, native DB/auth/concurrency and owned
fixture file/process operations; no UI/AJAX/REST/cron/third-party service path.
Two fix categories: F1 error-handling regression, mechanical WPCS; zero unresolved
manual decisions. Escalated database, resource, edge and adversarial review.

| Workflow | Source-scoped inventory / outcome |
|---|---|
| Bloat/Structure | Explicit pre-feature base above; helper reports 12 changed PHP, zero oversized. Five source TOTAL 180/58/60/81/57 after the final bound fix; two existing harness 60/78; three proof helpers and two tests below 100 each. CODE=1 known helper artifact is not used. All files Consolidate/keep cohesive; zero split/remove/defer, no Phase 2 cleanup needed. New locking selector intentionally differs from unchanged readonly/two-row selectors; do not widen their ceilings/semantics. Reference/registration search confirms test-only consumers. PS1 reviewed separately. |
| 03 Error Handling | F1 fixed below; terminal no further updates needed. Native false/throw/warning/close outcomes refuse; finally restores handler and owned resources; unknown outcomes never become success/retry. No UI/loading/feedback surface applies. |
| 04 WP Best Practices | Eight property docblocks plus two multi-key-array formatting violations fixed mechanically; no deprecated runtime API, new registrations, options defaults or public paths. Final full WPCS required below; existing tooling functionWhitelist deprecation disclosed. Native prepared SQL preserves ownership/raw-byte/ambient-session semantics that option APIs cannot supply. PHP 7.4 contract unchanged. |
| 05 Database | Source-scoped inventory below complete; no schema/migration/index/cleanup change justified. New native fixture transaction evidence is distinct from historical/in-memory models and whole-plugin Database Review. |
| 06 Performance | Terminal no updates needed. Three fixed ten-row indexed passes (<=30 protected reads), one UPDATE, fixed metadata/session queries, original 10s monotonic acceptance and UTC deadline; no retry. Each frame <=6 MiB, individual role caps before fetch, bounded parsers. JSON fingerprint encoding/escaped copies have bounded additional memory overhead, not a measured platform memory guarantee. Native blocking IO can overrun acceptance; not hard cancellation. No authority caching/query batching which weakens locks. |
| 07 Edge Cases | Twelve categories considered. High auth/replay/lock/CAS/unknown-ack/resource/corruption scenarios map to pure or genuine native tests above; unsupported profiles refuse, no defaults/repair. UI/browser/external services/deletion cascades N/A. Registration/cancellation/effects/cache convergence remain a named future gate, not passing tests. |
| 07A Adversarial | Final new pure/API 7 / 60; related 44 / 500, no skips. Strong exact successor/history/context/intent/refusal assertions; API test is isolated, not genuine core. Native private positive and physical failure assertions above. Ten assertion-killed/restored controls below, permanent F1/F2 RED/GREEN. Final Clean only upon full exact-head CI, recorded after successful execution. No assertions weakened, tests deleted/skipped, grants fabricated or generic exception counted as native lock denial. |
| Feature Security (final focused) | F2 exact encoded bound fixed below; affected 07A/WordPress/native checks rerun, then final focused review repeated with no outstanding source-scoped issue. Strict typed purpose/context/nonce separation, genuine fresh auth, original scope/budget, exact all-ten dependencies, bounded prepared fixed SQL, ledger-only CAS/readback, ownership/poison/redaction and no public write handle. No hostile same-process sandbox or active-authority/whole-plugin security approval claimed. |

**F1 — encoding failure, fixed:** initial pure `context()` hashed a failed
`json_encode` result for an unencodable frame. Permanent invalid-UTF8 regression
failed on that fingerprint, then passed after explicit is_string refusal. Full
prepare already rejected unsupported codecs; this fix makes the helper itself
fail closed, not a new authenticated success path. Related suite stays green.

**F2 — complete encoded-request bound, fixed:** final focused security review
found separate envelope-plus-nonce length accounting omitted the nonce key/JSON
separator overhead. A permanent synthetic UTF-8 nonce test accepted 4097 bytes
before the fix (RED). Count the complete canonical envelope INCLUDING nonce now;
the same test rejects 4097 and accepts exactly 4096 (GREEN). Added one test/five
assertions; related suite **44 / 500** and genuine **740 / 13** rerun. Original
raw/per-field bounds, authorization and nonce semantics remain strict. WPCS
alignment warnings introduced by this small fix were mechanically corrected.

07A strengthening moved bad-nonce and renewed-auth negatives before the positive
callback-count check, so bypasses are caught by protected-read/write decisions,
not merely by an event count. Initial call-count-first controls were discarded as
quality evidence; new order was rerun green before counting behavioral controls.

Eight native controls: always-false actual private result; removed pre-read guard;
removed pre-lock guard; removed exact locked comparison; removed FOR UPDATE;
removed old-byte CAS predicate; removed post-commit guard; conflated observation
nonce domain. Each assertion-failed and had an individually green restored proof
(710 checks before final harness strengthening, 740 afterward). Two pure controls:
removed full-context comparison and removed pending/history/reserve gate, each
assertion-failed then restored **6 / 55** green. Immediately after controls, all
five new source files matched pre-control snapshots; the subsequent F2 bound fix
is separately covered by RED/GREEN and final native rerun. No shared source delta
or syntax/environment failure counted as mutation evidence.

### Source-scoped database inventory

Installed custom tables/migrations/meta/transients: zero. Ten existing/proposed
manifest options are listed in the companion design; nine are dependencies only,
ledger is closed canonical JSON with no creation/default in this owner. Exact
non-autoload markers `no`, `off`, `auto-off`; missing/unsupported rows refuse.
New query patterns: `checkpoint_row` prepared option_name/compiled cap LIMIT 2,
optional compiled FOR UPDATE/full unique BTREE; `write_ledger` prepared raw/ID/
fixed name/autoload/old bytes LIMIT 1. No JOIN/ORDER/application-wide scan; fixed
transaction/session/metadata queries reuse the unchanged owned native profile.
WHERE predicates include full ID/binary metadata/value; ordinary frequency zero
because owner is test-loaded only. No schema version/uninstall change needed.

Three disposable tables use supervisor CREATE TABLE LIKE and exact-target DROP
after preview. Schema/charset/index inventory is the predecessor record's inspected
InnoDB/utf8mb4 core options/users/usermeta schema: options unsigned bigint auto PK,
unique option_name varchar(191), longtext value, autoload varchar(20)/index; users
unsigned bigint auto PK plus core login/nicename/email indexes; usermeta unsigned
bigint auto PK, indexed user_id/meta_key, longtext value. User ID -> owned usermeta
only, no actual application relation/cascade. Genuine core role/session metadata
is fixture-only. Helper frame SELECT binds compiled roles; seed UPSERT binds fixed
manifest values; NOWAIT binds names. KILL uses verified exclusively owned integer
thread ID, never caller input/borrowed session. No grant, production schema/DDL,
cleanup retention, row provisioning, permission or migration change.

## Validation, publication and next gate

Executed final local new **7 / 60**, related **44 / 500**, no skips, with
`php vendor/bin/phpunit --do-not-cache-result --filter 'ScanLedgerCheckpoint|ScanObservationContractTest|ScanNativeObserverBoundaryTest|ClosedScanEffectLedgerTest|ScanEffectManifestTest|ScanTargetCodecTest|NativeClosedBeginBoundary|NativePairedBoundary'`.
Native proof commands above are separate from the isolated PHP suite. Exact-head
full CI PHP 7.4/8.3 is required before 07A Clean/publication completion; it does not
execute LocalWP SQL. Final QA and publication receipts follow after execution.

Executed final local QA: full WPCS **192 files**, existing dependency-config
functionWhitelist deprecation banner only; targeted source/helper syntax passes.
`npm run build`, `verify:runner`, `lint:runner`, `test:dev-tools` (**3** passing),
`npm audit --audit-level=moderate`, Composer strict validate/audit all pass.
Generated assets/runner and existing shared guard/storage/session files unchanged;
runner hash `7356eacd57ed67f4265db8198f8ca5809e07f4bd985b1d6d00a88cb915112117`.
Dashboard docs-only local full suite **299 / 1423**, two existing unrelated Windows
skips, lint **138 files**, structure/build/Composer strict/audit/npm moderate audit
pass. Full uploader suite is executed in exact-head CI, not inferred from these
filtered local tests; dashboard CI must independently pass without those skips.

Proof limits: one local native profile, no HTTP/browser route, actual stored grant,
real registration/UUID provenance, physical lost-wire acknowledgement/crash proof,
production participant/cache integration, effect/cancellation race or scan-stop.
Checkpoint revision is not a durable operation/effect receipt or permission. Pure
scope reference consistency is not full safety/enrollment/runtime eligibility.
No snapshot/queue/application writes, production wiring, grant/activation/cancel/
rollback, package/release/tag/deploy. Full pre-release reserved for a coherent RC.

Implementation-plan progress: the all-ten native transaction/ledger-only CAS and
acknowledgement boundary now has genuine owned proof. Meaningful remaining work
crosses the registration/intent/provenance protocol gate, not generic maintenance.

Recommended next gated slice: **local-only V2.3 Closed Registration and Reserved
Intent Native Integration Design**. Define trusted once-reserved identities,
nonce/provenance, closed registration/reservation transitions and unknown-outcome
resolution before extending this revision-only owner. Design-only: no storage or
site operations, effect execution, production wiring, grants, activation,
cancellation or rollback. Obtain explicit approval for that new trust contract.

## Exact-head publication receipts

Implementation `1a117a4673eb30268a6539a9f45a5422435e12b8` passes
[Quality 39157696961](https://github.com/NichlasB/alynt-drime-backups-uploader/actions/runs/39157696961):
PHP 7.4 and 8.3 each **1440 tests / 12865 assertions**, no skipped, risky,
incomplete or failing tests. Full coding standards, proof-helper syntax, Node/dev
tests, build, generated-asset checks and audit pass. Source-scoped **07A final
status: Clean**. F2 affected checks and final focused Feature Security rerun after
the fix/validation leave no unresolved source-scoped finding. CI does not execute
the separately completed LocalWP native proof and does not expand its limits.

Dashboard `262315397f98926c059e1fb591a6b574203f9ad7` passes
[CI 39157704975](https://github.com/NichlasB/alynt-drime-backups-dashboard/actions/runs/39157704975):
both PHP versions **299 / 1430**, no skips, plus standards/structure/build/manifest/
audits. Local Windows skips remain disclosed. GitHub's Ubuntu-label migration
annotation is informational, not a test warning.

Toolkit receives only a source-scoped checklist addendum; historical whole-plugin
version/readiness rows remain unchanged. Its zero configured workflows/check runs
means CI N/A, not green. This uploader receipt-only follow-up is separately pushed
and its own exact-head CI checked before handoff; earlier-head CI is not substituted.
No additional source/test/native/site operations follow this receipt update.
