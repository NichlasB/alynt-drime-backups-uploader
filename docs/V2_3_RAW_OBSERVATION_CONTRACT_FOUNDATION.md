# V2.3 Fixed Raw Observation and Native-Session Admission Contract Safety Foundation

2026-10-18. Approved R3 **repository-only, dormant, in-memory** foundation.
Implementation base (excluded): `c06f29aa094995eee1bd77ce832154f441a7f711` on master.
The companion dashboard design baseline is
`ae4264d68ece8349e13746ec22855b818d08d791`; its
`docs/V2_3_RAW_OBSERVATION_NATIVE_ADMISSION_DESIGN.md` owns the wider design.
These explicit bases cover this coherent foundation, not a final evidence-only delta.

## Implemented boundary

Four test-loaded runtime-definition files, no production bootstrap changes:

- `includes/class-closed-writer-json-contract.php`: bounded root-object JSON,
  existing plain-data limits, decoded duplicate-member rejection including escaped
  aliases, and shared bounded traversal. No PHP unserialization or callbacks.
- `includes/class-closed-writer-observation-contract.php`: strict synthetic row
  projection, exact fixed names/typed lengths/non-autoload metadata, positive
  canonical bounded row IDs, different safety/target IDs, repeated raw-tuple
  coherence, strict schema 6/8 and primitive settings compatibility. Hashes a
  separately versioned, length-delimited private binding over both exact tuples,
  simulated scope/session and unchanged semantic context v1. Retains hashes only.
- `includes/class-closed-writer-session-contract.php`: one simulated transaction
  lifetime with pinned original command ID, bounded original wall window and
  monotonic millisecond budget. Ordered safety/target locks and pair verification
  precede simulated commit; reconciliation requires both locks and rollback, not
  a target write. Bad/unknown facts poison permanently; unknown COMMIT stays latched
  through close. A late definite acknowledgement records its simulated historical
  fact without refreshing time, issuing admission or making the lifetime reusable.
- `includes/trait-closed-writer-contract-privacy.php`: shared no-clone/no-restoration/
  no-serialization and debug redaction; every disposition remains `candidate_only`
  with storage/dispatch/execution support false.

The observation and session factories are deliberately named `simulate`: caller
metadata, clocks and events are **data**, never trusted providers or native facts.
Their inputs cannot reach the genuine fixed request owner. Existing request
issue/consume results remain exactly unavailable; no fake public success was added.
Observation comparison does not prove authentication, read order, a primary DB,
pre-fetch SQL bounds, cache freshness, a connection or native session identity.
The private binding is not exported or added to a request/receipt/schema/digest v1.
The synthetic session ID is not a mysqli thread ID or a credential.

`confirmed_idle` and `closed` are model labels, not evidence that SQL committed,
rolled back, unlocked or a physical handle closed. `commit_unknown=false` alone
never certifies no effect. Later native construction must establish physical facts
independently and cannot promote this model or a true Boolean into admission.
The model covers one transaction, not the entire durable-begin/atomic-pair flow,
three-comparison retry orchestration, genuine authorization or receipt reconciliation.

No actual row reader, native factory/handle, endpoint/DSN parsing, connection/session
configuration, SQL, state write, fixture storage, application/user/database/cron
operation, production loading, cache invalidation, grant, dispatch, activation,
schedule apply or rollback is introduced or executed. No plugin minimum/version,
dependency, REST/nonce/action/schema contract, package, release or deploy change.
Versions remain uploader 0.5.25 / dashboard 0.1.77.

## Test scope and non-vacuous controls

Four test classes plus `tests/Support/RawObservationFixture.php` load definitions
explicitly; all fixtures are PHP arrays/strings only. No WordPress installation is
bootstrapped and no Plugin Tester target or Novamira/SQL workflow is needed here.

- Positive decoded JSON and schema-6/8 coherent observations must be accepted as
  data. Whitespace, nested sibling keys, escaped string bytes, non-ASCII/NUL settings,
  supported non-autoload variants and row-field ordering have accept controls.
