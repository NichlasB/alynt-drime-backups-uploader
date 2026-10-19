# V2.3 Observation Origin Retention and Guarded Request Construction Safety Foundation

2026-10-19, approved R3 **repository-only, dormant, isolated API tests only**.
Excluded coherent review baselines: uploader
`fd1d99be0dd35211c38aade64a6c4797f94d904d`, dashboard
`ee4931230ab7090e05a0613314d7949a5da01ecc`, toolkit
`44b6dd27177f0ba7d1d8cc4a8207a7be3b532731`. Review covers the complete seven-file
executable/test delta plus directly reused guards, registration construction,
eligibility, origin, request and privacy contracts, not a later docs-only tail.
Uploader 0.5.25/dashboard 0.1.77 unchanged; not an RC.

## Scope and invariants

- New final `Registration_Observation_Construction` has only zero-argument creation
  and inherited redacted disposition/transfer refusal publicly available. Its two
  private operations are test-reflected only. No origin object, provider, actor,
  clock, connection, writer or continuation can be supplied through a public port.
- Retention calls its OWN unchanged guarded registration constructor against a
  private synthetic captured frame. It revalidates complete eligibility and checks
  the independently minted full mutation nonce before retaining minimal original
  installation/scope/manifest/typed command/historical actor/operation/context DATA.
  The original issuance guards remain intact. No admission/write is attempted;
  retained DATA is not evidence that an attempt or native barrier happened.
- Only primitive original identity data and a non-authorizing immutable origin
  survive retention. No captured row bytes, raw proposal, cookie, roles, database
  object or write-capable handle is retained. Historical and all ten newly issued
  proposal IDs remain unavailable as lookup IDs.
- Read construction requires its own retained original command ID, an exact small
  observe_registration intent and a fresh authorized current administrator. That
  administrator may differ from the immutable historical mutation actor. Current
  installation must still match the original. No current receipt is used to learn
  or replace origin; lost context refuses without reconstruction/reset/remint.
- Separate blog/observed-ID-bound read issuance nonce precedes UUID/nonce creation;
  a fresh lookup ID is generated once, then a fresh <=300-second typed read envelope
  and separate full receipt nonce. Both read nonce guards are independently renewed
  around callbacks with original actor/cookie/core-frame checks. Full query binding
  reuses the unchanged strict pure origin/request contracts. The mutation window is
  neither extended nor reused as read authorization.
- Each construction phase has a real monotonic <10-second budget; this is not a
  native capture-to-COMMIT or cross-phase transaction budget. UTC cannot decrease.
  Every read attempt consumes and clears origin, IDs and transient core facts on
  success/refusal/throw. Retention and observation latches cannot reset or revive a
  writer. Re-entry poisons the outer phase, not just the nested call.
- A small private window trait installs owned bulk-autoload short-circuit and exact
  role-query-only options fences before authentication callbacks, then removes only
  its own callbacks. Options-table token detection also rejects comment/newline SQL
  variants. The role SQL is a fixed comparison string built from already validated
  ASCII core identifiers, never executed here or constructed from posted values.
  Existing core role-view restoration is reused; previous error handler restored.
- A private positive read blueprint remains candidate_only; storage, dispatch,
  execution, barrier_verified and closure_proven are false. There is no observation
  reader, result classification, native transaction, ingress closure or retry grant.

Existing production bootstrap, native owners/readers/admission, parsers, guards and
codecs are unchanged. No endpoints/hooks wired into the running plugin. New source
is explicitly loaded only by tests. No actual WordPress authentication/users/UUIDs,
site/LocalWP/Novamira/browser, options/cron/SQL/native handles/database/fixture,
application-state, schema/version/dependency, grants/activation/effects/rollback,
package/release/tag/deploy operations. Site confirmation and Novamira check N/A:
repositories only, no Plugin Tester target accessed.

## Non-vacuous isolated proof

New suite: **16 tests / 618 assertions**, no skips, in two separate-process test
classes and one fixture helper. Related registration/observation/admission/core/
scan/real-writer/native-boundary API cohort: **296 / 4272**, no skips.

Positive tests require own retention and a correctly bound fresh private blueprint,
including a separately authorized observer. Refusals cover missing/malformed/stale
or cross-purpose intent, changed original ID, wrong issuance/full nonce domains,
revoked session/capability/role, changed method/cookie/actor/blog/cache/core object/
table, callback exceptions/re-entry, elapsed phase budget, one-attempt ID collision,
private surface/serialization and complete clearing. Filter inventory and direct
closure calls verify installation/removal/policy at API level only. Assertions about
callback facts occur AFTER the caught callback, so swallowed assertion exceptions
cannot turn a broken check into an expected refusal.

