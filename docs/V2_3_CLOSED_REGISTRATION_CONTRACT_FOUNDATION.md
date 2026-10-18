# V2.3 Closed Registration and Reserved Intent Contract Safety Foundation

Approved 2026-10-18, R3 **repository-only, new tests entirely in-memory**.
Explicit excluded review bases: uploader
`1c0dc5b80ffcfbc9b556c57d73d2d3cfa18348cb`, dashboard
`156e762f20f119f02a83866684c9835ddfcfefd1`, toolkit
`dc5bc89a507494217ee934150c98edcbdb01181c`. Version remains 0.5.25 / 0.1.77.
The companion dashboard owns
`docs/V2_3_CLOSED_REGISTRATION_RESERVED_INTENT_DESIGN.md`. This implements its
closed DATA layer, not its authenticated factory, native owner or runtime flow.

## Scope, surfaces and implementation-plan progress

- Four new dormant includes: `class-closed-registration-contract.php`,
  `trait-closed-registration-data.php`, `trait-closed-registration-records.php`,
  `trait-closed-registration-transitions.php`.
- Two new test classes and `tests/Support/ClosedRegistrationFixture.php`.
- Public static pure surface: inspect, registration_context, register,
  reservation_context, reserve, lookup. No providers, UUID/nonce issuance,
  clock reads, WordPress authentication, session, storage/native handles, hooks,
  scheduling, command execution or production loading. No new dependencies.
- Existing schema-1 codecs, observers/checkpoint, bootstrap, safety schema-8
  contracts and native owners remain unchanged. Neither codec imports the other.
- Restore workflow Option A/check, rerun with correct filesystem/Git visibility,
  verified clean master at the uploader base and the existing external September
  20 archive. No new archive, restore, backup or checkpoint branch created.
- No site was targeted/accessed; Plugin Tester, Novamira, SQL, actual options,
  users, sessions and cron were not used. No release/package/tag/deploy.

Implementation-plan progress: closed registration -> one fixed WPvivid intent ->
separate historical candidate lookup now have executable bounded pure contracts.
Actual trusted admission/construction, full-frame eligibility and native execution
are NOT implemented by this milestone. This is not maintenance-only progress.

## Normative contract refinements discovered during implementation

1. **Reconstructable context**: the proposed operation did not retain the original
   dependency digest used in its registration context. Added exact field
   `registration_dependency_digest` immediately after `context_digest`. It is a
   64-character private data fingerprint, not raw rows, session data or provenance.
   Recompute registration context from scope, fixed manifest, original command
   excluding its circular context field, all ten reserved identities, actor label
   and this digest. Also validate the separately domain-separated issued-command
   digest. A matching digest still proves consistency only.
2. **Pinned genesis**: pure scope has exact ordered keys `site_uuid`,
   `enrollment_binding`, `authority_generation`, `ledger_id`, `origin_created_at`.
   Both origin ID and creation time participate in context binding. No genesis
   constructor, automatic provisioning, migration, restore adoption or reset API.
3. **Derivable reservation**: reservation reuses the original issued/expires times,
   once-reserved WPvivid command ID and registration revision; only purpose,
   confirmation, operation ID and fresh dependency/candidate context differ. This
   makes its exact envelope/receipt reconstructable without adding stored nonce
   bytes or a second arbitrary request record. Original mutation expiry is strict
   half-open and never refreshed. Actual nonce/auth boundaries remain unimplemented.
4. **Narrow supported state**: schema 2 accepts only empty, one registered, or one
   intent-reserved operation. No terminal protocol exists, so a second operation,
   imported terminal history, cancellation, unresolved marker or effect receipt is
   rejected. The design's 16-operation ceiling is not claimed as supported history.
5. **Capacity**: retain 131072 root bytes, 4096 key/value nodes after the unchanged
   shared decoder, depth 15, 4096 header, 6144 operation, 1024 receipt limits.
   Reserve 1536 operation bytes at registration for the supported three 64-byte
   intent hashes, maximum bookkeeping receipt and syntax; retain 16384 closure plus
   6144 incoming-record root headroom. Worst native integer/time scalar shapes
   pass actual register -> reserve with both receipts within caps. No future
   three-effect/terminal receipt capacity is claimed. Registration also reserves
   revision headroom for BOTH supported transitions, each retaining five increments
   below PHP_INT_MAX; cannot register into an immediately unreservable revision.

All public classifications retain candidate_only=true, storage_supported=false,
dispatch_supported=false, execution_supported=false, retry_supported=false and
authenticated=false. Inspect/lookup export no raw records, IDs or after bytes.
Register/reserve return private successor JSON DATA, never a handle or admission
certificate. Their actor/frame/UUID inputs are synthetic labels, not trusted ports
that a future native owner may expose to callers.

Lookup validates fresh typed read-purpose data independently of expired mutation,
exact scope/genesis and expected historical context. Purpose mismatch is
domain_conflict. Absence is **not_observed_in_candidate**, deliberately NOT a native
barrier, no-effect, rollback, retry, cancellation or quiescence result. No public
or private flag permits these actions. No search-latest/compensation/remint path.

## Source-scoped canonical focused reviews

### FEATURE_LIGHT_REVIEW_PROMPT.md

Feature scope: dormant pure closed registration/reservation/lookup contracts.
Files reviewed: seven changed PHP files, directly related unchanged schema-1,
bounded decoder/manifest/target conventions and production load graph.
Risk areas: Other (untrusted bounded data and prospective trust contract), no UI,
AJAX/REST, database, cron, filesystem or third-party API implementation.
Issues found: 2 design/edge-case gaps; issues fixed: 2; manual decisions: 0.