- Duplicate and escaped-alias JSON members, malformed/unsupported JSON values,
  adjacent byte/depth/node bounds and shared array key/value budget have refusals.
- Every second-row tuple component is independently changed; noncanonical/coerced
  IDs/lengths/autoload/values, row recreation, identity mismatch, schema 7 and large
  values refuse. Equal semantic state with different raw safety bytes changes the
  private binding without changing semantic context v1. New session data also
  changes the binding; a positive native capability is never returned.
- Original command/session IDs and clock construction have adjacent typed
  accept/refuse controls. Unknown events/providers/metadata cannot call or coerce
  anything; direct close with COMMIT in flight retains uncertainty. Restoration
  refuses even an otherwise empty crafted object; no public native accessors exist.
- Ordered transaction simulation must advance positively. Missing/reordered locks,
  premature commit, same-session metadata changes, falsey/type-coerced transaction
  flags, wall/monotonic regression, expiry, budget exhaustion and pre-commit
  timestamp movement poison rather than refresh or restart.
- Uncertain commit cannot be cleared by rollback acknowledgement or close;
  reconciliation cannot use pair commit. Copy/serialization refuses and debug
  output omits fixture secrets, database and session IDs.

Initial red: 31 tests / 108 assertions / 31 expected missing-definition failures.
Initial implemented checkpoint: **34 tests / 368 assertions**; final four-class
foundation suite: **38 / 481**, no skips. Combined
`RawObservationContract|ClosedWriter(JsonContract|SessionContract|ContractBoundary)|
FixedCoreRequest|RealClosedWriter|SerializedSettings`: **248 / 2313**, no skips.
These are isolated API/data tests, not the prior genuine-core 266-check proof or
schema-7 native fixture proof. Neither prior proof is rerun or promoted here.

Controlled guard-removal mutations, each isolated and restored afterward:

| Mutant | Executed permanent tests | Result |
| --- | --- | --- |
| Remove decoded-key duplicate check | JSON contract: 21 tests / 50 assertions | Five expected failures, including escaped and nested aliases. |
| Remove second-observation comparisons | Observation contract: 6 / 124 | One expected failure on changed safety row ID. |
| Remove source-phase transition guard | Session contract: 7 / 169 | One expected failure on skipped lock/commit ordering. |
| Omit safety tuple from private binding | Observation contract: 6 / 129 | One expected failure: equal semantic context cannot hide changed raw bytes. |
| Erase unknown-COMMIT latch | Session/boundary contracts: 11 / 294 | One expected failure on the uncertainty invariant. |

All four runtime SHA-256 hashes matched the pre-mutation originals after restoration.
The final 248 / 2313 combined suite and 38 / 481 foundation suite then passed again.
An additional deterministic in-memory differential run passed 400 native-JSON
roundtrips with escaped/control/multibyte strings and 400 escaped-alias refusals;
it is supplementary, not a fuzzing completeness or storage proof claim.

## Canonical source-scoped feature reviews

`FEATURE_LIGHT_REVIEW_PROMPT.md` was read and applied to this foundation, its three
test classes/helper and directly reused pure provenance/codec and dormant request
boundary. Feature scope: exact-row/coherence/private binding and single-transaction
simulation. Risk areas: Other (private data, resource bounds, lifetime protocol),
not registered UI/AJAX/REST/DB/cron/file/third-party operations.
Files reviewed: fifteen changed files across uploader/dashboard, plus directly
reused pure dependencies and the unchanged genuine request/loader boundary.

Light-review finding F1 (fixed during this slice): treating a late same-session
commit acknowledgement like an expired new mutation would erase its simulated
historical fact. Terminal acknowledgement is now recorded without permission to
repeat; pre-commit time movement still refuses. The permanent adjacent test proves
both decisions. No user-facing error/UI state is applicable; fixed refusals are
intentional for pure data. No source runtime issue remains identified in this scope.

