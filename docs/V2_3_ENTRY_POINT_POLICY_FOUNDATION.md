# V2.3 Cadence Authority Entry-Point Policy Foundation

Approved local-only on 2026-10-14, including focused reviews. Baselines: uploader `b62da2c`, dashboard `66ababf`. The canonical activation/cohabitation design is dashboard `docs/V2_3_CADENCE_AUTHORITY_ACTIVATION_DESIGN.md`. This slice implements only a dormant pure classifier, not mode activation or rollback readiness.

## Implementation And Trust Contract

`includes/class-cadence-authority-entry-point-policy.php` defines a final stateless class with `evaluate(entry_point, snapshot = null, now = null)`. It has no state reader, callback, hook, constructor side effect, schema write, scheduler or ambient clock. **It is not added to the production include list.** Tests explicitly load the file; production factories, Cron, scan/queue facade, CLI, administrator handlers and remote worker remain unchanged.

The exact private version-1 policy projection is a synthetic internal interface, **not** a stored option/schema, remote payload, signed intent or public status contract. An eventual separately reviewed trusted local adapter must establish provenance and build it from authoritative state. Passing an exact-shaped array cannot authenticate consent or authorize an action.

| Projection fields | Required interpretation |
| --- | --- |
| `policy_version`, `mode`, `lifecycle` | Integer 1; exact legacy/client-owned mode; exact legacy/preparing/active/draining/disabled lifecycle. Mode and lifecycle must agree. |
| `ever_activated`, `handoff_verified`, `consent`, `auto_scan_enabled`, `model_enabled` | Literal booleans. Model enablement is not administrator consent. Handoff is a trusted adapter assertion only, not a proof manufactured here. |
| `permission_generation`, `current_permission_generation` | Nonnegative integers. Active observations require a positive matching generation; never-activated legacy requires zero, while verified historical handoff requires a positive matching generation. No reset or generation write occurs. |
| `binding`, `current_binding` | Empty or exact lowercase 64-hex strings; active admission requires nonempty matching local bindings. They are neither exported nor accepted as authorization. |
| `pending` | Exact none/claimed/started/recovery_required projection. Any unfinished work blocks scan/requeue/legacy classification; an expired lease is not represented as cleared uncertainty. The future adapter must summarize both dispatch and journals conservatively. |
| `next_due_at`, `observed_at`, `expires_at` | Nonnegative UTC integers, positive observation and expiry strictly after observation with maximum 300-second window. Explicit positive integer `now` must be at/beyond observation and strictly before expiry. Active due must be positive; legacy due must be zero. |

Reject missing/extra keys, unknown versions/enums, arrays/objects where scalars belong, coercible numeric strings, contradictory mode/lifecycle, malformed bindings, invalid clocks and oversized freshness windows. There is no decoding, normalization, repair, storage initialization or inferred legacy fallback. Work is bounded by 16 exact fields and fixed entry-point vocabularies; large/wrong-shaped values are rejected without private-state traversal.

## Policy Matrix And Output

Every result has exactly `disposition`, `code`, `dispatch_supported: false`, and `execution_supported: false`. No policy, binding, generation, owner, path, signature, exception or request is echoed. Dispositions say which additional policy is required, **not** that work is allowed.

| Observation | Scan/queue disposition |
| --- | --- |
| Absent, invalid, stale or uncertain | Blocked; never infer legacy. |
| Explicit never-activated legacy / verified historical handoff | `legacy_policy_required`; all existing capability, nonce, signed-action and queue rules remain required. Scheduled scan alone also requires automatic scanning enabled, preserving manual legacy behavior. |
| Preparing, draining or disabled authority | Blocked, including manual/direct/CLI/remote scans and requeue; no legacy fallback. |
| Active, valid local context, due scheduled scan | `guarded_dispatch_required`; no owner/start is granted. The atomic dispatcher must still acquire a fresh claim, persist start and recheck ownership/context. |
| Active but not-due scheduled scan | Blocked. Exact due boundary is admitted only to the next guard, not executed. |
| Active administrator/CLI scan or run/remote/direct scan, raw queue write, legacy Schedule Apply | Blocked as unsupported entry point. Do not route these into a scheduled claim or turn a rejected CLI run into uploads. No real handler is altered by this slice. |
| Active failed-item requeue, otherwise valid and without uncertainty | `queue_policy_required`; independent existing authorization, item validation and persistence remain required. This classification is independent of due time, not a new scan. |
| Recognized upload-next or upload-abort | Neutral `outside_scope` / `existing_upload_policy_required`, even when policy is unavailable. This neither grants nor blocks upload, multipart abort, retries or retention; existing worker policy is independent. |
| Reserved rollback | Always blocked, regardless of policy or lifecycle. |

