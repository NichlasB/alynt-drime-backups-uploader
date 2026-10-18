# V2.3 Closed Scan-Effect Ledger and Fixed Target Codec Foundation

Date: 2026-10-18. Approved R3 repository-only implementation and focused canonical
reviews. Dormant, explicitly test-loaded, **pure in-memory data only**. Development
source remains 0.5.25; companion dashboard remains 0.1.77. Not a release candidate.

## Explicit review boundary and ownership

Excluded uploader base: `a73f558969af5c397862b03ae38cbb00fbab4fd7`.
Excluded dashboard base: `d2482d07fd0060ec5d2fd9fc3a81266e032e0312`.
Toolkit tracker base: `0f187a49c3832226714a92d4087edc701bad6270`.
Review the complete additions against these bases, not a later receipt commit.
Authority: companion dashboard
`docs/V2_3_NATIVE_SCAN_EFFECT_STORAGE_PARTICIPANT_DESIGN.md`.

Scope: five new include files, three test classes, one synthetic fixture helper,
this owning record and both implementation plans/design cross-reference. Direct
dependencies reviewed: unchanged primitive serialized-settings parser and closed
JSON/plain-data contracts. Existing settings disable codec, schema-8 null grant,
native two-row owners, bootstrap, authentication boundaries and producers/queue
callers are unchanged. Search of symbol/file references finds only the new files
and explicit tests, not production loading, registrations or callers.

Restore workflow Option A/check ran before edits: clean/synced master at the
excluded base, Git checkpoint eligible, existing external September 20 uploader
archive. No new backup, archive, branch, restore or site operation was performed.

## Implemented contracts, not capabilities

- `includes/class-scan-effect-manifest.php`: fixed ten roles/names/caps in design
  lock order. Compare exact synthetic database/prefix/blog and every row's ID,
  name, non-autoload value, octet string and bytes. IDs must be distinct canonical
  positive decimal strings below PHP_INT_MAX; missing/reordered/oversized rows
  refuse. Aggregate cap six MiB. A matching frame is not native observation.
- `includes/class-closed-scan-effect-ledger.php` and
  `includes/trait-closed-scan-effect-records.php`: exact ordered closed-only
  `scan_effect_storage_closed_v1` schema 1 JSON, fixed scope and manifest digest,
  unique operation/command/effect/terminal/resolution identities, at most 16
  retained operations and one nonterminal. Reserved, intent, unresolved, draining
  and terminal shapes cannot silently promote to a grant. Receipt purpose/domain,
  hashes, classifications, bounded distinct revisions and all-three terminal
  receipt digests must agree. No ID allocation, persistence, transitions or pruning.
- Context consistency is SHA-256 of PHP's default `json_encode` of the ordered
  list `[domain, site_uuid, enrollment_binding, operation_id, command_id,
  entry_point, generation, participant_digest, issued_at, expires_at, terminal_id,
  resolution_id, ordered_effect_ids]`. This newly specified closed-data binding
  is **not** authenticated provenance, a signature or the existing request digest.
  Canonical here means exact PHP encoding/field order, not RFC 8785.
- Ledger cap 131072 bytes; header 4096; record 6144; reserved terminal allowance
  16384. Pure `budget_candidate` accepts at most 108544 existing bytes with fewer
  than 16 operations and no pending operation. It is arithmetic over supplied
  counts, not proof of real capacity, registration/admission or a writable store.
- `includes/class-scan-target-bytes.php`: uses the unchanged strict primitive
  parser, then computes spans over already validated immutable bytes. It never
  calls `unserialize`, callbacks, WordPress, filesystem or SQL. The span walker
  does not constitute a second accepting serialization grammar.
- `includes/class-scan-target-codec.php`: fixed two snapshot roles and four queue
  verbs (`append_new`, `prepend_requeue`, `remove_existing`,
  `set_attempts_existing`). Preserve untouched entry bytes and unknown supported
  primitive fields; attempts change only their integer token. Exact failed-record
  path/signature required for requeue. Signature, normalized non-stream path,
  WPvivid ID/name, uploaded/failed and actual active `local_file` duplicates refuse.
  Queue dependencies are data supplied explicitly, never reader providers.

