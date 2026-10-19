# V2.3 Fixed Admission-Origin Retention and Guarded Lifecycle Owner Foundation

2026-10-19. Approved R3 **repository-only, isolated API tests only**. No WordPress
bootstrap, LocalWP, users, database/storage/native execution or production wiring.
No grants, activation, backup/restore, effects, schedules or rollback enabled.
Versions remain uploader0.5.25/dashboard0.1.77; no package/release/tag/deploy.

## Ownership and coherent boundary

Excluded source review baselines: uploader
`1259bbdec189992fcd625524ac8ae11a2bad9807`, dashboard
`1c26c5892615a738d053e1a27079eee0c5fc106c`, toolkit
`1ae758c61947179efb25e8f0dd6433c068b5ae76`. Entire seven-file guarded-owner/test
delta plus direct construction, core guard, eligibility, window, origin, lifecycle
DATA, parser and privacy dependencies; not a documentation-tail review.
Dashboard owns `docs/V2_3_REGISTRATION_ADMISSION_OBSERVATION_LIFECYCLE_DESIGN.md`
and implementation plan. Prior DATA owner:
[lifecycle foundation](V2_3_REGISTRATION_ADMISSION_LIFECYCLE_FOUNDATION.md).

Option B external source ZIP and matching `.txt`, clean/synced1259bbd:
`C:/Users/Captain/Documents/AI Workflows/Toolkits/toolkit-snapshots/alynt-drime-backups-uploader/alynt-drime-backups-uploader-20261019-203134.zip`,
10,712 files, SHA256
`106E1760817DFBCA1AD7EF9B8B2231C15865E0339E92DA71293CDE83C3E29D2F`.
Not site/database backup; no checkpoint branch, stash or source rollback.

## Implemented fixed-owner boundary

`includes/class-registration-lifecycle-request-owner.php` is final/test-loaded,
zero-argument factory, private `prepare_admission(captured, body)`. No public frame,
origin, actor, clock, handle or callback provider; the captured-frame argument is
a private reflection seam in isolated tests, not native capture provenance.

One root attempt installs existing role-only query/autoload fences, pins core/
actor/cookie and one real monotonic start, then calls the existing guarded
registration constructor **exactly once**. Existing closed ten-row eligibility,
all ten generated IDs and full proposal/context are preserved. Before retention,
the owner's own full command nonce is verified, not merely parsed.

The root derives minimal origin from that exact proposal/current original actor,
not a receipt, supplied context or second constructor. It reuses lifecycle DATA
`original()` to validate original proposal/actor/context/operation and the complete
used/reserved set, including all ten fresh IDs and site/genesis. Then it retains
minimal origin/IDs and the original clock **before** final issuance and full-command
guards. Same-request identity, unrenewed ten-second budget, original-clock and
exact retained-origin/ID equality are checked before positive private completion.

The old packet/walk validators were moved byte-for-byte into stateless
`trait-registration-observation-packet-data.php`, reused by both the existing
handoff trait and new owner. Independent method-body comparison to1259bbd confirms
exact equality. This avoids copying traversal or importing the handoff's mutable
stage/claim/finish state into a request owner. Existing 16384-byte/depth8/nodes512
aggregate and smaller origin/proposal bounds are unchanged.

Finally always consumes the constructor/root, erases origin/IDs/clock, restores
owned filters, role view and prior error handler. Nested finally protects handler
restoration even if window cleanup throws. Re-entry poisons the outer root;
metadata re-entry before construction cannot mint IDs/nonces. Positive private
return is only preparation consistency and cannot resume the erased origin.

**No native admission seam has been enabled.** There are no sent/released event
imports, transaction/SQL/reader/writer methods, handle exports, observation nonce
issuance, observer entry point or production loader/endpoint. The root does not
accept the DATA model's synthetic phases as genuine facts. Actual native admission
must eventually be joined inside this same root stack, before cleanup, after a
separate approval/proof. Do not call this positive return a write/release receipt,
durable origin, barrier, retry permission or admission-to-observation integration.

## Non-vacuous isolated API evidence

New **13 tests / 1072 assertions**, no skips, three isolated-process classes plus
one31-line fixture, using existing API doubles and synthetic closed frames only.
Initial RED: missing-owner assertion; GREEN: meaningful positive private root.
Tests require exactly ten generated IDs, one command nonce and six guards in
order: issuance/issuance/issuance/command/issuance/command. During the last two
guards origin is already present, minimal, exact and bound to all ten IDs; after
return every retained slot is empty. An always-unavailable implementation fails.

Permanent tests cover each nonce checkpoint's independent refusal, issuance/
command domain substitution, initial session/user/role/capability denial before
IDs, malformed/read-purpose/extra intent, all-ten invalid frame tuples, revocation
after retention, actor/cookie/blog/database/method swaps, original deadline/reset/
clock regression, altered origin/ID set, re-entry, warnings/throws, owned fences,
roles/error-handler restoration, privacy and lack of native/loading ports.
Protected option/query traps record **zero attempted calls**, even when caught.
No existing test weakened/removed or skip added.