This is a **pre-admission** classifier only. After the dispatcher creates its own claim/start, `pending` legitimately changes. Do not inject this result as its post-start context reader or re-run it to cancel an owned callback: existing exact owner/hash/revision/due/lease checks govern that different boundary. A stale classification cannot fence later context changes or queue effects. Future adapters and actual entry-point integration require separate approval and proof.

## Focused Review Scope And Findings

The user approved the feature Light/Security sequence plus scoped `03`, `07`, `07A` review perspectives. These are **source-scoped authority reviews**, not the full plugin pre-release suite or active-mode permission. No UI, forms, assets, REST contract, real database or site is changed; UI/UX review is not applicable. New source remains below the 300-line threshold, with cohesive small tests/support; no bloat/splitting workflow is justified.

Light/Security boundary inspection covers the new classifier plus prior authority store, validation/mutations/dispatch traits, evidence producer/context/inspection, guarded dispatcher, CAS dependency, and relevant Cron/bootstrap/factory call sites (13 runtime boundary files), plus the three new test/support files. Existing read-only evidence and callback/state regressions remain compatibility checks. Prior prototype SQL/process proof is historical evidence, **not rerun or expanded here**. Shared WP-Cron interception is not installed.

### Scoped Phase 1 Inventory

| ID | Risk/finding | Layer and disposition |
| --- | --- | --- |
| P1 | Missing/unknown/corrupt policy or self-reported execution fields could accidentally imply legacy/authority permission. | High admission risk: strict rejection plus exact false-flag/output assertions; pure unit. |
| P2 | Disable/drain or incomplete historical handoff could permit scan/requeue fallback. | High policy risk: lifecycle/entry-point matrix plus contradictory handoff/generation cases; pure unit. |
| P3 | Revoked consent, changed binding/generation, pending work or exact expiry could leave stale admission. | High policy risk: injected deterministic negative/time-boundary tests; pure unit. No claim to fence a real race. |
| P4 | Global automatic-scan setting could unintentionally block existing legacy manual actions or classify upload as scan. | Compatibility risk: separate scheduled/manual and neutral upload/abort dispositions; pure unit. |
| T1 | Initial definition-exists test proves file presence, not policy behavior. | Weak test found by scoped 07A: replace with exact absent-policy rejection. No assertion is merely removed to make a failing contract green. |
| T2 | Initial due-time fixture decremented observation time but kept expiry, accidentally producing a 301-second window. | Confirmed test defect: remove unintended observation mutation, preserve due/queue assertions and separate freshness-boundary tests. Not a production bug or production red-to-green claim. |
| R1 | Durable consent/handoff provenance, common scan/enqueue admission, context-revocation races, real queue effects and lifecycle/downgrade remain unimplemented. | Known gated runtime requirements, not silently fixed by this classifier. Storage/WordPress/concurrency proof needed before activation. |

Scoped `03` found no silent effectful operation in the classifier: it performs none. Missing/corrupt context produces a stable result without exception/path disclosure or automatic retry. UI/loading/confirmation/network categories are inapplicable to this dormant pure layer. Prior guarded callback recovery retains uncertainty on failed completion/marking; production Cron still has no authority observer, a documented activation gate.

Scoped `07` considered empty/corrupt policy, enum/type/string/numeric/time limits, independent entry points, old evidence, permissions, uncertainty, retries and compatibility. External services, browser interactions, deletion cascades and actual WordPress variations require later integration evidence, not new mock tests or site experiments. Approved Phase 2 strategy is prevent/detect malformed admission with no recovery or replay; R1 remains deferred.

### Scoped 07A Test Inventory And Phase 2

| Test group | Cases / protected contract | Quality/layer |
| --- | --- | --- |
| `CadenceAuthorityPolicyMatrixTest` | 72 explicit lifecycle/entry-point combinations; absent policy, legacy automatic/manual split and due/requeue boundary. | Strong pure unit; exact four-field outputs, false flags and unchanged input; no mocked classifier. |
| `CadenceAuthorityPolicyValidationTest` | Missing/wrong/extra fields, contradictory states, context changes, unfinished work, handoff, invalid clocks, freshness and secret-bearing/unknown input. | Strong pure unit; injected fixed UTC values, exact rejection codes, bounded 10,000-character hostile route, no network/SQL. |
| `Support/CadenceAuthorityPolicyFixture.php` | Minimal synthetic active/legacy/handoff projections and exact-output helper. | Test support only, not a permission provider. Matrix expected outcomes are independent of production constants/algorithm. |
| Existing authority/callback regressions | 90 baseline cases / 449 assertions, including retained uncertainty, ownership and version-1 due-advancement evidence staleness. | Useful at their existing unit/interleaving layer; real-process/SQL history has separate proof limits. No tests removed/consolidated. |