All outputs retain `candidate_only=true`, `storage_supported=false`,
`dispatch_supported=false`, `execution_supported=false`. Ledger/manifest results
are redacted. Codec `after` bytes/spans are **private internal test-loaded data**,
not a public response, executable closure, write handle or receipt of an event.
No factory/provider/native session is exposed. Do not wire these into status output.

## Canonical source-scoped review record

Executed order: Feature Light -> Feature Bloat/Structure -> DS3 03 -> 04 -> 06 ->
07 -> 07A -> final Feature Security after fixes and targeted validation. Governing
runner and actual canonical prompt bodies read; routine approved fixes continued
without an intermediate approval request. These are not whole-plugin reviews.

| Workflow | Phase 1 inventory / disposition | Execution and final outcome |
|---|---|---|
| Feature Light | Five cohesive source files, four test/support files; backend-only typed data and bounds. Inspect callers, error handling and proof-layer claims. | Escalated parser/resource and ledger/dedup risks to the following reviews. No UI/UX workflow applies. |
| Feature Bloat/Structure | Explicit-base helper measures all nine changed PHP files; zero oversized. Shared primitive parser reused, no duplicate accepting grammar, obsolete diagnostics or production coupling. | No updates needed. Keep cohesive files; no arbitrary splitting. Final TOTAL source 138/94/82/258/135, tests/support 69/31/81/32. Helper CODE=1 artifact is not treated as a code-line measurement. |
| 03 Error Handling | Zero silent I/O, loading, confirmation, message, feedback, empty UI or form issues; no network, async, bulk, permission handler or external service in scope. Invalid input returns false, not repair/default permission. | No updates needed. Internal failure is non-authorizing; no public logs/payload disclosure or retry introduced. |
| 04 WP Best Practices | No deprecated calls, hooks, queries, options/transients writes, meta, AJAX/REST, assets or public paths added. Prefixes, ABSPATH, PHP 7.4 preserved. | Mechanical WPCS fixes applied; full lint passes 183 files. Existing PHPCompatibility functionWhitelist configuration deprecation is disclosed, not suppressed. No WordPress API shim offered as runtime proof. |
| 06 Performance | No queries, network, cache, assets, background processing or eager production loading. Byte, node, depth, role/count and proposal caps bound work. Nested dedup at most 64 proposals against bounded 128-record maps. | No updates needed. See resource limits below; no unnecessary caching, dependency, optimization benchmark or native memory claim. |
| 07 Edge Cases | F1 requeue without failed-record provenance; F2 root enrollment relocation; capacity, malformed encoding, pending/terminal ambiguity, duplicate/path/WPvivid and stale tuple cases. | F1/F2 fixed; all mapped to permanent pure regressions in 07A. Real concurrency, WordPress variations/permissions, service/file/deletion/browser behavior are outside this pure boundary, not verified. |
| 07A Adversarial Test-Suite | Baseline 18 tests / 225 assertions after F1, no skips. Narrow pure layer; no mocks of these units. Four added high-value limit/state/resource groups plus F2 regression. No same-layer redundancy justified removal. | Final status Clean, source-scoped: 23 / 259; related 103 / 1231, no skips. Nine bounded assertion-killed/restored controls below. Full pushed-head PHP matrix passes, receipts below. No tests/assertions removed, weakened or skipped; no artificial active/success grant added. |
| Feature Security (final focused gate) | Strict types/keys/encoding/caps instead of lossy sanitation; no request ingress/output/SQL/file effect/authorization handle. Closed data cannot authorize an operation. | No outstanding source-scoped issue after F1/F2. Both defects have permanent regression coverage; actual authentication/nonces/storage remain separate runtime handoffs. This is not prompt 13 or whole-plugin release security. |

### Confirmed defects and red-to-green evidence

