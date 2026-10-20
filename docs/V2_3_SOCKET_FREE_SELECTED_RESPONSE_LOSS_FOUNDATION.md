# V2.3 Socket-Free Selected Response-Loss Eligibility and Accounting Safety Foundation

2026-10-20. Approved repository-only synthetic bytes/messages/virtual clocks.
Uploader0.5.25/dashboard0.1.77 unchanged. No WordPress bootstrap/authentication,
SQL/storage operation, native process/relay/socket/IPC experiment, response loss,
production wiring, grants, activation, rollback, package/release/tag or deployment.
Normal repository validation/build tools are not a native witness experiment.

## Ownership and explicit review boundary

Uploader owns three new `scripts/tests/selected-loss-*.mjs` pure helpers, four
`tests/node/selected-loss-*.test.mjs` tests and the independent invented fixture
`tests/node/support/selected-loss-fixtures.mjs`. Dashboard owns the governing
`docs/V2_3_SELECTED_COMMIT_RESPONSE_LOSS_WITNESS_DESIGN.md`, successor pointer and
implementation-plan entry. No production caller or existing passive/v1 helper
changed. Toolkit owns only source-scoped checklist evidence.

Excluded review bases: uploader`665ec48ba88095307e8256a549823f04c4c48e44`
(immediately before this foundation), dashboard
`776a4f19bad779816bb95450a8ac44da06d8f08f` (before its governing design).
Together these cover the coherent design/foundation, not a verification-doc tail.
Only this surface and directly reused profile/framer/fixture contracts reviewed;
not a new review of all prior native/WordPress foundations. Dashboard starts
`6b569f2`, toolkit`da3a255`; all three clean/synced before edits.

Source-only restore points under toolkit-snapshots (not site/database backups):

- Uploader`alynt-drime-backups-uploader-20261020-200221.zip`,11119 files,
  SHA256`33DB7A71B6D72A14677484E9513D17DEFD8FF2A93C109B3C619B6048ECA7A04E`.
- Dashboard`alynt-drime-backups-dashboard-20261020-201235.zip`,13180 files,
  SHA256`C0F40951E55A6E5A3FECCA876056C971C58DEDF7A681D3636562346A95E8F8CF`.

Master/toolkit/repository/Site Operations guidance read. Repository-only scope
does not operate on Plugin Tester; no target/service/Novamira/credential check.

## Implemented pure contracts

`selected-loss-witness.mjs` pins distinct unregistered root/auth/capture/writer
identities and copied configuration. Three strict native-shaped profiles, unique
thread labels, capture closed/auth+writer live, capture/writer negotiation equal,
all frame boundaries and egress credits complete before binding. Literal sequence0
COM_QUERY `COMMIT AND NO CHAIN NO RELEASE`, not a substring, row or prepared command.
Original monotonic virtual root10s, >=6s remaining at binding/request/full response;
no reset. The first complete sequence1 canonical seven-byte OK is the only candidate.

Per-frame private credits bind role/direction/exact owned bytes. Attempt precedes
one successful completion; wrong bytes/credit/lifetime/callback/replay cannot earn
egress. An OK racing the unresolved request callback permanently refuses selection,
even if that callback completes before `decide`. Other pending/partial/coalesced
traffic refuses selection. Any candidate response attempt defeats zero-write match,
including an attempted partial write or failed callback.

`decide` is a **simulated transition, not a transport instruction**. It consumes
one response, records exactly11 withheld framed bytes, frees its owned copy, keeps
a non-replayable credit tombstone, and never returns a send/close-capable handle.
Before cut, refusal leaves ordinary modeled forwarding possible. After cut, fault,
expiry, extra writer bytes, repeated decision or late callback cannot replay/rearm;
outcome is inconclusive, not an alternative successful path. No retry/rollback.

All six streams must conserve ingress = completed egress, except exactly11 bytes
on writer-server direction. No extra discard allowance. Final claim requires the
closed failure summary, no pending frames and timely original root; cleanup remains
bounded by the original root28s ceiling. All roles closed, queue empty and each
independent invented exit/pipe/listener/session/lock/fixture/application/ledger/
other-row/guard receipt true before `synthetic_match`. A forged all-true receipt
is still invented data, never physical proof.

`selected-loss-control.mjs` is a separate v2 canonical ASCII JSON/LF grammar, not
a mode added to v1. Distinct64-lowerhex attempt/root/pair tokens; exact ordered
keys/types/roles/seq/direction; original independent supervisor/worker clock domains;
first ROOT/BIND-byte timing, bounded1s rendezvous and original6s eligibility.
Closed FINAL case/check/group/false-return/pre8/post0/root-erased claims validated
by `selected-loss-contract.mjs`. Claims are not authenticated evidence. At most
4096 bytes per line,8 lines/32KiB, no escaped/duplicate/reordered/unknown/trailing
fields or partial EOF. Final output erases transcript pins and exports no tokens.

