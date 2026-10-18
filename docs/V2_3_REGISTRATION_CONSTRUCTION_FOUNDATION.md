# V2.3 Closed Registration Fixed Request Construction and Eligibility Foundation

Approved 2026-10-18, R3 **repository-only, dormant/test-loaded**. Excluded coherent
review bases: uploader `20f78f94b795dc6537d1c5bb464b29da27d78dcd`, dashboard
`1aa763a4af95ff7292541744fd92991cd2ba3e80`, toolkit
`0689677c47de79677b1cbdec30d3d4ffdbec9c5f`. Versions unchanged 0.5.25 / 0.1.77.
Refines [pure contracts](V2_3_CLOSED_REGISTRATION_CONTRACT_FOUNDATION.md) and the
companion dashboard `docs/V2_3_CLOSED_REGISTRATION_RESERVED_INTENT_DESIGN.md`.

## Implemented boundary and explicit limits

Four new includes: `class-registration-request-contract.php`,
`class-registration-request-owner.php`, `trait-registration-eligibility.php`,
`trait-registration-construction.php`. Two new test classes and one fixture helper.
No existing production source, bootstrap, guard, codec, native owner or test changed.

- Strict flat forms validate bounded original and once-unslashed JSON, including
  nonce/key overhead (4096 total / 2048 per string), UTF-8, references, exact field
  sets and canonical decimal magnitude before cast. Four typed purposes have
  independent full-envelope action bindings; issuance uses server blog/purpose,
  separate from registration, reservation and receipt actions. Parsing a form is
  never nonce verification or authorization.
- Zero-argument owner creation is zero IO; no caller actor, clock, provider,
  enablement or raw-frame port. Public issue/consume validate method/form and use
  the unchanged fixed genuine-core guard, then return redacted unavailable. There
  is **no authoritative reader**, no public success/blueprint/UUID/nonce export and
  no reachable native continuation. Constructor/copy/serialization/debug privacy
  follows the existing closed-contract policy. No new hooks/admin/REST/AJAX/CLI.
- A PRIVATE once-attempted construction helper accepts already captured data for
  the future fixed reader. Only isolated test reflection invokes it today. Initial
  genuine-core session/current local manage_options/issuance nonce guards precede
  eligibility and UUID construction. Original actor/cookie/core installation are
  pinned; scope cannot be adopted from posted data or changed mid-request.
- Complete ten-tuple manifest validation uses cheap fixed map-count checks before
  key traversal, all reference/row/aggregate caps and exact current blog/prefix/
  DB_NAME. Lossless settings must already contain site UUID and false auto-scan.
  Safety must be exact schema 8 with COMPLETE valid original/retained chain,
  disabled lifecycle/false consent/null grant and no pending old writer. The new
  schema-2 genesis/manifest/enrollment/generation must agree and have no pending
  operation. Connection grammar and both snapshots/queue/dependencies are checked;
  connection consistency is NOT pairing/enrollment eligibility.
- Legacy chain dependencies must already be explicitly loaded. Missing validators
  refuse; no autoload, incomplete-shape fallback or parallel permissive validator.
  The existing strict model's immutable memory reader is reused, not a writable
  storage adapter; no resulting model/backend handle escapes.
- Outside locks, the private helper obtains real UTC time and calls fixed core
  wp_generate_uuid4 exactly once per ten roles. Reject malformed/colliding values
  against each other, site/genesis and ALL retained/reserved schema-8 IDs; no retry
  or remint after refusal. Full-frame JSON must encode successfully before hashing.
  Every dependency tuple participates in the context; hashes are not provenance.
- Construct the exact private proposal and validate its pure successor/capacity,
  discarding the after bytes. Renew original session/actor/frame/nonce guards before
  nonce creation and after it. Core wp_create_nonce binds the full typed command;
  parse emitted form again and require exact envelope/nonce round-trip. No command
  is consumed, storage is not read/written, and no receipt is manufactured.
- Private construction is non-reentrant and consumed on the first attempted
  inspection, including throws/refusals. No private lifetime is restored/retried.
  Private visibility is not a sandbox against hostile same-process PHP/reflection.
  The private blueprint is data, not a native writer or public transferable token.

Only registration blueprint construction is implemented. Reservation/receipt
grammar is guarded but has no private candidate producer, reader, consume/apply or
recovery integration here. No scheduled authority, grant, activation, effects,
cancellation, rollback or production wiring. Original typed command deadline remains
300 seconds and core guard clocks cannot regress. No native ten-second acceptance,
blocking-IO timeout or physical transaction claim is made by this constructor.

No site was accessed: Plugin Tester/Novamira/SQL/users/sessions/options/cron were
not used. NEW tests are pure data or isolated API doubles, not a genuine core/storage
proof. Existing general QA retains its own isolated tooling/filesystem tests.
Restore workflow Option A/check verified clean uploader master at the base and the
existing external September 20 archive; no archive/backup/restore/branch created.

## Non-vacuity and executed checks

Initial positive RED: missing fixed owner fails before implementation.
Final new `php vendor/bin/phpunit --do-not-cache-result --filter 'RegistrationConstructionTest|RegistrationRequestContractTest'`:
**10 tests / 261 assertions**, no skips. Private positive blueprint construction
must pass; an always-unavailable PRIVATE implementation cannot satisfy the suite.
Public unavailable is intentional, not an artificial auth/storage success path.

