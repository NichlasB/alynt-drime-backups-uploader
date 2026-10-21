# V2.3 Same-Root Closed Admission/Observation Lifecycle Integration Safety Foundation

2026-10-21. Explicitly approved local-only, isolated API tests only. R3 lifecycle/
authorization semantics are confined to a dormant composition; production wiring
and native execution remain disabled. No WordPress bootstrap, actual users/cookies,
SQL, storage writes, native proof/transactions, fixtures, services, listeners,
response loss, grant, activation, rollback, package, release, tag or deployment.
Uploader0.5.25/dashboard0.1.77 unchanged; this is not a release candidate.

## Ownership, baseline and restore points

Uploader owns five new dormant PHP files, four test classes, three test-support
files and this record. Dashboard owns its plan milestone and readiness-design
successor pointer. Explicit excluded coherent source review bases: uploader
3cd9ed9786e578a666ed8814ba724092e99079c9 and dashboard
9388869c1bea20d7d4cdd974fdd80172c45244f3. Toolkit starts at
ff3aa66676318dfa6fcb55693cd884a73c80b8e3. All started clean/synced; old
admission, guard, outcome, observation and handoff sources are direct dependencies,
not a whole-history review claim. No existing source/test/proof pin is rewritten.

Parent: dashboard docs/V2_3_CLOSED_ADMISSION_OBSERVATION_PRODUCTION_WIRING_READINESS_DESIGN.md,
following its selected same-request diagnostic policy. The original native root
cannot be called and then resumed: its finally erases origin, and its root-local
full pin is not a read continuation. We preserve that original source and its
historical proof claims rather than quietly changing it under old source hashes.

Source-only OptionB restore points under Toolkits/toolkit-snapshots:

- alynt-drime-backups-uploader/alynt-drime-backups-uploader-20261021-162051.zip plus .txt,
  11399 files, SHA256
  A11E0456E18A2A3764E08792565A15AC38C3DB6E174FD94B654CF08D6FA4F49C.
- alynt-drime-backups-dashboard/alynt-drime-backups-dashboard-20261021-162100.zip plus .txt,
  13248 files, SHA256
  5460F8D5389CE3A3D0F25E31E17DCE256A3EACF99183A2CF687C7D8BB2A45F40.

These are repository ZIPs, not site/database backups. No site target, credentials,
LocalWP service discovery or Novamira call is needed for this repository-only work.

## Actual implementation and deliberate closed boundaries

New includes/class-registration-lifecycle-integration.php has a zero-argument
factory, private entry and the existing redacted disposition/serialization
refusals. No runtime include, hook, endpoint, CLI or production constructor loads
it. All six private physical/capture/attempt/release/read boundaries are fixed
unavailable; no native trait, connection factory, SQL or enabled switch is added.
The final owner cannot progress natively, even if invoked through test reflection.

New methods/root/state/read traits share the orchestration unchanged between the
final owner and an explicitly isolated test composition. Tests replace only the
private IO/physical boundaries with non-IO API doubles. The qualifier is wrapped
to observe its unchanged return, not replaced with a favorable classifier.
Positive composition tests are required; testing only the final owner's default
refusal would be vacuous. This composition evidence is NOT positive execution of
the final owner with real WordPress/native dependencies.

The root:

1. Consumes an independent root latch, pins the original core/method/actor/cookie,
   installs only owned role/query fences and starts its clock before capture.
2. Runs existing core guards before a protected-capture boundary. Reuses actual
   registration construction, candidate preparation and own admission-origin
   retention, including the complete historical plus ten new identities, before
   the once-only attempt boundary.
3. Preserves raw capture semantics through the existing sealed qualifier.
   Committed/uncertain history plus positive release are prerequisites only;
   false never becomes committed/current or diagnostic permission. Not-written,
   rolled-back, malformed, unavailable and release-unknown branches stop.
4. Transfers only its own minimal origin/identity set internally, unsets the
   full stack pin and erases full frames/proposal/context/replacement/seal before
   any read-auth callback. No supplied origin, serialized continuation, callback,
   owner, outcome, actor or clock parameter exists on the private root entry.
5. Freshly guards the original same-request mutation intent after release, then
   mints/guards the separate small read intent and distinct full read command.
   A nonce-creation callback cannot switch actor/core or issue a lookup identity
   before original-request continuity checks. Reuses the existing read builder
   and consumed stage/claim/finish validators, without setting another owner's
   capture latch or calling a nested consuming owner.