Witness resources: body cap2MiB/shared8MiB owned scratch+pending/64MiB input and
observed bytes/4096 frames per pair; fixed3 roles/6 streams, at most one pending
credit per direction plus the consumed-response tombstone. Completed credits are
deleted, not kept in a growing set. Selected response header cap7 before allocation.
Owned scratch is cleared; borrowed input is unchanged. No real clock, callback
provider, network, process or storage API is imported by these three helpers.

All results explicitly deny native, zero-delivery, IPC, authentication, storage,
dispatch, execution and suppression support. Positive label is `simulated_match`,
never `witness_complete`. The independent witness and v2 transcript models are not
yet joined to an owned supervisor or genuine worker.

## Canonical source-scoped review checkpoint

Sequence: Feature Light -> Bloat/Structure -> 03 backend failure handling -> 07
read-only inventory/handling -> 06 resources -> 07A adversarial suite -> final
Feature Security after fixes/controls/local QA. No UI/UX, Database Review or
whole-plugin release-readiness claim; full pre-release suite reserved for an RC.

### Feature Light and Bloat/Structure

Scope: eight new MJS files plus owning/related records; risk area Other (pure
test-support parsing/state/accounting), no UI/AJAX/REST/DB/cron/public behavior.
Implementation review tightened four guard/reporting details: request-callback
race latch, intervening writer-command refusal, immediate post-cut writer-byte
refusal, and accounting status sampled before cleanup clears pending storage.
These are foundation implementation findings, not discovered production defects;
no captured pre-fix red run is claimed. Positive/negative tests protect contracts.
No manual product decision or production fix required.

Canonical bloat helper with explicit665ec48 reports0 changed PHP/JS/CSS/0 oversized
before/after; it omits MJS (and the initial files were untracked), so **not** evidence
of zero feature growth. Supplemental actual MJS TOTAL/CODE counts, in file order
contract/control/witness/control-test/controls-test/resource-test/witness-test/fixture:
12/11,70/69,135/132,73/73,50/49,43/43,123/122,42/41. All below250. Classify each
Consolidate/retain after inspection: no dead/debug code, removals, split-now or02
deferral. Shared summary validation avoids divergence; separate v2 parser preserves
v1 fail-closed grammar intentionally. Direct-reference/registration searches found
only these test-support imports; no WordPress hook/REST/AJAX/cron registration.
Phase 2 recommendation: No updates needed; no further bloat edits justified.

### 03 / 07 / 06 Phase 1 inventory and Phase 2 dispositions

Read-only review inventory of implemented surface, before adversarial strengthening:

| ID | Category / risk | Severity in synthetic harness | Handling / regression handoff |
|---|---|---|---|
| SL1 | Empty/malformed config, frames, final/receipt | Medium | Exact shapes/types; no getter evaluation; permanent refusal; pure tests |
| SL2 | Boundary clocks, caps, stale intent and original reserve | High | BigInt monotonic/original root; preallocation/aggregate/queue/frame caps; pure bounded tests |
| SL3 | Concurrency: reply before callback, late/repeated credits | High | Sticky ineligible/cut tombstone; ordered private credits; virtual event permutations |
| SL4 | Corruption: wrong profile/OK/sequence/extra or partial frame | High | Strict shared profile + first full reply + all boundaries; pure fixtures |
| SL5 | Input extremes: fragmentation/coalescence/resource volume | Medium | Fixed budgets; representative reused2MiB fixture, no exhaustion |
| SL6 | Permissions/identity: borrowed lifetime/root/domain | High | Distinct pinned identities/tokens; no authority/capability handles; pure tests |
| SL7 | Deletion/recovery: post-cut fault, failed callbacks, cleanup claims | High | No replay/rearm; independent conjunction; no physical cleanup claim |

07 explored all12 categories: empty, boundaries, concurrency, corruption, extremes,
identity/permission and recovery/deletion as above; external services, WordPress
environment, plugin/theme conflicts and browser/client are N/A (no runtime/wiring/
service/global/UI surface). Seven scenarios:0 Critical,5 High,2 Medium,0 Low; these
are guarded harness risks, not seven unresolved defects. No parent application
entity is deleted; only owned in-memory scratch/credit cascade. All seven have
permanent pure/virtual coverage; physical OS/auth/DB handoff remains gated.
Phase 2: verified proportionate handling above; no additional production edit.

03 considered all12 categories: backend malformed input/failure/timeout/retry and
redaction apply; remaining UI/loading/forms/bulk/external/permission UX is N/A.
No silent external side effect exists. Constructor TypeError/refusal/redacted final
outcome are intentional test APIs; no auto retry after cut. Phase 2 recommendation:
No updates needed beyond verified foundation guards; no user-facing change.