**F1 — Medium, new codec requeue contract**, `class-scan-target-codec.php`,
`queue`: missing failed entry or different failed path could still produce a
`prepend_requeue` candidate. Test
`test_requeue_requires_the_exact_failed_record_instead_of_new_discovery` first
assertion-failed on the defective code. Require exact failed signature/path; the
same regression then passed. Updated positive fixture now supplies that actual
synthetic failed record. No authorization inference from matching path/data.

**F2 — High if reused as trusted binding, dormant data validator only**,
`trait-closed-scan-effect-records.php`, `operation`: changing root enrollment plus
supplied scope left a structurally accepted existing record. Test
`test_record_context_cannot_be_relocated_to_a_different_root_enrollment` first
assertion-failed. Enforce the ordered closed context digest defined above; same
test passes. This detects inconsistent relocation, not hostile recomputation or
authenticated provenance. A future native owner must supply trusted scope.

### 07A behavior/risk inventory and controls

All three new classes are pure unit/contract tests with deterministic in-memory
fixtures; existing bootstrap has WordPress shims, **not core authentication**.
Positive outcomes compare exact classification/count/after bytes and false support
flags, not only truthiness or configured mock calls. Fixture hashes set up input;
independent negative alterations and mutation controls test the validator behavior.

| Test group | Protected risk and meaningful assertions | Layer / quality |
|---|---|---|
| `ClosedScanEffectLedgerTest` (10 methods) | Valid empty/reserved/16 terminal histories, context/domain/IDs/revisions, intent/draining/unresolved, no-change/cancel receipt distinction, malformed/corrupt JSON, exact reserve arithmetic. | Pure, strong for data semantics only. No physical terminal/cancel event claimed. |
| `ScanEffectManifestTest` (3) | Correct exact ordered frame succeeds; all ten roles x all five tuple fields independently differ; scope/order/absence/ID reuse/byte caps refuse. | Pure, strong tuple comparison, not native locking/profile proof. |
| `ScanTargetCodecTest` (10) | Both snapshots, four queue verbs and byte preservation; duplicate signatures/paths/WPvivid, independent active/uploaded/failed, strict types/callback-shaped data, parser corruption/depth/nodes/count/byte bounds. | Pure, strong deltas, not path authorization, file eligibility, sink fencing or worker proof. |

Bounded temporary source controls each produced an intended **assertion failure**,
not syntax/bootstrap/environment failure, and were restored using finally blocks:
closed-only guard; enrollment context binding; terminal reserve subtraction; all
terminal receipt digests; exact complete dependency equality; normalized-path/
WPvivid dedup; failed-record requeue; 256-KiB proposal bound; always-unavailable
replacement. The last fails the positive ledger test. No mutation tool installed,
coverage percentage target, retained mutant or synthetic authorization path.

Exact resource discriminators: 108544/108545 bytes reserve; 512/513 snapshot entries;
128/129 queue result; 64/65 proposed additions; 262144/262145 proposal bytes;
oversized ledger/snapshot; nested >15 and node-budget rejection. UTF-8, private
NUL/newline and object-shaped strings remain inert bytes; actual object/reference/
float/duplicate/coercion/trailing tokens reject. No machine exhaustion attempted.

The shared JSON decoder first bounds input to 128 KiB and depth 15 and has its
own 65536-node uniqueness ceiling. The ledger's stricter 4096-node check is **after
decode**, not a 4096-allocation memory proof. Serialized targets use the unchanged
one-MiB / 4096-node / depth-15 parser, plus lower fixed-role and proposal bounds.
No current claim of streaming JSON, native capacity reservation or load profiling.

## Validation and proof limits

Executed locally:

- Focused PHPUnit command below:
  **23 / 259**, no skips; final run 0.066s, 26 MiB.
- Related PHPUnit command below:
  **103 / 1231**, no skips, 1.356s. These remain isolated API tests.
