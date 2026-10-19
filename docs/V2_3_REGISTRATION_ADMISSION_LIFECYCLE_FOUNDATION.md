# V2.3 Admission-Origin Lifecycle DATA Safety Foundation

## Successor fixed-owner checkpoint — 2026-10-19

The separately approved isolated API foundation is recorded in
[fixed lifecycle owner](V2_3_REGISTRATION_LIFECYCLE_OWNER_FOUNDATION.md): one guarded
construction, exact original provenance before final preparation guards, no native
execution or synthetic outcome import, unconditional same-stack cleanup. The
shared packet methods were extracted unchanged for stateless reuse; prior DATA
semantics remain covered. New13/1072, related381/10690, eight assertion-killed/
exactly restored controls. This does not upgrade DATA into genuine admission or
release provenance. Next gate is Plugin Tester owned authentication/cleanup proof,
with fresh exact target/fixture approval; native transactions/wiring remain disabled.

## Original DATA checkpoint

2026-10-19. Approved R3 repository-only, **in-memory tests only**. No WordPress,
LocalWP, users, database/storage operations, native execution or production wiring.
Grants, activation, effects and schedule rollback remain disabled/unchanged.
Development versions remain uploader0.5.25/dashboard0.1.77; no package/release/deploy.

## Ownership and coherent review scope

Excluded uploader baseline `ed6791fdd6eab49fb01c5e389dbc7d2033c49cd3`, dashboard
`05fac9ef375ae8d5c8c10ddd9affbf8c31cc9fd4`, toolkit
`745c1a122ead20d9963fa0e232cdd328fb9393a4`. This is the complete new DATA foundation,
not a documentation-tail review. Dashboard owns the predecessor design:
`docs/V2_3_REGISTRATION_ADMISSION_OBSERVATION_LIFECYCLE_DESIGN.md` and plan record.
Uploader owns this implementation/evidence record. Historical native contention
and admission proofs are not reclassified as genuine lifecycle integration.

External source restore point, Option B, clean/synced uploader ed6791f:
`C:/Users/Captain/Documents/AI Workflows/Toolkits/toolkit-snapshots/alynt-drime-backups-uploader/alynt-drime-backups-uploader-20261019-194741.zip`
and matching `.txt`,10,690 files, SHA256
`C1EF1578BAA5739D2F4C2ECDF4D8DC0BCDABAE089E762A2FF841B2011FB11E40`.
Source snapshot only, not site/database backup; no branch/stash/rollback created.

## Implemented contract and deliberate limits

`includes/class-registration-admission-lifecycle.php` is final/test-loaded only,
zero-argument factory, private operations. Separate
`trait-registration-admission-lifecycle-data.php` validates original proposal/IDs
and root clocks. Existing bounded observation handoff/candidate contracts are
reused unchanged; no second history parser or new native adapter.

- First retention attempt consumes before validation. Exact primitive origin,
  original proposal/context/actor/operation and all ten proposed IDs must agree;
  supplied complete collision list includes site/genesis and at most128 unique
  UUIDs. Full proposal is validated but never retained. No ID generation.
- Origin precedes modeled DML. Fixed sent/ack labels enforce ordering and a single
  write; a boolean false is not accepted as an outcome. Unknown remains unknown,
  acknowledged commit remains historical, acknowledged rollback closes/erases.
- Read follows modeled writer release, once only. Root origin/ID slots are cleared
  before transfer to the existing bounded consumed handoff. Both exact synthetic
  forms/nonce bytes and lookup collision set are pinned through comparison.
- Existing origin4096/depth6/nodes256 and handoff16384/depth8/nodes512 ceilings
  remain; proposal also uses the smaller bound. References, recursive graphs,
  objects/resources/floats, malformed UTF-8, wrong shapes and excess arity refuse.
- Writer's original half-open10-second budget, separate once-started read10-second
  budget and original root20-second cap are explicit synthetic integer clocks.
  No regressions/start replacement; stale mutation at retention/read entry refuses.
  Fresh read expiry cannot renew either monotonic budget. No actual timer used.
- Full current pending-aware ten-row candidate validation is reused. Registered,
  reserved, absent and conflicting histories produce redacted candidate results.
  All authentication/barrier/closure/retry/storage/dispatch/execution flags remain
  false. Absence is never promoted to `not_observed_at_barrier`.
- Failure/repetition/interruption consumes and clears all retained slots. Clone,
  serialization/restoration and public operations/providers are refused/redacted.
  Existing bootstrap, native admission and observation owners do not load it.

Synthetic phase labels are **not genuine event attestations**. These tests do not
establish actual original-ID issuance, complete native used-ID capture, protected
read authorization, separate nonce-domain verification, physical writer release,
SQL ordering/atomicity, uncertain COMMIT recovery, ingress closure or retry safety.
The model accepts internally consistent synthetic origin DATA; it cannot make that
DATA trusted provenance. No hidden runtime success path, write-capable handle or
capability import was added. Real integration still needs explicit approval.
This first model admits only unknown-DML/unknown-COMMIT and acknowledged-commit
diagnostic traces. Unknown BEGIN/rollback acknowledgements and other omitted
transitions terminal-refuse; the state table is not an exhaustive native outcome
classifier and must not be used as one by a later owner.