Four temporary controls were each ASSERTION-killed and restored: initial auth guard
removed (identity PRNG position changes before denied auth); full schema-8 validator
removed (bad grant/chain candidate admitted); post-nonce fresh auth removed (actor
revocation admitted); private builder always false (positive fails). Final restored
new suite **10 / 261** (including later clock-independent dependency assertions).
The API-double PRNG discriminator is test-only, not a claim
about genuine WordPress random generation. A first control attempt hit PHPUnit
child-result closure serialization instead of reporting the assertion; not counted.
New fixture teardown releases its own memory graph before child-result reporting;
the corrected behavioral rerun produced the failures above, without test weakening.

Additional positives/negatives cover all ten changed tuple bindings with direct
dependency-digest assertions independent of UTC rollover, exact scope,
valid-but-pending schema-8 rejection, actual retained-history/genesis collisions,
unchanged input frames, original actor and three guards, no nonce before admission,
separate purpose/actions and every normalized field, exact 4096/4097 encoded body,
malformed/stale shapes/overflow/objects/references, post-nonce revocation, throws,
reentrancy, consumed owner and public redaction. No protected query or option API
call occurred in these exercised paths (traps record even caught attempts).

Executed QA on 2026-10-18:

- `php vendor/bin/phpunit --do-not-cache-result --filter 'RegistrationConstructionTest|RegistrationRequestContractTest|ClosedRegistration|FixedCoreRequest|ScanLedgerCheckpoint|ClosedScanEffectLedgerTest|ScanEffectManifestTest|ScanEffectFenceContractTest|ScanTargetCodecTest|ScanObservationContractTest|ScanNativeObserverBoundaryTest|RealClosedWriter|NativeClosedBeginBoundary|NativePairedBoundary'`:
  **250 tests / 2936 assertions**, no skips; isolated API checks, not genuine WP.
- Uploader `npm run lint`: **200 files**, clean; existing dependency-config PHPCS/
  PHPCompatibility functionWhitelist deprecation banner remains. New syntax PHP 7.4.
- `npm run build`, `npm run verify:runner`, `npm run lint:runner`,
  `npm run test:dev-tools` (3 existing tests), `npm audit --audit-level=moderate`,
  `php ../alynt-drime-backups-dashboard/composer.phar validate --strict`,
  `php ../alynt-drime-backups-dashboard/composer.phar audit`: pass, no generated
  changes; zero reported dependency vulnerabilities/advisories.
- Dashboard unchanged-runtime PHPUnit **299 / 1423**, two existing local Sodium
  signing skips. `npm run lint` **138 files**, `npm run check:structure`,
  `npm run build`, `php composer.phar validate --strict`, `php composer.phar audit`,
  `npm audit --audit-level=moderate`: pass; generated assets unchanged.
- `git diff --check` and final source/load-graph review pass. No new production
  require, dependency/version/schema change, updater or release artifact.

## Canonical source-scoped focused reviews

FEATURE_LIGHT_REVIEW_PROMPT.md: feature scope fixed dormant request/eligibility;
seven new PHP files and directly reused guard/grammar/model/load boundaries reviewed.
Risk areas: Other (future authentication/context/data trust); no live/UI/remote/DB
implementation. Issues found/fixed: two test-support gaps: failure reporting above
and context-only assertions could be confounded by UTC rollover; direct independent
dependency assertions now protect every tuple. No test weakened. Additional
mechanical WPCS corrections and bounded-count-before-key traversal completed.
Manual decisions: zero. Four source files 169 / 148 / 100 / 116 total lines;
cohesive protocol/owner/eligibility/construction, no stale diagnostics, generic
framework, meaningful bloat or extra split needed. Bloat workflow unnecessary.
UI/UX N/A backend-only; no translation/HTML output contract added.

Final FEATURE_SECURITY_REVIEW_PROMPT.md after fixes, validation/restored controls:
no outstanding issue within this dormant source scope. Checked strict input/size/
reference/UTF-8/decimal validation, nonce-domain separation, fresh core session and
site-scoped capability ordering, original actor/frame/cookie binding, schema-8
chain/pending/grant and schema-2/genesis/IDs, post-nonce revocation, fail-closed
exception/lifetime policy, no protected reads/writes, public redaction and absent
production wiring. No confirmed defect requires a missing-coverage 07A handoff.
These focused workflows are NOT DS3/Database/07A, whole-plugin final security or
release-readiness completion. Toolkit addendum is source-scoped only; historical
whole-plugin rows remain unchanged. FULL_PRE_RELEASE_WORKFLOW_PROMPT.md still
required for the next coherent RC before packaging/publishing.

## Progress, next gate and publication

Implementation-plan progress: fixed construction and full eligibility are now
executable dormant code with positive and guard-removal evidence, not generic
maintenance. Actual WordPress auth/nonce/UUID semantics, authoritative protected
read-before/after ordering, native connection/lock/commit/receipt/resolution,
reservation producer, deployment and physical execution remain unproved/disabled.
Earlier genuine core/native results are historical, not rerun for this foundation.

Recommended next gated slice: **Plugin Tester local-only V2.3 Closed Registration
Construction and Eligibility Owned Proof**, genuine core authorization/nonce/UUID
and isolated owned-frame proof, with separately confirmed exact target and disposable
fixture user/storage creation/cleanup. Keep existing application state, production
wiring, grants, activation, native apply/effects/cancellation/rollback untouched.
No production reader or writer should be enabled by that proof.

Commits/pushes and exact-head CI are verified at handoff. No package/release/tag/deploy.
