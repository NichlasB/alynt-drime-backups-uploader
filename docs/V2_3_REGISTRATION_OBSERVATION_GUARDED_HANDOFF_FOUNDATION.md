# V2.3 Guarded Observation Private Handoff Integration Safety Foundation

Approved 2026-10-19, R3 **repository-only/dormant; new isolated API tests only**.
Excluded source-scoped review baselines: uploader
`ba213031937078f226ed9d9e5b6efe6899d7fddb`, dashboard
`6deb33ef615a7f0cac0afa7adaad19936bfd18d7`. Review the complete delta from these
commits, not a later verification receipt. Refines the
[DATA foundation](V2_3_REGISTRATION_OBSERVATION_HANDOFF_FOUNDATION.md) and dashboard
`docs/V2_3_REGISTRATION_OBSERVATION_NATIVE_HANDOFF_DESIGN.md`. Versions remain
uploader 0.5.25/dashboard 0.1.77; no package, release, tag or deployment.

## Implementation and ownership

The existing dormant observation constructor now has a fixed **private**
`observe_candidate(captured, body)` path. In isolated API tests, `captured` means
complete synthetic candidate bytes, not a protected native observation. No
caller origin/object, read blueprint, provider, clock, connection or execution
flag is accepted. This is not wired into a production loader or native caller.

- `includes/trait-registration-observation-read-construction.php` factors the
  existing guarded read builder, called only by wrappers with consumed read
  ownership. It preserves the independent small/full nonce domains, guard before
  one UUID attempt, own-origin matching and original actor/cookie/core binding.
  It also pins its original window start. Core/cookie/full-form facts are private
  wrapper locals; never retained in DATA or returned as a candidate capability.
- `includes/trait-registration-observation-handoff-data.php` moves the existing
  bounded stage/claim/finish machine into one shared private trait. Method bodies
  were compared exactly with the predecessor; no validation/limit was relaxed.
  `includes/class-registration-observation-handoff.php` remains a zero-argument
  DATA factory with the same private operations and redacted public surface.
- `includes/trait-registration-observation-guarded-handoff.php` latches the outer
  monotonic start before building, stages **its own** original facts/forms/used ID
  set before origin cleanup, clears origin, renews both nonce domains and fresh
  guards before claim, consumes/clears before full candidate parsing, and renews
  both domains again before returning any result. Original outer/window clocks
  cannot be renewed; reentry poisons the attempt. All exit paths clear origin,
  staged/inflight state and only owned filters; the prior error handler is restored.
- `includes/class-registration-observation-construction.php` composes those
  private helpers. The original construction-only entry point still clears
  origin/window unconditionally in `finally` and exports only its former private
  blueprint. There is no retain flag, public handoff/candidate endpoint or
  constructor seam. Retention and existing native capture/wrappers are unchanged.

Four valid API paths now reach distinct **candidate-only** results: registered,
retained reserved-intent, absent and conflicting history. Every native barrier,
closure, authentication, retry, storage, dispatch and execution flag stays false.
No `not_observed_at_barrier`, write continuation, raw rows/forms, cookie, actor,
core object or handle is returned. The public owner surface remains exactly
create/disposition and its existing privacy methods. No actual native method is
called by the new path; reader/counters/native attempt latches remain untouched.

## Tests and semantic controls

New `tests/RegistrationObservationIntegrationTest.php`,
`tests/RegistrationObservationIntegrationBoundaryTest.php` and
`tests/Support/RegistrationObservationIntegrationFixture.php` reuse the existing
isolated core API doubles and complete in-memory schema chains. No new success
flag, injectable authorizer, clock, reader or WordPress/bootstrap seam was added.
The first integration assertion failed before implementation. Restored results:

- New isolated API: **14 tests / 1701 assertions**, no skips.
- Complete observation subset: **71 / 3718**, no skips.
- Related registration/closed-writer/native-boundary regressions: **336 / 7087**,
  no skips.

Tests observe all seven nonce phases, independent domains, origin present during
construction but removed before transfer renewal, staged slot removed before
finish renewal, and consumed facts before final guards. They exercise malformed/
mismatched intents before auth; bad issuance before UUID/nonce minting; full
nonce denial before staging; denial of each late nonce; initial and late session/
capability/actor/cookie/core/method/prefix/time drift; reentry through four entry
points; role-only autoload/query fences throughout; original cleanup/error-handler
restoration; collision without reminting; complete current-frame validation;
redacted public results and no production/native caller.

The predecessor DATA dormancy test intentionally no longer forbids the now-
approved private constructor composition. It still excludes production loaders
and native admission; the new API tests prove the fixed private surface and no
native callers/attempts. This changes the authorized consumer boundary, not a
failure expectation merely to obtain green results. Its two former constructor-
source assertions are replaced by stronger integration/public/native assertions.

Ten source controls each caused a **semantic assertion failure**, not a parse or
runtime error; exact source text was verified restored after each. The restored
new and related suites passed afterward:

| Removed requirement | Regression caught |
|---|---|
| Initial small read guard | UUID/nonce issuance occurs before authorization. |
| Full read nonce guard | Staging occurs before full-domain acceptance, even though later guards refuse. |
| Pre-claim renewal | Missing prescribed nonce phases/transfer ordering. |
| Post-finish renewal | Late denial no longer invalidates a positive candidate. |
| Origin cleanup before transfer renewal | Original origin survives into the staged phase. |
| Original window-start pin | A callback-renewed start is accepted. |
| Original observed intent matching | Mismatched intent reaches authentication. |
| Original construction-only cleanup | Origin survives its old consuming entry point. |
| Reentry poison | Nested refusal no longer invalidates the outer candidate. |
| Owned window cleanup | Original scope/clock/filters survive completion. |