Phase 1 baseline `php vendor/bin/phpunit --do-not-cache-result --filter 'GuardedScan|CronGuarded|CadenceAuthority'` passed 90 tests / 449 assertions. Scoped Phase 2 adds permanent P1-P4 protection, strengthens T1, corrects T2 and preserves all prior tests. Initial feature-definition red result showed missing new functionality only, not a confirmed existing production defect. No mutation/coverage tool was installed or percentages claimed. Final scoped 07A status depends on targeted/full suite results below; final whole-plugin prompt 13 and runtime acceptance remain required before release/activation.

Feature Light Review: no remaining significant non-security issue in the reviewed boundary. Feature Security Review: no confirmed new security defect requiring a production fix; negative behavior has permanent regression coverage. These conclusions do not prove consent authenticity, runtime fencing or whole-plugin release security.

## Verification And Next Gate

Current targeted checks: 157 new tests / 330 assertions; combined authority/callback group 247 tests / 779 assertions, no skips. PHPCS passed for the new file and the full 128-file tree; known nonfatal PHPCompatibility `functionWhitelist` deprecation remains visible. Build, deterministic runner verification/syntax, three dev-tool tests, npm audit (zero vulnerabilities) and Composer audit via the dashboard's available phar passed. Generated assets/runner are unchanged. Standalone PHP, loading only this class with an ABSPATH fixture and no WordPress bootstrap/functions, returned the exact absent-policy blocked result.

Full uploader `php vendor/bin/phpunit --do-not-cache-result` passed **579 tests / 3493 assertions**, four existing Windows skips, in 5:07.247. No failures/errors/risky tests or new skips. The new 157 cases are pure decisions, not added DB/process/HTTP/WordPress claims. Scoped 07A status: **Clean** for the approved automated boundary; real provenance, lifecycle and integration are explicitly outside this proof. No test consolidation, dependency change or existing production defect fix was needed.

Dashboard docs-only validation passed 299 tests / 1423 assertions (two existing Windows skips), PHPCS 138 files, structure, build and both dependency audits. Restore-point `check` confirmed clean uploader source checkpoint `b62da2c` before edits; the initial restricted check misidentified Git and was rerun successfully with repository access. No new archive or site/database backup was created. No LocalWP/Novamira/browser/site/SQL access occurred. Versions remain uploader `0.5.25` and dashboard `0.1.77`; no release/package is warranted for this dormant foundation. New class/test/support sizes are 171/63/101/34 lines. Exact-commit CI on PHP 7.4/8.3 and final push/status are the remaining publication completion gates.

Recommended next gated slice: **local-only V2.3 Cadence Authority Durable Consent and Lifecycle Safety Foundation**. Review and implement a versioned dormant CAS-backed consent/lifecycle extension with migration, monotonic generation, invalidation and uncertainty-preservation tests. This is a new storage/permission boundary requiring explicit approval; no production bootstrap, actual opt-in, real site/database writes, scan/queue/remote-action wiring or rollback execution is implied. Do not replace that meaningful gate with maintenance churn.

## Publication Receipt

Implementation `f5d2313d6f6aeb3db38954b476cd5526bfed0e3e` is pushed to `origin/master`; exact-commit [Quality 38541619475](https://github.com/NichlasB/alynt-drime-backups-uploader/actions/runs/38541619475) passed PHP 7.4/8.3, each 579 tests / 3547 assertions without skips, plus coding standards/runner checks and Node build/dev-tool/audit. Dashboard docs milestone `6a2d75214e95be850c24be3f70787343ad7e2e9e` passed [CI 38541632564](https://github.com/NichlasB/alynt-drime-backups-dashboard/actions/runs/38541632564), each PHP version passing 299 tests / 1430 assertions without skips plus lint/structure/build/audits. Toolkit scoped-review record `99afb9ecbd78aeb71af4dbba2e7fe41d2878e0a3` was pushed and synced; GitHub reports zero configured workflows/commit-status checks, so no toolkit CI success is claimed. Source-boundary checks show no modification to existing uploader production files, constructors/callers, protocol/evidence or bootstrap; dashboard changes are docs only. The approved policy milestone and focused reviews are complete, not release or activation clearance. The next meaningful plan item is the separately approved dormant durable-consent/lifecycle storage foundation.
