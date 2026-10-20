# V2.3 Single-Root Retained-Origin Native Admission Join Safety Foundation

2026-10-20. Approved R3 **repository-only, isolated API tests only**. No actual
WordPress authentication, LocalWP/site access, database/storage writes, native
transactions, fixtures/users, production loading/wiring, grants, observation,
activation, effects or schedule rollback. No package, release, tag or deployment.
Uploader0.5.25/dashboard0.1.77 unchanged.

## Ownership and coherent boundary

Uploader owns the coordinator, two shared private traits, three new test classes,
two API fixture/harness files, one directly affected proof source-location check
and this record. Dashboard owns matching implementation-plan/design records.
Explicit excluded bases cover this whole coherent join, not a trailing docs commit:
uploader `4e597a6072593dd2791839a5b5f81692af27d4c2`, dashboard
`9d967cca5f83925f074e6957f0042d315e8ee11a`, toolkit
`5ad1b8053d15ffd31a1c9ea720c5bfb9858cc5cb`. Parent contract is dashboard
`V2_3_NATIVE_ADMISSION_JOIN_OUTCOME_DESIGN.md`; previous owned capture proof remains
historical evidence, not execution of this new join.

Pristine Option B source-only restore point completed before the first edit:
`alynt-drime-backups-uploader-20261020-102148.zip` plus matching `.txt`,10833 files,
SHA256 `3896D7EE59B3E5D13C11C1407ED7AED111885DE31A99B50311644141BDCFF2F9`,
under `C:/Users/Captain/Documents/AI Workflows/Toolkits/toolkit-snapshots/alynt-drime-backups-uploader/`.
Not a site/database backup; no stash, checkpoint branch or source rollback.

## Fixed private root, not nested consuming wrappers

The existing final test-loaded coordinator has two thin **private** entries:
`admit()` selects standalone behavior; `admit_retained()` selects the joined path.
Both delegate directly to one private `admission_root()`. Its internal boolean is
fixed by those entries, never a public mode/generation/provider input. No public
operation, injectable frame/proposal/origin/actor/clock/handle, new hook or endpoint.
Both entries share one attempt latch; neither invokes another consuming owner.
Public factory/disposition/privacy remain unchanged and non-authorizing.

The existing root flow and real ten-second budget are shared, not copied into a
second wrapper. Standalone retains its nine existing nonce guards/no origin.
Joined root adds a separate full-command verification before retention, then
renews independent issuance/full-command guards before BEGIN and after release.
Initial issuance precedes protected capture. Existing role-only autoload/query
fences span callbacks and restore only their own exact registrations.

| Joined source stage | Fixed invariant |
|---|---|
| Before capture | Original request/actor/cookie/auth session and real monotonic start pinned; no renewed inner window. |
| Once-construction | One existing constructor, one command nonce and ten fresh IDs. No nested lifecycle preparation or second native constructor. |
| Before owned writer factory | Full command authorization and prepared candidate precede minimal origin retention; complete historical collision set plus all ten original IDs retained. |
| Before BEGIN | Exact origin/IDs/clock and full writer candidate pin unchanged after all callback gates; no rebasing/reminting. |
| After native release | Exact continuity checked before and after final auth callbacks. Historical native capture stays separate; false may follow a historical commit. |
| Every return | Native cleanup first, then capture/origin/IDs/clock/full private writer slots erased, owned filters/error handler restored, root permanently consumed. |

Minimal retained properties contain only bounded primitive origin, complete IDs
and original clock. Existing original/packet/UUID/scope/context validators are
reused unchanged: origin4096/depth6/nodes256, aggregate16384/depth8/nodes512 and
complete IDs12..128. Eligibility is rechecked outside locks solely to obtain the
complete historical collision set, not through a new parser or constructor.
No parser/auth/WordPress callback is introduced inside native row locks.

The separate **root-local** exact pin additionally compares full before/proposal/
context/after/preparation time. It is not the minimal origin, a public packet or
an observer handoff; existing full-frame/ledger limits still apply. Copies are
PHP value/COW copies of already validated candidates, not a new persisted graph.
There is no returned continuation, durable origin or handle retained past return.

Joined reentry through either private entry poisons the active outer join and
clears origin; restoring DATA cannot clear interruption. The original real budget
rejects before protected capture when reentry/expiry occurs there. Ten-second
writer acceptance is unchanged, not hard I/O cancellation or a renewed20-second
observer window. Unknown/late outcomes never remint, retry or authorize reads.

No native outcome classifier, current-acceptance marks, read nonce, diagnostic
selection, observer, recovery or effect path is added. Previously captured raw
send/ack/idle/close facts remain unchanged and non-authorizing. No current auth
claim is inferred from the final boolean. Actual physical source execution is
separately gated even though the dormant fixed call path now exists.

## Falsifiable isolated evidence and routine fixes