Eight semantic controls were assertion-killed and then SHA256-exactly restored:

| Control | Permanent failure |
|---|---|
| C1 always refuse | Positive fixed owner must succeed. |
| C2 omit pre-retention command guard | Required six-checkpoint count/order changes. |
| C3 omit retained issuance guard | Required six-checkpoint count/order changes. |
| C4 omit retained command guard | Denied final nonce incorrectly succeeds. |
| C5 omit original provenance equality | Altered retained actor incorrectly succeeds. |
| C6 omit complete identity equality | Missing retained ID incorrectly succeeds. |
| C7 omit final origin erase | Retained origin survives completion. |
| C8 omit original clock check | Regressing original observation incorrectly succeeds. |

Owner restored SHA256:
`FCFF2FB71766AE80EF631447367D2C95CD066A80F2AB1177F0ECAFF1A774FD95`.
An initial control-restoration context mistake was caught by hash verification;
corrected, full pristine suite rerun, then all eight controls rerun successfully.
No control counted as complete before assertion failure and exact restoration.

## Canonical source-scoped reviews and validation

Feature scope: fixed dormant owner only. Files reviewed: seven changed PHP plus
eight direct unchanged guard/construction/eligibility/window/origin/lifecycle DATA/
request-parser/privacy dependencies. Risk areas: Other (authentication/order),
no UI/AJAX/REST/database/cron/file/API operation. No outstanding manual decision.

1. **FEATURE_LIGHT_REVIEW_PROMPT.md**: fixed one-stack state, reuse instead of
   duplicate parsers, positive/denied/repeated/error paths and documentation
   checked. Cleanup hardened with nested finally and pre-construction interruption
   check; no remaining non-security issue. No new public/user-facing strings/UI.
2. **FEATURE_BLOAT_AND_STRUCTURE_REVIEW_PROMPT.md**, both phases, explicit1259bbd
   helper before/after: seven PHP, zero oversized, all Consolidate/retain; TOTAL
   owner219/handoff184/packet60/cleanup63/denial80/flow29/fixture31. CODE=1 is the
   existing helper anomaly, not a size claim. Direct/require/hook/filter/AJAX/REST/
   cron/string references checked; intentionally dormant, not dead public code.
   No further Phase2 edits, forced split or pre-release02 deferral.
3. **FEATURE_SECURITY_REVIEW_PROMPT.md**, final after fixes/controls/restoration:
   exact bounds/aliases, current auth guards and separate issuance/command nonce
   actions, before-retention command verification, immutable original IDs/context,
   original clock, consume/erase and no native capability/export checked. No
   outstanding confirmed security defect/regression handoff. This is not genuine
   WordPress authentication, physical transaction or full Security Audit evidence.

Restored related suite **381 tests / 10690 assertions**, no skips; WPCS226 and
seven PHP syntax checks pass (existing dependency functionWhitelist deprecation).
Build/deterministic runner/dev-tools3 pass; generated outputs unchanged. Dashboard
local299/1423, two existing Sodium skips, structure passes. Exact-head CI/counts
recorded at handoff and in the toolkit scoped entry, never inferred from older CI.

No native proof/WordPress/cache/user/database operation was performed. API doubles
do not establish actual core auth, physical capture, concurrent release, uncertain
COMMIT recovery, closure or retry. No Database Review/whole-plugin readiness row
refresh, UI/UX workflow, package/release/deploy. Full pre-release remains required
for the next coherent RC before publishing a plugin containing this work.

Implementation-plan progress: fixed guarded same-owner construction now retains
original provenance before its final preparation guards; the next evidence layer
is genuine owned authentication/cleanup, not more generic DATA maintenance.
**Recommended next gated slice: Plugin Tester local-only V2.3 Fixed Admission-Origin
Retention and Guarded Lifecycle Owner Owned Authentication and Cleanup Proof**,
with confirmed exact local target and owned disposable users/storage fixtures and
cleanup, preserving application state. Native admission/observer transactions,
wiring, grants/activation/rollback remain disabled. Fresh explicit site/fixture
approval required; this is not approval for native admission-to-observation execution.

Follow-up: the separately approved genuine owned-core authentication/cleanup proof
is recorded in [lifecycle owner auth proof](V2_3_REGISTRATION_LIFECYCLE_OWNER_AUTH_PROOF.md).
This historical isolated-API foundation record does not itself claim that runtime evidence.

The approved repository-only **Fixed Native Admission Join and Lifecycle Outcome
Integration Design** is owned by dashboard
`docs/V2_3_NATIVE_ADMISSION_JOIN_OUTCOME_DESIGN.md`. It maps actual private native
helpers into this root **before** unconditional cleanup, never after the returned
preparation boolean or through a second constructor. Attempt/send/ack/idle facts,
historical outcome, client release and current auth remain separate. Source/tests,
native execution, production wiring and grants/activation/rollback unchanged.
Next gated slice: in-memory-only Admission Outcome and Release DATA Safety Foundation;
this design approval does not authorize that implementation or native fixture work.
