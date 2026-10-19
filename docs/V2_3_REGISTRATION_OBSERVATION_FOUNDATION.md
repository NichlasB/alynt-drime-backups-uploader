# V2.3 Registration Observation Origin and Result Safety Foundation

2026-10-19, approved R3 **repository-only, dormant, in-memory new tests only**.
Excluded review bases: uploader `b0461eade2f11f67ece7e7370a3933f9151adac1`,
dashboard `d8881745290e74a177a3ac840f76e548ee6e5a2a`, toolkit
`3f015ca1dbb9471e898d06baf401ef12a4f665e9`. Scope is all **nine new executable/
test/helper files** plus direct dependencies, not a later documentation tail.
Versions remain uploader 0.5.25/dashboard 0.1.77, not an RC.

Dashboard owns `docs/V2_3_REGISTRATION_RECEIPT_OBSERVATION_CLOSURE_DESIGN.md`.
This implements only its origin/read-grammar/candidate-result/simulated-lifetime
layer. It does not retain genuine construction context or implement observation IO.

## Implemented scope and ownership

- `trait-registration-observation-data.php`: shared pure bounded primitive
  traversal, exact ordered data maps, canonical UUID and positive integer syntax.
  Before JSON allocation: **256 key/value nodes, depth six, 4096 string bytes**;
  after encoding: **4096 bytes**. Objects/resources/floats/references/invalid UTF-8
  refuse. These new graph bounds do not widen old request/ledger/parser bounds.
- `class-registration-observation-origin.php`: final immutable DATA factory,
  exact domain/installation/scope/manifest/typed original register command/actor/
  operation/context binding. DB_NAME is compared as an existing constant label,
  not read from a site/database. Existing mutation grammar validates exact typed
  envelope equality without issuing/verifying a nonce. Placeholder nonce is only
  a syntax adapter to that pure parser, not a fabricated authentication success.
- Origin `matches` compares bounded immutable data; `accepts` binds the small/full
  read forms to original site/command/identity and fresh synthetic read time.
  `observe` additionally requires exact original current installation/scope, then
  reuses unchanged complete strict schema-2 history lookup. A matching receipt
  must match the entire original command, operation and historical actor, not a
  context learned from that same receipt. Self-consistent wrong historical labels
  yield domain_conflict; a replaced scope is unavailable rather than adopted.
- Valid pending registration remains observable after mutation expiry and after a
  retained valid reservation. No registration empty-slot predicate is reused or
  weakened globally. Current codec has no terminal/superseded history positive.
  All results stay candidate DATA: not_observed_in_candidate, barrier_verified=false,
  closure_proven=false, authenticated=false and retry/storage/dispatch/execution
  unsupported. No caller flag can promote absence to not_observed_at_barrier.
- `class-registration-observation-request.php`: separate small read-only purpose
  grammar, raw/once-unslashed encoded 4096 cap and 2048-per-value bound; deterministic
  read-issuance action hashes `[blog, observe_registration, observed_command_id]`
  under the new observe_issue_v2 prefix. Reuses unchanged full receipt grammar;
  rejects observe_reservation in this first origin lane, mismatched IDs/site,
  stale or malformed read time and known identity collisions. No nonce/UUID/core
  authentication function is called; strings/actions do not prove authorization.
- `class-registration-observation-lifetime.php`: zero-argument/zero-IO factory,
  private stage/finish only test-reflected. Internally creates its own pure origin,
  never imports an object/provider. Attempt consumed before input checks; malformed,
  premature, repeated and failed comparison cannot reopen. Exact original small/
  full forms, original synthetic monotonic start, nondecreasing clocks and <10s
  acceptance bound are retained. All facts cleared before a positive or refusal.
- Existing privacy trait keeps objects noncloneable/nonserializable and debug
  output redacted. No raw data getter, native continuation, writer/barrier import,
  actual actor or clock port added. Private PHP visibility is not a sandbox against
  privileged same-process reflection or SQL.

Existing source, guards, construction/admission/readers, codecs, bootstrap and
production wiring are unchanged. Only new tests explicitly load this foundation.
No actual WordPress bootstrap/authentication/UUID issuance, site/LocalWP/Novamira/
browser/database/native handle/transaction/storage/fixture/user/options/cron,
schema/version/dependency, grants/activation/effects/closure migration/rollback,
package/release/tag/deploy operation. Target confirmation/Novamira are N/A because
this task accesses repositories only, not Plugin Tester or another site.

## Non-vacuous tests and controls

Initial RED: ten assertion failures for missing approved origin, before source.
Final new suite: **15 tests / 285 assertions**, no skips. New tests/helper are
`RegistrationObservationOriginTest`, `RegistrationObservationRequestTest`,
`RegistrationObservationLifetimeTest`, `RegistrationObservationLimitsTest` and
`Support/RegistrationObservationFixture.php`.

Positives require exact registered_closed from valid pending and reserved history,
fresh read after mutation expiry, final valid synthetic budget, immutable copies
and cleared lifetime. Absence stays candidate-only. Coverage includes replaced
installation/genesis (including an independently valid replacement ledger), jointly
substituted small/full lookup IDs, wrong historical actor/operation/full command,
nonce form substitution, read-purpose separation/blog binding, retained identity
collisions, malformed/aliased/unknown/overflow/UTF-8/depth/node/encoded-byte inputs,
every nested origin reference, boundary half-open deadlines and original clocks,
object import/refusal/reentry lifetime, privacy and absent production loading.