6. Renews both read domains before staging, claim, protected read and after
   consumed candidate parsing. Original admission10s, read10s inside original20s,
   nonregressing UTC and original intent expiry remain acceptance constraints;
   there is no hard I/O cancellation claim or reset of root age.
7. Returns only the existing nine-field candidate result, with all authentication,
   barrier, closure, retry, storage, dispatch and execution flags false. Candidate
   absence stays not_observed_in_candidate, never not_observed_at_barrier.
8. Final cleanup consumes/erases even refused, interrupted or throwing paths,
   restores only owned filters and the prior error handler, and refuses a
   previously computed candidate if final cleanup is unconfirmed or reentered.

The doubled IO fixture stores synthetic row/ack history externally for assertions.
It is not storage provenance, physical release or proof that no bytes remain in
all process memory. Private visibility is not a defense against hostile same-process
PHP/reflection. No raw private object/nonce/handle is exported through the result.

## Source-scoped canonical review record

FEATURE_LIGHT_REVIEW_PROMPT.md: backend-only five source/seven test-support PHP
files plus owning records/direct dependencies. Scope:Other, explicit R3 approval.
All nine categories reviewed: scope/architecture confined; actual helper reuse;
redacted terminal errors/no automatic retry; UX/accessibility N/A; existing WP
capability/session/nonce semantics reused, no route; malformed/stale/reentry/clock/
identity cases fail closed; fixed bounded loops/clocks, no new parser/query;
plan/owner updated; escalation to scoped03/04/07 then07A and final Feature Security.
No UI/UX workflow. No public contract/version/readme behavior changed.

FEATURE_BLOAT_AND_STRUCTURE_REVIEW_PROMPT.md Phase1: explicit uploader base above,
12 changed PHP files, zero oversized. Helper TOTAL counts: class113, composition31,
state170, root152, read81; tests/support all below150. CODE column reported an
implausible1 for every file and is not used as code-size evidence; TOTAL values
drive the documented threshold decision. Classification:Consolidate/keep as-is
for all12, no justified removals/splits/deferred02 work. Five source boundaries
separate final unavailable ports, trait composition, lifetime, preparation and
read admission; WPCS requires one object structure per source file. Reference
search confirms private/test-only use and absence from runtime registrations.
Phase2 recommendation:No updates needed; no cosmetic split or framework added.

03 Error Handling: source-scoped terminal refusal on warnings/exceptions, no raw
exception export; attempt exception does not invent acknowledgements. Release/
read throws and cleanup failure cannot return a favorable result. No UI/loading/
success notice, external API, destructive confirmation or bulk operation applies.
During implementation, final-cleanup reentry was found and blocked before
completion; its permanent boundary test remains. No deployed defect claim.

04 WP Best Practices: fixed prefixed classes, no new public hooks/options/transients/
AJAX/REST/assets/SQL or deprecated APIs; exact method/intent and existing fresh
capability/session guards. Owned closures are retained for exact removal, never
remove_all_filters. WPCS run, not replaced by syntax alone. Native storage/profile
compatibility remains out of scope; no database review claim.

07 ->07A risk/test inventory, isolated API layer throughout:

| ID / impact | Test groups and assertions | Layer limit / runtime handoff |
|---|---|---|
| SR1 High: unauthorized capture/identity issuance | DenialTest malformed/method/nonce/actor/role/cookie/extra arguments; exact pre-capture denial and seeded UUID consumption, trapped option/SQL calls. | Genuine core authentication needs approved WordPress proof. |
| SR2 High: foreign/stale origin or writer context | BoundaryTest release-time actor/cookie/blog/connection/physical/intent/role/session/origin/IDs/proposal faults; own origin before attempt; no read/lookup UUID on refusal. | Native context/physical session continuity is doubled. |
| SR3 High: refusal promoted to permission | IntegrationTest committed and false/throwing uncertain positives; DenialTest not-written/rolled-back/unavailable/unknown release. Actual reused qualifier remains non-authorizing/current false. | Synthetic sent/ack/close labels, not real uncertainty or release. |
| SR4 High: missing or reordered read admission | Exact20 nonce sequence/four domains, staged/inflight/finished assertions; every guard individually refused; original actor cannot change during read nonce creation. | No public request registration/native pre-BEGIN exists. |
| SR5 High: replay/clock renewal | BoundaryTest reentry during capture/attempt/release/read/final cleanup; root/read age, reset, UTC regression, expired intent; consumed second invocation. | Acceptance budgets, not actual timeout/crash or session cancellation. |
| SR6 High: retained private bytes or misleading absence | Writer frame erased before read guards; all private slots/fences/error-handler restored; candidate-only absence and literal-false result flags. | No physical cleanup, locks or server-quiescence claim. |
| SR7 High: vacuous or bypass-insensitive tests | ControlsTest unavailable-port positive assertion fails; five fixed-source in-memory guard/release/history/erasure/read-admission controls assertion-killed; disk hashes unchanged. | Controls mutate isolated source composition only, not native code or disk. |

