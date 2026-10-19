# V2.3 Registration Observation Private Handoff and Barrier Result Safety Foundation

Approved 2026-10-19, R3 **repository-only/dormant, new tests in-memory only**.
Excluded review baselines: uploader `d6318fa547f77754976a0fe813b9d251a28d1576`,
dashboard `91c51c41bfac0a1739a5081525ca062d5e655826`. Review the entire feature
delta from these commits, not a later receipt. Dashboard owns
`docs/V2_3_REGISTRATION_OBSERVATION_NATIVE_HANDOFF_DESIGN.md`; this record owns the
corresponding executable DATA subset. Versions remain uploader 0.5.25/dashboard
0.1.77. No packaging, release, tag or deployment.

## Scope and delivered behavior

Two new test-loaded classes, three test classes and one reusable fixture:

- `includes/class-registration-observation-handoff.php`: zero-argument factory,
  private stage/claim/finish, consume-before-comparison, removal of staged slot
  before claim, unconditional retained-state clearing before finish validation.
  Own primitive copies bind original origin, both exact forms/nonce bytes,
  ordered issued/reserved ID labels and original monotonic start. Subsequent
  observations cannot regress UTC/monotonic time, renew the ten-second budget or
  outlive the fresh query. Returns only booleans/private candidate DATA.
- `includes/class-registration-observation-candidate.php`: validates all ten
  current tuples, exact original installation/scope, disabled settings, complete
  valid schema-8 chain with null grant/no old writer pending, canonical schema-2
  history, connection/snapshots/queue cross-role codecs and identity domains.
  Accepts valid registration pending and retained first reserved-intent history;
  does not require admission's empty slot or obsolete pre-write frame equality.
  Exact historical command/context/actor/operation remain bound by existing
  origin/lookup contracts, never learned from the candidate receipt.
- `tests/RegistrationObservationCandidateTest.php`,
  `tests/RegistrationObservationHandoffTest.php`,
  `tests/RegistrationObservationHandoffLimitsTest.php` and
  `tests/Support/RegistrationObservationHandoffFixture.php`: complete existing
  in-memory schema chains, valid and invalid candidates, no new core/auth/storage
  shims and no owned site fixture creation.

Candidate classifications remain `registered_closed`, `not_observed_in_candidate`
and `domain_conflict`; invalid candidates return false/unavailable. **No
`not_observed_at_barrier` or native-qualified result is minted.** Every successful
DATA result has `candidate_only=true`, and barrier/closure/retry/authentication/
storage/dispatch/execution flags false. Returned flags, caller booleans or phase
transcripts cannot qualify a barrier. No handles, commands, raw rows, forms,
nonces, actor/session facts or write continuations are returned.

The aggregate handoff has a pre-encoding 512-node/16384-string-byte/depth-8 walk,
plus a 16384 encoded-byte cap. Original origin/form limits remain unchanged.
The ordered synthetic ID list is unique, canonical and at most 128 entries; it
must include the original site/genesis/command/operation IDs and exclude lookup.
This checks **supplied DATA consistency**, not whether a real owner retained its
complete issued set. The future genuine owner must derive that set itself.
References/cycles, objects/resources/floats/callbacks and imported origin objects
are refused before encoding/retention; clone/serialization/debug privacy reuses
the existing closed-contract policy.

No pre-existing source or tests changed. Existing construction-only origin
cleanup, native owners/transactions, production loader/wiring and security guards
are untouched. The new classes are referenced only by each other and their
test fixture; neither is wired into admission or genuine construction.

## Evidence and limits

New pure tests: **23 tests / 1045 assertions**, no skips. Related restored suite:
**322 tests / 5388 assertions**, no skips. Positive registered/reserved/absent
cases prevent an always-unavailable implementation from passing. Conflicts use
valid complete histories against different original DATA; malformed history is
unavailable, not promoted into conflict evidence.

Eleven reversible source controls were each killed by **semantic assertion
failure**, not parser/runtime errors, and exact source text was verified restored
after each control. Restored new and related suites then passed:

| Removed requirement | Semantic regression caught |
|---|---|
| Complete-frame gate | Malformed non-ledger payload is accepted. |
| Disabled-settings requirement | Enabled settings are accepted. |
| Complete schema-8 validation | Tampered provenance chain is accepted. |
| Old writer pending refusal | A valid old-writer pending chain becomes observable. |
| Current cross-domain identity checks | Lookup or registration IDs collide with retained safety identities. |
| Exact claim origin/forms/ID binding | A substituted original context or nonce/ID list is accepted. |
| Original clock start | A renewed start crosses the private handoff. |
| Staged issued-ID collision checks | Lookup reuses a reserved identity. |
| Final exact raw-form comparison | Different nonce bytes are accepted at finish. |
| Reference refusal in aggregate walk | Aliased retained components cross the handoff. |
| Encoded aggregate bound | JSON expansion exceeds the encoded cap despite fitting raw-string budget. |

Consumption/cleanup, last valid half-open time, post-claim regression, all ten
payload codecs, tuple names/autoload/lengths/row identity/order, per-role byte
caps, bounded traversal, invalid UTF-8, expired/malformed reads and privacy have
permanent tests. No assertion failure is swallowed by a broad test catch.

These are **in-memory tests**, not isolated WordPress API integration, genuine
authentication, protected-read ordering, nonce-domain verification, physical
session ownership, native locks/transactions/zero-DML proof, contention, lost-wire
or crash evidence. Nonce strings are modeled immutable syntax, not verified
credentials. Interleaved method traces prove only modeled one-shot refusal, not
real callback or multi-process serialization. There is no real uncertain-admission
origin retention link, ingress closure/drain/quiescence, retry permission,
Database Review or rollback safety claim.

No WordPress/site/LocalWP/Novamira/browser/SQL/bootstrap was accessed. No actual
users/options/cron/storage/credentials or application state were touched; site
target confirmation and Novamira discovery are not applicable. No owned proof
runner was executed. Existing grants, activation, effects and rollback remain
disabled; production wiring is unchanged.

## Canonical source-scoped feature reviews

Read the toolkit router/runner, quick reference, project context and the actual
canonical DS2 prompts. Scope: the six named PHP files plus directly needed
unchanged origin/request/manifest/codec/provenance helpers and affected design/
plan records. No whole-plugin release-readiness row is refreshed.

### FEATURE_LIGHT_REVIEW_PROMPT.md

Feature scope: bounded private handoff and complete pending-aware candidate
classification. Files reviewed: **6 changed PHP files**, plus affected records
and the directly needed unchanged dependencies. Risk area: **Other — dormant
DATA contracts**; no UI/AJAX/REST/DB/cron/file/third-party mutation surface.
Significant non-security issues found/auto-fixed/manual decisions: **0 / 0 / 0**.

Architecture/naming match existing test-loaded contracts; refusal is deliberately
private false, not a user-facing silent failure. All traversals/parsers are
bounded and make no queries. Bounded repeated canonical validation intentionally
reuses existing validators rather than importing a caller certificate or changing
admission's pending policy. Original smaller and aggregate bounds are distinct,
not interchangeable duplicates. No public settings/hooks/routes/strings need UI
or translation changes. Initial PHPCS formatting/doc-comment errors were fixed
before review; no old source was refactored. No UI/UX workflow applies.

### FEATURE_BLOAT_AND_STRUCTURE_REVIEW_PROMPT.md

Phase 1 was read-only. Used the canonical PowerShell helper with explicit
`-BaseRef d6318fa547f77754976a0fe813b9d251a28d1576` for both measurements;
reported scope matched all six new PHP files, **0 oversized**. All classified
`Consolidate`/retain intact after inspection: **no consolidation edit necessary**.
No removal/split/defer candidate, dead/debug fallback or runtime hook registration
was found; direct references are intentionally test-loaded. Phase 2 simple:
validate no edits needed, rerun helper and relevant tests. No Phase 2 edits.