Callbacks collect facts/faults, not assertions that the owner could swallow.
Options/native I/O traps are checked after both successful and refused calls.
These tests establish **isolated API ordering only**: not genuine WordPress
authentication, native physical-session identity, protected-row capture, a
transaction/zero-DML barrier, competing-process serialization, lost-wire/crash
behavior, real uncertain-admission recovery, durable ingress closure, quiescence,
retry permission, Database Review or rollback safety. Synthetic frames are never
promoted into native evidence. No real users, sessions, options or cron were used.

## Canonical source-scoped feature review

Followed the toolkit runner and actual canonical DS2 Light, Bloat/Structure and
final Security prompts. Scope: **9 changed PHP files** named above plus the
modified predecessor `tests/RegistrationObservationHandoffLimitsTest.php`, and
directly affected unchanged guard/eligibility/origin/request/candidate/window
helpers and plan records. Dashboard is records-only; no UI workflow applies.

Light: risk area Other/private dormant guarded API integration; no significant
non-security defect or manual decision remains (**0 / 0 / 0** found/fixed/manual).
Reuse avoids duplicate guard builders or public capability ports. Routine PHPCS
closing-brace spacing was fixed before review. No settings, hooks, routes,
translated strings, debug branches or production registration were added.

Bloat Phase 1 read-only; explicit base `ba213031937078f226ed9d9e5b6efe6899d7fddb`
used for both canonical helper passes, nine changed PHP files, **zero oversized**.
All classified Consolidate/retain intact, with no further consolidation required.
No removals, split-now or architecture-sensitive deferrals; Phase 2 simple,
re-measure/validate with no edits. Direct references and hook/filter/REST/cron
registrations were searched; private extraction is needed for same-owner reuse,
not threshold-driven file splitting. Both measurement snapshots are identical:

| File | TOTAL | Helper CODE | Limit | Over by |
|---|---:|---:|---:|---:|
| construction class | 239 | 1 | 300 | 0 |
| DATA factory class | 30 | 1 | 300 | 0 |
| guarded handoff trait | 101 | 1 | 300 | 0 |
| shared DATA trait | 229 | 1 | 300 | 0 |
| read construction trait | 84 | 1 | 300 | 0 |
| predecessor limits test | 77 | 1 | 300 | 0 |
| new boundary test | 85 | 1 | 300 | 0 |
| new integration test | 68 | 1 | 300 | 0 |
| new API fixture | 47 | 1 | 300 | 0 |

As recorded previously, this PowerShell helper's CODE=1 metric is anomalous;
TOTAL is the mandated threshold and no decision relies on CODE. No toolkit fix
or unrelated structure change was made.

Final focused Security completed after fixes, restored controls/regressions and
structure review: no confirmed source-scoped security defect remains. Checked
strict arity/original intent, fresh guards/nonce domains, immutable owner facts,
clock and one-shot lifetime, privacy, callback fences, cleanup and lack of native/
public import/export. Regression handoff: no confirmed defect requires further
coverage beyond these permanent API tests. No HTML, SQL, file handler, new endpoint
or dangerous unserialization exists in the new integration. This is not whole-
plugin security/release readiness. DS2 has no toolkit checklist completion row;
historical numbered/full-release rows remain untouched. FULL_PRE_RELEASE remains
required for a coherent future release candidate before packaging this work.

## Validation and safety record

Source-folder-only restore point, not a site/database backup:
`C:\Users\Captain\Documents\AI Workflows\Toolkits\toolkit-snapshots\alynt-drime-backups-uploader\alynt-drime-backups-uploader-20261019-153513.zip`
with same-stem `.txt`, clean baseline above, 10588 files, SHA-256
`E2867289A0ABDEDA19BDA7CCB111E76FB4643F85C493C2335148777D29C59F5C`.
No checkpoint branch or rollback. User approval covers this R3 isolated subset,
not actual WordPress/site operations; site-target/Novamira discovery N/A.

Local uploader PHPCS **220 files**, build, runner verification/syntax and **3**
developer-tool tests pass (existing `functionWhitelist` deprecation only).
Dashboard **299 / 1423**, two existing local Sodium skips; PHPCS **138**, structure
and build pass. Both Composer strict validation/audits and npm moderate audits
pass, no advisories. PHP syntax and whitespace checks pass. Versions, dependency
manifests/locks, assets/runner, core guards, native capture/transactions and
production loaders/wiring unchanged. No site/LocalWP/SQL/browser/Novamira or owned
proof runner was accessed; actual application state untouched. Exact-pushed-head
CI success is required and actual run IDs/results belong in the final handoff.

## Implementation-plan progress / recommended next gated slice

The guarded same-owner construction-to-candidate handoff is executable under
isolated APIs; genuine WordPress authentication/cleanup and the native observer
remain unproved. The next meaningful lane is owned runtime proof, not more safe-
local cleanup.

**Recommended next gated slice: Plugin Tester local-only V2.3 Guarded Observation
Private Handoff Owned Authentication and Cleanup Proof**. Explicitly confirm the
exact local target and owned disposable users/ten-row fixtures/testing/cleanup;
prove genuine core nonce/session/capability fences and cleanup without changing
existing application state or wiring. No observer transaction/barrier, admission
write, grants, activation, effects or rollback. Separate site/fixture approval is
required before that work; native barrier and uncertain-write lifecycle remain
later gates.
