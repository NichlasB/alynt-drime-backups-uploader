# V2.3 Durable Consent And Lifecycle Safety Foundation

Approved local-only on 2026-10-14, an R3 storage/permission design boundary. Source checkpoints: uploader `ef04476`, dashboard `cef9c75`. This completes the dormant model foundation, not administrator opt-in, lifecycle activation or runtime rollback. The canonical product plan remains dashboard `docs/IMPLEMENTATION_PLAN.md` and its activation/cohabitation design.

## Scope And Ownership

The new final `Cadence_Authority_Lifecycle_Store` and preparation trait extend the existing dedicated exact-byte CAS model with private schema 3. They are **absent from the production bootstrap** and explicitly loaded only by tests. Existing schema-1/2 readers, factories, Cron, scan/queue callers, remote actions, settings, protocol and version-1 evidence are unchanged. Construction and snapshot reads create nothing.

All state writes in this slice use an in-memory adapter. No LocalWP, live site, SQL, actual option, scheduled event, pairing, credential, permission setting or remote service was accessed or changed. Versions remain uploader `0.5.25` and dashboard `0.1.77`; no package, release or deployment is warranted for this dormant foundation.

## Private Schema And Invariants

Schema 3 retains the schema-2 root fields and exact journal/dispatch validation, adding exactly `lifecycle`. It uses the existing dedicated, non-autoloaded safety-option identifier, not a new setting or public wire contract. Reads are bounded to 128 KiB, JSON depth 16 and 50 journal records. The production adapter is not invoked here.

| Lifecycle field | Meaning and accepted domain |
| --- | --- |
| `phase` | `preparing`, `draining`, `disabled` only; no `active`, `legacy` or handoff phase. |
| `consent` | Literal boolean synthetic model choice, not authenticated administrator permission. True requires preparing and a positive permission generation. |
| `permission_generation` | Strict nonnegative integer below PHP_INT_MAX. Every accepted consent/revocation transition increments it, including repeated revocation. No reset or reuse. |
| `history` | `new_model` for absent-row creation; `unknown` for migration. Neither asserts never-activated client history or verified legacy handoff. |
| `changed_at` | Strict positive injected UTC timestamp; mutation rejects invalid clocks and regression. |

Every valid state requires `authority.enabled === false`, including states with recorded synthetic consent. Any unfinished journal or recovery dispatch requires draining with consent false. Draining without unfinished work is rejected rather than silently repaired. Fresh dispatch/reservation is unavailable; an exact old terminal dispatch receipt may be observed but never restarted. This foundation does not introduce a recovery-resolution or draining-to-legacy API.

## Explicit Preparation And Migration

`prepare_lifecycle(binding, cadence, due)` is the only initializer. Supported cadences remain 15 minutes, 30 minutes and hourly. Inputs are strict scalar values and exact local binding hashes. New/schema-1 models require due strictly after the injected clock and within one cadence interval. Schema-2 migration preserves its existing cadence/due exactly, including an already-due value.

Absent rows start preparing with no consent, permission generation zero and disabled authority. Existing schema 3 is idempotently observed only for matching binding/cadence/due and a nonfuture lifecycle timestamp. It never resets consent or counters. Schema-1/2 migration validates the original complete state, blocks **all** unfinished journals or dispatches even after lease expiry, preserves terminal records/native evidence markers verbatim, increments storage generation and schedule revision, and disables authority. Consent is never inferred from a previous enabled flag, succeeded journal or historical receipt.

Missing keys, unknown schemas, malformed JSON, autoloaded data, active-shaped state or context mismatch are rejected without overwrite. Older readers reject schema 3 without dropping its fields. This is not an automatic upgrade/downgrade path; a production rollout would require separately approved compatibility, retention and operational migration decisions.

Preparation uses at most three create/exact-byte-CAS attempts. A lost race reloads the winner: newly unfinished legacy work blocks migration; a completed competing migration/consent transition is observed without being reset. No deletion or replacement of a corrupt row is attempted.

## Consent, Revocation And Rebinding

`record_consent(binding, expected_permission_generation, consent, replacement_binding = null)` requires the exact observed epoch and binding. The inherited bounded update reloads and revalidates them on every CAS attempt. Successful transitions atomically increment permission generation, schedule revision and storage generation with consent/phase/timestamp. Returned integers are model receipts, never execution grants.

Grant records preparing but leaves authority disabled. Revocation records disabled, or draining when uncertainty remains. Replacement binding is allowed only with revocation, in the same CAS; consent is not transferred to the new context. Journals, dispatch identity/phase, evidence, cadence and due are retained. Expiry, restart, rebinding and repeated revocation never erase unfinished work. Old initializer and blind invalidation APIs are rejected on this subclass.