07A Phase1 baseline was30 tests/3002 assertions/no skips; review found the
self-derived guard-count loop insufficient to detect removal. Phase2 strengthened
the positive test to require the exact20 sequence and explicit phase ordering.
Fixed declarations/fixture issues found during initial development were resolved
(trait aliases/load order/event constant; UUID helper is observed through the
existing seeded generator, not replaced; public redacted disposition kept).
No historical test removed, relaxed, skipped or consolidated. No new dependencies,
coverage percentage claim, repeated arbitrary sleeps or semantic-control disk edit.
Full local validation passed; final supported-PHP CI evidence remains the
publication gate. No unresolved automated finding in the approved isolated layer.

Final FEATURE_SECURITY_REVIEW_PROMPT.md completed source-scoped after local
validation/fixes: reviewed original-request continuity, four intent domains, no protected read before
authorization, minimal owner provenance, once-only release/read, primitive bounds,
private-handle/secret redaction and no production exposure. No residual scoped
security defect; final-cleanup reentry and guard-loop weakness have permanent
isolated regression protection. Test-only eval consumes fixed local source and
fixed counterfactual substitutions, never request PHP, and is not runtime-loaded.
No wider whole-plugin security/readiness refresh.
No deeper06 needed: parser/traversal/SQL resource limits unchanged, fixed handoff
and10-identity loop only. No05 Database Review or physical transaction proof.
FULL_PRE_RELEASE remains required for a later coherent RC before publishing.

## Verification and next gate

Completed preservation checks so far:

- Full local php ./vendor/bin/phpunit --do-not-cache-result:
  **1918 tests/46214 assertions**,170MiB,11m38.045s, four existing Windows
  platform skips, no failures/errors/risky tests. Final strengthened tests are
  included (1888 prior tests/43131 assertions plus30/3083).
- Final related PHP filter SameRootLifecycle plus RegistrationAdmissionLifecycle,
  RegistrationLifecycleOwner, RegistrationNativeQualification,
  RegistrationObservationIntegration, RegistrationObservationRequest,
  RealClosedWriterRequestContract and SelectedQualification:
  **186 tests/13795 assertions**, zero skips/failures. New family30 tests/
  3083 assertions (derived difference from unchanged related156/10712), including
  five assertion-killed source controls and the unavailable-port positive control.
- Configured uploader WPCS **240 files** passed; existing PHPCompatibility
  functionWhitelist comma-array deprecation remains reported, not suppressed.
  npm run lint:runner, npm run build and npm run verify:runner passed.
  Runner SHA256 unchanged:
  7356eacd57ed67f4265db8198f8ca5809e07f4bd985b1d6d00a88cb915112117.
- Selected transcript synthetic Node tests **18/18**, zero skips/failures;
  original source identities remain valid. No native CLI/proof executed.
- Dashboard local PHP **299/1423**, two existing Windows Sodium skips;
  WPCS138, structure/build passed, generated files unchanged.
- Canonical same-base bloat measurement repeated,12 PHP files/zero oversized;
  final test class max73/support max56/source max170 TOTAL lines.
  git diff --check and frozen source/loader/worker/build comparisons passed.

Exact-head supported-PHP CI receipts are the final publication closeout gate;
green isolated checks do not establish a genuine/native join. Old native proof,
source pins, worker/relay/transcript, production loader and runner remain unchanged.

Implementation-plan progress: R1 now has a fixed dormant orchestration with
non-vacuous positive/refusal/guard-order protection; real native boundaries and
production readiness remain unimplemented.

Recommended next gated slice: **local-only V2.3 Same-Root Fixed Native Boundary
Integration Design**, design-only. Map the six currently unavailable boundaries
to existing genuine capture/admission/release/zero-DML observation helpers with
one resource owner, explicit supported profile, before-read erasure and bounded
failure cleanup. Specify new source identities and genuine no-fault/uncertain
acceptance, without wiring or running native code. This is not another DATA
classifier or permission grant; implementation/native proof requires subsequent
approval and exact disposable target/scope. No package/release/deploy is warranted.