- Uploader `npm run lint`: **183 files**, clean apart from the disclosed existing
  tooling deprecation banner. Build, verify:runner, lint:runner, three Node dev-tool
  tests, npm audit moderate, Composer validate strict/audit pass. Generated assets
  and runner bytes unchanged. Composer invoked using companion composer.phar.
- Dashboard PHPUnit **299 / 1423**, two pre-existing unrelated Windows skips;
  lint **138 files**, structure, build, Composer strict validation/audit and npm
  audit moderate pass. No dashboard source or public contract change.

Exact focused and related commands:

```text
php vendor/bin/phpunit --do-not-cache-result --filter 'ClosedScanEffectLedgerTest|ScanEffectManifestTest|ScanTargetCodecTest'
php vendor/bin/phpunit --do-not-cache-result --filter 'ClosedScanEffectLedgerTest|ScanEffectManifestTest|ScanTargetCodecTest|ScanEffectFenceContractTest|CachePublicationContractTest|SerializedSettings|NativePairedBoundary|NativeClosedBeginBoundary|QueueTest'
```

Full uploader suites run on exact pushed-head Quality CI (PHP 7.4/8.3), not claimed
from the local filtered run. Both passed; source-scoped 07A final status **Clean**,
without a genuine WordPress/native or whole-plugin readiness inference.
No package/release/deploy.

No actual readers, cache calls, storage/fixture/site/user/database operations,
production wiring, credentials, grants, activation, cancellation or rollback.
Core `wp_normalize_path` source was inspected as text for its non-stream subset;
no WordPress bootstrap/API or genuine runtime proof ran. No actual native guard
ordering, purpose-nonce separation, protected-read timing, transaction/lock/CAS,
commit certainty, cache convergence or worker participation can be proved here.
Existing native owners/auth boundaries and their prior proof records are unchanged;
this review does not rerun or refresh them. No Database Review claim.

## Implementation-plan progress and next gate

The open fixed-row/closed-ledger/typed-delta item now has a bounded, non-authorizing
foundation with discriminating tests, not only a design. Safe local implementation
for **this approved milestone** is complete; meaningful native integration changes
category and must not be replaced by maintenance churn.

Recommended next gated slice: **Plugin Tester local-only V2.3 Fixed Scan-Effect
Manifest and Closed Target Observation Proof**. Approve a dormant fixed ten-row
native read-only observer and owned disposable fixture creation/cleanup; verify
raw role/order/scope/metadata/byte refusal and codec observations using the genuine
local database profile, before introducing any new native effect writer. Confirm
the exact LocalWP target/owned fixtures under Site Operations first. Keep existing
application state, production wiring, grants, activation, cancellation and rollback
untouched/disabled. This gate is not approval for scan effects or an active grant.

Full canonical pre-release remains reserved for the next coherent release
candidate, before packaging/publishing any plugin containing this work. Historical
whole-plugin readiness rows are not refreshed by these source-scoped reviews.

## Exact pushed-head CI receipts

Implementation head `83ccb4ff2f849edac0ef722c2c1e17b190833b19` passes
[Quality 39151344560](https://github.com/NichlasB/alynt-drime-backups-uploader/actions/runs/39151344560):
PHP 7.4 and 8.3 each **1426 tests / 12740 assertions**, no skips, failures, warnings,
risky or incomplete tests; Node/build/audit and generated-asset verification pass.
GitHub's Ubuntu-label migration advisory is informational, not a test warning.

Dashboard companion documentation head
`e867c6d256938d4c20a2f4885904fc9c566c1f34` passes
[CI 39151352030](https://github.com/NichlasB/alynt-drime-backups-dashboard/actions/runs/39151352030):
PHP 7.4 and 8.3 each **299 / 1430**, no skips, plus lint/structure/build/manifest/
audits. The two local Windows skips are not hidden by these Linux results.

The toolkit checklist receives a source-scoped addendum only; zero configured
toolkit workflows means CI N/A, not passing. This receipt-only follow-up is pushed
and its own final exact-head CI independently checked at handoff; it does not use
an earlier-head result as a substitute. No source/test changes follow these receipts.