The test-only primitive probe isolates traversal limits, not a valid origin factory
or native profile. Its 127-versus-128 element node and six-versus-seven depth pairs
and escaped encoded-byte pair avoid relying only on shape rejection. A php://memory
resource fixture is in-memory only. No actual authentication/storage proof claimed.

Ten distinct deliberate source counterfactuals caused semantic assertion failures,
not syntax or mock-count errors, then were exactly restored with whole-source checks:

| Broken behavior | Required discriminator |
|---|---|
| Always-unavailable result | Positive pending-history result must be an array/classified registered_closed |
| Original installation equality | Valid history cannot be observed under changed installation labels |
| Original genesis equality | Independently valid replacement genesis/ledger must still refuse |
| Historical actor equality | Wrong original actor is domain_conflict, not registered_closed |
| Original observed-command equality | Matching substituted small/full IDs cannot replace retained original ID |
| Read purpose restriction | Existing generic observe_reservation grammar cannot enter this origin lane |
| Original small/full form equality | Changed nonce forms cannot renew the staged lifetime |
| Original monotonic start equality | A new start cannot reset elapsed acceptance |
| Consumed staging guard | Repeated stage cannot reopen or replace original facts |
| Blog-bound issuance digest | Different blogs require distinct read-issuance action data |

The always-unavailable control runs its direct positive test to avoid downstream
false-result dereference errors obscuring the intended assertion. All ten final
controls were assertion-killed; full normal 15/285 rerun passed after restoration.
The primitive probe initially depended on setUp load order; explicit require fixed
the test-only discovery issue. No existing tests weakened/deleted or artificial
success/authentication path added. No confirmed production security defect found.

## Canonical source-scoped reviews

Actual toolkit runner, canonical Feature Light then final Feature Security followed.
Nine new executable/test/helper files and directly reused strict request/history/
privacy/manifest code reviewed against the excluded bases above.

Feature scope: bounded immutable origin, read syntax/query binding and consumed DATA
result lifetime. Risk: future R3 trust boundary, no current UI/AJAX/REST/DB/cron/file
or external-service handler. Significant source issues/manual decisions: **zero**;
test-only load-order issue fixed. New source totals **91/67/156/143 lines** for data/
request/origin/lifetime. Shared small validators serve this stricter graph profile;
existing request/history code reused unchanged. No oversized component, unnecessary
split/debug path or meaningful bloat trigger, so full Bloat/Structure unnecessary.
UI/UX/i18n/output escaping N/A: no UI or user-facing strings/output changed.

Final Security after tests/controls/validation: exact bounded primitives, immutable
origin, full scope/command/actor/operation matching, independent purpose action data,
one-shot refusal/clearing, no private-byte/handle export or production caller verified.
Signed-context DATA is validated exactly, not lossy sanitized. Syntax APIs returning
nonce/envelope DATA are not core authentication. No SQL/file/endpoint/actual auth
surface or confirmed security defect requiring a new regression handoff.

No canonical DS3/Database/07A/whole-plugin security/readiness completion claimed.
Source-scoped toolkit addendum only; historical whole-plugin rows unchanged.
Full pre-release remains required for a coherent RC before packaging/publishing.

## Validation and publication

Executed repository-only on 2026-10-19:

- `php vendor/bin/phpunit --do-not-cache-result --filter RegistrationObservation`:
  **15 / 285**, no skips, after all controls restored.
- Related uploader filter adds `RegistrationObservation` to the predecessor's
  complete registration/admission/fixed-core/scan/real-writer/native-boundary cohort:
  **280 tests / 3654 assertions**, no skips. Full uploader suite required on Linux
  exact-head PHP 7.4/8.3 CI, not falsely claimed run locally.
- `npm run lint`: **212 PHP files**, passed; existing PHPCompatibility comma-array
  functionWhitelist deprecation only. Build, runner verification/lint and dev-tools
  **3 tests** pass; generated runner/assets unchanged.
- Uploader Composer strict validate/audit via companion composer.phar and npm
  moderate audit pass, no reported advisories/vulnerabilities, dependencies unchanged.
- Dashboard full local **299 / 1423**, two existing Sodium signing skips; WPCS
  **138**, structure/build, Composer strict validate/audit and npm moderate audit
  pass. Exact-head CI must cover both signing tests. No generated output changed.
- Source safety: clean/synced excluded heads verified before edits; changes are
  recoverable in Git. No source/site backup/archive/restore operation performed.

Final diff/whitespace, pushed heads/remote synchronization and exact-head CI for
uploader/dashboard verified at handoff without receipt-only docs commit loops.
Toolkit has no configured CI; its exact head and empty check/status surfaces are
verified as N/A, not a queued combined pending status.

## Implementation-plan progress and next gate

Immutable origin/read-intent/candidate-result and consumed lifetime are now executable
and non-vacuously tested, not maintenance-only work. A self-consistent synthetic
origin does NOT prove genuine core construction/retention. In-memory clock labels
are not timers; positive candidate lookup is not a native barrier, zero-DML proof,
physical receipt, delayed-worker fence, closure, auth-valid-at-COMMIT or quiescence.

Recommended next gated slice: **local-only V2.3 Observation Origin Retention and
Guarded Request Construction Safety Foundation**, dormant/test-only integration
with isolated API doubles. Establish fixed private retention from genuine-construction
requirements before an uncertain attempt and independent read-purpose construction,
without accepting caller origin objects or reviving the consumed writer. Actual
WordPress authentication/native storage/fixture proof requires a later separately
confirmed Plugin Tester scope. Keep production wiring, closure migration/writes,
grants/activation/effects/rollback disabled; approve the new origin/auth integration
boundary before implementing it.