| File (same before/after) | TOTAL | Helper CODE | Limit | Over by |
|---|---:|---:|---:|---:|
| observation candidate class | 139 | 1 | 300 | 0 |
| observation handoff class | 243 | 1 | 300 | 0 |
| candidate test | 77 | 1 | 300 | 0 |
| handoff limits test | 75 | 1 | 300 | 0 |
| handoff test | 67 | 1 | 300 | 0 |
| handoff fixture | 40 | 1 | 300 | 0 |

The helper's CODE=1 values are anomalous under this PowerShell (`-split` uses a
negative count in its code-line routine); do not interpret them as actual code
size or improvement. The required threshold uses TOTAL, which was verified
against the files. No structural decision relies on CODE. Toolkit helper/source
is unchanged; no unrelated toolkit fix or forced file splitting was added.

### FEATURE_SECURITY_REVIEW_PROMPT.md — final focused gate

Completed after routine fixes, controls, restored targeted validation and bloat
review. No confirmed security defect found in this source-scoped foundation;
regression handoff: no confirmed defect requires additional coverage beyond the
permanent new tests. Strict validation, no normalization into authorization,
bounded pre-encoding traversal, complete closed provenance/history, immutable
original facts, consumed private state and non-authorizing output checked.

No raw superglobals, hooks/endpoints, SQL, file/user operations, dangerous
unserialization, output HTML, credential handlers or native handles exist in the
new source. Capability/CSRF/escaping/runtime ownership checks are not replaced by
these models; those genuine integration obligations remain separately gated.
This is not the full DS3 suite, a Database Review, physical proof or final
whole-plugin security/release audit. DS2 feature prompts have no completion rows
in the toolkit checklist; historical numbered/full-release rows remain untouched.
FULL_PRE_RELEASE remains reserved for the next coherent release candidate before
packaging/publishing code containing this work.

## Validation / publication

Source-folder-only restore point (not a site/database backup):
`C:\Users\Captain\Documents\AI Workflows\Toolkits\toolkit-snapshots\alynt-drime-backups-uploader\alynt-drime-backups-uploader-20261019-150909.zip`
and same-stem `.txt` sidecar, clean uploader baseline above, 10567 files,
SHA-256 `C771002108DC34F7323DA078552320F8C7306039EC5EFABAFF7EE597A70EA18D`.
No checkpoint branch, source rollback or site backup was performed.

New/related PHPUnit results above are actual restored results. Existing dashboard
full local tests: **299 / 1423**, two existing Sodium-signing skips; Linux CI must
independently cover them. Both existing Composer strict manifests/audits and npm
moderate-threshold audits pass, no advisories. Uploader PHPCS **217 files**, build,
deterministic runner verification/syntax and developer-tool **3 tests** pass;
existing PHPCS `functionWhitelist` configuration deprecation only. Dashboard
PHPCS **138 files**, structure and build pass. All six new PHP files pass syntax
checks; existing runtime/tests/manifests/lockfiles and generated assets/runner
remain unchanged. Whitespace and relative-link checks pass. Both remote master
heads match the excluded baselines before publication. Exact-pushed-head CI is
required before final handoff; report actual results/run IDs there. No version,
dependency or schema change, package/release/deploy is performed.

## Implementation-plan progress / recommended next gated slice

The approved observation handoff and pending-aware result DATA subset is now
executable and non-vacuously tested; the native barrier is **not implemented**.
This lane reaches the next guarded-construction integration boundary, not more
generic cleanup.

**Recommended next gated slice: local-only V2.3 Guarded Observation Private
Handoff Integration Safety Foundation**. Use isolated API tests to feed the
constructor's own retained origin, independently guarded exact read forms and
original clocks into a fixed private consumed handoff before cleanup; keep
existing construction-only cleanup unchanged. No caller-imported capability,
actual WordPress/site/native/storage operations, native qualification, production
wiring, grants, activation or rollback. This requires a separate explicit
integration approval. Genuine owned native lock/transaction and uncertain-write
lifecycle proofs remain later gates.
