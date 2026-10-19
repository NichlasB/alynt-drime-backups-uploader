# V2.3 Fixed Native Admission Outcome Capture Safety Foundation

2026-10-19. Approved R3 **repository-only, isolated API tests**. Uploader0.5.25 /
dashboard0.1.77 unchanged. No actual WordPress authentication, storage, native
execution, LocalWP/site access, production loading/wiring, grants, activation,
effects, rollback execution, package, release, tag or deployment.

## Ownership, baseline and source protection

Uploader owns five affected runtime PHP files, two test classes, four API support
files and this record. Dashboard owns the implementation-plan/design successor
records. Explicit excluded review bases: uploader
`dc449c732087aff56724b1561dab8cdd7582d8c6`, dashboard
`2a914478cc38e834f0bf1475db5a56d9195f7a00`, toolkit
`279034f79f24fade980ccf130466d632a3d56882`. This covers the whole new capture feature,
not a trailing documentation commit. Parent contracts remain the dashboard native
join/outcome design and uploader `V2_3_ADMISSION_OUTCOME_RELEASE_DATA_FOUNDATION.md`.

Pristine Option B source-only restore point completed **before editing**:
`alynt-drime-backups-uploader-20261019-231752.zip` and matching `.txt`, 10,786 files,
SHA256 `D2F29FCB7A8DD0396391ACEAE4D6E9DBB83D8E80B389B010CCB25C6688FB538B`,
under `C:/Users/Captain/Documents/AI Workflows/Toolkits/toolkit-snapshots/alynt-drime-backups-uploader/`.
No site/database backup, restore, stash or checkpoint branch performed.

## Dormant fixed source observations

The existing final native admission coordinator remains test-loaded only, with
the same inert factory/disposition and no public operation. No route, hook,
provider, handle import, frame factory or authority switch is added. The existing
auth/nonce/constructor flow and original ten-second budget are unchanged. This
does **not** join the retained lifecycle owner to native admission or establish
original-before-DML provenance; the standalone owner still constructs its proposal.

The new capture trait creates one bounded private primitive map before the fixed
writer factory. Event keys reuse the existing DATA constant; no second parser.
Observation positions are unique and capped at32. Unknown/duplicate/malformed or
late observations poison the capture without expanding its keys. Erasure consumes
the latch even if capture never started. There is no public snapshot getter or
classification port and no retained origin, receipt, nonce, SQL or row payload.

| Fixed source seam | Captured fact / conservative meaning |
|---|---|
| Owned writer factory returns or unwinds | Observe whether an exact fresh owned writer exists, including failed configuration. This is not a creation timestamp, successful connection claim or renewed root window. |
| Before CAS prepare | Reserve the sole write attempt; preparation/binding failure still consumes it. It is not a DML send marker. |
| After successful bind and final session/budget checks, before execute | Latch DML may-have-been-sent; no server-receipt claim. |
| Raw BEGIN/execute/COMMIT/rollback/close return | Preserve raw boolean independently, before later checks. Non-return/exception remains unknown. Raw false is also semantically unknown, never a definite server rejection. |
| Execute acknowledgement / affected rows | Separate exact one/zero/other row observation; no affected-row inference from acknowledgement alone. |
| All ten locked comparisons and ten readbacks, session and budget | Preserve existing comparisons; mark readback only after all ten pass. |
| BEGIN/COMMIT/rollback verified session | Ownership/idle marks require the original session check, not the raw acknowledgement. |
| Writer cleanup | Verify original owned identity before any rollback/session query; detach before close, record raw close, clear owned slots, seal once. |
| Outer root finally | Erase capture regardless of returned admission decision; never export a continuation. |

`raw_returns` has five fixed slots: `not_called`, unknown, or exact boolean. The
separate DATA `acks` use unknown for false/interrupted returns; only true produces
an acknowledgement position. A false BEGIN cannot be mislabeled not-written.
An incomplete observation is not a certificate: a future consumer must require
the unpoisoned complete DATA contract as well as actual same-root provenance.

Current auth/intent/physical/unreentered facts deliberately remain **unknown** and
`accepted_at` stays0. No final boolean is converted into those separate facts.
No diagnostic selection, read nonce, observer, retry or recovery branch is wired.
The DATA classifier is not invoked from native code. Even consistent captured
facts alone authorize nothing.

## Cleanup and ownership

The shared `trait-native-closed-begin-session.php` is unchanged (SHA256
`7B2D0DA3019AF5C6EAF38A4DC320851B7FA36E1C83CDD96105AA3526B4A95D17`), so its other
closed-begin/scan/observation consumers receive no new policy. The registration-
specific session trait reuses the fixed writer factory/session checks and legacy
pre-writer read-only cleanup. Its narrow cleanup specialization is intentional:
it needs raw acknowledgements, sealed observations and exact ownership bookkeeping,
not a copied authentication/construction wrapper or a generic callback framework.

A separate private handle pin owns only the exact writer the fixed factory created;
it is never placed in primitive DATA. If the reader slot changes, no query/close is
sent to a substituted or borrowed handle; capture is invalidated and only the
original owned writer is closed. Both slots and physical facts are cleared.
Cleanup is consumed before I/O, sealed afterward; nested cleanup poisons, while
later idempotent finally-cleanup issues no second command or close and cannot
promote unknown acknowledgement. A later poisoned phase cannot erase prior
COMMIT/readback/idle observations. Client close is not server quiescence.