New **50 tests /3033 assertions**, no skips. Actual shared root, constructor,
candidate, guard, retention and budget bodies execute against API doubles.
WordPress API functions, frame/physical profile, capture, owned writer/session,
native transaction forwarding and cleanup are explicitly **doubled**. In
particular, the forwarding double's write counter/committed phase is not DML,
COMMIT acknowledgement, transaction atomicity or genuine WordPress authentication.

Positive path proves one construction/all ten IDs, one nonce creation, joined ten
versus standalone nine guard order, retention after command authorization/before
factory/forwarding, same original start and exact origin through release, no
protected API access, exact filter cleanup, private erasure and no replay.
All ten nonce guards independently refuse; all ten lost IDs, changed origin/IDs/
clock/full writer pin, pre-/post-release faults, original expiry, cross-entry
reentry, late session loss, uncertainty and close refusal have permanent coverage.
Actual final-class malformed entry refuses without I/O. A tokenized thin-entry
contract ties each fixed mode to the positively exercised source root; replacing
the real entry with unconditional false cannot hide behind harness positives.
That contract is static wiring evidence, not a full native positive-entry proof.

Seven temporary controls were assertion-killed, each exact source SHA256 restored:
prewrite continuity omitted (changed pin reaches forwarding); post-guard continuity
omitted (altered origin accepted); full command-before-retention omitted (guard
sequence changes); outer origin erase omitted (private DATA survives); interruption
latch suppressed (reentry accepted); elapsed budget removed (twenty protected
projections occur after expiry); actual retained entry disconnected (fixed-source
wiring contract fails). No syntax/bootstrap-only kill, skips, weaker expectations
or production success bypass. Final restored-source suites rerun successfully.

Routine fixes: WPCS parameter docs/alignment/brace issues corrected; assignment in
condition made explicit. Review caught a copied test budget that could not falsify
the actual budget: source budget now belongs to the shared root and the harness
uses it directly. Added actual-entry delegation protection to distinguish harness
positives from an unexercised/disconnected facade. No confirmed security defect
or manual design decision remains. Prior native proof's closure-file assertion
now points to the shared root; it was syntax-checked, **not physically rerun**.

## Canonical source-scoped review and validation

Feature Light: nine PHP files plus owning records/direct unchanged native session,
capture, transaction/storage, DATA and core guard dependencies. Risk Other/dormant
auth/native seams; no UI/AJAX/REST/cron/Drime/user-facing surface. The concrete test
quality and routine source-style findings above are fixed; no UI/UX workflow.

Feature Bloat/Structure phases1/2: explicit4e597a6 boundary,9 PHP/zero oversized.
Before/final TOTAL: coordinator161/162, join124/124, root135/154, proof caller83/83,
boundary17/45, denial75/79, positive40/40, fixture31/31, harness59/58.
All Consolidate/retain cohesive responsibilities; root extraction is required
reuse, not cosmetic splitting. No dead removal, forced split or02 deferral.
CODE=1 helper anomaly is not a reduction claim. Direct/string/require/hooks/
filters/shortcodes/AJAX/REST/cron checked; final measurement uses the same baseline.

Final Feature Security after restored controls and validation: exact private mode
selection, same root/complete IDs and clocks, command-before-retention, original
auth/intent gates/fences, no public origin/handle import, no outcome promotion or
fallback, bounded primitive data, consumed reentry and unconditional erase reviewed.
No outstanding focused issue or confirmed-security-defect regression handoff.
Not whole-plugin Security Audit, formal DS3/Database Review or physical proof.

Local related **457/15616**, no skips; uploader WPCS232, runner syntax/build/verify
and dev-tools3/3 pass, generated outputs unchanged. Dashboard local299/1423 with
two existing Sodium skips, WPCS138 and structure pass. Full Windows uploader suite
not rerun; actual full exact-head Linux CI/counts recorded in the toolkit scoped
entry after publication. Historical whole-plugin readiness rows not refreshed.
Full pre-release remains required for a coherent RC before packaging/publishing.

Shared native session, retained lifecycle owner, transaction/storage and native
capture/session bodies remain byte-identical. Existing physical admission/capture
counts are historical, not new genuine auth/storage/source-join verification.
No actual wire-loss/crash, quiescence, durable closure or safe retry is proved.

Implementation-plan progress: one fixed source root now retains original origin/
all ten IDs before native forwarding, with falsifiable isolated orchestration and
unchanged production wiring. The next meaningful lane is a physical owned proof,
not more maintenance or an observer/activation expansion.
**Recommended next gated slice: Plugin Tester local-only V2.3 Single-Root
Retained-Origin Native Admission Join Owned Authentication, Failure and Cleanup
Proof**, exact confirmed target and owned disposable users/storage/testing/cleanup.
Require fresh approval, preserve existing state; keep production wiring, grants,
observation, activation and schedule rollback disabled.