## Non-vacuous tests and semantic controls

Four permanent `RegistrationAdmissionLifecycle*Test.php` classes and one shared
31-line fixture compose existing pure fixtures, no API/native/storage doubles.
Initial RED: five assertion failures for missing approved model, then GREEN.
Final new **22 tests / 2408 assertions**, no skips. Positive unknown-DML,
unknown-COMMIT and acknowledged-commit paths exercise registered/reserved/empty
histories; controls cannot pass an always-unavailable owner. Tests also cover
original actor/context conflict, all-ten-row corruption, every proposed ID's
omission, malformed/aliased/oversized inputs, one-shot erase, privacy/loading,
exact phase/root deadlines and final valid half-open boundaries.

Eight semantic controls were individually assertion-killed (not syntax/runtime
errors), then SHA256-exactly restored:

| Control | Permanent assertion protecting it |
|---|---|
| C1 allow DML directly after retention | Out-of-order DML must refuse and erase. |
| C2 bypass proposal/ID validation | Each required identity omission must refuse before DML. |
| C3 allow observation at DML-sent | Read must wait for modeled writer release. |
| C4 make root deadline inclusive | Exact original ten-second boundary must refuse. |
| C5 relabel uncertain as not-written | Positive uncertain path must reach a non-authorizing result. |
| C6 keep root origin after transfer | Root copy must already be null after read admission. |
| C7 keep root clock after finish | Every retained slot must be null after completion. |
| C8 retain rolled-back origin | Acknowledged rollback must close/erase without read. |

Restored source SHA256: class
`F74572370E678ACB8689A860EC20884B28A97A512BB216B98ED715A66A7D036C`, trait
`AAC4220DC958D6CBFA0B84679DC4B69E5B67A9B4C97AC9C8E785AA9B258567B0`.
No existing test weakened/removed, skips added or physical proof rerun.

## Canonical source-scoped reviews

Feature scope: dormant lifecycle DATA only. Files reviewed: seven changed PHP
files plus eight direct unchanged DATA/handoff/parser/privacy dependencies.
Risk areas: Other (future admission trust/order), no UI/AJAX/REST/DB/cron/file/API
operation. Issues found/remaining after validation:0; manual decisions:0.

1. **FEATURE_LIGHT_REVIEW_PROMPT.md**: naming/architecture, fixed state order,
   refusal/repetition, byte-exact validation, resource bounds and documentation
   checked. WordPress APIs/translation/output escaping are not applicable to pure
   private DATA; no callbacks, hooks, endpoints or user-facing strings introduced.
2. **FEATURE_BLOAT_AND_STRUCTURE_REVIEW_PROMPT.md**, both phases: mandatory helper
   against explicit ed6791f before/after, seven changed PHP files, zero oversized.
   TOTAL class215/trait73/boundary48/clock46/origin79/flow61/fixture31, all under300
   (all tests/support also under150). CODE=1 is the existing helper anomaly, not a
   meaningful code-size claim. Each classified Consolidate/retain; no further
   cleanup/split required. Direct references, fixed require lists and hook/filter/
   AJAX/REST/cron/string registrations checked; intentionally dormant is not dead
   production code. No Phase2 edits needed, no pre-release02 deferral.
3. **FEATURE_SECURITY_REVIEW_PROMPT.md**, final after controls/restoration and
   validation: pre-encoding graph/type/alias bounds, immutable original context,
   no boolean-to-certainty promotion, exact forms/ID collision pinning, original
   deadlines, consume/erase and no public handles/capability export checked.
   Feature passes source-scoped security review; no confirmed defect requires a
   new regression handoff. This does not certify actual auth/nonce/SQL behavior.

UI/UX workflows N/A: no UI. No canonical Database Review, physical transaction or
whole-plugin release-readiness refresh claimed. FULL_PRE_RELEASE remains reserved
for the next coherent release candidate before packaging/publishing this work.

## Validation and publication

Restored related in-memory suite **368 tests / 9618 assertions**, no skips.
Uploader WPCS224 files and all seven new PHP syntax checks pass; the existing
dependency functionWhitelist deprecation is unchanged, with no code errors.
Build/deterministic runner and developer tools3/3 pass; generated outputs unchanged.
Dashboard local299/1423 with two existing Sodium skips; structure passes. Exact-head
CI/counts are recorded at handoff and in the toolkit source-scoped entry, not
inferred from predecessor green runs. Historical whole-plugin checklist rows stay
unchanged. Toolkit has no configured Actions workflows; no toolkit CI green claim.

Implementation-plan progress: the bounded lifecycle DATA contract is now executable
and non-vacuously tested, but genuine original admission provenance remains gated.
**Recommended next gated slice: local-only V2.3 Fixed Admission-Origin Retention
and Guarded Lifecycle Owner Safety Foundation**, dormant fixed-owner/guarded
composition with isolated API tests only, no actual storage/native execution or
production wiring. New explicit approval is required for the genuine request/
authorization integration source boundary; owned native admission-to-observation
proof, wire-loss/crash/closure/retry and rollout remain later gates.