Legal standalone positive behavior remains guarded as before. Failure behavior is
intentionally stricter at the approved seam: reserve before prepare, require bind
success and final session/budget before execute. Fixed SQL, manifest, protected-row
read ceiling and one-CAS ceiling are preserved. No auth/permission policy is relaxed.
The retained lifecycle owner remains unchanged, SHA256
`FCFF2FB71766AE80EF631447367D2C95CD066A80F2AB1177F0ECAFF1A774FD95`.

## Evidence and proof limits

New local PHPUnit **11 tests / 230 assertions**, no skips, plus **542 explicit
worker checks / 8 groups** (separate counts, not added to PHPUnit assertions).
Groups: positive commit20, ambiguous BEGIN/COMMIT87, write failures122, rollback82,
cleanup48, all-ten readback130, lifetime48, ownership5. Child processes use `php -n`:
no native mysqli extension, WordPress bootstrap, credentials, sockets or database.
Where PHP7.4 ships JSON as a shared module, the launcher probes and loads only the
already-installed JSON module. It never enables mysqli or installs dependencies.

The harness composes the actual production transaction/storage/capture/session
trait bodies. SQL API, physical-session/policy checks, deadline responses, row
projections and writer creation are **test doubles**, not genuine native/core proof.
The full authenticated `admit()` positive path is not exercised by those doubles.
Existing isolated DATA/guard tests remain a distinct regression layer.

Tests observe markers **inside** fake prepare/query/execute/close calls, including
throws. Positive commit/rollback prevents an always-unavailable capture from
passing. Every readback role is independently corrupted. Cases include bind and
prepare failures consuming attempt1 without a DML marker; final session/budget
loss; execute/statement-close failures; true COMMIT followed by failed idle; false/
interrupted BEGIN/COMMIT/rollback; late close failure preserving commit; explicit
never-created versus failed-created writer; second cleanup/reentry, foreign/auth
handle substitution, duplicate/bad/overflow facts, late acknowledgement, one-shot
erasure, inert public surface and unchanged production loading.

Twelve bounded controls each failed meaningful assertions (not syntax/bootstrap
failures), and the exact source SHA256 was restored after each: reservation before
prepare; bind success; DML marker ordering; raw COMMIT acknowledgement; all-ten
readback comparisons; positive close acknowledgement; ownership before cleanup I/O;
sealed late acknowledgement; outer erase; capture reopen refusal; duplicate event
refusal; single cleanup consumption. Parent worker failures preserve the explicit
`Outcome API assertion` reason. No test skip, weakened expectation or artificial
native success path was introduced.

## Canonical source-scoped review record

Feature Light: eleven PHP files, this record and dashboard plan/design records,
plus direct unchanged shared factory/session, DATA and existing proof callers.
Risk=Other/dormant native seams; no user-facing UI/AJAX/REST/cron/external behavior.
Three routine findings fixed: TEST-1 lazy/entry-guarded worker discovery so PHPUnit
cannot install fake mysqli or exit its parent; CAP-1 seal after cleanup to prevent
late promotion; CAP-2 pin ownership and check before any cleanup query. Permanent
tests and assertion-killed controls protect the capture failures. No manual design
decision outstanding. No UI/UX workflow applies.

Feature Bloat/Structure: explicit dc449c7 boundary, eleven changed PHP files,
zero oversized. Initial/final TOTAL: coordinator255/255, storage97/97,
transaction100/100, capture147/147, session111/111, API test16/29, boundary43/43,
cases89/91, doubles34/34, harness49/49, worker49/49. CODE=1 helper anomaly is not a
size/reduction claim. All Consolidate/retain cohesive responsibilities; no removals,
forced splits or oversized02 deferral. Extra API launcher checks preserve PHP7.4
isolation portability. Phase2 needs no bloat cleanup; same boundary remeasured.
Direct/string/require/hook/filter/shortcode/AJAX/REST/cron references checked.

Final Feature Security after fixes/controls/validation: private fixed source calls,
unchanged prepared SQL predicates and authorization gates, exact owned-handle
cleanup before any callback, raw return ambiguity, no retry/promotion/handle export,
bounded primitive retention and unconditional erase inspected. No outstanding
focused issue or uncovered confirmed-security-defect regression handoff. Not a
whole-plugin Security Audit, DS3 completion, Database Review, physical transaction,
genuine auth, wire-ack-loss/crash, server-quiescence or durable-recovery proof.

Local related regressions **407/12583**, no skips; WPCS230, lint:runner, build,
verify:runner and dev-tools3/3 pass; generated outputs unchanged. Dashboard local
299/1423 with two existing Sodium skips, WPCS138 and structure pass. Full Windows
uploader suite not rerun; exact-head Linux CI supplies the full repository suite.
Actual pushed-head runs/counts are recorded in the toolkit scoped entry after
publication. Historical whole-plugin readiness rows are not refreshed. Full
pre-release remains required for a coherent RC before packaging/publishing.

## Next boundary

Implementation-plan progress: fixed source observation/ownership/erase seams now
have falsifiable isolated API evidence. Actual native/auth/session/storage behavior
and original-root admission provenance are **not** established by these doubles.

**Recommended next gated slice: Plugin Tester local-only V2.3 Fixed Native
Admission Outcome Capture Owned Failure and Cleanup Proof**, using the exact
confirmed local target and owned disposable users/storage fixtures plus cleanup,
preserving existing application state. It must exercise genuine fixed source
paths rather than import synthetic event labels; do not upgrade old manually
constructed transaction fixtures into capture provenance. Production wiring,
grants, activation, diagnostics and schedule rollback remain disabled. Actual
authentication/native storage execution requires fresh target/fixture approval.