- Data integrity/context: missing retained registration dependency prevented
  reconstruction. Fixed by the exact bounded field above; permanent tamper tests.
- Edge case/recovery: a one-increment-only revision check could register at a
  revision that could not subsequently reserve. Fixed by two-transition headroom;
  max-7 positive through both transitions / max-6 registration negative.
- Style fixes applied only to the new files; no old tests or source rewritten.
- Four runtime files measure 134 / 147 / 196 / 142 total lines, with cohesive
  facade/data/record/transition responsibilities. No debugging, stale paths,
  duplicated framework or unnecessary additional splits identified.
  FEATURE_BLOAT_AND_STRUCTURE_REVIEW_PROMPT.md is not needed for this boundary.
- UI/UX, translations, output escaping, request nonces, protected reads and runtime
  admission are N/A to this pure backend layer, not verified runtime behavior.
- Light review complete source-scoped after fixes; not full pre-release review.

### Final FEATURE_SECURITY_REVIEW_PROMPT.md

Final source-scoped review after fixes, lint, targeted regression and restored
controls: **no outstanding source security finding**. Strict ordered fields and
types, domain/version/genesis/actor/identity/receipt binding, failed-JSON refusal,
duplicate/canonical JSON checks, UTF-8/byte/node/depth caps, reference/object/float
rejection, overflow-before-arithmetic and fixed snapshot grammar examined.
No sanitizing/coercion replaces validation. No input superglobals, credential
handling, SQL, auth assertions, nonce creation/verification, native ports,
unserialization callbacks, logging, HTML output or production entry point added.
No confirmed security defect remains requiring a 07A regression handoff; the
pure-model regressions below permanently cover the reviewed behavioral risks.
Not prompt 13, whole-plugin security/readiness, Database Review, or real-core proof.
Canonical checklist evidence is a separate source-scoped addendum only.

## Non-vacuity and executed validation

- Initial RED: required positive test fails when the contract does not exist.
- New GREEN: `php vendor/bin/phpunit --do-not-cache-result --filter ClosedRegistration`:
  **10 tests / 168 assertions**, no skips.
- Five temporary controls each produced an assertion failure and were immediately
  restored: duplicate identity guard removal; expiry guard removal; missing receipt
  falsely classified as present; effect receipt refusal removed; always-false
  registration. Restored full new suite **10 / 168**, no source mutation retained.
  These are pure behavioral controls, not native/core/physical transaction proof.
- New positives include changed and no-change snapshot candidates, exact historical
  receipt consistency after mutation expiry, all-false support flags, untouched
  non-WPvivid slots/original receipt, capacity/extreme scalars and immutable input.
  Negatives cover stale scope/genesis/revisions/intent, collisions, wrong domains/
  actor/purpose, malformed/duplicate/escaped JSON, corrupted receipts, references,
  objects without callbacks, invalid UTF-8, byte/node/depth/overflow and replay.
- `php vendor/bin/phpunit --do-not-cache-result --filter 'ClosedRegistration|ScanLedgerCheckpoint|ClosedScanEffectLedgerTest|ScanEffectManifestTest|ScanEffectFenceContractTest|ScanTargetCodecTest|ScanObservationContractTest|ScanNativeObserverBoundaryTest|RealClosedWriter|NativeClosedBeginBoundary|NativePairedBoundary'`:
  **156 tests / 2083 assertions**, no skips. Existing API tests are isolated/stubbed,
  not newly executed genuine WordPress authentication or native proof.
- Uploader `npm run lint`: **196 files**, passes; old PHPCS/PHPCompatibility
  functionWhitelist dependency-config deprecation remains. New files individually
  pass WPCS/PHPCompatibility. No minimum-version changes; PHP 7.4 syntax retained.
- `npm run build`, `npm run verify:runner`, `npm run lint:runner`,
  `npm run test:dev-tools` (3 existing tests), `npm audit --audit-level=moderate`,
  `php ../alynt-drime-backups-dashboard/composer.phar validate --strict`,
  `php ../alynt-drime-backups-dashboard/composer.phar audit`: pass. No generated
  runner/asset changes; zero reported npm vulnerabilities/Composer advisories.
- Dashboard unchanged-runtime QA: PHPUnit **299 / 1423**, two existing local
  Sodium-unavailable skips; lint **138 files**, structure/build, Composer strict
  validate/audit and npm moderate audit pass. Linux exact-head CI must verify both
  signing tests as well as the rest of the suite.

No actual UUID generation, authentication/nonce/session exercise, protected-read
ordering, schema-8 eligibility/history-ID binding to real data, native lock/CAS/
acknowledgement, transaction, concurrent writer, lost-wire, cleanup, terminal/effect
receipt or scan-stop proof ran here. No native proof harness was invoked.
Existing general QA/CI retains its own isolated tooling/filesystem tests; only
the NEW contract fixtures/proofs are exclusively in-memory, not a site/storage test.
FULL_PRE_RELEASE_WORKFLOW_PROMPT.md remains required for the next coherent RC
before packaging/publishing; this focused checkpoint does not replace it.

## Next gate and publication

Recommended next gated slice: **local-only V2.3 Closed Registration Fixed Request
Construction and Eligibility Safety Foundation**. Bind the pure candidates to a
dormant fixed construction boundary, complete schema-8 disabled/consent/grant and
history-ID checks, exact manifest/genesis/frame and genuine-core authorization
requirements. Keep storage writes, production wiring, grants, activation, effects,
cancellation and rollback disabled; genuine Plugin Tester proof needs a separately
confirmed target/fixture scope. This is a new authentication/eligibility boundary,
not permission to turn the pure actor/digest labels into trusted authority.

Publication and exact-head CI results are recorded after execution; no release.