06 considered all10 categories: N+1/query/table/assets/HTTP/background/cache/lazy
production loading N/A. Memory and JS loops bounded by fixed roles/credits/line/
frame/bytes; semantic import controls read only trusted repository sources and run
eight bounded cases. Resource tests assert rejection, not throughput or machine
exhaustion. No caching/index/background architecture needed. Phase 2 recommendation:
No updates needed after resource verification; no production optimization claim.

### 07A test inventory, strengthening and proof limits

Pre-feature full Node baseline331/331. Initial witness55 scoped tests passed;
expanded control/resource baseline110/110 before semantic controls. Phase 1 found
non-vacuity/counterfactual strength still needed, not a failing production feature.
Phase 2 added eight actual-source in-memory assertion-killed counterfactuals, then
accessor/profile and configuration-copy assertions. Final scoped115/full446 pass,
no skipped/cancelled/todo. No tests removed/consolidated or weak expectations waived.

| Test file | Layer / assertions protected | Quality / limits |
|---|---|---|
| selected-loss-witness.test.mjs | Pure state/accounting; exact positive11-byte match, native denials, sticky failures, receipt/summary/domain/profile cases | Strong for model; no actual authentication, SQL, sockets or private qualification |
| selected-loss-control.test.mjs | Pure separate v2 grammar, clocks/identity/ordering/types/bounds/EOF, unchanged v1 refusal | Strong for decoder; not IPC or authenticated FINAL |
| selected-loss-resource.test.mjs | Pure fragment/coalescence/header/queue/volume/count limits and frame-credit callbacks | Strong bounded representatives; not load/native transport proof |
| selected-loss-controls.test.mjs | Eight counterfactual guard bypasses against actual helper source; exact ERR_ASSERTION only; unchanged disk SHA256 | Strong non-vacuity, not production red/green or native fault injection |

Eight controls: positive cut unavailable, wrong-pair credit, unresolved-request
race latch, six-second reserve, post-cut attempted replay, independent ledger
receipt, v2 version domain, supervisor1s rendezvous. Unique replacement/import
verified before exercise; syntax/bootstrap/TypeError are not accepted as assertion
kills. No filesystem source mutation/restoration required. No artificial success
API or production guard removed. Existing passive/v1 tests remain intact.

No coverage percentage claim. API/model tests cannot establish native zero-delivery,
durability, worker false return, private uncertain DATA/qualification, real guards,
cleanup locks/session absence or application checksums. Physical witness and
authentication/storage proofs from predecessors remain historical and separate.

### Final Feature Security gate

After scoped fixes, eight controls and local validation: exact inputs/resources/
identity/order, no raw values in final output, no callback-provider/write handles,
no native imports or production references. Trusted-source data-URL imports occur
only in the semantic test; not eval of user input. SQL/HTML/WordPress capability/
nonce/filesystem endpoints absent; no claim to reverify those runtime boundaries.
Feature passes scoped security review; no unresolved scoped security defect or
new security regression handoff. Whole-plugin prompt13 remains a future RC gate.

## Validation and publication

Local: scoped115/full446 Node pass/no skips; related isolated PHP173/10171 pass/no
skips (not native proof). PHPCS235 passes with existing third-party PHPCompatibility
`functionWhitelist` format deprecation, not suppressed. Runner lint/build/verify
pass; generated runner SHA256 remains
`7356EACD57ED67F4265DB8198F8CA5809E07F4BD985B1D6D00A88CB915112117`.
Dashboard related docs receive its standard PHP/lint/structure/build validation.
Exact pushed heads/actual CI suite counts and run links belong in the toolkit's
source-scoped completion record, not an invented self-referencing commit hash.
Local verification is complete; the toolkit records 07A publication status and
exact-head Linux verification separately, without implying native proof.

## Implementation-plan progress and next gated slice

This advances the selected-response-loss eligibility/accounting plan item with a
falsifiable socket-free foundation; it does not implement the uncertainty experiment.
No further generic safe-local cleanup is recommended to defer the real boundary.

**Recommended next gated slice: Plugin Tester local-only V2.3 Selected COMMIT
Response-Loss and Owned Cleanup Proof.** Requires fresh exact-target confirmation
and approval for owned fixtures/listeners/processes and one selected response
discard/owned writer-pair close. First prove no-fault transparency, failure isolation
and bounded unresolved-worker/pump/pipe handoff before enabling any response cut.
Then independent ledger/other-row/guard/exit/pipe/session/lock/checksum/fixture cleanup
evidence; preserve existing state and production wiring. No indefinite join/pipe
drain or automatic fixture DROP while unresolved work may still exist. If bounded
handoff cannot be established, stop without cutting. Keep activation/rollback and
client/production operations disabled. Actual private uncertain provenance remains
a distinct future qualification claim, not inferred from false return or a spent flag.