The deterministic bloat helper with explicit uploader base reports nine changed
PHP files, zero oversized: runtime TOTAL 162/128/203/75, tests/helper 34/32/56/51/32.
Its CODE=1 metric is not credible complexity evidence and is not
used. Responsibilities are cohesive (JSON, exact observation, simulated lifetime,
shared transfer refusal); no split or generic framework is warranted. No bloat/UI
workflow trigger; skip those prompts with this rationale rather than perform churn.
The existing owner and production loader remain unchanged. PHP 7.4 compatibility,
privacy/error paths, finite parser costs and typed metadata were checked.

Final `FEATURE_SECURITY_REVIEW_PROMPT.md` was applied after fixes, mutation controls
and validation. No confirmed security issue remains identified in this source
scope. Regression handoff: no confirmed security defect requires new coverage.
Exact bytes are validated, not normalized; public handler authorization/CSRF,
HTML escaping, query preparation, files and third-party calls are inapplicable to
these unregistered pure definitions. Test-only `unserialize` uses a fixed crafted
empty contract object solely to prove refusal; no runtime unserialization exists.
No full pre-release/whole-plugin security, Database Review,
physical transaction or native provenance claim follows from this focused review.
Feature prompts have no mapped toolkit checklist row; historical whole-plugin
readiness dates are not refreshed. Canonical full pre-release remains required for
the next coherent candidate before packaging/publication.

## Verification and next gate

Toolkit restore-point **Option A/check** verified the clean uploader master at the
explicit base, checkpoint-eligible. Existing external source archive was reported,
not recreated or restored. No backup/database/WordPress action was performed.
Target: `C:\Development\WordPress\Plugins\alynt-drime-backups-uploader`;
snapshot root: `C:\Users\Captain\Documents\AI Workflows\Toolkits\toolkit-snapshots`.
No checkpoint branch, archive or sidecar created.

Executed verification:

| Check | Result |
| --- | --- |
| Foundation and combined targeted PHPUnit commands above | 38 / 481 and 248 / 2313 passed after exact mutation restoration, no skips. |
| Uploader `npm run lint` and focused four-file PHPCS rerun | 168 files passed; existing third-party PHPCompatibility `functionWhitelist` deprecation notice remains, not a new source warning or ignored failure. |
| Uploader `npm run build`, `npm run verify:runner`, `npm run lint:runner` | Passed; generated assets/runner unchanged. No proof/runner command was executed against a site. |
| Uploader `npm run test:dev-tools` | Three Node tests passed. |
| Uploader strict Composer validation/audit via the available dashboard `composer.phar`; `npm audit --audit-level=moderate` | Valid manifest and zero advisories/vulnerabilities. The uploader has no local `composer.phar`; an initial missing-path attempt was corrected, not counted as a pass. |
| Dashboard PHPUnit/lint/structure/build | 299 / 1423, two existing Windows skips; 138 PHPCS files, structure and build passed; no generated changes. |
| Source baseline/diff/dormancy and document links | Existing owner/core guard/model, bootstrap, schema/request contracts, versions and generated files unchanged; no native/API/SQL calls in new definitions. |

The supplementary local full-suite run was launched at the 34-new-test checkpoint,
before four additional boundary methods were added. It is not substituted for
the final suite: exact pushed-head Quality CI must run the complete current suite
on PHP 7.4/8.3, and its result is verified and linked in the final handoff. Neither
successful local models nor CI establish native authentication/storage integration.

Implementation-plan progress: bounded raw observation and single-use session
contracts now have executable non-authorizing controls. Physical raw observation,
authentication-session fencing and fixed native session ownership remain unproven.

**Recommended next gated slice: Plugin Tester local-only V2.3 Fixed Native Raw
Observation and Session-Ownership Implementation/Proof**, with separately confirmed
owned disposable authentication/options fixtures and creation/cleanup audits.
Implement/prove the fixed bounded reader and native/auth idle-session fences against
owned tables, without existing application changes, a paired writer, production
wiring, grants, activation or rollback. This is the next meaningful physical
boundary; do not substitute more maintenance or treat simulations as native proof.