Test shims opt into action-bound nonce maps and a hook inventory; old default nonce
and no-op hook behavior are preserved. No fake writer/storage or authentication
success path is added to production. The initial Patchwork add_filter override was
incompatible with its early test-bootstrap definition; a four-line opt-in inventory
resolved that test-only instrumentation issue without changing production hooks.

Eight deliberate source counterfactuals were assertion-killed and then restored
exactly, comparing the whole source after each control:

| Broken protection | Semantic discriminator |
|---|---|
| Always-unavailable private read | Valid positive must return a bound blueprint |
| Independent full mutation nonce | Valid issuance cannot substitute for the full command guard |
| Independent full read nonce | Valid read issuance cannot substitute for the receipt guard |
| Early original command binding | Stale intent must refuse before authentication |
| Known original/history identity exclusion | Collision refuses after exactly one UUID attempt |
| Re-entry poisoning | Nested refusal cannot leave the outer retention successful |
| Real phase budget | Instrumented elapsed phase cannot return a positive blueprint |
| Options query fence | Forbidden/comment/newline statements must be rejected independently of successful read construction |

API doubles do NOT establish genuine WordPress nonce/session/bulk-autoload behavior,
actual protected-read ordering on storage, physical connection ownership, all-ten
locks, zero DML, atomicity/receipt/unknown-ack behavior or delayed-worker closure.
The collision and elapsed-budget tests use explicit reflection instrumentation;
they are not random-generation quality, real timeout or storage proofs. Predecessor
pure tests cover valid history after mutation expiry; this API checkpoint does not
wait for a real mutation deadline or run a native observation. No Database Review
or physical transaction proof claimed.

## Canonical source-scoped reviews

Actual toolkit runner, Feature Light and final Feature Security prompts followed.

Feature scope: dormant own-origin retention and independently guarded read blueprint.
Files reviewed: **seven changed executable/test files**, plus direct dependencies.
Risk: future R3 origin/auth boundary; no public UI/AJAX/REST/DB/cron/file/API handler.
Light review found/fixed one test-quality issue: assertions inside caught callbacks
could be vacuous; facts now asserted outside. WPCS layout/docs fixed. Owner/window
are **269/103 lines**, reuse strict existing construction/guards/eligibility, no
oversized file, generic factory, debug path or meaningful bloat/splitting trigger.
Full Bloat/Structure unnecessary. UI/UX/i18n/output escaping N/A, backend-only.

Final focused Security after fixes/controls/validation: one new dormant fence defect
fixed (keyword-based options matching could miss comment-separated table access).
Now strict table-token/exact role-query policy with permanent isolated regression
coverage and a guard-removal discriminator. Recommended layer for genuine behavior
is separately approved WordPress integration; API tests are not a replacement.
Other confirmed security defects/manual decisions: none. Exact signed grammar is
validated, not lossy sanitized; original authorization/independent nonce/session/
scope checks, private state and no production caller/handle export reviewed.

Toolkit evidence is explicitly source-scoped. No canonical DS3/Database/07A or
historical whole-plugin release-readiness row refreshed. FULL_PRE_RELEASE remains
required for the next coherent RC before packaging/publishing this work.

## Validation and publication

Repository-only checks: new isolated 16/618 and related 296/4272, uploader WPCS
214 files, build, deterministic runner verification/lint, dev-tools three tests,
Composer strict validation/audit and npm moderate audit. Generated files unchanged.
Dashboard unchanged-runtime full local 299/1423, two existing local Sodium skips;
WPCS 138 files, structure/build, Composer strict/audit and npm moderate audit.
Existing uploader PHPCompatibility comma-array deprecation only. No dependencies
changed. Full Linux PHP 7.4/8.3 exact-head CI required for both pushed owners;
toolkit has no CI and its empty checks/status surfaces are N/A, not queued work.
Final pushed heads/CI links reported at handoff without receipt-only docs loops.

## Implementation-plan progress and next gate

The approved own-origin retention / guarded construction milestone is executable
and non-vacuously API-tested. This advances the observation plan, not maintenance
churn, native admission integration, storage observation or production activation.

Recommended next gated slice: **Plugin Tester local-only V2.3 Observation Origin
Retention and Guarded Construction Owned Authentication Proof**. Approve exact
local target and owned disposable users/ten-row fixture creation/testing/cleanup;
prove genuine core guard/nonce/autoload ordering and private guarded capture-to-own
retention/read construction, without invoking admission writes or implementing the
native observer transaction. Keep existing application state, production wiring,
grants, activation, effects and rollback untouched. Actual native observation and
durable ingress closure remain later distinct boundaries, not implied permission.