Generation mismatch prevents ABA reuse and stale-grant resurrection after revocation; binding mismatch blocks old-context writes after rebind. Clock/type failures and exhausted counters reject before persistence rather than wrapping or reinitializing. Modeled failed writes return errors without reporting success or partially committing lifecycle state. Three unresolved CAS conflicts return contention, with no unbounded retry.

## Focused Source And Test Reviews

These are source-scoped Light/Security, error-path, edge-case and adversarial test perspectives; **not** a new full-plugin pre-release workflow clearance. No toolkit release-checklist rows are re-certified by this record. UI/accessibility, browser, actual permissions and REST request reviews are inapplicable to this unexposed model; eventual adapters must establish trusted provenance and independent capability/nonce/intent checks.

| Finding | Resolution or retained gate |
| --- | --- |
| New row was initially described as `never` history. | Corrected before publication to `new_model`; tests assert migration retains `unknown`. Row creation cannot prove activation history or handoff. |
| Model consent might accidentally imply activation. | Whole-state validation requires disabled authority; negative fresh-dispatch/reservation tests and unchanged bootstrap/callers protect the boundary. |
| Stale consent or rebind race might resurrect permission. | Exact epoch/binding checks run again after losing CAS; competing revocation and rebind tests exercise real model transformations. |
| Migration could discard completed receipts or clear expired uncertainty. | Preserve terminal journals/native markers/dispatch receipts; reject every unfinished older phase at expiry; exact receipt cannot restart. |
| Partial writes, corruption, counter overflow or contention could reset state. | Error/code and byte-preservation assertions; failed create/read/migration/revoke, strict schema/type/clock/counter limits and bounded retries. |
| In-memory CAS might overstate persistence guarantees. | SQL adapter, two-process concurrency, cache behavior and commit/outcome ambiguity are not proven here. Prior schema-2 proof is not schema-3 certification. |

The memory fixture mocks storage only, not lifecycle decisions or older migration algorithms. Deterministic interleavings execute a second store against the same bytes. Recovery fixtures are synthetic valid rows, not evidence that runtime recovery can reach or resolve them. Existing tests are preserved, no skips or thresholds are added, and no coverage percentage is claimed.

Unexpected exceptions from an injected clock/backend remain internal exceptions under the existing storage contract. They are not authenticated failures, guaranteed no-write outcomes, redacted transport responses or permission to retry; future caller/SQL proof must reconcile uncertain commit outcomes and prevent exception disclosure before any exposure. This slice introduces no UI/HTTP/logging surface and does not claim exception-boundary certification.

## Executed Validation

- `php vendor/bin/phpunit --do-not-cache-result --filter 'CadenceLifecycle|CadenceAuthorityPolicy'`: final tree **212 tests / 586 assertions**, no skips; includes **55 new lifecycle tests / 256 assertions**.
- `php vendor/bin/phpunit --do-not-cache-result --filter 'CadenceLifecycle|CadenceAuthority|GuardedScan|CronGuarded'`: final combined lifecycle/policy/authority/callback regressions **302 tests / 1035 assertions**, no skips.
- Full uploader `php vendor/bin/phpunit --do-not-cache-result`: **633 tests / 3744 assertions**, four existing Windows skips, no failures/errors/risky tests. Discovery preceded the final extra terminal-receipt test; that test passed in the final focused run above. Exact-commit Linux CI must cover all **634** final-tree cases before publication is complete.
- Uploader `php vendor/bin/phpcs`: 130 runtime PHP files passed; existing nonfatal PHPCompatibility `functionWhitelist` deprecation remains visible. `npm run build`, `verify:runner`, `lint:runner`, `test:dev-tools` (three tests), `npm audit --audit-level=high` and Composer audit via the available dashboard phar passed; both audits reported zero vulnerabilities. Generated assets/runner remain unchanged.
- Dashboard docs-only checks: PHPUnit **299 / 1423**, two existing Windows skips; PHPCS (138 files), structure guard, build and npm/Composer audits passed.
- Final source review: new runtime files are 133 and 109 lines, support fixture 39 lines; no dependencies, existing production code, bootstrap, protocol, versions or generated outputs changed. Source restore-point check confirmed clean uploader checkpoint `ef04476` before edits; no site/database backup or new archive was created.

## Implementation-Plan Progress And Next Gate

Durable dormant lifecycle storage, conservative migration and epoch-bound revocation/rebinding are implemented and unit/interleaving-tested. The meaningful next step is **local-only V2.3 Cadence Authority Consent/Lifecycle Isolated Storage Proof**: explicitly owned disposable Plugin Tester storage, real CAS failures and two-process races, preserving actual options/cron. This requires separate database/fixture approval and resolved LocalWP target confirmation. Keep production wiring, actual opt-in, authority activation, schedules, scan/queue effects and rollback execution disabled. Do not substitute maintenance or packaging for this persistence-proof gate.
